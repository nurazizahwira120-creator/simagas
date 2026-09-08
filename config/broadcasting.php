<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Koneksi siaran bawaan
    |--------------------------------------------------------------------------
    | Diambil dari .env supaya bisa berbeda antara komputer sendiri dan
    | server. Nilai cadangannya 'null' (BUKAN 'pusher') dengan sengaja:
    | kalau .env di server belum diisi, aplikasi harus tetap berjalan normal
    | tanpa real-time, bukan melempar error di setiap pengiriman pengumuman.
    |
    | Untuk mengaktifkan Pusher, isi di .env:
    |   BROADCAST_CONNECTION=pusher
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    'connections' => [

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER', 'ap1'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'ap1').'.pusher.com',
                'port' => (int) env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',

                /*
                 | Batas waktu panggilan ke Pusher.
                 |
                 | Ini BUKAN penyetelan kosmetik. Pengiriman pengumuman
                 | memanggil Pusher di tengah-tengah request web; kalau
                 | jaringan server sedang bermasalah dan tidak ada batas
                 | waktu, halaman "Kirim Pengumuman" menggantung sampai
                 | max_execution_time habis dan admin melihat layar putih —
                 | padahal pengumumannya sendiri sudah tersimpan.
                 */
                'timeout' => 10,
            ],
            'client_options' => [
                // 'verify' => false,   // JANGAN diaktifkan di server produksi.
            ],
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
