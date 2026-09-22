<?php

/*
|--------------------------------------------------------------------------
| Identitas pemasangan (merek) — satu sumber untuk seluruh aplikasi
|--------------------------------------------------------------------------
|
| Berkas ini yang membuat satu kode bisa dipakai BANYAK SEKOLAH tanpa
| disalin. Setiap pemasangan cukup punya .env sendiri; kodenya identik, dan
| perbaikan yang ditulis sekali langsung berlaku untuk semuanya.
|
| ============ KENAPA BUKAN DISALIN JADI PROJECT BARU ============
| Menyalin project memang paling cepat HARI INI. Biayanya baru terasa pada
| perbaikan pertama: setiap bug harus diperbaiki dua kali, di dua tempat,
| dengan dua kali pengujian. Yang terjadi kemudian selalu sama — satu
| salinan diperbaiki, satunya tidak, dan enam bulan lagi tidak ada yang
| ingat lagi mana yang benar.
| ================================================================
|
| ============ APA YANG TIDAK ADA DI SINI ============
| NAMA SEKOLAH yang dipakai di kop laporan dan kartu siswa TIDAK dibaca dari
| sini, melainkan dari tabel `pengaturan_sistem` — supaya Super Admin bisa
| mengubahnya sendiri lewat menu Pengaturan Sistem tanpa menyentuh server.
| Nilai di bawah hanya dipakai sebagai CADANGAN saat barisnya belum pernah
| dibuat (mis. pemasangan yang baru saja di-migrate).
|
| BERKAS GAMBAR juga tidak di sini. logo.png, logo-mark.png, favicon.ico,
| apple-touch-icon.png, dan manifest.json tinggal di public_html masing-
| masing server dan SENGAJA tidak ikut disalin oleh .cpanel.yml — jadi
| mengganti logo cukup mengunggah berkas, dan `git pull` tidak menimpanya.
| ====================================================
|
*/

return [

    /*
    | Nama produknya, bukan nama sekolahnya. Muncul di judul tab, sidebar,
    | halaman login, dan judul notifikasi HP.
    |
    | ============ KENAPA KUNCI BARU, BUKAN APP_NAME ============
    | Memakai APP_NAME bawaan Laravel terasa lebih rapi, dan itu memang
    | pilihan pertama saya. Masalahnya: APP_NAME SUDAH ada di setiap .env
    | yang sedang berjalan, dan isinya tidak bisa saya pastikan dari sini.
    | Kalau di server tertulis "Laravel" — nilai bawaan yang sangat sering
    | tidak pernah diganti — maka sejak deploy berikutnya seluruh judul tab,
    | sidebar, dan halaman login sekolah yang sudah jalan berubah menjadi
    | "Laravel". Kerusakan yang tidak melempar galat apa pun.
    |
    | Kunci BARU tidak punya risiko itu: .env yang sudah ada belum
    | memuatnya, jadi nilainya jatuh ke bawaan di bawah ini, dan pemasangan
    | yang sedang berjalan dijamin tidak berubah sedikit pun.
    | ===========================================================
    */
    'aplikasi' => env('SEKOLAH_APLIKASI', 'SIMAGAS'),

    /*
    | Kalimat kecil di bawah nama aplikasi pada sidebar dan judul tab.
    */
    'tagline' => env('SEKOLAH_TAGLINE', 'Sistem Absensi Digital'),

    /*
    | Cadangan nama sekolah — lihat catatan di atas. Yang berlaku sehari-hari
    | adalah isi tabel `pengaturan_sistem`.
    */
    'nama' => env('SEKOLAH_NAMA', "SMK Islam Assya'roniyyah"),

];
