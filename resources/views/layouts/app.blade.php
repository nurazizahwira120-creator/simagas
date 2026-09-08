<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0d9488">
    {{-- Judul tab: nama halaman dulu, baru merek.

         Kalau judulnya dipaku jadi "SIMAGAS - Sistem Absensi Digital" saja,
         SEMUA tab akan terbaca sama persis — dan Anda sering membuka beberapa
         tab sekaligus (dashboard + laporan + pengaturan), jadi tab-nya tidak
         bisa dibedakan lagi. Bentuk di bawah tetap memberi merek di setiap tab,
         dan halaman yang tidak menyetel judul sendiri jatuh ke nama lengkap
         merek persis seperti yang diminta. --}}
    {{-- Ditulis sebagai SATU ekspresi PHP, bukan @hasSection/@else/@endif.

         Bentuk lamanya menghasilkan judul rusak di SETIAP halaman:
         "Jadwal Ekskul — SIMAGAS@elseSIMAGAS - Sistem Absensi Digital".
         Sebabnya halus — Blade hanya mengompilasi direktif yang TIDAK
         didahului huruf/angka (pola @-nya memakai \B), dan di sana @else
         menempel langsung di belakang kata "SIMAGAS". Akibatnya @else
         diperlakukan sebagai teks biasa dan kedua cabangnya ikut tercetak.
         Nyaris tidak pernah ketahuan karena tab browser memotong judul
         panjang — tapi terbaca utuh di bookmark, riwayat, dan hasil cetak. --}}
    <title>{{ View::hasSection('title')
        ? trim(View::yieldContent('title')) . ' — SIMAGAS'
        : 'SIMAGAS - Sistem Absensi Digital' }}</title>

    {{-- Memuat CSS/JS hasil build Vite (@vite ada DI DALAM partial ini —
         jangan ditambahkan lagi di sini) + favicon + font + skrip tema. --}}
    @include('partials.head-assets')

    {{-- Livewire v3. Sebenarnya v3 menyuntikkan aset ini otomatis, tapi
         ditulis eksplisit supaya jelas terbaca di mana tempatnya dan tidak
         hilang kalau nanti auto-injection dimatikan lewat config. --}}
    @livewireStyles

    {{-- Tempat halaman menitipkan <link>/<style> tambahan lewat @push('styles').
         Dipakai halaman yang butuh CSS pustaka pihak ketiga (mis. Leaflet di
         Pengaturan Sistem & Absen Radius). Ditaruh SETELAH @vite supaya CSS
         pustaka bisa ditimpa utility Tailwind kalau perlu. --}}
    @stack('styles')
</head>
{{-- Kerangka TailAdmin: latar gray-50 saat terang, gray-900 saat gelap.

     `overflow-hidden` di <body> DISENGAJA tidak dipasang; yang menggulung
     adalah #area-konten di dalam. Pola ini yang membuat sidebar tetap diam
     saat isi halaman digulung — kalau <body> yang menggulung, sidebar ikut
     naik-turun dan topbar sticky-nya jadi tidak masuk akal. --}}
<body class="min-h-screen bg-gray-50 font-sans text-gray-700 antialiased dark:bg-gray-900 dark:text-gray-300">

{{-- ====================================================================
     SPLASH SCREEN (PRELOADER)
     ====================================================================
     Menutupi layar sampai halaman selesai dimuat, lalu memudar sambil
     sedikit membesar.

     TIGA LAPIS PENGAMAN — kenapa tidak cukup satu:
     Splash ini menutupi SELURUH aplikasi. Kalau ia gagal menutup, tidak ada
     satu tombol pun yang bisa ditekan — jadi setiap jalur yang bisa membuatnya
     macet harus punya jalan keluar sendiri.

       1. window.onload mungkin SUDAH lewat saat Alpine baru siap (mis. halaman
          dipulihkan dari bfcache saat menekan tombol Back). Karena itu
          document.readyState diperiksa dulu, bukan langsung memasang listener
          yang tidak akan pernah terpanggil lagi.
       2. Event 'load' menunggu SEMUA aset. CSS & JS aplikasi sekarang dilayani
          dari server sendiri (hasil build Vite), tapi Google Fonts masih dari
          internet — kalau koneksi sekolah lambat atau diblokir, 'load' bisa
          tertahan puluhan detik. Batas keras 4 detik memastikan aplikasi tetap
          bisa dipakai walau fontnya belum sempat turun.
       3. Kalau Alpine tidak jalan sama sekali (mis. Livewire belum di-install
          lewat composer), x-show tidak akan pernah bekerja dan splash ini
          menutup aplikasi SELAMANYA. Skrip kecil di bawahnya tidak bergantung
          pada Alpine dan membersihkannya secara paksa.

     Dipakai addEventListener, bukan window.onload = ..., supaya tidak menimpa
     handler onload lain yang mungkin dipasang halaman/pustaka lain.
--}}
<div id="splash-screen"
    x-data="{ show: true }"
    x-show="show"
    x-init="
        const tutup = () => setTimeout(() => show = false, 1000);
        if (document.readyState === 'complete') { tutup(); }
        else { window.addEventListener('load', tutup, { once: true }); }
        setTimeout(() => show = false, 4000);
    "
    x-transition:leave="transition ease-in duration-700"
    x-transition:leave-start="opacity-100 transform scale-100"
    x-transition:leave-end="opacity-0 transform scale-110"
    role="status"
    aria-live="polite"
    class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-gray-50 dark:bg-gray-900">

    <img src="{{ asset('logo.png') }}" alt="Logo SIMAGAS"
        class="mb-8 h-auto w-56 animate-pulse drop-shadow-xl md:w-64">

    {{-- Tiga titik memantul. Delay-nya NEGATIF supaya animasinya dimulai di
         tengah siklus — kalau delay-nya positif, ketiga titik diam dulu lalu
         baru bergerak, dan gelombangnya tidak pernah terlihat pada splash
         sesingkat ini. --}}
    <div class="flex items-center gap-2.5">
        <span class="h-3.5 w-3.5 animate-bounce rounded-full bg-brand-400 [animation-delay:-0.32s]"></span>
        <span class="h-3.5 w-3.5 animate-bounce rounded-full bg-brand-500 [animation-delay:-0.16s]"></span>
        <span class="h-3.5 w-3.5 animate-bounce rounded-full bg-gray-800 dark:bg-gray-200"></span>
    </div>

    <p class="mt-6 text-sm font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">Memuat Sistem...</p>
</div>

<script>
    // Lapis pengaman ke-3 (lihat catatan di atas): murni JavaScript biasa,
    // tanpa Alpine. Kalau setelah 8 detik splash masih terlihat, berarti
    // Alpine memang tidak pernah jalan — buang paksa elemennya supaya
    // aplikasinya tetap bisa dipakai.
    setTimeout(function () {
        var splash = document.getElementById('splash-screen');
        if (splash && splash.style.display !== 'none') {
            splash.remove();
        }
    }, 8000);
</script>

@php
    /*
     | VERSI_ASET — harus sama persis dengan --simagas-versi di
     | resources/css/app.css. Dibandingkan di JavaScript bawah halaman.
     |
     | Kenapa ada: kegagalan paling sering saat update paket ini adalah
     | `resources/views` tersalin tapi CSS hasil build-nya TIDAK. Tailwind
     | cuma mengompilasi class yang ia temukan di source, jadi class yang
     | baru muncul di paket ini tidak ada sama sekali di CSS lama —
     | sidebar hilang, kolom pencarian hilang, nama pengguna hilang, dan
     | browser tidak melaporkan apa pun karena bagi Tailwind class tak
     | dikenal bukan error. Pernah terjadi dan butuh berjam-jam untuk
     | ditelusuri; sekarang aplikasinya yang memberi tahu sendiri.
     */
    $versiAset = '2026-09-05d';

    $pengguna = auth()->user();
    $inisial = $pengguna
        ? Str::of($pengguna->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('')
        : '?';
    $adaPencarian = $panelPrefix !== '' && Route::has($panelPrefix . '.siswa.index');
    $adaProfil = $panelPrefix !== '' && Route::has($panelPrefix . '.profil');
@endphp

{{-- Latar gelap saat sidebar dibuka di layar kecil --}}
<div id="sidebar-overlay"
    class="fixed inset-0 z-40 hidden bg-gray-900/50 backdrop-blur-[2px] lg:hidden print:hidden"
    aria-hidden="true"></div>

{{-- ================= KERANGKA APLIKASI ================= --}}
{{-- print:* — waktu dicetak, tinggi layar & scroll internal dilepas supaya
     isi halaman yang panjang tidak terpotong jadi satu lembar. --}}
<div class="flex h-screen overflow-hidden print:block print:h-auto print:overflow-visible">

    {{-- Sidebar hidup di komponennya sendiri: resources/views/components/sidebar.blade.php --}}
    <x-sidebar />

    <div id="area-konten"
        class="relative flex flex-1 flex-col overflow-y-auto overflow-x-hidden print:overflow-visible">

        {{-- ================= TOPBAR ================= --}}
        <header class="sticky top-0 z-30 flex w-full border-b border-gray-200 bg-white/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95 print:hidden">
            <div class="flex w-full items-center gap-2 px-4 py-3 sm:gap-4 md:px-6 lg:py-4">

                {{-- Satu tombol, dua tugas: di layar kecil membuka sidebar,
                     di layar besar menciutkannya jadi ikon saja. Digabung
                     karena keduanya menjawab pertanyaan yang sama dari
                     pengguna ("saya mau ruang lebih lega"). --}}
                <button type="button" id="sidebar-open"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 motion-reduce:transition-none lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false">
                    <x-icon name="menu" class="h-5 w-5 lg:hidden" />
                    <x-icon name="chevron-double-left" class="hidden h-5 w-5 lg:block" />
                </button>

                <div class="min-w-0 flex-1">
                    @if ($adaPencarian)
                        {{-- Pencarian diarahkan ke daftar siswa — satu-satunya pencarian
                             nyata di aplikasi ini. Kalau peran yang login tidak punya
                             rute itu, kolomnya tidak dirender daripada memasang kotak
                             yang tidak berfungsi. --}}
                        <form method="GET" action="{{ route($panelPrefix . '.siswa.index') }}" class="hidden max-w-md sm:block">
                            <label for="topbar-search" class="sr-only">Cari siswa</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-gray-400">
                                    <x-icon name="search" class="h-5 w-5" />
                                </span>
                                <input id="topbar-search" type="search" name="cari" value="{{ request('cari') }}"
                                    placeholder="Cari siswa…"
                                    class="shadow-theme-xs h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 pl-11 pr-4 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500">
                            </div>
                        </form>
                    @endif
                </div>

                <div class="flex shrink-0 items-center gap-2 sm:gap-3">

                    {{-- ---- Tombol pasang aplikasi (PWA) --------------------
                         Dimulai TERSEMBUNYI dan hanya muncul kalau browser
                         benar-benar menawarkan pemasangan (event
                         beforeinstallprompt). Tombol yang selalu terlihat tapi
                         mati saat diklik lebih buruk daripada tidak ada tombol
                         sama sekali.

                         Di iPhone tombol ini TIDAK akan muncul: Safari iOS
                         belum mendukung beforeinstallprompt, dan pemasangan di
                         sana lewat menu Bagikan > Tambahkan ke Layar Utama.
                         Langkahnya ditulis di BACA-DULU.md.

                         Soal nama class: brief menyebut `border-stroke`,
                         `dark:bg-meta-4`, `dark:hover:bg-meta-3` — kosakata
                         TailAdmin v1 yang tidak ada di TailAdmin Pro 2.2.0
                         (Tailwind v4) milik project ini; padanan v4-nya yang
                         dipakai di bawah. --}}
                    <button type="button" id="installPwaBtn" hidden
                        class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-100 px-4 py-2 transition duration-300 hover:bg-gray-200 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-800 dark:bg-gray-800 dark:hover:bg-gray-700"
                        title="Pasang SIMAGAS sebagai aplikasi di perangkat ini">
                        <img src="{{ asset('images/logo/icon-192x192.png') }}" alt=""
                            class="h-6 w-6 rounded-md">
                        <span class="hidden text-sm font-medium text-gray-800 sm:block dark:text-white">Install App</span>
                    </button>


                    {{-- ---- Tombol ganti tema -------------------------------
                         Menyimpan pilihannya lewat window.simagasTema (lihat
                         resources/js/app.js), kunci yang SAMA dengan skrip
                         kecil di <head>. Dua ikon selalu ada di DOM dan yang
                         menentukan mana yang terlihat adalah varian dark: —
                         jadi tidak ada kedipan ikon salah saat halaman baru
                         dimuat dalam mode gelap. --}}
                    <button type="button" id="tombol-tema"
                        class="flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 motion-reduce:transition-none lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        aria-label="Ganti mode terang / gelap">
                        <x-icon name="moon" class="h-5 w-5 dark:hidden" />
                        <x-icon name="sun" class="hidden h-5 w-5 dark:block" />
                    </button>

                    {{-- ---- Notifikasi -------------------------------------
                         Komponen Livewire tersendiri (App\Livewire\NotificationBell)
                         karena isinya butuh query database dan aksi
                         tandai-terbaca / hapus. Penyegarannya didorong Pusher
                         lewat event 'segarkan-lonceng' di akhir berkas ini. --}}
                    <livewire:notification-bell />

                    {{-- ---- Menu pengguna ----------------------------------- --}}
                    @if ($pengguna)
                        <div class="relative" data-dropdown>
                            <button type="button" data-dropdown-tombol
                                class="flex items-center gap-2 rounded-full text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                aria-haspopup="true" aria-expanded="false">
                                {{-- Inisial jadi dasar, foto ditumpuk di atasnya; kalau
                                     berkas fotonya hilang, onerror membuang <img>-nya
                                     sehingga yang tampil inisial, bukan ikon gambar
                                     rusak. Lihat catatan yang sama di <x-sidebar />. --}}
                                <span class="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-500 text-xs font-bold text-white lg:h-11 lg:w-11">
                                    {{ $inisial }}
                                    @if ($pengguna->foto_url)
                                        <img src="{{ $pengguna->foto_url }}" alt="" onerror="this.remove()"
                                            class="absolute inset-0 h-full w-full object-cover">
                                    @endif
                                </span>
                                <span class="hidden min-w-0 md:block">
                                    <span class="block max-w-[9rem] truncate text-sm font-medium text-gray-700 dark:text-gray-200">{{ $pengguna->name }}</span>
                                    <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $pengguna->role->label() }}</span>
                                </span>
                                <x-icon name="chevron-down" class="hidden h-4 w-4 text-gray-400 md:block" />
                            </button>

                            <div data-dropdown-panel hidden
                                class="shadow-theme-lg absolute right-0 mt-2 w-60 rounded-2xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
                                <div class="border-b border-gray-200 px-2 pb-3 dark:border-gray-800">
                                    <p class="truncate text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $pengguna->name }}</p>
                                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $pengguna->email }}</p>
                                </div>

                                @if ($adaProfil)
                                    <a href="{{ route($panelPrefix . '.profil') }}"
                                        class="mt-2 flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                                        <x-icon name="identification" class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                                        Profil Saya
                                    </a>
                                @endif

                                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                                    @csrf
                                    <button type="submit"
                                        class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-error-50 hover:text-error-600 dark:text-gray-300 dark:hover:bg-error-500/10 dark:hover:text-error-500">
                                        <x-icon name="logout" class="h-5 w-5" />
                                        Keluar
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </header>

        {{-- ================= KONTEN ================= --}}
        <main class="mx-auto w-full max-w-[1536px] p-4 md:p-6">

            @if (session('status'))
                <div class="mb-6 flex items-start gap-3 rounded-2xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-success-500/15 text-success-600 dark:text-success-500">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <p class="pt-1.5 text-sm font-medium text-success-700 dark:text-success-500">{{ session('status') }}</p>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 flex items-start gap-3 rounded-2xl border border-error-200 bg-error-50 p-4 dark:border-error-500/30 dark:bg-error-500/10">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-error-500/15 text-error-600 dark:text-error-500">
                        <x-icon name="exclamation-triangle" class="h-5 w-5" />
                    </span>
                    <ul class="space-y-1 pt-1.5 text-sm font-medium text-error-700 dark:text-error-500">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')

        </main>
    </div>
</div>

<script>
    /*
     | Perilaku kerangka: sidebar, mode ciut, dropdown, dan tombol tema.
     |
     | Ditulis JavaScript biasa, BUKAN Alpine — walau TailAdmin aslinya
     | memakai Alpine untuk semua ini. Alasannya satu: Alpine di project ini
     | datang menumpang di dalam bundel Livewire. Kalau Livewire gagal dimuat
     | (belum di-composer require, atau berkasnya kena cache rusak), seluruh
     | navigasi ikut mati — menu tidak bisa dibuka, tombol logout di dropdown
     | tidak terjangkau. Dengan JS biasa, kerangkanya tetap hidup apa pun yang
     | terjadi pada Livewire.
     */
    (function () {
        // ---- Sidebar (layar kecil) & mode ciut (layar besar) -------------
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebar-overlay');
        var tombolBuka = document.getElementById('sidebar-open');
        var tombolTutup = document.getElementById('sidebar-close');
        var LEBAR_DESKTOP = window.matchMedia('(min-width: 1024px)');
        var KUNCI_CIUT = 'simagas-sidebar-ciut';

        function bacaCiut() {
            try { return localStorage.getItem(KUNCI_CIUT) === '1'; } catch (e) { return false; }
        }

        function pasangCiut(ciut) {
            document.body.classList.toggle('sidebar-ciut', ciut);
            try { localStorage.setItem(KUNCI_CIUT, ciut ? '1' : '0'); } catch (e) { /* diabaikan */ }
        }

        // Diterapkan lebih awal supaya sidebar tidak sempat terlihat lebar
        // dulu lalu menyempit sendiri di depan mata pengguna.
        if (bacaCiut()) document.body.classList.add('sidebar-ciut');

        function buka() {
            if (!sidebar) return;
            sidebar.classList.remove('-translate-x-full');
            if (overlay) overlay.classList.remove('hidden');
            tombolBuka.setAttribute('aria-expanded', 'true');
            document.body.classList.add('overflow-hidden');
        }

        function tutup() {
            if (!sidebar) return;
            sidebar.classList.add('-translate-x-full');
            if (overlay) overlay.classList.add('hidden');
            tombolBuka.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('overflow-hidden');
        }

        if (tombolBuka) {
            tombolBuka.addEventListener('click', function () {
                if (LEBAR_DESKTOP.matches) {
                    pasangCiut(!document.body.classList.contains('sidebar-ciut'));
                } else {
                    var terbuka = tombolBuka.getAttribute('aria-expanded') === 'true';
                    terbuka ? tutup() : buka();
                }
            });
        }

        if (tombolTutup) tombolTutup.addEventListener('click', tutup);
        if (overlay) overlay.addEventListener('click', tutup);

        // Kalau layar dibesarkan ke ukuran desktop saat menu mobile terbuka,
        // kunci scroll body harus ikut dilepas.
        LEBAR_DESKTOP.addEventListener('change', function (e) {
            if (e.matches) tutup();
        });

        // ---- Tombol pasang aplikasi (PWA) --------------------------------
        /*
         | Browser memutuskan sendiri KAPAN sebuah situs layak dipasang. Kalau
         | syaratnya terpenuhi (manifest valid + service worker + HTTPS/
         | localhost) ia menembakkan 'beforeinstallprompt'; kalau tidak,
         | event itu tidak pernah datang dan tombolnya tetap tersembunyi.
         | Itu perilaku yang benar: tombol yang mati saat diklik jauh lebih
         | membingungkan daripada tombol yang memang tidak ada.
         |
         | Di iPhone event ini tidak ada sama sekali — Safari iOS memasang
         | lewat menu Bagikan > Tambahkan ke Layar Utama. Jadi tombol ini
         | memang tidak akan muncul di sana, dan itu disengaja.
         */
        var tombolPasang = document.getElementById('installPwaBtn');
        var promptTertunda = null;

        // Sudah berjalan sebagai aplikasi terpasang? Jangan tawarkan lagi.
        var sudahTerpasang = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        if (tombolPasang && !sudahTerpasang) {
            window.addEventListener('beforeinstallprompt', function (e) {
                // Menahan tawaran bawaan browser supaya pemasangan terjadi
                // saat pengguna menekan tombol kita, bukan sebagai spanduk
                // yang muncul mendadak di tengah halaman.
                e.preventDefault();
                promptTertunda = e;
                tombolPasang.hidden = false;
            });

            tombolPasang.addEventListener('click', function () {
                if (!promptTertunda) return;

                tombolPasang.hidden = true;
                promptTertunda.prompt();

                promptTertunda.userChoice.then(function (pilihan) {
                    // Kalau pengguna membatalkan, tombolnya DIMUNCULKAN LAGI.
                    // Brief menyembunyikannya secara permanen; itu berarti
                    // orang yang tidak sengaja menekan "Nanti" kehilangan
                    // satu-satunya jalan memasang aplikasi sampai ia
                    // menemukan menu tersembunyi di browsernya.
                    if (pilihan && pilihan.outcome !== 'accepted') {
                        tombolPasang.hidden = false;
                    }

                    // Event ini hanya boleh dipakai sekali; browser akan
                    // mengirim yang baru kalau memang masih menawarkan.
                    promptTertunda = null;
                });
            });

            window.addEventListener('appinstalled', function () {
                tombolPasang.hidden = true;
                promptTertunda = null;
            });
        }

        // ---- Tombol ganti tema -------------------------------------------
        var tombolTema = document.getElementById('tombol-tema');
        if (tombolTema && window.simagasTema) {
            tombolTema.addEventListener('click', function () {
                window.simagasTema.ganti();
            });
        }

        // ---- Dropdown (notifikasi & pengguna) ----------------------------
        // Satu handler untuk semua dropdown: setiap wadah cukup diberi
        // data-dropdown, tombolnya data-dropdown-tombol, panelnya
        // data-dropdown-panel. Menambah dropdown baru nanti tidak perlu
        // menulis JavaScript lagi.
        var dropdowns = Array.prototype.slice.call(document.querySelectorAll('[data-dropdown]'));

        function tutupSemuaDropdown(kecuali) {
            dropdowns.forEach(function (d) {
                if (d === kecuali) return;
                var panel = d.querySelector('[data-dropdown-panel]');
                var tombol = d.querySelector('[data-dropdown-tombol]');
                if (panel) panel.hidden = true;
                if (tombol) tombol.setAttribute('aria-expanded', 'false');
            });
        }

        dropdowns.forEach(function (d) {
            var tombol = d.querySelector('[data-dropdown-tombol]');
            var panel = d.querySelector('[data-dropdown-panel]');
            if (!tombol || !panel) return;

            tombol.addEventListener('click', function (e) {
                e.stopPropagation();
                var akanBuka = panel.hidden;
                tutupSemuaDropdown(d);

                // Lonceng notifikasi memakai Alpine (komponen Livewire
                // tersendiri), jadi ia tidak ikut tersapu tutupSemuaDropdown.
                // Dua sistem dropdown itu saling memberi tahu lewat event ini
                // supaya tidak pernah ada dua panel terbuka bersamaan.
                if (akanBuka) window.dispatchEvent(new CustomEvent('simagas-tutup-dropdown'));

                panel.hidden = !akanBuka;
                tombol.setAttribute('aria-expanded', akanBuka ? 'true' : 'false');
            });

            panel.addEventListener('click', function (e) { e.stopPropagation(); });
        });

        document.addEventListener('click', function () { tutupSemuaDropdown(null); });

        // Arah sebaliknya: lonceng dibuka -> dropdown lain ditutup.
        window.addEventListener('simagas-tutup-lain', function () { tutupSemuaDropdown(null); });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            tutupSemuaDropdown(null);
            tutup();
        });

        // ---- Pemeriksa versi CSS -----------------------------------------
        // Lihat catatan pada $versiAset di atas. Kalau CSS-nya tertinggal,
        // halaman akan terlihat rusak tanpa satu pun pesan error — jadi
        // pesannya dibuat di sini.
        var versiBlade = @json($versiAset);
        var versiCss = getComputedStyle(document.documentElement)
            .getPropertyValue('--simagas-versi').trim().replace(/^["']|["']$/g, '');

        if (versiCss !== versiBlade) {
            /*
             | Pesannya BEDA tergantung SISI MANA yang tertinggal — dan itu
             | penting, bukan sekadar kalimat yang lebih rapi.
             |
             | Versi sebelumnya selalu berbunyi "berkas CSS-nya versi lama".
             | Padahal arah sebaliknya sama seringnya terjadi: berkas public/
             | sudah tersalin tapi resources/views/ belum, atau sudah tersalin
             | tapi cache Blade di storage/framework/views/ masih memegang
             | versi lama. Pesan yang menuduh sisi yang salah membuat orang
             | menyalin ulang public/ berkali-kali — dan tidak pernah selesai,
             | karena yang tertinggal justru sisi satunya.
             |
             | Perbandingannya leksikografis: penanda versinya berformat
             | "YYYY-MM-DDx" yang memang terurut apa adanya sebagai teks.
             */
            var cssLebihBaru = versiCss > versiBlade;

            var judul = cssLebihBaru
                ? 'Tampilan mungkin tidak benar: berkas tampilan (Blade) versi lama.'
                : 'Tampilan tidak akan benar: berkas CSS-nya versi lama.';

            var saran = cssLebihBaru
                ? 'Yang tertinggal BUKAN CSS-nya, melainkan sisi PHP. Salin ulang ' +
                  '<code>resources/views/</code> dari paket, lalu WAJIB bersihkan cache ' +
                  'tampilan: <code>php artisan view:clear</code> — atau hapus semua berkas ' +
                  '<code>*.php</code> di <code>storage/framework/views/</code> lewat File Manager. ' +
                  'Cache lama tetap dipakai kalau tanggal berkas hasil unggahan lebih tua ' +
                  'daripada cache-nya.'
                : 'Salin ulang folder <code>public/build/</code> beserta ' +
                  '<code>public/simagas.css</code> dari paket, lalu tekan Ctrl+Shift+R. ' +
                  'Kalau Anda memakai npm, jalankan <code>npm run build</code>.';

            var pita = document.createElement('div');
            pita.setAttribute('role', 'alert');
            pita.style.cssText =
                'position:fixed;left:0;right:0;bottom:0;z-index:99999;' +
                'background:' + (cssLebihBaru ? '#7a4b0a' : '#7a271a') + ';color:#fff;' +
                'padding:14px 18px;font:14px/1.5 system-ui,sans-serif;text-align:left';
            pita.innerHTML =
                '<strong>' + judul + '</strong><br>' +
                'CSS terbaca <code>' + (versiCss || 'tidak ada') + '</code>, ' +
                'halaman meminta <code>' + versiBlade + '</code>. ' + saran;
            document.body.appendChild(pita);
        }
    })();
</script>

{{-- Harus di AKHIR body, setelah semua markup Livewire dirender. --}}
@livewireScripts

{{-- Tempat halaman/komponen menitipkan <script> tambahan lewat @push('scripts').
     Sengaja SETELAH @livewireScripts supaya Alpine (yang ikut dibundel
     Livewire v3) sudah tersedia untuk skrip yang dititipkan di sini. --}}
@stack('scripts')

{{-- ====================================================================
     NOTIFIKASI REAL-TIME (Pusher) + EFEK SUARA

     Blok ini SENGAJA di akhir <body>, sesudah @livewireScripts: ia memanggil
     Livewire.dispatch(), dan Livewire baru ada setelah skripnya dimuat.

     Hanya dipasang untuk pengguna yang sudah login DAN ketika kunci Pusher
     benar-benar terisi. Tanpa penjagaan itu, halaman di server yang belum
     mengisi .env akan mencoba menyambung ke WebSocket dengan key kosong dan
     mencetak error merah di konsol setiap beberapa detik selamanya.
     ==================================================================== --}}
@auth
    @php
        $pusherKey = config('broadcasting.connections.pusher.key');
        $pusherCluster = config('broadcasting.connections.pusher.options.cluster', 'ap1');
    @endphp

    {{-- Berkas suara. preload="auto" supaya bunyi pertama tidak tertunda
         unduhan; ukurannya hanya belasan KB. --}}
    <audio id="notifSound" src="{{ asset('audio/notif.mp3') }}" preload="auto"></audio>

    @if ($pusherKey)
        <script src="https://js.pusher.com/8.4/pusher.min.js"></script>
        <script>
        (function () {
            'use strict';

            var KEY = @json($pusherKey);
            var CLUSTER = @json($pusherCluster);
            var USER_ID = {{ (int) auth()->id() }};
            var metaCsrf = document.querySelector('meta[name="csrf-token"]');
            var CSRF = metaCsrf ? metaCsrf.getAttribute('content') : '';

            /* ============================================================
               EFEK SUARA — dan kenapa ada "pembuka kunci"

               Semua browser modern MENOLAK memutar audio sebelum pengguna
               pernah berinteraksi dengan halaman (klik/ketuk/tekan tombol).
               Jadi document.getElementById('notifSound').play() yang ditulis
               polos akan melempar NotAllowedError pada notifikasi pertama —
               diam, tanpa bunyi, dan dengan error merah di konsol.

               Penanganannya: play() dibungkus, kegagalannya ditangkap, dan
               satu interaksi pertama apa pun dipakai untuk "menghangatkan"
               elemen audio (diputar tanpa suara lalu langsung dihentikan).
               Sesudah itu pemutaran berikutnya diizinkan browser.
               ============================================================ */
            var audio = document.getElementById('notifSound');
            var siapBunyi = false;

            function hangatkanAudio() {
                if (siapBunyi || ! audio) { return; }

                var v = audio.volume;
                audio.volume = 0;

                var p = audio.play();

                if (p && typeof p.then === 'function') {
                    p.then(function () {
                        audio.pause();
                        audio.currentTime = 0;
                        audio.volume = v;
                        siapBunyi = true;
                    }).catch(function () {
                        audio.volume = v;
                    });
                }
            }

            ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
                window.addEventListener(ev, hangatkanAudio, { once: true, passive: true });
            });

            window.simagasBunyiNotif = function () {
                if (! audio) { return; }

                try {
                    audio.currentTime = 0;
                    var p = audio.play();

                    if (p && typeof p.catch === 'function') {
                        // Ditelan diam-diam: notifikasinya SUDAH tampil di
                        // lonceng, jadi bunyi yang gagal bukan kegagalan yang
                        // perlu diteriakkan ke konsol pengguna.
                        p.catch(function () {});
                    }
                } catch (e) { /* elemen audio tidak didukung; abaikan. */ }
            };

            /* ============================================================
               PUSHER

               Memakai pusher-js LANGSUNG, bukan Laravel Echo. Echo adalah
               pembungkus tipis di atas pustaka ini; memakainya di sini berarti
               satu berkas CDN tambahan dan satu lapisan lagi yang bisa gagal,
               sementara yang dibutuhkan cuma: berlangganan satu channel dan
               mendengarkan satu event. Nama channel-nya tetap mengikuti
               konvensi Laravel ('private-App.Models.User.{id}'), jadi kalau
               nanti Anda ingin memakai Echo, tidak ada yang perlu diubah di
               sisi server.
               ============================================================ */
            if (typeof Pusher === 'undefined') { return; }

            var pusher = new Pusher(KEY, {
                cluster: CLUSTER,
                forceTLS: true,
                authEndpoint: '{{ url('broadcasting/auth') }}',
                auth: { headers: { 'X-CSRF-TOKEN': CSRF } },
            });

            var channel = pusher.subscribe('private-App.Models.User.' + USER_ID);

            function adaNotifikasiBaru(data) {
                window.simagasBunyiNotif();

                // Isi loncengnya DIBACA ULANG dari server lewat Livewire,
                // bukan disisipkan dari payload Pusher. Payload itu datang
                // dari luar; menampilkannya apa adanya berarti apa pun yang
                // bisa menembus channel ikut tampil sebagai notifikasi resmi.
                if (window.Livewire) {
                    window.Livewire.dispatch('segarkan-lonceng');
                }

                if (window.simagasSwal && data && data.judul) {
                    window.simagasSwal.toast({
                        icon: 'info',
                        title: data.judul,
                        timer: 5000,
                    });
                }
            }

            // Diekspos supaya bisa diuji dari konsol browser tanpa menunggu
            // pengumuman sungguhan:  window.simagasNotifMasuk({judul: 'Tes'})
            window.simagasNotifMasuk = adaNotifikasiBaru;

            channel.bind('pengumuman.baru', adaNotifikasiBaru);

            // Nama event bawaan Laravel, untuk notifikasi yang memakai
            // via() => ['broadcast']. Dipasang sekalian supaya menambah jenis
            // notifikasi real-time lain nanti tidak perlu menyentuh berkas ini.
            channel.bind(
                'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
                adaNotifikasiBaru
            );

            channel.bind('pusher:subscription_error', function (status) {
                // 403 di sini artinya routes/channels.php belum terdaftar atau
                // withBroadcasting() belum dipasang di bootstrap/app.php.
                console.warn('[SIMAGAS] Berlangganan channel notifikasi gagal.', status);
            });
        })();
        </script>
    @endif
@endauth

</body>
</html>
