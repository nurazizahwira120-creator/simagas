{{--
    429 — permintaan terlalu sering (rate limiting).

    Muncul dari throttle pada login dan dari pembatas laju lain. Penyebab
    tersering yang wajar: tombol "Simpan" atau "Scan" ditekan berulang kali
    karena jaringan lambat, sehingga puluhan permintaan terkirim sekaligus.

    Pesannya harus jelas bahwa ini AKAN pulih sendiri — tanpa itu pengguna
    akan menekan tombolnya lagi dan lagi, yang justru memperpanjang blokir.
--}}
@include('errors._tampilan', [
    'kode' => 'TERLALU SERING',
    'judul' => 'Terlalu banyak permintaan',
    'pesan' => 'Sistem menerima terlalu banyak permintaan dari perangkat ini dalam waktu singkat, jadi permintaan berikutnya ditahan sebentar.',
    'saran' => [
        'Tunggu sekitar satu menit tanpa menekan tombol apa pun.',
        'Jangan menekan tombol berulang kali saat jaringan terasa lambat.',
        'Setelah itu halaman bisa dipakai normal kembali.',
    ],
    'nada' => 'kuning',
    'tampilkanWaktu' => false,
])
