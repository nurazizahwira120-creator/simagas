<?php

namespace App\Http\Controllers\Kepsek;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsensiSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;

class KepsekController extends Controller
{
    /**
     * Dashboard kepala sekolah: statistik kehadiran sekolah hari ini,
     * plus 3 kelas dengan persentase kehadiran terendah biar bisa
     * langsung ditindaklanjuti.
     */
    public function index(Request $request)
    {
        $totalSiswa = Siswa::count();

        $totalHadirHariIni = AbsensiSiswa::whereDate('tanggal', today())
            ->where('status', AbsensiStatus::Hadir)
            ->count();

        $persentaseKehadiran = $totalSiswa > 0
            ? round(($totalHadirHariIni / $totalSiswa) * 100, 1)
            : 0.0;

        $kelasTerendah = Kelas::query()
            ->withCount('siswa')
            ->withCount(['siswa as hadir_count' => function ($query) {
                $query->whereHas('absensi', function ($query) {
                    $query->whereDate('tanggal', today())
                        ->where('status', AbsensiStatus::Hadir);
                });
            }])
            ->get()
            // Kelas tanpa siswa dibuang di sini (bukan lewat HAVING di query)
            // supaya tidak bergantung pada dukungan HAVING-atas-alias tiap
            // driver database.
            ->filter(fn (Kelas $kelas) => $kelas->siswa_count > 0)
            ->map(function (Kelas $kelas) {
                $kelas->persentase_hadir = round(($kelas->hadir_count / $kelas->siswa_count) * 100, 1);

                return $kelas;
            })
            ->sortBy([
                ['persentase_hadir', 'asc'],
                ['nama_kelas', 'asc'],
            ])
            ->take(3)
            ->values();

        return view('kepsek.dashboard', [
            'user' => $request->user(),
            'totalSiswa' => $totalSiswa,
            'totalHadirHariIni' => $totalHadirHariIni,
            'persentaseKehadiran' => $persentaseKehadiran,
            'kelasTerendah' => $kelasTerendah,
        ]);
    }
}
