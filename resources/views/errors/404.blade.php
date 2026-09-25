{{--
    404 — alamat tidak ada.

    BUKAN kerusakan sistem, jadi TIDAK boleh berbunyi "sistem sedang dalam
    perbaikan": kalau semua galat diberi pesan yang sama, guru yang hanya
    salah ketik URL akan melapor "sistemnya rusak" dan waktu Admin habis
    memeriksa server yang sebenarnya sehat.

    Kode waktu juga tidak ditampilkan — tidak ada yang perlu dicari di log.
--}}
@include('errors._tampilan', [
    'kode' => '404',
    'judul' => 'Halaman tidak ditemukan',
    'pesan' => 'Alamat yang Anda buka tidak ada di sistem. Kemungkinan tautannya sudah berubah atau salah ketik.',
    'saran' => [
        'Periksa kembali alamat yang diketik.',
        'Kalau Anda mengikuti tautan dari pesan lama, tautannya mungkin sudah tidak berlaku.',
        'Gunakan menu di dashboard untuk berpindah halaman.',
    ],
    'nada' => 'netral',
    'tampilkanWaktu' => false,
])
