<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\JadwalEkskul;
use App\Services\RekapEkskulBulanan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rekap Absensi Ekskul per bulan — layar & PDF.
 *
 * ============ SIAPA MELIHAT APA ============
 *  Kepsek & Super Admin : semua ekskul.
 *  Pembina              : hanya ekskul yang ia bina.
 *  Lainnya              : 403.
 * Rutenya didaftarkan untuk banyak peran (pembina bisa guru, staf, admin
 * TU, ...), jadi pembatasan sesungguhnya ADA DI SINI, bukan di grup rute.
 * ===========================================
 *
 * Perhitungannya di App\Services\RekapEkskulBulanan — dipakai bersama
 * layar dan PDF, supaya angka keduanya tidak mungkin berbeda.
 */
class RekapEkskulController extends Controller
{
    public function index(Request $request, RekapEkskulBulanan $rekap)
    {
        [$bulan, $boleh, $ekskul] = $this->saringan($request);

        return view('ekskul.rekap', [
            'bulan' => $bulan,
            'daftarEkskul' => $boleh,
            'ekskulTerpilih' => $ekskul,
            'semua' => $this->pengatur($request),
            'ringkasan' => $ekskul ? null : $rekap->ringkasan($bulan, $boleh->pluck('id')->all()),
            'rincian' => $ekskul ? $rekap->rincian($ekskul, $bulan) : null,
        ]);
    }

    /** PDF: satu ekskul (rincian), atau semua yang boleh dilihat (ringkasan + rincian masing-masing). */
    public function unduh(Request $request, RekapEkskulBulanan $rekap): Response
    {
        [$bulan, $boleh, $ekskul] = $this->saringan($request);

        $daftar = $ekskul ? collect([$ekskul]) : $boleh;

        $data = [
            'bulan' => $bulan,
            'label' => $bulan->translatedFormat('F Y'),
            'ringkasan' => $ekskul ? null : $rekap->ringkasan($bulan, $boleh->pluck('id')->all()),
            'rincian' => $daftar->map(fn (JadwalEkskul $e) => $rekap->rincian($e, $bulan))->values(),
            'dicetak_pada' => now(),
            'dicetak_oleh' => $request->user()->name,
        ];

        $nama = 'Rekap-Absensi-Ekskul-' . $bulan->format('Y-m')
            . ($ekskul ? '-' . preg_replace('/[^A-Za-z0-9]+/', '-', $ekskul->nama_ekskul) : '')
            . '.pdf';

        $response = response($rekap->render($data), 200, [
            'Content-Type' => 'application/pdf',
            // Berisi data kehadiran anak di bawah umur — jangan di-cache bersama.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);

        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $request->boolean('lihat') ? 'inline' : 'attachment',
            $nama,
        ));

        return $response;
    }

    private function pengatur(Request $request): bool
    {
        return in_array($request->user()?->role, [UserRole::SuperAdmin, UserRole::Kepsek], true);
    }

    /**
     * Bulan & ekskul dari query string — disahkan, bukan dipercaya mentah.
     * Ekskul yang tidak boleh dilihat diperlakukan seperti tidak ada (403).
     *
     * @return array{0: Carbon, 1: \Illuminate\Support\Collection, 2: ?JadwalEkskul}
     */
    private function saringan(Request $request): array
    {
        $bulanInput = (string) $request->query('bulan', '');

        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanInput)
            ? Carbon::createFromFormat('Y-m-d', $bulanInput . '-01')->startOfMonth()
            : now()->startOfMonth();

        if ($bulan->gt(now()->startOfMonth())) {
            $bulan = now()->startOfMonth();
        }

        $pegawaiId = $request->user()?->pegawai?->id;

        $boleh = JadwalEkskul::query()
            ->with('pembina:id,nama,user_id')
            ->when(! $this->pengatur($request), fn ($q) => $q->where('pembina_id', $pegawaiId ?? 0))
            ->orderBy('nama_ekskul')
            ->get();

        abort_if($boleh->isEmpty() && ! $this->pengatur($request), 403, 'Anda bukan pembina ekskul mana pun.');

        $id = (int) $request->query('ekskul', 0);
        $ekskul = $id ? $boleh->firstWhere('id', $id) : null;

        abort_if($id && ! $ekskul, 403, 'Anda tidak berhak melihat rekap ekskul ini.');

        // Pembina dengan satu ekskul langsung melihat rinciannya.
        if (! $ekskul && ! $this->pengatur($request) && $boleh->count() === 1) {
            $ekskul = $boleh->first();
        }

        return [$bulan, $boleh, $ekskul];
    }
}
