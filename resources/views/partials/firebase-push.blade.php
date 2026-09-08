{{--
    ====================================================================
    NOTIFIKASI HP (Web Push / Firebase Cloud Messaging) — sisi browser
    ====================================================================

    Disertakan dari resources/views/layouts/app.blade.php, di dalam @auth.

    Isi partial ini SAMA SEKALI TIDAK DIRENDER kalau kredensial Firebase
    belum diisi di .env. Itu penting: server yang belum dikonfigurasi tidak
    akan memuat SDK, tidak meminta izin apa pun, dan tidak mencetak satu
    baris error pun di konsol. Fitur ini benar-benar mati sampai .env-nya
    lengkap, bukan setengah hidup.

    ================== TIGA HAL YANG WAJIB TERPENUHI ==================
      1. HTTPS. Di http:// biasa, Notification & Service Worker ditolak
         browser. simagas.online sudah HTTPS, jadi aman — tapi di Laragon
         lokal (http://absensi-smk.test) fitur ini memang tidak akan jalan,
         dan itu normal. Pakai localhost kalau ingin mengujinya lokal.
      2. IZIN dari pengguna. Sekali "Blokir" ditekan, browser TIDAK AKAN
         bertanya lagi selamanya — hanya bisa dipulihkan lewat setelan
         situs. Karena itu lihat catatan di bawah soal cara meminta izin.
      3. .env lengkap (FIREBASE_WEB_* dan FIREBASE_VAPID_KEY).
    ==================================================================
--}}

@php
    $konfigFirebase = \App\Services\FirebasePushService::konfigWeb();
@endphp

@if (\App\Services\FirebasePushService::aktif())

    {{--
        ============ KARTU AJAKAN, BUKAN DIALOG LANGSUNG ============
        Permintaan asli meminta Notification.requestPermission() dipanggil
        setiap kali aplikasi dibuka. Di sini SENGAJA tidak persis begitu,
        dan alasannya bukan gaya-gayaan:

        Dialog izin yang muncul mendadak begitu halaman terbuka — sebelum
        pengguna tahu ia sedang ditawari apa — ditolak oleh sebagian besar
        orang secara refleks. Dan "Blokir" itu PERMANEN: browser tidak akan
        pernah bertanya lagi, dan kode kita tidak punya cara apa pun untuk
        memintanya ulang. Satu dialog yang salah waktu = wali murid itu
        kehilangan fitur ini SELAMANYA.

        Chrome juga sudah menerapkan "abusive notification prompt
        blocking": situs yang sering meminta izin lalu ditolak akan
        dibungkam otomatis untuk semua pengunjungnya.

        Jadi alurnya: kartu kecil di pojok yang menjelaskan dulu, baru
        dialog browser muncul SETELAH tombol "Aktifkan" ditekan. Yang
        menekan tombol itu sudah tahu ia mau apa, dan hampir selalu
        mengizinkan.

        Yang TETAP otomatis di setiap pembukaan aplikasi (persis seperti
        yang diminta) adalah PENGAMBILAN & PENGIRIMAN TOKEN untuk pengguna
        yang izinnya sudah diberikan — lihat mulaiJalan() di bawah.
        =============================================================
    --}}
    <div id="push-ajakan"
        class="fixed bottom-4 right-4 z-[99999] hidden w-[calc(100%-2rem)] max-w-sm rounded-2xl border border-gray-200 bg-white p-4 shadow-theme-lg dark:border-gray-800 dark:bg-gray-900"
        role="dialog" aria-live="polite" aria-label="Aktifkan notifikasi HP">

        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-500/10 text-brand-600 dark:text-brand-400">
                <x-icon name="bell" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
                    Aktifkan notifikasi di HP ini?
                </p>
                <p class="mt-1 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                    Anda akan langsung diberi tahu saat kehadiran terdeteksi, walau aplikasi sedang tertutup.
                </p>

                <div class="mt-3 flex items-center gap-2">
                    <button type="button" id="push-aktifkan"
                        class="rounded-lg bg-brand-500 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                        Aktifkan
                    </button>

                    <button type="button" id="push-nanti"
                        class="rounded-lg px-3 py-2 text-xs font-medium text-gray-500 transition-colors hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/[0.03]">
                        Nanti saja
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Versi DIPATOK (10.12.2), bukan "latest": rilis Firebase yang
         mengubah perilaku akan langsung terpasang di semua HP pengguna
         tanpa satu pun perubahan di sisi kita. --}}
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js"></script>

    <script>
    (function () {
        'use strict';

        var KONFIG = @json($konfigFirebase);
        var VAPID = @json((string) config('firebase.vapid_key'));
        var URL_SIMPAN = @json(route('fcm.token.simpan'));
        var URL_HAPUS = @json(route('fcm.token.hapus'));
        var URL_SW = @json(asset('firebase-messaging-sw.js')) + '?' + new URLSearchParams({
            apiKey: KONFIG.apiKey || '',
            authDomain: KONFIG.authDomain || '',
            projectId: KONFIG.projectId || '',
            storageBucket: KONFIG.storageBucket || '',
            messagingSenderId: KONFIG.messagingSenderId || '',
            appId: KONFIG.appId || '',
            icon: @json((string) config('firebase.icon')),
            badge: @json((string) config('firebase.badge')),
        }).toString();

        /*
         | Scope KHUSUS, bukan '/'.
         |
         | Satu URL hanya boleh dikendalikan satu service worker. Kalau
         | firebase-messaging-sw.js didaftarkan di '/', ia MENGGANTIKAN
         | public/sw.js — dan halaman offline beserta status "installable"
         | PWA-nya ikut hilang tanpa satu pun pesan error. Alamat di bawah
         | memang tidak pernah dibuka siapa pun; ia hanya penanda wilayah.
         */
        var SCOPE_SW = '/firebase-cloud-messaging-push-scope';

        var KUNCI_TUNDA = 'simagas-push-ditunda';
        var TUNDA_HARI = 7;

        var metaCsrf = document.querySelector('meta[name="csrf-token"]');
        var CSRF = metaCsrf ? metaCsrf.getAttribute('content') : '';

        var messaging = null;
        var tokenTerkirim = null;

        // ----------------------------------------------------------------
        // Penjagaan lingkungan
        // ----------------------------------------------------------------
        var didukung =
            'serviceWorker' in navigator &&
            'Notification' in window &&
            'PushManager' in window &&
            window.isSecureContext;

        if (! didukung || ! KONFIG.apiKey || ! VAPID) {
            /*
             | Berhenti DIAM-DIAM. Ini bukan kegagalan: iOS di bawah 16.4,
             | browser dalam mode penyamaran, dan situs yang dibuka lewat
             | http:// biasa memang tidak bisa menerima Web Push. Menulis
             | error merah di konsol untuk sesuatu yang tidak bisa
             | diperbaiki pengguna hanya melatih orang mengabaikan konsol.
             */
            return;
        }

        // ----------------------------------------------------------------
        // Utilitas
        // ----------------------------------------------------------------
        function ditunda() {
            try {
                var sampai = parseInt(localStorage.getItem(KUNCI_TUNDA) || '0', 10);
                return sampai > Date.now();
            } catch (e) {
                return false;
            }
        }

        function tundaSelama(hari) {
            try {
                localStorage.setItem(KUNCI_TUNDA, String(Date.now() + hari * 86400000));
            } catch (e) { /* mode penyamaran: cukup berlaku untuk sesi ini. */ }
        }

        function tampilkanAjakan(tampil) {
            var kartu = document.getElementById('push-ajakan');
            if (kartu) { kartu.classList.toggle('hidden', ! tampil); }
        }

        // ----------------------------------------------------------------
        // Firebase
        // ----------------------------------------------------------------
        function siapkanMessaging() {
            if (messaging) { return Promise.resolve(messaging); }

            try {
                if (! firebase.apps.length) { firebase.initializeApp(KONFIG); }
                messaging = firebase.messaging();
            } catch (e) {
                return Promise.reject(e);
            }

            return navigator.serviceWorker.register(URL_SW, { scope: SCOPE_SW })
                .then(function (reg) {
                    /*
                     | Menunggu service worker benar-benar AKTIF sebelum
                     | meminta token.
                     |
                     | getToken() yang dipanggil terhadap registration yang
                     | masih 'installing' gagal dengan
                     | "AbortError: Failed to execute 'subscribe' on
                     | 'PushManager'" — error yang muncul HANYA pada
                     | kunjungan PERTAMA (saat SW-nya baru dipasang) dan
                     | hilang setelah halaman di-refresh. Sangat sulit
                     | ditebak kalau tidak tahu penyebabnya.
                     */
                    return navigator.serviceWorker.ready.then(function () { return reg; });
                })
                .then(function (reg) {
                    messaging.__reg = reg;
                    return messaging;
                });
        }

        function ambilDanKirimToken() {
            return siapkanMessaging()
                .then(function (m) {
                    return m.getToken({
                        vapidKey: VAPID,
                        serviceWorkerRegistration: m.__reg,
                    });
                })
                .then(function (token) {
                    if (! token) { return null; }

                    // Token yang sama tidak dikirim dua kali dalam satu
                    // kunjungan halaman. Livewire wire:navigate berpindah
                    // halaman tanpa memuat ulang skrip ini, tapi
                    // pemanggilan dari tempat lain tetap mungkin.
                    if (token === tokenTerkirim) { return token; }

                    return fetch(URL_SIMPAN, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': CSRF,
                            'Accept': 'application/json',
                        },
                        // 'same-origin' supaya cookie sesi ikut terkirim —
                        // tanpa itu Laravel melihatnya sebagai tamu dan
                        // menjawab 401.
                        credentials: 'same-origin',
                        body: JSON.stringify({ token: token }),
                    }).then(function (r) {
                        if (r.ok) { tokenTerkirim = token; }
                        return token;
                    });
                });
        }

        function pasangPendengarDepan() {
            if (! messaging) { return; }

            /*
             | Notifikasi yang datang saat tab SIMAGAS SEDANG DIBUKA.
             |
             | Browser SENGAJA tidak menampilkan notifikasi sistem dalam
             | keadaan ini — anggapannya pengguna sudah melihat aplikasinya.
             | Jadi kabarnya ditampilkan di dalam halaman, memakai toast
             | SweetAlert2 dan bunyi notif.mp3 yang sudah dipakai lonceng
             | pengumuman. Satu kejadian, satu gaya pemberitahuan.
             */
            messaging.onMessage(function (payload) {
                var data = (payload && payload.data) || {};

                if (typeof window.simagasBunyiNotif === 'function') {
                    window.simagasBunyiNotif();
                }

                if (window.simagasSwal && typeof window.simagasSwal.toast === 'function') {
                    // Kunci bahasa Inggris, bukan judul/teks: window.simagasSwal
                    // menerima nama asli SweetAlert2. Penerjemahan kunci
                    // Indonesia hanya dilakukan di pendengar event
                    // 'swal:toast', bukan di pemanggilan langsung seperti ini.
                    window.simagasSwal.toast({
                        icon: 'success',
                        title: data.judul || 'SIMAGAS',
                        text: data.isi || '',
                    });
                }
            });
        }

        // ----------------------------------------------------------------
        // Alur utama
        // ----------------------------------------------------------------
        function mulaiJalan() {
            if (Notification.permission === 'granted') {
                /*
                 | INI bagian "dijalankan setiap kali user login / membuka
                 | aplikasi" yang diminta. Bukan basa-basi: Firebase boleh
                 | mengganti token perangkat kapan saja (browser diperbarui,
                 | data situs dibersihkan, izin dicabut lalu diberikan
                 | lagi), dan token lama berhenti berfungsi TANPA
                 | pemberitahuan apa pun. Mengirim ulang di setiap pembukaan
                 | adalah cara termurah memastikan yang tersimpan di server
                 | selalu token yang hidup.
                 */
                ambilDanKirimToken()
                    .then(pasangPendengarDepan)
                    .catch(function (e) {
                        console.warn('[SIMAGAS] Token notifikasi gagal diambil:', e && e.message);
                    });

                return;
            }

            // Pernah ditolak -> tidak ada yang bisa dilakukan kode ini.
            // Browser tidak akan bertanya lagi; hanya pengguna yang bisa
            // memulihkannya lewat setelan situs.
            if (Notification.permission === 'denied') { return; }

            if (ditunda()) { return; }

            // Kartunya baru muncul sesudah halaman tenang, supaya tidak
            // menutupi apa pun yang sedang dibaca pengguna saat membuka
            // aplikasi.
            setTimeout(function () { tampilkanAjakan(true); }, 2500);
        }

        document.addEventListener('click', function (e) {
            if (e.target.closest('#push-nanti')) {
                tampilkanAjakan(false);
                tundaSelama(TUNDA_HARI);
                return;
            }

            if (! e.target.closest('#push-aktifkan')) { return; }

            tampilkanAjakan(false);

            // requestPermission() dipanggil DI DALAM penanganan klik —
            // itulah "user gesture" yang membuat browser menampilkan
            // dialognya tanpa curiga.
            Notification.requestPermission().then(function (izin) {
                if (izin !== 'granted') {
                    tundaSelama(TUNDA_HARI);
                    return;
                }

                return ambilDanKirimToken().then(pasangPendengarDepan);
            }).catch(function (err) {
                console.warn('[SIMAGAS] Gagal mengaktifkan notifikasi:', err && err.message);
            });
        });

        /*
         | ============ LEPAS TOKEN SAAT LOGOUT ============
         | Tanpa ini, HP yang dipinjamkan ke orang lain akan terus berbunyi
         | membawakan kabar kehadiran anak pemilik akun sebelumnya —
         | kebocoran data yang sangat mudah terjadi dan sangat sulit
         | dijelaskan ke orang tua murid.
         |
         | Dipasang pada fase BUBBLE (bukan capture) dan TIDAK memanggil
         | preventDefault: dialog konfirmasi SweetAlert2 sudah memakai fase
         | capture untuk form yang sama, dan mencampuri urutan itu akan
         | membuat tombol logout berhenti bekerja.
         |
         | keepalive:true membuat permintaan ini tetap diselesaikan browser
         | walau halamannya langsung berpindah setelah form terkirim.
         */
        document.addEventListener('submit', function (e) {
            var form = e.target;

            if (! form || ! form.action || form.action.indexOf('/logout') === -1) { return; }
            if (Notification.permission !== 'granted' || ! tokenTerkirim) { return; }

            try {
                fetch(URL_HAPUS, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF,
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    keepalive: true,
                    body: JSON.stringify({ token: tokenTerkirim }),
                }).catch(function () { /* diabaikan: logout tidak boleh tertahan. */ });
            } catch (err) { /* sama. */ }
        });

        // Disediakan supaya halaman lain (atau konsol, saat mendiagnosis)
        // bisa memicu ulang tanpa perlu tahu isi berkas ini.
        window.simagasPush = {
            aktifkan: function () {
                return Notification.requestPermission()
                    .then(function (izin) {
                        return izin === 'granted' ? ambilDanKirimToken().then(pasangPendengarDepan) : null;
                    });
            },
            segarkanToken: ambilDanKirimToken,
            status: function () { return Notification.permission; },
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', mulaiJalan);
        } else {
            mulaiJalan();
        }
    })();
    </script>
@endif
