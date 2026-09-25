{{--
    500 — galat tak terduga di sisi server.

    Inilah halaman yang paling sering dilihat pengguna saat ada bug: setiap
    Exception yang tidak tertangkap berakhir di sini ketika APP_DEBUG=false.
    (Laravel mengubah Throwable biasa menjadi HttpException 500 lebih dulu,
    lalu mencari view errors::500 — lihat Handler::prepareResponse.)

    Sengaja TIDAK menyebut nama tabel, kolom, atau pesan exception. Pesan
    teknis aslinya sudah masuk storage/logs/laravel.log; menampilkannya di
    layar hanya membocorkan struktur database ke siapa pun yang membuka URL.
--}}
@include('errors._tampilan', [
    'kode' => 'GANGGUAN SISTEM',
    'judul' => 'Sistem sedang dalam perbaikan',
    'pesan' => 'Ada gangguan teknis di server kami. Tim sudah menerima laporannya secara otomatis dan sedang menanganinya.',
    'saran' => [
        'Data yang sudah tersimpan sebelumnya tetap aman.',
        'Coba muat ulang halaman ini beberapa menit lagi.',
        'Kalau masih sama, laporkan ke Admin dengan menyebut kode di bawah.',
    ],
    'nada' => 'merah',
    'tampilkanWaktu' => true,
])
