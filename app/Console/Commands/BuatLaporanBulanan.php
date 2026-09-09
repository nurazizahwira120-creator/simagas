<?php

namespace App\Console\Commands;

use App\Services\LaporanBulananService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Membuat laporan kehadiran bulanan dalam bentuk PDF.
 *
 * Dijalankan otomatis oleh penjadwal setiap tanggal 1 pukul 01:00 WIB
 * (lihat routes/console.php), dan bisa dijalankan manual kapan saja:
 *
 *   php artisan simagas:laporan-bulanan                 # bulan LALU
 *   php artisan simagas:laporan-bulanan --bulan=2026-08 # bulan tertentu
 *   php artisan simagas:laporan-bulanan --bulan=2026-08 --paksa
 *
 * ============ KENAPA BAWAANNYA BULAN LALU ============
 * Perintah ini berjalan tanggal 1 pukul 01:00 — saat itu bulan berjalan
 * baru berumur satu jam dan datanya kosong. Yang dimaksud "laporan bulanan"
 * jelas bulan yang BARU SAJA SELESAI, jadi itulah bawaannya.
 *
 * Kalau bawaannya bulan berjalan, cron akan menghasilkan 12 laporan kosong
 * setahun dan tidak ada yang menyadarinya sampai kepala sekolah membukanya.
 * =====================================================
 */
class BuatLaporanBulanan extends Command
{
    protected $signature = 'simagas:laporan-bulanan
                            {--bulan= : Bulan yang dilaporkan, format YYYY-MM. Kosongkan untuk bulan lalu.}
                            {--paksa : Buat ulang walau laporan bulan itu sudah ada.}';

    protected $description = 'Merekap kehadiran pegawai & siswa sebulan penuh menjadi berkas PDF.';

    public function handle(LaporanBulananService $service): int
    {
        $bulan = $this->tentukanBulan();

        if ($bulan === null) {
            $this->error('  Format --bulan tidak dikenali. Contoh yang benar: --bulan=2026-08');

            return self::FAILURE;
        }

        $label = $bulan->translatedFormat('F Y');

        $this->newLine();
        $this->line("  <fg=cyan>SIMAGAS — Laporan Kehadiran {$label}</>");
        $this->newLine();

        if (! $service->siap()) {
            $this->error('  Template resources/views/laporan/bulanan-pdf.blade.php tidak ditemukan.');

            return self::FAILURE;
        }

        // Laporan yang sudah ada TIDAK ditimpa tanpa diminta. Cron hosting
        // kadang menjalankan perintah yang sama dua kali; tanpa penjagaan
        // ini, laporan yang sudah diunduh kepala sekolah bisa berubah
        // isinya di belakang layar.
        $adaSebelumnya = \App\Models\MonthlyReport::whereDate('periode', $bulan->startOfMonth())->exists();

        if ($adaSebelumnya && ! $this->option('paksa')) {
            $this->warn("  Laporan {$label} sudah ada. Tambahkan --paksa untuk membuatnya ulang.");
            $this->newLine();

            return self::SUCCESS;
        }

        try {
            $laporan = $service->buat($bulan);
        } catch (Throwable $e) {
            // Dicatat ke log DAN ditampilkan. Perintah ini berjalan jam 1
            // pagi tanpa ada yang menonton layarnya, jadi log adalah
            // satu-satunya jejak yang tersisa keesokan harinya.
            Log::error('Pembuatan laporan bulanan gagal.', [
                'periode' => $bulan->format('Y-m'),
                'error' => $e->getMessage(),
            ]);

            $this->newLine();
            $this->error('  Gagal membuat laporan:');
            $this->line('    ' . $e->getMessage());
            $this->newLine();

            return self::FAILURE;
        }

        $this->line('  <fg=green>✓</> Laporan tersimpan: <fg=cyan>storage/app/public/' . $laporan->file_path . '</>');
        $this->newLine();

        $this->table(
            ['Keterangan', 'Nilai'],
            [
                ['Periode', $label],
                ['Hari kerja', $laporan->jumlah_hari_kerja . ' hari'],
                ['Pegawai', $laporan->jumlah_pegawai . ' orang'],
                ['Siswa', $laporan->jumlah_siswa . ' orang'],
                ['Kehadiran pegawai', $laporan->kehadiran_pegawai . ' hari-orang'
                    . ($laporan->persenPegawai() !== null ? ' (' . $laporan->persenPegawai() . '%)' : '')],
                ['Kehadiran siswa', $laporan->kehadiran_siswa . ' hari-orang'
                    . ($laporan->persenSiswa() !== null ? ' (' . $laporan->persenSiswa() . '%)' : '')],
            ],
        );

        $this->newLine();
        $this->info('  Laporan sudah muncul di menu Laporan Bulanan (Kepsek & Super Admin).');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Bulan yang akan dilaporkan.
     *
     * Dikembalikan sebagai CarbonImmutable di AWAL bulan, sehingga seluruh
     * kode di bawahnya tidak perlu memikirkan jam berapa perintah ini
     * dijalankan.
     */
    private function tentukanBulan(): ?CarbonImmutable
    {
        $pilihan = $this->option('bulan');

        if (blank($pilihan)) {
            return CarbonImmutable::now()->subMonthNoOverflow()->startOfMonth();
        }

        // createFromFormat dipakai, bukan parse(): parse('2026-13') tidak
        // melempar error melainkan menghasilkan tanggal yang salah diam-diam.
        try {
            $bulan = CarbonImmutable::createFromFormat('Y-m', trim((string) $pilihan));
        } catch (Throwable) {
            return null;
        }

        return $bulan === false ? null : $bulan->startOfMonth();
    }
}
