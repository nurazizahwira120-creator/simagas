<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Models\AbsensiPegawai;
use App\Models\Penghargaan;
use Illuminate\Http\Request;

class PegawaiDashboardController extends Controller
{
    /**
     * Dashboard self-service untuk role guru/staff/admin_tu: status
     * kehadiran sendiri hari ini + riwayat bulan berjalan. Sengaja
     * strukturnya mirip Ortu\OrtuController — bedanya di sini "anak"-nya
     * adalah diri sendiri (lewat $user->pegawai), bukan siswa.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $pegawai = $user->pegawai;

        if (! $pegawai) {
            return view('pegawai.dashboard', [
                'user' => $user,
                'pegawai' => null,
                'absensiHariIni' => null,
                'riwayat' => collect(),
                'penghargaan' => null,
            ]);
        }

        $absensiHariIni = AbsensiPegawai::where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', today())
            ->first();

        $riwayat = AbsensiPegawai::where('pegawai_id', $pegawai->id)
            ->whereYear('tanggal', today()->year)
            ->whereMonth('tanggal', today()->month)
            ->whereDate('tanggal', '<', today())
            ->orderByDesc('tanggal')
            ->get();

        /*
         | Apresiasi bulan BERJALAN.
         |
         | Penghargaan dibuat tanggal 1 pukul 00:01 untuk bulan SEBELUMNYA
         | (lihat KalkulasiPenghargaanGuru), tetapi periodenya dicatat sebagai
         | bulan yang dinilai. Yang dicari di sini adalah penghargaan yang
         | SEDANG BERLAKU — yaitu milik bulan lalu, karena itulah yang baru
         | saja diumumkan dan pantas dipajang sepanjang bulan ini.
         |
         | Satu query, tanpa relasi tambahan: bannernya hanya butuh kolom pada
         | baris penghargaan itu sendiri.
         */
        $penghargaan = Penghargaan::query()
            ->where('user_id', $user->id)
            ->where('peran', Penghargaan::PERAN_GURU)
            ->where('periode', Penghargaan::periodeBulan(now()->subMonthNoOverflow()))
            ->first();

        return view('pegawai.dashboard', [
            'user' => $user,
            'pegawai' => $pegawai,
            'absensiHariIni' => $absensiHariIni,
            'riwayat' => $riwayat,
            'penghargaan' => $penghargaan,
        ]);
    }
}
