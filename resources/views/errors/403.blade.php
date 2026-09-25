{{--
    403 — pengguna terautentikasi, tetapi perannya tidak berhak.

    Halaman ini adalah WAJAH dari middleware 'role' dan seluruh pemeriksaan
    hak akses (mis. pastikanBerhak() di komponen Livewire). Kalau ia terasa
    seperti kerusakan sistem, guru yang mencoba membuka menu kepala sekolah
    akan melapor sebagai bug — padahal sistem justru bekerja benar.

    Sengaja tidak menyebut halaman apa yang ditolak: menyebutkannya berarti
    memberi tahu penebak URL bahwa alamat itu memang ada.
--}}
@include('errors._tampilan', [
    'kode' => '403',
    'judul' => 'Anda tidak punya akses',
    'pesan' => 'Halaman ini hanya bisa dibuka oleh peran tertentu, dan akun Anda tidak termasuk di dalamnya.',
    'saran' => [
        'Ini bukan kerusakan sistem — halaman ini memang dibatasi.',
        'Kalau Anda merasa seharusnya berhak, minta Admin memeriksa peran akun Anda.',
        'Pastikan Anda tidak sedang login dengan akun yang salah.',
    ],
    'nada' => 'kuning',
    'tampilkanWaktu' => false,
])
