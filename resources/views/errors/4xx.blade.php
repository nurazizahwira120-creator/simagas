{{--
    Penadah untuk SEMUA galat 4xx yang tidak punya berkas sendiri
    (400 Bad Request, 401, 405 Method Not Allowed, 413 Payload Too Large,
    dan seterusnya).

    Nadanya netral dan sengaja TIDAK berbunyi "sistem sedang dalam
    perbaikan": keluarga 4xx berarti permintaannya sendiri yang tidak bisa
    dilayani, bukan servernya yang rusak. Contoh paling sering di sistem ini
    adalah 413 — foto absensi yang diunggah melebihi batas upload_max_filesize
    di hosting. Menyebut "server rusak" di situ akan menyesatkan.

    Kode waktu tidak ditampilkan karena tidak ada exception untuk dicari di
    log; yang perlu diperiksa adalah permintaannya.
--}}
@include('errors._tampilan', [
    'kode' => 'PERMINTAAN DITOLAK',
    'judul' => 'Permintaan tidak bisa diproses',
    'pesan' => 'Sistem tidak dapat memproses permintaan ini. Biasanya karena alamatnya tidak sesuai, atau berkas yang dikirim terlalu besar.',
    'saran' => [
        'Kalau Anda sedang mengunggah foto, coba pakai foto dengan ukuran lebih kecil.',
        'Kembali ke beranda lalu ulangi lewat menu, jangan lewat tautan lama.',
        'Kalau berulang terus, laporkan ke Admin beserta langkah yang Anda lakukan.',
    ],
    'nada' => 'netral',
    'tampilkanWaktu' => false,
])
