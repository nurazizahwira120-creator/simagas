<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiEkskul;
use App\Models\JadwalEkskul;
use App\Models\SesiEkskul;
use App\Models\Siswa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * REKAP ABSENSI EKSKUL SATU BULAN.
 *
 * ============ DUA TINGKAT ============
 *  ringkasan() : satu baris per ekskul — untuk Kepsek/Admin melihat ekskul
 *                mana yang berjalan dan mana yang sering kosong.
 *  rincian()   : satu ekskul — per anggota (H/I/S/A + % hadir) dan matriks
 *                per tanggal pertemuan, seperti buku absen kertas.
 * =====================================
 *
 * ============ ARTI ANGKANYA ============
 *  Terjadwal  : hari ekskul di bulan itu SAMPAI HARI INI, dikurangi hari
 *               libur Kalender Pendidikan. Pola libur mingguan KBM sengaja
 *               diabaikan — ekskul memang sering berjalan di hari tanpa KBM.
 *  Terlaksana : sesi yang dimulai lewat scan QR DAN diakhiri pembina.
 *  Diisi      : tanggal yang punya absensi anggota (termasuk koreksi
 *               susulan tanpa sesi — ditandai terpisah di rincian).
 *  % Hadir    : catatan Hadir ÷ semua catatan absensi di bulan itu.
 * =======================================
 *
 * Jumlah query tetap berapa pun jumlah anggotanya: semua penjumlahan
 * dikerjakan database (GROUP BY) atau sekali tarik per ekskul.
 */
class RekapEkskulBulanan
{
    /** Urutan kolom status di layar & PDF. */
    public const STATUS = [AbsensiStatus::Hadir, AbsensiStatus::Izin, AbsensiStatus::Sakit, AbsensiStatus::Alpha];

    public function __construct(private readonly AturanSesiEkskul $aturan)
    {
    }

    /**
     * Tanggal ekskul terjadwal pada bulan $bulan, sampai hari ini.
     *
     * @return array<int, string>  ['2026-10-03', '2026-10-10', ...]
     */
    public function tanggalTerjadwal(JadwalEkskul $jadwal, Carbon $bulan): array
    {
        $hari = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth()->min(today()->endOfDay());
        $hasil = [];

        while ($hari->lte($akhir)) {
            if ($this->aturan->hariCocok($jadwal, $hari) && ! $this->aturan->libur($hari)) {
                $hasil[] = $hari->toDateString();
            }

            $hari = $hari->copy()->addDay();
        }

        return $hasil;
    }

    /**
     * @param  array<int, int>|null  $idEkskul  null = semua ekskul
     * @return array{bulan: Carbon, label: string, baris: Collection<int, array<string, mixed>>, total: array<string, int>}
     */
    public function ringkasan(Carbon $bulan, ?array $idEkskul = null): array
    {
        $awal = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth();

        $ekskul = JadwalEkskul::query()
            ->with('pembina:id,nama,user_id')
            ->withCount('anggota')
            ->when($idEkskul !== null, fn ($q) => $q->whereIn('id', $idEkskul))
            ->orderBy('nama_ekskul')
            ->get();

        $ids = $ekskul->pluck('id');

        $sesi = SesiEkskul::query()
            ->whereIn('jadwal_ekskul_id', $ids)
            ->whereAntaraTanggal('tanggal', $awal, $akhir)
            ->groupBy('jadwal_ekskul_id')
            ->select('jadwal_ekskul_id')
            ->selectRaw('COUNT(*) as dimulai')
            ->selectRaw('SUM(CASE WHEN waktu_selesai IS NOT NULL THEN 1 ELSE 0 END) as selesai')
            ->toBase()->get()->keyBy('jadwal_ekskul_id');

        $absen = AbsensiEkskul::query()
            ->whereIn('jadwal_ekskul_id', $ids)
            ->whereAntaraTanggal('tanggal', $awal, $akhir)
            ->groupBy('jadwal_ekskul_id')
            ->select('jadwal_ekskul_id')
            ->selectRaw('COUNT(*) as catatan')
            ->selectRaw('SUM(CASE WHEN status_kehadiran = ? THEN 1 ELSE 0 END) as hadir', [AbsensiStatus::Hadir->value])
            ->selectRaw('COUNT(DISTINCT tanggal) as pertemuan')
            ->toBase()->get()->keyBy('jadwal_ekskul_id');

        $baris = $ekskul->map(function (JadwalEkskul $e) use ($sesi, $absen, $bulan) {
            $s = $sesi->get($e->id);
            $a = $absen->get($e->id);
            $terjadwal = count($this->tanggalTerjadwal($e, $bulan));
            $selesai = (int) ($s->selesai ?? 0);
            $catatan = (int) ($a->catatan ?? 0);

            return [
                'ekskul' => $e,
                'anggota' => (int) $e->anggota_count,
                'terjadwal' => $terjadwal,
                'terlaksana' => $selesai,
                'sesi_terbuka' => (int) ($s->dimulai ?? 0) - $selesai,
                'pertemuan_diisi' => (int) ($a->pertemuan ?? 0),
                'persen_terlaksana' => $terjadwal > 0 ? (int) round(min($selesai, $terjadwal) / $terjadwal * 100) : null,
                'persen_hadir' => $catatan > 0 ? (int) round((int) $a->hadir / $catatan * 100) : null,
            ];
        })->values();

        return [
            'bulan' => $awal,
            'label' => $awal->translatedFormat('F Y'),
            'baris' => $baris,
            'total' => [
                'ekskul' => $baris->count(),
                'terjadwal' => (int) $baris->sum('terjadwal'),
                'terlaksana' => (int) $baris->sum('terlaksana'),
            ],
        ];
    }

    /**
     * Rincian satu ekskul satu bulan.
     *
     * @return array{bulan: Carbon, label: string, ekskul: JadwalEkskul, pertemuan: Collection, siswa: Collection, terjadwal: array, kosong: array, total: array}
     */
    public function rincian(JadwalEkskul $jadwal, Carbon $bulan): array
    {
        $awal = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth();
        $jadwal->loadMissing('pembina:id,nama,user_id');

        $catatan = AbsensiEkskul::query()
            ->where('jadwal_ekskul_id', $jadwal->id)
            ->whereAntaraTanggal('tanggal', $awal, $akhir)
            ->get(['siswa_id', 'tanggal', 'status_kehadiran']);

        $sesi = SesiEkskul::query()
            ->where('jadwal_ekskul_id', $jadwal->id)
            ->whereAntaraTanggal('tanggal', $awal, $akhir)
            ->get()
            ->keyBy(fn (SesiEkskul $s) => $s->tanggal->toDateString());

        $tanggalDiisi = $catatan->map(fn ($c) => $c->tanggal->toDateString())->unique();

        // Pertemuan = tanggal yang punya absensi ATAU sesi.
        $pertemuan = $tanggalDiisi->merge($sesi->keys())->unique()->sort()->values()
            ->map(fn (string $t) => [
                'tanggal' => Carbon::parse($t),
                'kunci' => $t,
                'sesi' => $sesi->get($t),
                'diisi' => $tanggalDiisi->contains($t),
            ]);

        // Anggota sekarang + siswa yang tercatat di bulan itu walau sudah keluar.
        $anggota = $jadwal->anggota()->with('kelas:id,nama_kelas')->get();
        $keluar = $catatan->pluck('siswa_id')->unique()->diff($anggota->pluck('id'));
        $semua = $anggota->concat($keluar->isEmpty() ? [] : Siswa::with('kelas:id,nama_kelas')->whereIn('id', $keluar)->get())
            ->sortBy(fn (Siswa $s) => mb_strtolower($s->nama))->values();

        $perSiswa = $catatan->groupBy('siswa_id');
        $nol = array_fill_keys(array_map(fn ($s) => $s->value, self::STATUS), 0);

        $siswa = $semua->map(function (Siswa $s) use ($perSiswa, $nol, $anggota) {
            $milik = $perSiswa->get($s->id, collect());
            $jumlah = $nol;
            $perTanggal = [];

            foreach ($milik as $c) {
                $jumlah[$c->status_kehadiran->value]++;
                $perTanggal[$c->tanggal->toDateString()] = $c->status_kehadiran;
            }

            $total = array_sum($jumlah);

            return [
                'siswa' => $s,
                'anggota_aktif' => $anggota->contains('id', $s->id),
                'jumlah' => $jumlah,
                'total' => $total,
                'persen' => $total > 0 ? (int) round($jumlah[AbsensiStatus::Hadir->value] / $total * 100) : null,
                'per_tanggal' => $perTanggal,
            ];
        });

        $terjadwal = $this->tanggalTerjadwal($jadwal, $bulan);
        $terlaksana = $sesi->filter(fn (SesiEkskul $s) => $s->sudahSelesai())->keys()->all();

        $totalStatus = $nol;
        foreach ($siswa as $b) {
            foreach ($b['jumlah'] as $k => $n) {
                $totalStatus[$k] += $n;
            }
        }
        $semuaCatatan = array_sum($totalStatus);

        return [
            'bulan' => $awal,
            'label' => $awal->translatedFormat('F Y'),
            'ekskul' => $jadwal,
            'pertemuan' => $pertemuan,
            'siswa' => $siswa,
            'terjadwal' => $terjadwal,
            // Hari terjadwal yang sama sekali tidak ada sesi maupun absensinya.
            'kosong' => array_values(array_diff($terjadwal, $pertemuan->pluck('kunci')->all())),
            'total' => [
                'status' => $totalStatus,
                'terjadwal' => count($terjadwal),
                'terlaksana' => count($terlaksana),
                'pertemuan' => $pertemuan->count(),
                'persen_hadir' => $semuaCatatan > 0 ? (int) round($totalStatus[AbsensiStatus::Hadir->value] / $semuaCatatan * 100) : null,
            ],
        ];
    }

    /** PDF A4 landscape — gaya sama dengan Laporan Kehadiran Siswa. */
    public function render(array $data): string
    {
        $kelas = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($kelas)) {
            throw new RuntimeException('Paket PDF belum terpasang. Jalankan di server: composer require barryvdh/laravel-dompdf');
        }

        $pdf = $kelas::loadView('laporan.rekap-ekskul-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true]);

        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $huruf = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $lebar = $dompdf->getFontMetrics()->getTextWidth('Halaman 99 dari 99', $huruf, 6.5);
        $dompdf->getCanvas()->page_text(841.89 - 28 - $lebar, 570, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $huruf, 6.5, [0.42, 0.45, 0.5]);

        return $pdf->output();
    }
}
