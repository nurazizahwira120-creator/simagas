{{--
    503 — layanan sedang tidak tersedia.

    Dua hal berbeda berakhir di halaman ini:
      1. "php artisan down" (mode pemeliharaan saat deploy).
      2. Server kehabisan sumber daya / layanan pendukung tidak merespons.

    Keduanya sama-sama SEMENTARA dan tidak disebabkan pengguna, jadi nadanya
    kuning (peringatan) bukan merah (galat), dan sarannya cukup "tunggu".

    Catatan penting soal mode pemeliharaan: saat "artisan down" aktif,
    Laravel menyajikan halaman ini SEBELUM aplikasi benar-benar dijalankan.
    Karena itu halaman ini tidak boleh butuh database maupun sesi — dan
    memang tidak, lihat catatan di errors/_tampilan.blade.php.
--}}
@include('errors._tampilan', [
    'kode' => 'PEMELIHARAAN',
    'judul' => 'Sistem sedang dalam perbaikan',
    'pesan' => 'Kami sedang melakukan pemeliharaan singkat pada sistem. Layanan akan kembali normal dalam beberapa menit.',
    'saran' => [
        'Tidak ada data yang hilang selama pemeliharaan.',
        'Absensi yang sempat gagal terkirim bisa diulang setelah sistem normal.',
        'Silakan coba lagi beberapa menit kemudian.',
    ],
    'nada' => 'kuning',
    'tampilkanWaktu' => true,
])
