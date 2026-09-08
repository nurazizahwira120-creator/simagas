<?php

namespace App\Notifications;

use App\Models\Pengumuman;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Pengumuman broadcast yang masuk ke lonceng setiap penerima.
 *
 * ==================== SALURAN YANG DIPAKAI ====================
 * via() mengembalikan ['database'] SAJA — dorongan real-time-nya TIDAK lewat
 * sini, melainkan lewat App\Events\PengumumanDisiarkan yang dilempar
 * KelolaPengumuman sesudah semua baris notifikasi tersimpan.
 *
 * Alasannya ada dua dan keduanya nyata di server sungguhan (penjelasan
 * lengkap ada di komentar App\Events\PengumumanDisiarkan):
 *   1. channel 'broadcast' bawaan Laravel MASUK ANTREAN, jadi tanpa queue
 *      worker yang berjalan terus ia tidak pernah terkirim;
 *   2. ia mengirim satu permintaan HTTP ke Pusher PER PENERIMA — 300 wali
 *      murid berarti 300 permintaan berurutan dalam satu request web.
 *
 * Event terpisah itu mengirim ke seluruh penerima dalam satu panggilan dan
 * berjalan langsung tanpa worker.
 *
 * toBroadcast() di bawah TETAP DISEDIAKAN dan sudah benar: kalau suatu saat
 * Anda menjalankan queue worker dan ingin memakai cara bawaan Laravel, cukup
 * tambahkan 'broadcast' ke via() — tidak ada lagi yang perlu diubah.
 * =============================================================
 *
 * Pengiriman WhatsApp ditangani TERPISAH lewat job SendWhatsAppNotification,
 * bukan lewat channel notifikasi — supaya jeda antar pesan bisa diatur
 * (syarat gateway Fonnte agar nomor sekolah tidak diblokir) dan supaya
 * pengumuman tetap masuk lonceng walau pengiriman WhatsApp gagal.
 */
class PengumumanBaru extends Notification
{
    public function __construct(
        private readonly int $pengumumanId,
        private readonly string $judul,
        private readonly string $isi,
        private readonly string $pengirim,
    ) {}

    public static function dari(Pengumuman $pengumuman): self
    {
        return new self(
            $pengumuman->id,
            $pengumuman->judul,
            $pengumuman->isi_pesan,
            $pengumuman->pembuat?->name ?? 'Sekolah',
        );
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'judul' => $this->judul,
            'pesan' => 'Pengumuman dari ' . $this->pengirim,
            'cuplikan' => Str::limit($this->isi, 2000),
            'ikon' => 'bell',
            'warna' => 'info',
            'pengumuman_id' => $this->pengumumanId,

            // Pengumuman TIDAK punya halaman tujuan sendiri: isinya sudah
            // tampil utuh di dalam lonceng. Membuat halaman detail hanya untuk
            // menampilkan dua paragraf yang sama berarti satu klik tambahan
            // tanpa informasi tambahan.
            'rute' => null,
        ];
    }

    /**
     * Bentuk payload kalau 'broadcast' ditambahkan ke via().
     *
     * onConnection('sync') itu WAJIB kalau Anda mengaktifkannya tanpa queue
     * worker — tanpa baris itu event-nya hanya menumpuk di tabel `jobs` dan
     * tidak pernah sampai ke Pusher.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }
}
