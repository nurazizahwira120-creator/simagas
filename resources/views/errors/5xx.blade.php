{{--
    Penadah untuk SEMUA galat server 5xx yang tidak punya berkas sendiri
    (502 Bad Gateway, 504 Gateway Timeout, 507, dan seterusnya).

    Kenapa berkas ini perlu ada padahal 500 dan 503 sudah dibuat:
    Laravel mencari view "errors::{kode}" dulu; kalau tidak ada, ia mencoba
    "errors::5xx"; kalau itu juga tidak ada, ia jatuh ke halaman putih bawaan
    Symfony yang berbunyi "Whoops, looks like something went wrong."

    Tanpa berkas ini, 502 dari PHP-FPM yang mati — kejadian yang justru khas
    di hosting bersama — tetap menampilkan layar putih itu. Jadi ia bukan
    berkas pelengkap, melainkan jaring terakhir.

    Lihat Illuminate\Foundation\Exceptions\Handler::getHttpExceptionView().
--}}
@include('errors._tampilan', [
    'kode' => 'GANGGUAN SISTEM',
    'judul' => 'Sistem sedang dalam perbaikan',
    'pesan' => 'Server sedang tidak bisa memproses permintaan Anda. Gangguan ini ada di sisi kami, bukan pada perangkat Anda.',
    'saran' => [
        'Data yang sudah tersimpan sebelumnya tetap aman.',
        'Coba muat ulang halaman ini beberapa menit lagi.',
        'Kalau masih sama, laporkan ke Admin dengan menyebut kode di bawah.',
    ],
    'nada' => 'merah',
    'tampilkanWaktu' => true,
])
