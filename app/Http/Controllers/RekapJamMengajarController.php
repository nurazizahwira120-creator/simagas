<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Pegawai;
use App\Services\RekapJamMengajar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Rekap Jam Mengajar Guru — beban mengajar berbasis JAM PELAJARAN (JP).
 * Lihat App\Services\RekapJamMengajar untuk arti setiap kolom.
 *
 * Dua aksi, satu sumber data: index() menampilkan di layar, unduh()
 * menghasilkan PDF. Keduanya memanggil hitung() dengan saringan yang sama,
 * jadi yang terlihat di layar persis yang tercetak.
 */
class RekapJamMengajarController extends Controller
{
    /**
     * Diperiksa ULANG di controller walau rutenya sudah di dalam grup
     * ber-middleware: rute ini didaftarkan di dua grup (kepsek & super-admin).
     */
    public const PERAN_BOLEH = [UserRole::Kepsek, UserRole::SuperAdmin];

    public function index(Request $request, RekapJamMengajar $rekap): View
    {
        $this->pastikanBerhak($request);

        [$dari, $sampai, $guruId] = $this->saringan($request);

        $galat = null;

        try {
            $data = $rekap->hitung($dari, $sampai, $guruId);
        } catch (\RuntimeException $e) {
            // Batas rentang — pesannya memang ditulis untuk pengguna.
            $galat = $e->getMessage();
            $data = null;
        }

        return view('laporan.rekap-jam-mengajar', [
            'data' => $data,
            'galat' => $galat,
            'dari' => $dari,
            'sampai' => $sampai,
            'guruId' => $guruId,
            // Hanya pegawai yang punya jadwal — daftar seluruh pegawai
            // (satpam, staf TU) di sini hanya menambah pilihan yang selalu kosong.
            'daftarGuru' => Pegawai::query()
                ->whereIn('id', \App\Models\JadwalPelajaran::query()->select('guru_id'))
                ->orderBy('nama')
                ->get(['id', 'nama']),
        ]);
    }

    public function unduh(Request $request, RekapJamMengajar $rekap): Response
    {
        $this->pastikanBerhak($request);

        [$dari, $sampai, $guruId] = $this->saringan($request);

        try {
            $data = $rekap->hitung($dari, $sampai, $guruId);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        $data['dicetak_pada'] = now();
        $data['dicetak_oleh'] = $request->user()->name;
        $data['saringan_guru'] = $guruId ? Pegawai::find($guruId)?->nama : null;

        $isi = $rekap->render($data);

        $nama = 'Rekap-Jam-Mengajar-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.pdf';

        $response = response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                $request->boolean('lihat') ? 'inline' : 'attachment',
                $nama,
            ),
        );

        return $response;
    }

    private function pastikanBerhak(Request $request): void
    {
        abort_unless(in_array($request->user()?->role, self::PERAN_BOLEH, true), 403);
    }

    /**
     * Bawaan: awal bulan ini sampai hari ini.
     *
     * @return array{0: Carbon, 1: Carbon, 2: int|null}
     */
    private function saringan(Request $request): array
    {
        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'guru' => ['nullable', 'integer', 'exists:pegawai,id'],
        ]);

        $dari = isset($data['dari']) ? Carbon::createFromFormat('Y-m-d', $data['dari'])->startOfDay() : today()->startOfMonth();
        $sampai = isset($data['sampai']) ? Carbon::createFromFormat('Y-m-d', $data['sampai'])->startOfDay() : today();

        return [$dari, $sampai, isset($data['guru']) ? (int) $data['guru'] : null];
    }
}
