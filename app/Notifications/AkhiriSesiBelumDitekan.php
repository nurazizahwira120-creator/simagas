<?php

namespace App\Notifications;

use App\Models\JadwalPelajaran;
use App\Services\PengingatAkhiriSesi;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Isi LONCENG untuk pengingat "Akhiri Sesi".
 *
 * Bunyi & getar di HP-nya datang dari push Firebase yang dikirim bersamaan
 * (lihat perintah simagas:pengingat-akhiri-sesi). Baris lonceng ini adalah
 * jejaknya: tetap ada setelah notifikasi di layar kunci diusap, dan bisa
 * diketuk untuk langsung membuka Jurnal & Absen Kelas.
 */
class AkhiriSesiBelumDitekan extends Notification
{
    public function __construct(
        private readonly string $mapel,
        private readonly string $pesan,
    ) {}

    public static function untuk(JadwalPelajaran $jadwal, Carbon $batas): self
    {
        return new self(
            (string) $jadwal->mata_pelajaran,
            PengingatAkhiriSesi::kalimat($jadwal, $batas),
        );
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Sesi kelas belum diakhiri',
            'pesan' => $this->pesan,
            'ikon' => 'clock',
            'warna' => 'danger',
            'mapel' => $this->mapel,

            // Akhiran nama rute — prefix panel (guru/wali-kelas/kepsek)
            // ditambahkan NotificationBell sesuai peran pembacanya.
            'rute' => '.jurnal-kelas',
        ];
    }
}
