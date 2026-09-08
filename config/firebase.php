<?php

/*
|--------------------------------------------------------------------------
| Firebase Cloud Messaging (Web Push)
|--------------------------------------------------------------------------
| SATU-SATUNYA tempat kredensial Firebase dibaca. Tidak ada satu pun nilai
| di bawah ini yang ditulis langsung di kode — semuanya datang dari .env,
| berkas yang tidak pernah ikut ter-commit ke GitHub.
|
| Isi berkas ini AMAN dibaca siapa pun yang melihat repository: yang ada di
| sini hanya NAMA kunci .env-nya, bukan nilainya.
|
| ============ CATATAN PENTING SOAL config:cache ============
| Sesudah `php artisan config:cache` dijalankan (dan GitHub Actions
| menjalankannya di setiap deploy), env() TIDAK LAGI BEKERJA di luar berkas
| config. Karena itu seluruh akses ke kredensial di aplikasi ini memakai
| config('firebase...'), bukan env('FIREBASE_...'). Kalau Anda menambah
| pemakaian baru, ikuti aturan yang sama — kalau tidak, fiturnya bekerja
| sempurna di localhost lalu mati diam-diam di cPanel.
| ===========================================================
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Sakelar utama
    |--------------------------------------------------------------------------
    | Kalau false, seluruh fitur push mati total: skrip Firebase tidak
    | dimuat di browser dan pengiriman dari server langsung berhenti tanpa
    | memanggil apa pun. Berguna untuk mematikan fitur ini sementara tanpa
    | mencopot berkas apa pun (mis. saat kuota Firebase bermasalah).
    */
    'enabled' => env('FIREBASE_PUSH_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Identitas project
    |--------------------------------------------------------------------------
    | Ada di Firebase Console -> Project settings -> General -> Project ID.
    */
    'project_id' => env('FIREBASE_PROJECT_ID'),

    /*
    |--------------------------------------------------------------------------
    | Kredensial service account (SISI SERVER — RAHASIA)
    |--------------------------------------------------------------------------
    | Dipakai untuk menandatangani permintaan ke FCM HTTP v1. Berkas JSON-nya
    | didapat dari Firebase Console -> Project settings -> Service accounts ->
    | "Generate new private key".
    |
    | BERKAS ITU SETARA KUNCI RUMAH. Siapa pun yang memilikinya bisa mengirim
    | notifikasi atas nama sekolah. Ia TIDAK BOLEH masuk ke repository dan
    | TIDAK BOLEH diletakkan di dalam public_html.
    |
    | Tersedia TIGA cara memasoknya. Pilih SATU — yang paling atas dipakai
    | lebih dulu kalau kebetulan terisi lebih dari satu:
    |
    |   1. FIREBASE_CREDENTIALS
    |      Jalur absolut ke berkas JSON-nya. INI YANG DISARANKAN UNTUK cPANEL.
    |      Taruh berkasnya SEJAJAR dengan public_html, bukan di dalamnya:
    |        FIREBASE_CREDENTIALS=/home/namaakun/rahasia/firebase.json
    |      Berkas di luar public_html tidak bisa diunduh siapa pun lewat
    |      browser, walau alamatnya ditebak dengan benar.
    |
    |   2. FIREBASE_CREDENTIALS_BASE64
    |      Seluruh isi JSON, di-encode base64 menjadi SATU BARIS panjang.
    |      Dipakai kalau hosting tidak mengizinkan menaruh berkas di luar
    |      public_html. Cara membuatnya ada di BACA-DULU.md.
    |
    |   3. FIREBASE_CLIENT_EMAIL + FIREBASE_PRIVATE_KEY
    |      Dua nilai itu saja, disalin dari dalam JSON-nya. Praktis, tapi
    |      paling rawan salah ketik: private key berisi banyak baris, dan di
    |      .env baris-baris itu harus ditulis sebagai \n literal di dalam
    |      tanda kutip ganda.
    */
    'credentials' => env('FIREBASE_CREDENTIALS'),
    'credentials_base64' => env('FIREBASE_CREDENTIALS_BASE64'),
    'client_email' => env('FIREBASE_CLIENT_EMAIL'),
    'private_key' => env('FIREBASE_PRIVATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Web SDK (SISI BROWSER — BUKAN RAHASIA)
    |--------------------------------------------------------------------------
    | Nilai-nilai ini memang dikirim ke browser dan bisa dilihat siapa pun
    | lewat "View Source". Itu wajar dan aman: Firebase merancangnya
    | demikian, pengamanannya ada di Security Rules dan di service account
    | di atas, bukan pada kerahasiaan apiKey.
    |
    | Semuanya ada di Firebase Console -> Project settings -> General ->
    | "Your apps" -> pilih aplikasi Web -> "SDK setup and configuration".
    |
    | ============ KENAPA BUKAN VITE_... ============
    | Variabel berawalan VITE_ DIBAKUKAN KE DALAM BUNDLE saat `npm run build`
    | dijalankan. Artinya: mengubah nilainya di .env cPanel TIDAK berpengaruh
    | apa-apa sampai aset-nya dibangun ulang — dan di cPanel tidak ada
    | Node.js untuk membangunnya. Gejalanya sangat membingungkan: .env sudah
    | benar, tapi browser tetap memakai nilai lama (atau kosong) selamanya.
    |
    | Karena itu nilai-nilai ini dibaca di sisi PHP lalu disuntikkan ke
    | halaman lewat Blade. Ganti .env, jalankan `php artisan config:clear`,
    | selesai — tanpa build ulang.
    | ===============================================
    */
    'web' => [
        'apiKey' => env('FIREBASE_WEB_API_KEY'),
        'authDomain' => env('FIREBASE_WEB_AUTH_DOMAIN'),
        'projectId' => env('FIREBASE_PROJECT_ID'),
        'storageBucket' => env('FIREBASE_WEB_STORAGE_BUCKET'),
        'messagingSenderId' => env('FIREBASE_WEB_SENDER_ID'),
        'appId' => env('FIREBASE_WEB_APP_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | VAPID key (kunci publik Web Push)
    |--------------------------------------------------------------------------
    | Firebase Console -> Project settings -> Cloud Messaging -> "Web Push
    | certificates" -> Key pair. Yang disalin adalah kunci PUBLIK-nya.
    |
    | Tanpa nilai ini getToken() selalu gagal dengan pesan yang menyesatkan
    | ("applicationServerKey must contain a valid P-256 public key"), padahal
    | yang kurang cuma satu baris di .env.
    */
    'vapid_key' => env('FIREBASE_VAPID_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Antrean pengiriman
    |--------------------------------------------------------------------------
    | Bawaannya 'sync' — pengiriman dilakukan LANGSUNG dalam permintaan yang
    | sama, tanpa queue worker.
    |
    | Ini keputusan sadar untuk cPanel: hosting shared umumnya tidak
    | menjalankan `php artisan queue:work` terus-menerus. Job yang di-queue
    | di sana hanya menumpuk di tabel `jobs` dan TIDAK PERNAH terkirim —
    | tanpa satu pun pesan error, karena dari sisi aplikasi dispatch-nya
    | memang berhasil.
    |
    | Kalau Anda memang punya queue worker (VPS, atau cron `queue:work
    | --stop-when-empty`), isi FIREBASE_PUSH_QUEUE=database supaya scan di
    | gerbang tidak lagi menunggu jawaban dari server Google.
    */
    'queue_connection' => env('FIREBASE_PUSH_QUEUE', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Batas waktu satu panggilan HTTP ke Google (detik)
    |--------------------------------------------------------------------------
    | Dibuat pendek DENGAN SENGAJA. Pada mode 'sync' angka inilah yang
    | menentukan berapa lama layar guru piket membeku kalau jaringan sekolah
    | sedang buruk. Lebih baik notifikasinya gagal daripada antrean siswa
    | menumpuk di gerbang.
    */
    'timeout' => (int) env('FIREBASE_PUSH_TIMEOUT', 8),

    /*
    |--------------------------------------------------------------------------
    | Ikon & lencana notifikasi
    |--------------------------------------------------------------------------
    | Jalur relatif dari akar situs. Dipakai oleh service worker saat
    | menggambar notifikasi di layar kunci HP.
    */
    'icon' => env('FIREBASE_PUSH_ICON', '/images/logo/icon-192x192.png'),
    'badge' => env('FIREBASE_PUSH_BADGE', '/images/logo/icon-192x192.png'),

];
