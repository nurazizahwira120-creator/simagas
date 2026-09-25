<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\LembarParafMengajar;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Lembar Paraf Guru Mengajar — cetakan A4 harian sebagai cadangan laporan
 * mengajar. Lihat App\Services\LembarParafMengajar untuk aturan isinya.
 *
 * Dua aksi, satu sumber data:
 *   index() -> halaman pratinjau di layar + tombol cetak
 *   cetak() -> berkas PDF A4 yang siap dicetak
 * Keduanya memanggil susun() yang sama dengan saringan yang sama, jadi yang
 * terlihat di layar persis yang tercetak di kertas.
 */
class LembarParafMengajarController extends Controller
{
    /**
     * Peran yang boleh membuka. Diperiksa ULANG di controller walau rutenya
     * sudah di dalam grup ber-middleware: rute ini didaftarkan di dua grup
     * (kepsek & super-admin), dan cukup satu grup baru yang lupa memasang
     * middleware untuk membuka daftar izin seluruh guru.
     */
    public const PERAN_BOLEH = [UserRole::Kepsek, UserRole::SuperAdmin];

    public function index(Request $request, LembarParafMengajar $lembar): View
    {
        $this->pastikanBerhak($request);

        [$tanggal, $urut] = $this->saringan($request);

        $galat = null;

        try {
            $data = $lembar->susun($tanggal, $urut);
        } catch (\RuntimeException $e) {
            // Batas jumlah baris — pesannya memang ditulis untuk pengguna.
            $galat = $e->getMessage();
            $data = null;
        }

        return view('laporan.lembar-paraf-mengajar', [
            'data' => $data,
            'galat' => $galat,
            'tanggal' => $tanggal,
            'urut' => $urut,
            'pilihanUrut' => LembarParafMengajar::URUTAN,
        ]);
    }

    public function cetak(Request $request, LembarParafMengajar $lembar): Response
    {
        $this->pastikanBerhak($request);

        [$tanggal, $urut] = $this->saringan($request);

        try {
            $data = $lembar->susun($tanggal, $urut);
        } catch (\RuntimeException $e) {
            abort(422, $e->getMessage());
        }

        // Waktu & pencetak ditetapkan DI SINI, satu kali, lalu dipakai kop
        // dan kaki setiap halaman. Kalau tiap halaman memanggil now()
        // sendiri, cetakan yang melewati pergantian menit bisa menampilkan
        // dua jam cetak berbeda dalam satu dokumen.
        $data['dicetak_pada'] = now();
        $data['dicetak_oleh'] = $request->user()->name;
        $data['peran_pencetak'] = $request->user()->role->label();

        $isi = $lembar->render($data);

        $nama = 'Lembar-Paraf-Mengajar-' . $tanggal->format('Y-m-d') . '.pdf';

        $response = response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);

        // lihat=1 -> dibuka di tab browser (langsung Ctrl+P);
        // tanpa itu -> diunduh sebagai berkas.
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
        abort_unless(
            in_array($request->user()?->role, self::PERAN_BOLEH, true),
            403,
        );
    }

    /**
     * @return array{0: Carbon, 1: string}
     */
    private function saringan(Request $request): array
    {
        $data = $request->validate([
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'urut' => ['nullable', 'in:' . implode(',', array_keys(LembarParafMengajar::URUTAN))],
        ]);

        $tanggal = isset($data['tanggal'])
            ? Carbon::createFromFormat('Y-m-d', $data['tanggal'])->startOfDay()
            : today();

        return [$tanggal, $data['urut'] ?? 'jam'];
    }
}
