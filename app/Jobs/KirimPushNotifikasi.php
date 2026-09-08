<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Satu pengiriman notifikasi push ke satu pengguna.
 *
 * ============ SOAL KONEKSI ANTREAN ============
 * Job ini memang implements ShouldQueue, TAPI koneksinya diambil dari
 * config('firebase.queue_connection') yang bawaannya 'sync' — artinya
 * secara bawaan ia BERJALAN LANGSUNG, bukan mengantre.
 *
 * Itu keputusan sadar untuk cPanel. Hosting shared tidak menjalankan
 * `php artisan queue:work`, jadi job yang benar-benar diantrekan hanya
 * menumpuk di tabel `jobs` dan tidak pernah terkirim — TANPA satu pun
 * pesan error, karena dari sisi aplikasi dispatch-nya memang sukses. Itu
 * bug yang paling menyita waktu untuk ditemukan: semua kode terlihat
 * benar, HP-nya saja yang diam.
 *
 * Kalau Anda punya queue worker, cukup isi FIREBASE_PUSH_QUEUE=database di
 * .env. Tidak ada kode yang perlu diubah.
 * ==============================================
 *
 * Kelasnya tetap berupa Job (bukan pemanggilan langsung di controller)
 * supaya pindah ke antrean betulan tidak menuntut perubahan apa pun di
 * sisi pemanggil.
 */
class KirimPushNotifikasi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Sekali percobaan saja saat berjalan 'sync'.
     *
     * Mencoba ulang di dalam permintaan web berarti guru piket menunggu
     * dua kali lipat lamanya untuk sesuatu yang bukan inti pekerjaannya.
     * Saat dijalankan lewat queue worker betulan, angka ini boleh
     * dinaikkan tanpa efek samping.
     */
    public int $tries = 1;

    /** Sedikit di atas timeout HTTP di FirebasePushService. */
    public int $timeout = 15;

    /**
     * @param  int  $userId  ID penerima. SENGAJA id, bukan objek User:
     *                       objek yang di-serialize ke antrean membawa
     *                       seluruh atribut — termasuk hash password —
     *                       ke dalam tabel `jobs` yang bisa dibaca siapa
     *                       pun yang punya akses phpMyAdmin.
     * @param  array<string, string|int|null>  $data
     */
    public function __construct(
        public int $userId,
        public string $judul,
        public string $isi,
        public array $data = [],
    ) {
    }

    public function handle(FirebasePushService $push): void
    {
        $user = User::query()
            ->select(['id', 'fcm_token', 'fcm_token_updated_at'])
            ->find($this->userId);

        if (! $user || ! $user->bisaMenerimaPush()) {
            return;
        }

        $push->kirimKe($user, $this->judul, $this->isi, $this->data);
    }

    public function failed(Throwable $exception): void
    {
        Log::warning('Job push notifikasi gagal.', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
