<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Rekap absensi siswa SATU BULAN — dua sumber dalam satu baris per siswa.
 *
 *   GERBANG (per hari, dari absensi_siswa)
 *     Hadir · Terlambat · Izin · Sakit · Alpa
 *     "Hadir" di sini = hadir TEPAT WAKTU. Terlambat dipisah supaya kelima
 *     kolom saling lepas dan jumlahnya sama dengan hari yang tercatat.
 *     Terlambat = hadir dengan jam scan melewati batas_terlambat_siswa
 *     (Pengaturan Sistem). Hadir tanpa jam scan (diisi manual wali kelas)
 *     dihitung tepat waktu — tidak ada bukti ia terlambat.
 *
 *   KELAS (per JAM PELAJARAN, dari absensi_kbm_siswa)
 *     Hadir · Izin · Sakit · Bolos · Alpa
 *     Satu baris jurnal = satu jadwal, yang panjangnya bisa 2–4 JP. Yang
 *     dijumlahkan adalah JP-nya (JamPelajaran::untukJadwal), bukan jumlah
 *     baris — bolos satu blok 3 JP dihitung 3 JP, bukan 1.
 *
 * Jumlah query TETAP berapa pun jumlah siswanya: siswa, rekap gerbang,
 * rekap kelas, jadwal, satu pengaturan. Penjumlahan per siswa dikerjakan
 * database (GROUP BY), bukan dengan memuat ribuan baris absensi ke memori.
 */
class RekapAbsensiSiswaBulanan
{
    public const KOLOM_GERBANG = ['hadir', 'terlambat', 'izin', 'sakit', 'alpa'];

    public const KOLOM_KELAS = ['hadir', 'izin', 'sakit', 'bolos', 'alpa'];

    public function __construct(private readonly JamPelajaran $jp)
    {
    }

    /**
     * @return array{bulan: Carbon, label: string, batas_terlambat: string, durasi_jp: int,
     *               baris: Collection<int, array{siswa: Siswa, gerbang: array<string,int>, kelas: array<string,int>}>,
     *               total: array{gerbang: array<string,int>, kelas: array<string,int>}}
     */
    public function hitung(Carbon $bulan, ?int $kelasId = null): array
    {
        $awal = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth();

        $siswa = Siswa::query()
            ->with('kelas:id,nama_kelas')
            ->when($kelasId, fn ($q) => $q->where('kelas_id', $kelasId))
            ->get(['id', 'nis', 'nama', 'kelas_id'])
            ->sortBy(fn (Siswa $s) => mb_strtolower(($s->kelas?->nama_kelas ?? '~') . '|' . $s->nama))
            ->values();

        $batas = (string) Pengaturan::ambil('batas_terlambat_siswa', '07:15');
        $batasDetik = strlen($batas) === 5 ? $batas . ':59' : $batas;

        $idSiswa = fn ($q) => $kelasId
            ? $q->whereIn('siswa_id', Siswa::query()->select('id')->where('kelas_id', $kelasId))
            : $q;

        // ---- GERBANG: satu query agregat ----
        $gerbang = AbsensiSiswa::query()
            ->whereAntaraTanggal('tanggal', $awal, $akhir)
            ->tap($idSiswa)
            ->groupBy('siswa_id', 'status')
            ->select('siswa_id', 'status')
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND jam_masuk IS NOT NULL AND jam_masuk > ? THEN 1 ELSE 0 END) as telat',
                [AbsensiStatus::Hadir->value, $batasDetik],
            )
            ->toBase()
            ->get()
            ->groupBy('siswa_id');

        // ---- KELAS: jumlah baris per (siswa, jadwal, status), lalu dikali JP ----
        $kelas = AbsensiKbmSiswa::query()
            ->whereAntaraTanggal('tanggal', $awal, $akhir)
            ->tap($idSiswa)
            ->groupBy('siswa_id', 'jadwal_id', 'status')
            ->select('siswa_id', 'jadwal_id', 'status')
            ->selectRaw('COUNT(*) as jumlah')
            ->toBase()
            ->get();

        $jpJadwal = JadwalPelajaran::query()
            ->whereIn('id', $kelas->pluck('jadwal_id')->unique())
            ->get(['id', 'jam_mulai', 'jam_selesai'])
            // Jadwal yang panjangnya tidak terbaca tetap dihitung 1 JP,
            // supaya jurnal yang benar-benar ada tidak hilang dari rekap.
            ->mapWithKeys(fn (JadwalPelajaran $j) => [$j->id => max(1, $this->jp->untukJadwal($j))]);

        $kelas = $kelas->groupBy('siswa_id');

        $nol = fn (array $kolom) => array_fill_keys($kolom, 0) + ['total' => 0];
        $total = ['gerbang' => $nol(self::KOLOM_GERBANG), 'kelas' => $nol(self::KOLOM_KELAS)];

        $baris = $siswa->map(function (Siswa $s) use ($gerbang, $kelas, $jpJadwal, $nol, &$total) {
            $g = $nol(self::KOLOM_GERBANG);

            foreach ($gerbang->get($s->id, []) as $r) {
                $n = (int) $r->jumlah;

                if ($r->status === AbsensiStatus::Hadir->value) {
                    // Terlambat dipisah dari hadir tepat waktu (lihat catatan kelas).
                    $g['terlambat'] += (int) $r->telat;
                    $g['hadir'] += $n - (int) $r->telat;
                } else {
                    $kunci = match ($r->status) {
                        AbsensiStatus::Izin->value => 'izin',
                        AbsensiStatus::Sakit->value => 'sakit',
                        default => 'alpa',
                    };
                    $g[$kunci] += $n;
                }

                $g['total'] += $n;
            }

            $k = $nol(self::KOLOM_KELAS);

            foreach ($kelas->get($s->id, []) as $r) {
                $jp = (int) $r->jumlah * (int) ($jpJadwal[$r->jadwal_id] ?? 1);
                $kunci = match ($r->status) {
                    StatusKbm::Hadir->value => 'hadir',
                    StatusKbm::Izin->value => 'izin',
                    StatusKbm::Sakit->value => 'sakit',
                    StatusKbm::Bolos->value => 'bolos',
                    default => 'alpa',
                };
                $k[$kunci] += $jp;
                $k['total'] += $jp;
            }

            foreach ($g as $kol => $n) {
                $total['gerbang'][$kol] += $n;
            }
            foreach ($k as $kol => $n) {
                $total['kelas'][$kol] += $n;
            }

            return ['siswa' => $s, 'gerbang' => $g, 'kelas' => $k];
        });

        return [
            'bulan' => $awal,
            'label' => $awal->translatedFormat('F Y'),
            'batas_terlambat' => substr($batas, 0, 5),
            'durasi_jp' => $this->jp->durasiMenit(),
            'baris' => $baris,
            'total' => $total,
        ];
    }

    /** PDF A4 landscape — sama gayanya dengan Rekap Jam Mengajar. */
    public function render(array $data): string
    {
        $kelas = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($kelas)) {
            throw new RuntimeException('Paket PDF belum terpasang. Jalankan di server: composer require barryvdh/laravel-dompdf');
        }

        $pdf = $kelas::loadView('laporan.rekap-absensi-siswa-pdf', $data)
            ->setPaper('a4', 'landscape')
            // Subset huruf: hanya karakter yang dipakai yang ikut tertanam.
            // Tanpa ini setiap PDF membawa seluruh huruf DejaVu (±800 KB).
            ->setOption(['isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true]);

        // Nomor halaman di setiap lembar: rekap satu sekolah bisa belasan
        // halaman. Digambar SESUDAH render — jumlah halaman baru diketahui
        // setelah tabel selesai ditata (pola yang sama dengan Lembar Paraf).
        // A4 landscape = 842 x 595 poin.
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $huruf = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        $lebar = $dompdf->getFontMetrics()->getTextWidth('Halaman 99 dari 99', $huruf, 6.5);
        $dompdf->getCanvas()->page_text(841.89 - 28 - $lebar, 570, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $huruf, 6.5, [0.42, 0.45, 0.5]);

        return $pdf->output();
    }
}
