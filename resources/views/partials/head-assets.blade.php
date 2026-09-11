{{-- Dipakai di <head> SEMUA halaman yang punya <head> sendiri: layouts/app,
     halaman Login, halaman Registrasi, Scanner Piket, dan dua halaman Kartu QR.

     ISI PARTIAL INI BERUBAH TOTAL sejak project memakai TailAdmin Pro.
     Dulu di sini ada <script src="https://cdn.tailwindcss.com"> beserta blok
     `tailwind.config` berisi ~60 token warna. Semuanya sudah PINDAH ke
     resources/css/app.css (Tailwind v4 mendefinisikan token lewat @theme,
     bukan lewat file config JavaScript).

     Kalau Anda mencari tempat mengganti warna aplikasi: buka
     resources/css/app.css, blok @theme. Tidak ada token warna lagi di sini.

     Pemuatan aset HANYA dilakukan di berkas ini — jangan ditambahkan lagi di
     layout atau di halaman, karena dua panggilan menghasilkan dua salinan CSS
     dan JS yang saling menimpa. --}}

{{-- Token CSRF untuk permintaan yang dikirim JavaScript — dipakai
     /broadcasting/auth (langganan channel Pusher). Tanpa meta ini,
     otorisasi channel selalu gagal 419 dan lonceng real-time diam. --}}
<meta name="csrf-token" content="{{ csrf_token() }}">

<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

{{-- Ikon untuk layar utama iPhone/iPad. Dua baris, bukan satu:
     apple-touch-icon.png (180×180) adalah ukuran yang benar-benar diminta
     Safari; icon-192x192.png dicantumkan sebagai cadangan bila berkas 180
     itu hilang saat penyalinan. Safari mengambil yang paling cocok dan
     mengabaikan sisanya, jadi mencantumkan keduanya tidak berbahaya. --}}
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
<link rel="apple-touch-icon" sizes="192x192" href="{{ asset('images/logo/icon-192x192.png') }}">

{{-- ====================================================================
     PWA — supaya SIMAGAS bisa dipasang sebagai aplikasi di HP.

     Tiga syarat WAJIB dipenuhi bersamaan, kalau salah satu kurang browser
     tidak akan pernah menawarkan pemasangan (dan tombol "Install App" di
     topbar tidak akan pernah muncul):
       1. manifest yang valid — public/manifest.json
       2. service worker yang punya handler fetch — public/sw.js
       3. HALAMAN DIBUKA LEWAT HTTPS atau localhost/127.0.0.1

     Syarat ketiga yang paling sering luput: di http://absensi-smk.test
     (HTTP biasa) service worker DITOLAK browser, jadi tombolnya tidak akan
     muncul di sana walau semua berkasnya sudah benar. Lihat catatan PWA di
     BACA-DULU.md.
     ==================================================================== --}}
<link rel="manifest" href="{{ asset('manifest.json') }}">

{{-- Warna bilah status Android & bilah judul PWA.
     NILAINYA HIJAU TOSCA (#0d9488), bukan #0F172A seperti pada contoh
     TailAdmin: warna ini muncul di bilah atas ponsel dan di kartu
     pengalih aplikasi, jadi ia harus sewarna dengan merek SIMAGAS —
     seluruh aplikasi ini sudah disapu ke tosca. Kalau Anda memang ingin
     bilah gelap, ganti kedua tempat sekaligus: baris di bawah ini dan
     "theme_color" di public/manifest.json. Berbeda antara keduanya
     membuat Android memakai satu warna dan splash screen warna lain. --}}
<meta name="theme-color" content="#0d9488">

<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="SIMAGAS">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

{{-- ====================================================================
     TEMA TERANG / GELAP — didefinisikan di sini, di <head>, DENGAN SENGAJA.

     Dua alasan, dan keduanya pernah jadi bug nyata:

       1. Tema harus menempel SEBELUM halaman digambar. Kalau class `dark`
          baru ditambahkan di akhir <body>, pengguna melihat kilatan putih
          dulu di setiap perpindahan halaman.

       2. Kode ini TIDAK boleh tinggal di resources/js/app.js. Berkas itu
          dimuat @vite sebagai <script type="module">, dan module SELALU
          ditunda sampai seluruh HTML selesai diurai — artinya ia berjalan
          SETELAH skrip kerangka di akhir <body>. Waktu skrip itu mencari
          window.simagasTema, isinya masih undefined dan tombol ganti tema
          diam saja tanpa error apa pun. Skrip klasik di <head> berjalan
          paling awal, jadi masalah urutan itu hilang sama sekali.

     Semua yang menyentuh localStorage dibungkus try/catch: di mode
     penyamaran beberapa browser melemparkan error di sana, dan error di
     <head> akan menghentikan sisa <head>.
     ==================================================================== --}}
<script>
    window.simagasTema = {
        KUNCI: 'simagas-tema',

        ambil: function () {
            try {
                return localStorage.getItem(this.KUNCI) === 'dark' ? 'dark' : 'light';
            } catch (e) {
                return 'light';
            }
        },

        pasang: function (tema) {
            document.documentElement.classList.toggle('dark', tema === 'dark');
            try {
                localStorage.setItem(this.KUNCI, tema);
            } catch (e) { /* tidak bisa disimpan; tetap berlaku untuk sesi ini. */ }
        },

        ganti: function () {
            this.pasang(this.ambil() === 'dark' ? 'light' : 'dark');
        },
    };

    window.simagasTema.pasang(window.simagasTema.ambil());

    /*
     | Pemuat Leaflet sesuai permintaan (peta di Pengaturan Sistem & Absen
     | Radius). Ditaruh di sini, bersama skrip tema, karena alasan yang sama:
     | ini skrip KLASIK di <head>, jadi ia sudah ada sebelum Livewire maupun
     | blok @script mana pun dijalankan. Kalau diletakkan di app.js (module,
     | selalu ditunda), pemanggilnya bisa berjalan lebih dulu dan mendapat
     | undefined.
     |
     | Leaflet dimuat HANYA di halaman yang benar-benar memakainya — bukan di
     | setiap halaman — dan hanya sekali walau dipanggil beberapa kali.
     |
     | Promise-nya DITOLAK, bukan digantung, kalau berkasnya gagal diunduh.
     | Internet sekolah bisa mati atau CDN diblokir, dan halaman Absen Radius
     | HARUS tetap bisa dipakai tanpa peta — jadi pemanggilnya perlu tahu
     | bahwa petanya gagal, bukan menunggu selamanya.
     */
    /*
     | Pendaftaran service worker.
     |
     | Dibungkus pengecekan isSecureContext, bukan langsung dipanggil: di
     | http:// biasa navigator.serviceWorker memang ADA tapi register()
     | selalu menolak, dan penolakan itu muncul sebagai error merah di
     | konsol setiap kali halaman dibuka. Error yang sudah pasti terjadi dan
     | tidak bisa diperbaiki pengguna hanya melatih orang mengabaikan konsol.
     |
     | Didaftarkan setelah 'load' supaya tidak berebut bandwidth dengan CSS
     | dan JS halaman yang sedang dibuka.
     */
    if ('serviceWorker' in navigator && window.isSecureContext) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}', { scope: '/' })
                .catch(function () { /* diabaikan: aplikasi tetap jalan tanpa PWA. */ });
        });
    }

    window.muatLeaflet = (function () {
        var janji = null;

        return function () {
            if (window.L) return Promise.resolve(window.L);
            if (janji) return janji;

            janji = new Promise(function (selesai, gagal) {
                var gaya = document.createElement('link');
                gaya.rel = 'stylesheet';
                gaya.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                document.head.appendChild(gaya);

                var skrip = document.createElement('script');
                skrip.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                skrip.onload = function () { selesai(window.L); };
                skrip.onerror = function () {
                    janji = null;
                    gagal(new Error('Leaflet gagal dimuat'));
                };
                document.head.appendChild(skrip);
            });

            return janji;
        };
    })();
</script>

@include('partials.sweetalert')

{{-- Perkakas bersama untuk layar scan kamera: nada & pola getar per hasil
     scan, kilatan layar, dan penghitung ukuran kotak bidik. Ditaruh di sini
     (skrip KLASIK di <head>) karena Scanner Piket memanggilnya dari skrip
     biasa di akhir body — alasan lengkapnya ada di dalam partial-nya. --}}
@include('partials.scan-kamera')

{{-- Angka statistik yang menghitung naik di dashboard. Skrip klasik di
     <head> karena atribut x-init milik Alpine memanggilnya lebih dulu
     daripada app.js sempat dijalankan. --}}
@include('partials.gerak')

{{-- ====================================================================
     MEMUAT CSS & JS — dengan cadangan, SENGAJA tidak langsung @vite.

     @vite() melempar VireManifestNotFoundException (HTTP 500) begitu
     public/build/manifest.json tidak ada. Akibatnya satu folder yang lupa
     tersalin membuat SELURUH aplikasi mati, halaman login sekalian — persis
     yang terjadi setelah paket versi sebelumnya dipasang.

     Urutan yang dipakai sekarang:
       1. public/hot ada          -> `npm run dev` sedang jalan, pakai @vite.
       2. manifest Vite ada       -> hasil `npm run build`, pakai @vite.
       3. selain itu              -> pakai salinan tetap public/simagas.css
                                     & public/simagas.js (dibuat otomatis
                                     oleh vite.config.js setiap kali build,
                                     dan ikut dikirim di dalam paket).

     Kalau ketiganya tidak ada pun halaman TETAP TERBUKA — hanya tampil polos
     tanpa gaya. Aplikasi yang jelek jauh lebih berguna daripada aplikasi yang
     tidak bisa dibuka sama sekali.
     ==================================================================== --}}
@php
    $adaVite = file_exists(public_path('hot'))
        || file_exists(public_path('build/manifest.json'));

    // Penanda versi supaya browser tidak menyajikan CSS lama dari cache
    // setelah update. filemtime dibungkus @ karena berkasnya boleh saja tidak ada.
    $capCss = @filemtime(public_path('simagas.css')) ?: 1;
    $capJs = @filemtime(public_path('simagas.js')) ?: 1;
@endphp

@if ($adaVite)
    {{-- Satu entry: app.js meng-import app.css, jadi Laravel memasang
         <link> CSS dan <script> JS-nya sekaligus dari sini. --}}
    @vite('resources/js/app.js')
@else
    <link rel="stylesheet" href="{{ asset('simagas.css') }}?v={{ $capCss }}">
    <script src="{{ asset('simagas.js') }}?v={{ $capJs }}" defer></script>
@endif
