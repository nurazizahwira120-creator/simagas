<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Laporan Absensi Harian — kehadiran siswa DAN pegawai untuk satu tanggal
 * dalam satu tampilan.
 *
 * Berbeda dari LaporanController & AbsensiPegawaiLaporanController yang
 * merekap satu bulan penuh per orang; halaman ini menjawab pertanyaan
 * "hari ini siapa saja yang belum hadir?" dan ikut menandai yang terlambat
 * berdasarkan batas jam di menu Pengaturan.
 */
class LaporanHarianController extends Controller
{
    public function index(Request $request)
    {
        $tanggalInput = $request->query('tanggal');

        $tanggal = ($tanggalInput && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalInput))
            ? Carbon::createFromFormat('Y-m-d', $tanggalInput)->startOfDay()
            : now()->startOfDay();

        $batas = Pengaturan::ambilBanyak([
            'batas_terlambat_siswa' => '07:15',
            'batas_terlambat_pegawai' => '07:00',
        ]);

        return view('super-admin.laporan-harian', [
            'tanggal' => $tanggal,
            'batasSiswa' => $batas['batas_terlambat_siswa'],
            'batasPegawai' => $batas['batas_terlambat_pegawai'],
            'siswa' => $this->rekapSiswa($tanggal),
            'pegawai' => $this->rekapPegawai($tanggal),
        ]);
    }

    /**
     * @return array{baris: \Illuminate\Support\Collection, total: int, hadir: int, belum: int}
     */
    private function rekapSiswa(Carbon $tanggal): array
    {
        $absensi = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        $baris = Siswa::query()
            ->with('kelas')
            ->orderBy('nama')
            ->get()
            ->map(fn (Siswa $s) => [
                'nama' => $s->nama,
                'nomor' => $s->nis,
                'grup' => $s->kelas?->nama_kelas ?? '-',
                'absensi' => $absensi->get($s->id),
            ]);

        return [
            'baris' => $baris,
            'total' => $baris->count(),
            'hadir' => $baris->filter(fn ($b) => $b['absensi']?->status === AbsensiStatus::Hadir)->count(),
            'belum' => $baris->filter(fn ($b) => $b['absensi'] === null)->count(),
        ];
    }

    /**
     * @return array{baris: \Illuminate\Support\Collection, total: int, hadir: int, belum: int}
     */
    private function rekapPegawai(Carbon $tanggal): array
    {
        $absensi = AbsensiPegawai::query()
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('pegawai_id');

        $baris = Pegawai::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (Pegawai $p) => [
                'nama' => $p->nama,
                'nomor' => $p->nip,
                'grup' => $p->jabatan,
                'absensi' => $absensi->get($p->id),
            ]);

        return [
            'baris' => $baris,
            'total' => $baris->count(),
            'hadir' => $baris->filter(fn ($b) => $b['absensi']?->status === AbsensiStatus::Hadir)->count(),
            'belum' => $baris->filter(fn ($b) => $b['absensi'] === null)->count(),
        ];
    }
}
