{{--
    419 — token CSRF kedaluwarsa (sesi habis).

    Paling sering terjadi begini: halaman login atau form dibuka, lalu
    ditinggal lama (tab HP yang tidak ditutup semalaman), baru kemudian
    dikirim. Tokennya sudah tidak berlaku, dan Laravel menolaknya.

    Yang membuat pengguna panik adalah pesan bawaannya, "Page Expired",
    yang terbaca seperti kerusakan. Karena itu tombol utama di halaman ini
    diarahkan supaya pengguna MEMUAT ULANG — token baru langsung terbit dan
    formnya bisa dikirim lagi.
--}}
@include('errors._tampilan', [
    'kode' => 'SESI BERAKHIR',
    'judul' => 'Sesi berakhir, silakan login ulang',
    'pesan' => 'Halaman ini terbuka terlalu lama sehingga sesi keamanannya berakhir. Tidak ada yang rusak — cukup muat ulang lalu kirim kembali.',
    'saran' => [
        'Tekan "Coba Lagi" untuk memperbarui halaman.',
        'Kalau tadi sedang mengisi form, isinya perlu diketik ulang.',
        'Sering terjadi kalau tab dibiarkan terbuka sejak hari sebelumnya.',
    ],
    'nada' => 'kuning',
    'tampilkanWaktu' => false,
])
