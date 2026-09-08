<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Izin akses channel siaran (Pusher)
|--------------------------------------------------------------------------
| Berkas ini didaftarkan lewat parameter `channels:` di bootstrap/app.php.
| Tanpa pendaftaran itu, berkas ini TIDAK PERNAH DIBACA dan setiap
| langganan channel privat ditolak dengan 403 — lonceng real-time diam
| tanpa satu pun pesan error di sisi PHP.
|
| Nama channel 'App.Models.User.{id}' mengikuti konvensi bawaan Laravel,
| jadi satu aturan di bawah ini melayani DUA hal sekaligus: event
| App\Events\PengumumanDisiarkan milik project ini, dan notifikasi apa pun
| yang nanti memakai via() => ['broadcast'] bawaan Laravel.
*/

    
    Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
