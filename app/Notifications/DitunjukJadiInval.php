<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Isi LONCENG untuk guru/staf yang baru ditunjuk menjadi guru inval.
 *
 * Satu penunjukan "semua jam hari ini" menghasilkan SATU notifikasi berisi
 * ringkasan jamnya — bukan satu notifikasi per jam pelajaran.
 */
class DitunjukJadiInval extends Notification
{
    public function __construct(
        private readonly string $pesan,
        private readonly string $cuplikan,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Anda ditunjuk menjadi guru inval',
            'pesan' => $this->pesan,
            'cuplikan' => $this->cuplikan,
            'ikon' => 'swap',
            'warna' => 'warning',
            // Akhiran nama rute; prefix panel ditambahkan NotificationBell.
            'rute' => '.guru-inval',
        ];
    }
}
