<?php

namespace App\Http\Controllers\Ortu;

use App\Http\Controllers\Controller;
use App\Models\AbsensiSiswa;
use App\Services\BerandaWaliMurid;
use App\Services\StatusKedatanganAnak;
use App\Support\AnakTerpilih;
use Illuminate\Http\Request;

class OrtuController extends Controller
{
    /**
     * BERANDA wali murid — satu layar berisi status kedatangan, pelajaran
     * hari ini, kehadiran bulan ini, dan pengumuman terbaru. Rinciannya
     * tetap di halaman masing-masing (Absensi Kedatangan, Pantauan KBM,
     * Rekap Akademik); Beranda hanya ringkasannya. Penyusunan datanya ada
     * di App\Services\BerandaWaliMurid.
     */
    public function dashboard(Request $request, BerandaWaliMurid $beranda)
    {
        return view('wali-murid.dashboard', $beranda->susun($request->user(), $request->query('anak')));
    }

    /**
     * Absensi Kedatangan: status kehadiran hari ini + riwayat bulan
     * berjalan, untuk anak yang wali_murid_id-nya = user yang login.
     * Mendukung lebih dari satu anak lewat query ?anak={siswa_id}.
     */
    public function index(Request $request, StatusKedatanganAnak $statusKedatangan)
    {
        $user = $request->user();

        $anakAnak = $user->siswaWali()
            ->with(['kelas:id,nama_kelas,wali_kelas_id', 'kelas.waliKelas:id,name,no_hp', 'kelas.waliKelas.pegawai:id,user_id,no_hp'])
            ->orderBy('nama')
            ->get();

        if ($anakAnak->isEmpty()) {
            return view('wali-murid.absensi-kedatangan', [
                'user' => $user,
                'anakAnak' => $anakAnak,
                'anak' => null,
                'absensiHariIni' => null,
                'status' => null,
                'kontak' => null,
                'riwayat' => collect(),
            ]);
        }

        // Anak dicari HANYA di dalam koleksi anak milik user sendiri —
        // kalau id di query tidak cocok (atau sengaja dimodifikasi ke id
        // siswa keluarga lain), jatuh ke anak yang diingat / anak pertama,
        // bukan ikut menampilkan data anak orang lain. Lihat AnakTerpilih.
        $anak = AnakTerpilih::pilih($anakAnak, $request->query('anak'));

        $absensiHariIni = AbsensiSiswa::where('siswa_id', $anak->id)
            ->wherePadaTanggal('tanggal', today())
            ->first();

        $riwayat = AbsensiSiswa::where('siswa_id', $anak->id)
            ->wherePadaBulan('tanggal', today()->year, today()->month)
            ->whereSebelumTanggal('tanggal', today())
            ->orderByDesc('tanggal')
            ->get();

        // Kartu status memakai aturan yang SAMA dengan Beranda: merah hanya
        // untuk Alpa resmi, bukan untuk "belum ada catatan".
        $status = $statusKedatangan->untuk($absensiHariIni);

        return view('wali-murid.absensi-kedatangan', [
            'user' => $user,
            'anakAnak' => $anakAnak,
            'anak' => $anak,
            'absensiHariIni' => $absensiHariIni,
            'status' => $status,
            'kontak' => $status['hubungi'] ? $statusKedatangan->kontakWaliKelas($anak) : null,
            'riwayat' => $riwayat,
        ]);
    }
}
