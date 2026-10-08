<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Services\RekapAbsensiSiswaBulanan;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laporan Bulanan Kehadiran Siswa — Kepala Sekolah & Super Admin.
 *
 * Satu baris per siswa, dua kelompok kolom:
 *   Gerbang : Hadir, Terlambat, Izin, Sakit, Alpa   (hari)
 *   Kelas   : Hadir, Izin, Sakit, Bolos, Alpa       (jam pelajaran)
 * Perhitungannya di App\Services\RekapAbsensiSiswaBulanan — dipakai
 * bersama tampilan layar dan PDF, supaya angka keduanya tidak mungkin beda.
 */
class LaporanController extends Controller
{
    public function index(Request $request, RekapAbsensiSiswaBulanan $rekap)
    {
        [$bulan, $kelasId, $daftarKelas] = $this->saringan($request);

        return view('kepsek.laporan', [
            'user' => $request->user(),
            'daftarKelas' => $daftarKelas,
            'kelasTerpilih' => $kelasId ? $daftarKelas->firstWhere('id', $kelasId) : null,
            'bulan' => $bulan,
            'data' => $rekap->hitung($bulan, $kelasId),
        ]);
    }

    /** Unduh PDF dengan saringan yang sama dengan layar. */
    public function unduh(Request $request, RekapAbsensiSiswaBulanan $rekap): Response
    {
        [$bulan, $kelasId, $daftarKelas] = $this->saringan($request);

        $data = $rekap->hitung($bulan, $kelasId) + [
            'kelas_terpilih' => $kelasId ? $daftarKelas->firstWhere('id', $kelasId)?->nama_kelas : null,
            'dicetak_pada' => now(),
            'dicetak_oleh' => $request->user()->name,
        ];

        $nama = 'Rekap-Absensi-Siswa-' . $bulan->format('Y-m')
            . ($data['kelas_terpilih'] ? '-' . preg_replace('/[^A-Za-z0-9]+/', '-', $data['kelas_terpilih']) : '')
            . '.pdf';

        $response = response($rekap->render($data), 200, [
            'Content-Type' => 'application/pdf',
            // Berisi data kehadiran anak di bawah umur: jangan disimpan cache
            // bersama (proxy sekolah, CDN) dan jangan dipakai ulang.
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);

        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $request->boolean('lihat') ? 'inline' : 'attachment',
            $nama,
        ));

        return $response;
    }

    /**
     * Bulan & kelas dari query string — disahkan, bukan dipercaya mentah.
     *
     * @return array{0: Carbon, 1: ?int, 2: \Illuminate\Support\Collection}
     */
    private function saringan(Request $request): array
    {
        $bulanInput = (string) $request->query('bulan', '');

        $bulan = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanInput)
            ? Carbon::createFromFormat('Y-m-d', $bulanInput . '-01')->startOfMonth()
            : now()->startOfMonth();

        $daftarKelas = Kelas::orderBy('nama_kelas')->get(['id', 'nama_kelas']);

        $kelasId = (int) $request->query('kelas_id', 0);
        $kelasId = $daftarKelas->contains('id', $kelasId) ? $kelasId : null;

        return [$bulan, $kelasId, $daftarKelas];
    }
}
