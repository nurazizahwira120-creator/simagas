<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\AbsensiSiswa;
use Illuminate\Http\Request;

class OrtuController extends Controller
{
    /**
     * Dashboard wali murid — RINGKASAN singkat.
     *
     * Isinya sengaja tipis: status anak hari ini, lalu pintasan ke tiga
     * halaman yang benar-benar berisi datanya. Sebelum ada menu Pantauan KBM
     * dan Rekap Akademik, dashboard inilah yang memuat seluruh riwayat
     * gerbang — isi itu kini pindah ke halaman Absensi Kedatangan
     * (method index() di bawah), supaya tidak ada dua halaman yang
     * menampilkan tabel yang sama.
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $anakAnak = $user->siswaWali()->with('kelas')->orderBy('nama')->get();

        $anak = $anakAnak->firstWhere('id', (int) $request->query('anak')) ?? $anakAnak->first();

        return view('wali-murid.dashboard', [
            'anakAnak' => $anakAnak,
            'anak' => $anak,
            'absensiHariIni' => $anak
                ? AbsensiSiswa::where('siswa_id', $anak->id)->whereDate('tanggal', today())->first()
                : null,
        ]);
    }

    /**
     * Absensi Kedatangan: status kehadiran hari ini + riwayat bulan
     * berjalan, untuk anak yang wali_murid_id-nya = user yang login.
     * Mendukung lebih dari satu anak lewat query ?anak={siswa_id}.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $anakAnak = $user->siswaWali()->with('kelas')->orderBy('nama')->get();

        if ($anakAnak->isEmpty()) {
            return view('wali-murid.absensi-kedatangan', [
                'user' => $user,
                'anakAnak' => $anakAnak,
                'anak' => null,
                'absensiHariIni' => null,
                'riwayat' => collect(),
            ]);
        }

        // Anak dicari HANYA di dalam koleksi anak milik user sendiri —
        // kalau id di query tidak cocok (atau sengaja dimodifikasi ke id
        // siswa keluarga lain), otomatis jatuh ke anak pertama, bukan
        // ikut menampilkan data anak orang lain.
        $siswaIdDiminta = $request->query('anak');
        $anak = $siswaIdDiminta
            ? $anakAnak->firstWhere('id', (int) $siswaIdDiminta)
            : null;
        $anak ??= $anakAnak->first();

        $absensiHariIni = AbsensiSiswa::where('siswa_id', $anak->id)
            ->whereDate('tanggal', today())
            ->first();

        $riwayat = AbsensiSiswa::where('siswa_id', $anak->id)
            ->whereYear('tanggal', today()->year)
            ->whereMonth('tanggal', today()->month)
            ->whereDate('tanggal', '<', today())
            ->orderByDesc('tanggal')
            ->get();

        return view('wali-murid.absensi-kedatangan', [
            'user' => $user,
            'anakAnak' => $anakAnak,
            'anak' => $anak,
            'absensiHariIni' => $absensiHariIni,
            'riwayat' => $riwayat,
        ]);
    }
}
