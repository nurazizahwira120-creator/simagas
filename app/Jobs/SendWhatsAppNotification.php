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
 * ============================================================
 *
 * ================== PESAN BASI TIDAK DIKIRIM ==================
 * Selama DUA MINGGU antrean di server tidak pernah diproses — tidak ada
 * yang menjalankan pekerja antreannya — dan 120 pesan menumpuk di tabel
 * `jobs` tanpa satu pun galat. Tidak ada wali murid yang menerima
 * WhatsApp selama itu, dan tidak ada layar yang memberi tahu siapa pun.
 *
 * Begitu pemrosesnya dinyalakan, tumpukan itu akan terkirim SERENTAK:
 * orang tua menerima rentetan "Ananda X telah HADIR pada 8 September
 * pukul 06:45" untuk hari-hari yang sudah lewat. Itu lebih buruk daripada
 * tidak menerima apa-apa — pesannya terlihat seperti kabar hari ini.
 *
 * Maka pesan yang umurnya melewati BATAS_BASI_MENIT dibuang, bukan
 * dikirim. Kabar "anak Anda sudah sampai sekolah" hanya berguna pada pagi
 * itu juga; tiga jam cukup untuk menampung gangguan server biasa tanpa
 * pernah mengirim kabar dari hari kemarin.
 *
 * Penjaga ini sengaja ditaruh DI DALAM job, bukan di pemroses antreannya:
 * dengan begitu ia tetap bekerja kalau suatu hari cron di cPanel mati lagi
 * lalu dinyalakan kembali — tanpa perlu ada yang ingat membersihkan
 * antrean lebih dulu.
 * ==============================================================
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Umur maksimal sebuah pesan sebelum dianggap basi (menit). */
    public const BATAS_BASI_MENIT = 180;

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

    /**
     * Kapan pesan ini dibuat (UNIX timestamp).
     *
     * ============ KENAPA BOLEH NULL ============
     * 120 job yang sudah mengantre di server dibuat SEBELUM properti ini
     * ada. Saat dibaca ulang dari tabel `jobs`, PHP mengisi properti yang
     * tidak ada di data lamanya dengan nilai bawaan — di sini null.
     *
     * Properti bertipe TANPA nilai bawaan akan melempar "must not be
     * accessed before initialization" pada job lama itu, dan seluruh 120
     * job-nya berakhir di `failed_jobs` alih-alih dibersihkan dengan rapi.
     *
     * null diperlakukan sebagai "tidak diketahui umurnya = basi": job yang
     * lahir sebelum penjaga ini ada, secara definisi, sudah berumur
     * setidaknya sejak penjaga ini dipasang.
     * ===========================================
     */
    public ?int $dibuatPada = null;

    public function __construct(
        public string $target,
        public string $message,
    ) {
        $this->dibuatPada = time();
    }

    public function handle(): void
    {
        if ($this->sudahBasi()) {
            // Info, bukan warning: ini keputusan yang disengaja, bukan
            // kegagalan. Nomor disamarkan — itu data pribadi wali murid.
            Log::info('Pesan WhatsApp basi dibuang, tidak dikirim.', [
                'target' => mb_substr($this->target, 0, 4) . '***',
                'umur_menit' => $this->dibuatPada === null
                    ? 'tidak diketahui (dibuat sebelum penjaga ini ada)'
                    : (int) floor((time() - $this->dibuatPada) / 60),
            ]);

            return;
        }

        WhatsAppService::send($this->target, $this->message);
    }

    public function sudahBasi(): bool
    {
        if ($this->dibuatPada === null) {
            return true;
        }

        return (time() - $this->dibuatPada) > self::BATAS_BASI_MENIT * 60;
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
