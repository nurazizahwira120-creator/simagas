<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AbsensiPegawaiLaporanController extends Controller
{
    /**
     * Laporan bulanan: rekap total Hadir/Izin/Sakit/Alpa per pegawai
     * selama satu bulan, bisa difilter per jabatan. Sengaja mengikuti pola
     * yang sama persis dengan Kepsek\LaporanController (versi siswa) demi
     * konsistensi, hanya "kelas" diganti "jabatan" karena pegawai tidak
     * dikelompokkan per kelas.
     */
    public function index(Request $request)
    {
        $bulanInput = $request->query('bulan');

        $bulan = ($bulanInput && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulanInput))
            ? Carbon::createFromFormat('Y-m-d', $bulanInput . '-01')->startOfMonth()
            : now()->startOfMonth();

        $jabatan = $request->query('jabatan') ?: null;

        $awalBulan = $bulan->copy()->startOfMonth()->toDateString();
        $akhirBulan = $bulan->copy()->endOfMonth()->toDateString();

        $pegawaiQuery = Pegawai::query()
            ->when($jabatan, fn ($query) => $query->where('jabatan', $jabatan))
            ->orderBy('nama');

        foreach (AbsensiStatus::cases() as $status) {
            $pegawaiQuery->withCount([
                'absensi as ' . $status->value . '_count' => function ($query) use ($awalBulan, $akhirBulan, $status) {
                    $query->whereBetween('tanggal', [$awalBulan, $akhirBulan])
                        ->where('status', $status);
                },
            ]);
        }

        $rekap = $pegawaiQuery->get();

        $daftarJabatan = Pegawai::query()
            ->select('jabatan')
            ->distinct()
            ->orderBy('jabatan')
            ->pluck('jabatan');

        return view('super-admin.laporan-pegawai', [
            'user' => $request->user(),
            'daftarJabatan' => $daftarJabatan,
            'jabatanTerpilih' => $jabatan,
            'bulan' => $bulan,
            'rekap' => $rekap,
        ]);
    }
}
