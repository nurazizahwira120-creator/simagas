<?php

namespace App\Http\Controllers\Kepsek;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LaporanController extends Controller
{
    /**
     * Laporan bulanan: rekap total Hadir/Izin/Sakit/Alpa per siswa selama
     * satu bulan, bisa difilter per kelas.
     */
    public function index(Request $request)
    {
        $bulanInput = $request->query('bulan');

        $bulan = ($bulanInput && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanInput))
            ? Carbon::createFromFormat('Y-m-d', $bulanInput . '-01')->startOfMonth()
            : now()->startOfMonth();

        $kelasId = $request->query('kelas_id') ?: null;

        $awalBulan = $bulan->copy()->startOfMonth()->toDateString();
        $akhirBulan = $bulan->copy()->endOfMonth()->toDateString();

        $siswaQuery = Siswa::query()
            ->with('kelas')
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->orderBy('nama');

        foreach (AbsensiStatus::cases() as $status) {
            $siswaQuery->withCount([
                'absensi as ' . $status->value . '_count' => function ($query) use ($awalBulan, $akhirBulan, $status) {
                    $query->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                        ->where('status', $status);
                },
            ]);
        }

        $rekap = $siswaQuery->get();

        $daftarKelas = Kelas::orderBy('nama_kelas')->get();

        return view('kepsek.laporan', [
            'user' => $request->user(),
            'daftarKelas' => $daftarKelas,
            'kelasTerpilih' => $kelasId ? $daftarKelas->firstWhere('id', (int) $kelasId) : null,
            'bulan' => $bulan,
            'rekap' => $rekap,
        ]);
    }
}
