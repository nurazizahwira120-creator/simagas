<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dorongan real-time ke lonceng penerima lewat Pusher.
 *
 * ============ KENAPA EVENT SENDIRI, BUKAN via() => ['broadcast'] ============
 * Cara "resmi" Laravel adalah menambahkan 'broadcast' ke via() milik
 * Notification. Di aplikasi ini cara itu RUSAK dalam dua hal yang keduanya
 * hanya muncul di server sungguhan, bukan saat mencoba dengan 2-3 akun:
 *
 *   1. TIDAK PERNAH TERKIRIM TANPA QUEUE WORKER. Channel 'broadcast' milik
 *      Laravel melempar event BroadcastNotificationCreated, dan event itu
 *      hanya implements ShouldBroadcast (bukan ...Now) — artinya ia MASUK
 *      ANTREAN. Project ini memakai QUEUE_CONNECTION=database, jadi di
 *      hosting cPanel tanpa worker yang jalan terus, notifikasinya tersimpan
 *      rapi di database tapi TIDAK ADA satu pun yang sampai real-time, tanpa
 *      pesan error apa pun.
 *
 *   2. SATU PANGGILAN HTTP PER PENERIMA. Setiap notifikasi jadi satu event,
 *      dan setiap event satu permintaan HTTP ke Pusher. Untuk 300 wali murid
 *      itu 300 permintaan berurutan di dalam satu request web — puluhan detik,
 *      biasanya melewati max_execution_time cPanel, dan berhenti di tengah
 *      jalan sehingga sebagian orang menerima dan sebagian tidak.
 *
 * Event ini menyelesaikan keduanya: ShouldBroadcastNow (langsung kirim, tanpa
 * worker), dan broadcastOn() mengembalikan BANYAK channel sekaligus sehingga
 * Pusher menerimanya dalam SATU panggilan. Pusher membatasi 100 channel per
 * panggilan, jadi pemanggilnya (KelolaPengumuman::siarkan) memecah penerima
 * per 100 orang.
 * ===========================================================================
 *
 * Isinya sengaja RINGKAS: judul, cuplikan, dan jumlah — bukan seluruh isi
 * pengumuman. Yang perlu real-time hanyalah "ada yang baru, bunyikan dan
 * segarkan"; isi lengkapnya diambil lonceng dari database lewat Livewire,
 * yang sudah terautentikasi.
 */
class PengumumanDisiarkan implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, int>  $userIds  Penerima untuk gelombang ini (maks 100).
     */
    public function __construct(
        public array $userIds,
        public string $judul,
        public string $cuplikan,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        // Nama channel MENGIKUTI konvensi Laravel ('App.Models.User.{id}')
        // supaya izin aksesnya cukup satu aturan di routes/channels.php dan
        // tetap cocok kalau nanti ada notifikasi lain yang memakai
        // via() => ['broadcast'] bawaan.
        return array_map(
            fn (int $id) => new PrivateChannel('App.Models.User.' . $id),
            array_values(array_unique($this->userIds)),
        );
    }

    /**
     * Nama event di sisi browser. Ditulis eksplisit supaya sisi JavaScript
     * tidak perlu menyebut nama class PHP berikut namespace-nya — nama itu
     * akan ikut berubah kalau class-nya dipindah, dan sisi JS diam-diam
     * berhenti bekerja tanpa error.
     */
    public function broadcastAs(): string
    {
        return 'pengumuman.baru';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'judul' => $this->judul,
            'cuplikan' => $this->cuplikan,
            'waktu' => now()->toIso8601String(),
        ];
    }
}
