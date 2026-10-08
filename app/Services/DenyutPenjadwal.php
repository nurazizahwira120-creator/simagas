<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * "Denyut nadi" penjadwal (cron) — supaya matinya ketahuan.
 *
 * ============ KENAPA PERLU ============
 * Banyak fitur bergantung pada cron cPanel `* * * * * php artisan
 * schedule:run`: kiriman WhatsApp, pengingat Akhiri Sesi, penandaan alpa,
 * laporan bulanan, pembersihan data. Kalau cron itu mati (paket hosting
 * diperbarui, perintahnya terhapus), SEMUANYA berhenti tanpa satu pun pesan
 * galat — aplikasinya tetap bisa dibuka, hanya saja tidak ada yang terjadi.
 *
 * Penjadwal menulis waktu sekarang ke satu berkas setiap menit (lihat
 * routes/console.php, 'simagas-denyut'). Dashboard Super Admin membaca
 * berkas itu: lebih dari BATAS_MENIT tanpa denyut = cron bermasalah.
 * =====================================
 *
 * Berkas, bukan cache: `php artisan optimize:clear` di setiap deploy ikut
 * mengosongkan cache, dan dashboard akan berteriak "cron mati" sesaat
 * setelah setiap deploy padahal tidak ada apa-apa.
 */
class DenyutPenjadwal
{
    public const BERKAS = 'penjadwal-denyut.txt';

    /** Lebih lama dari ini tanpa denyut = dianggap bermasalah. */
    public const BATAS_MENIT = 5;

    public static function catat(): void
    {
        Storage::disk('local')->put(self::BERKAS, (string) now()->getTimestamp());
    }

    public function terakhir(): ?Carbon
    {
        try {
            $isi = trim((string) Storage::disk('local')->get(self::BERKAS));
        } catch (Throwable) {
            return null;
        }

        return ctype_digit($isi) ? Carbon::createFromTimestamp((int) $isi)->setTimezone(config('app.timezone')) : null;
    }

    /**
     * @return array{terakhir: ?Carbon, sehat: bool, antrean_gagal: int, antrean_tertahan: int}
     */
    public function status(): array
    {
        $terakhir = $this->terakhir();

        return [
            'terakhir' => $terakhir,
            'sehat' => $terakhir !== null && $terakhir->gte(now()->subMinutes(self::BATAS_MENIT)),
            // Kiriman WA/push yang gagal dalam 7 hari terakhir.
            'antrean_gagal' => $this->hitung('failed_jobs', fn ($q) => $q->where('failed_at', '>=', now()->subDays(7))),
            // Pekerjaan yang sudah menunggu > 10 menit = antrean tidak diproses.
            'antrean_tertahan' => $this->hitung('jobs', fn ($q) => $q->where('created_at', '<', now()->subMinutes(10)->getTimestamp())),
        ];
    }

    private function hitung(string $tabel, callable $syarat): int
    {
        try {
            return Schema::hasTable($tabel) ? tap(DB::table($tabel), $syarat)->count() : 0;
        } catch (Throwable) {
            return 0;
        }
    }
}
