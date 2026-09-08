<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Antrean pengiriman satu pesan WhatsApp.
 *
 * ================== PERUBAHAN TANDA TANGAN ==================
 * Versi sebelumnya menerima objek AbsensiSiswa dan merangkai pesannya
 * sendiri, sehingga hanya bisa dipakai untuk satu kejadian: siswa hadir di
 * gerbang. Sekarang menerima (target, message) mentah, jadi satu job ini
 * melayani semua kebutuhan — notifikasi kehadiran gerbang, peringatan
 * alpa/bolos dari jurnal KBM, dan apa pun yang datang nanti.
 *
 * KALAU MASIH ADA JOB LAMA MENGANTRE di tabel `jobs` saat paket ini
 * dipasang, job tersebut akan GAGAL saat diproses karena bentuk datanya
 * berbeda. Kosongkan antrean lamanya lebih dulu:  php artisan queue:clear
 * ============================================================
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Tiga percobaan: cukup untuk gangguan jaringan sesaat. */
    public int $tries = 3;

    /** Jeda antar percobaan (detik). */
    public int $backoff = 30;

    /**
     * Batas waktu satu percobaan. Sedikit di atas timeout HTTP di
     * WhatsAppService supaya yang menghentikan proses adalah timeout HTTP
     * yang pesannya jelas, bukan queue worker yang memutus di tengah jalan.
     */
    public int $timeout = 30;

    public function __construct(
        public string $target,
        public string $message,
    ) {
    }

    public function handle(): void
    {
        WhatsAppService::send($this->target, $this->message);
    }

    public function failed(Throwable $exception): void
    {
        // Nomor tidak ditulis lengkap ke log — itu data pribadi wali murid,
        // dan log sering ikut terkirim saat minta bantuan teknis.
        Log::error('Notifikasi WhatsApp gagal setelah beberapa kali percobaan.', [
            'target' => mb_substr($this->target, 0, 4) . '***',
            'error' => $exception->getMessage(),
        ]);
    }
}
