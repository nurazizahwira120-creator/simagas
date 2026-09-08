<?php

namespace App\Http\Controllers\Pegawai;

use App\Http\Controllers\Controller;
use App\Models\AbsensiPegawai;
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

        return view('pegawai.dashboard', [
            'user' => $user,
            'pegawai' => $pegawai,
            'absensiHariIni' => $absensiHariIni,
            'riwayat' => $riwayat,
        ]);
    }
}
