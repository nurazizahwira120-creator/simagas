<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Lonceng untuk guru/pembina: sesinya ditutup Kepsek/Admin lewat
 * "Bantu Akhiri Sesi". Supaya tidak ada yang terkejut melihat sesinya
 * tiba-tiba selesai — dan tahu alasannya.
 */
class SesiDiakhiriAdmin extends Notification
{
    public function __construct(private readonly string $pesan)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Sesi Anda diakhiri oleh pimpinan',
            'pesan' => $this->pesan,
            'ikon' => 'check-circle',
            'warna' => 'warning',
            'rute' => '.dashboard',
        ];
    }
}
