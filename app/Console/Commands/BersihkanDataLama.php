<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang data "sampah" yang hanya menumpuk dan tidak pernah dibaca lagi.
 *
 * ============ YANG DIBERSIHKAN — DAN YANG TIDAK ============
 *   notifications  sudah dibaca & > 90 hari, atau apa pun > 365 hari.
 *                  Satu pengumuman ke 600 orang = 600 baris; tanpa ini tabel
 *                  lonceng tumbuh puluhan ribu baris per semester.
 *   failed_jobs    > 30 hari. Pekerjaan yang gagal sebulan lalu (mis. WA
 *                  ke nomor yang salah) tidak akan dicoba ulang siapa pun.
 *   cache          baris yang SUDAH KEDALUWARSA. Penyimpanan cache 'database'
 *                  tidak pernah menghapus baris kedaluwarsanya sendiri —
 *                  penghitung percobaan login, misalnya, tetap tinggal.
 *
 * TIDAK PERNAH disentuh: data absensi, nilai, jurnal, izin, laporan.
 * Itu arsip sekolah, bukan sampah.
 * ==========================================================
 *
 * Dihapus BERTAHAP (per 1.000 baris), bukan satu DELETE raksasa: di hosting
 * bersama, satu DELETE yang mengunci tabel notifikasi selama puluhan detik
 * membuat lonceng semua pengguna ikut macet.
 */
class BersihkanDataLama extends Command
{
    protected $signature = 'simagas:bersihkan-data
        {--uji-coba : Hanya menghitung, tidak menghapus apa pun.}';

    protected $description = 'Membuang notifikasi lama, antrean gagal lama, dan cache kedaluwarsa.';

    public const HARI_NOTIF_DIBACA = 90;

    public const HARI_NOTIF_SEMUA = 365;

    public const HARI_ANTREAN_GAGAL = 30;

    private const PER_PUTARAN = 1000;

    public function handle(): int
    {
        $ujiCoba = (bool) $this->option('uji-coba');

        $hasil = [
            'notifikasi' => $this->bersihkan('notifications', function ($q) {
                $q->where(function ($q) {
                    $q->whereNotNull('read_at')
                        ->where('created_at', '<', now()->subDays(self::HARI_NOTIF_DIBACA));
                })->orWhere('created_at', '<', now()->subDays(self::HARI_NOTIF_SEMUA));
            }, $ujiCoba),

            'antrean gagal' => $this->bersihkan('failed_jobs', function ($q) {
                $q->where('failed_at', '<', now()->subDays(self::HARI_ANTREAN_GAGAL));
            }, $ujiCoba),

            'cache kedaluwarsa' => $this->bersihkan(
                (string) config('cache.stores.database.table', 'cache'),
                fn ($q) => $q->where('expiration', '<', now()->getTimestamp()),
                $ujiCoba,
            ),
        ];

        foreach ($hasil as $nama => $jumlah) {
            $this->line(($ujiCoba ? 'Akan dihapus' : 'Dihapus') . " {$nama}: {$jumlah}");
        }

        return self::SUCCESS;
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder): void  $syarat
     */
    private function bersihkan(string $tabel, callable $syarat, bool $ujiCoba): int
    {
        if (! Schema::hasTable($tabel)) {
            return 0;
        }

        $query = fn () => tap(DB::table($tabel), $syarat);

        if ($ujiCoba) {
            return $query()->count();
        }

        $total = 0;

        // Kunci utama tabel `cache` adalah `key`, bukan `id`.
        $kunci = $tabel === config('cache.stores.database.table', 'cache') ? 'key' : 'id';

        do {
            $ids = $query()->limit(self::PER_PUTARAN)->pluck($kunci);

            if ($ids->isEmpty()) {
                break;
            }

            $total += DB::table($tabel)->whereIn($kunci, $ids)->delete();
        } while ($ids->count() === self::PER_PUTARAN);

        return $total;
    }
}
