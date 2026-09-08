/*
 |----------------------------------------------------------------------
 | Service worker notifikasi HP (Firebase Cloud Messaging)
 |----------------------------------------------------------------------
 | Berkas ini berjalan DI LATAR BELAKANG — bahkan ketika tab SIMAGAS sudah
 | ditutup dan HP sedang terkunci. Inilah yang membuat notifikasinya muncul
 | seperti aplikasi DANA atau BRImo, bukan seperti pesan di dalam halaman.
 |
 | ================== DUA SERVICE WORKER, DAN KENAPA AMAN ==================
 | Aplikasi ini punya DUA service worker sekaligus:
 |
 |   public/sw.js                  scope '/'                       -> PWA & offline
 |   public/firebase-messaging-sw.js  scope '/firebase-cloud-messaging-push-scope'
 |
 | Itu bukan kelalaian. Satu URL hanya boleh dikendalikan SATU service
 | worker, jadi kalau berkas ini didaftarkan di scope '/' juga, ia akan
 | MENGGANTIKAN sw.js — dan halaman offline beserta status "installable"
 | PWA-nya ikut hilang, tanpa satu pun pesan error.
 |
 | Scope '/firebase-cloud-messaging-push-scope' adalah alamat yang memang
 | tidak pernah dibuka siapa pun. Service worker ini karena itu tidak
 | mengendalikan satu halaman pun — dan memang tidak perlu: pesan push
 | dikirim ke service worker-nya langsung, bukan lewat halaman. Berkas ini
 | juga TIDAK memasang handler 'fetch' apa pun, supaya tidak pernah ikut
 | campur dalam pemuatan halaman.
 | =========================================================================
 |
 | ================== KENAPA KONFIGNYA DARI QUERY STRING ==================
 | Berkas ini statis — Blade tidak bisa menyuntikkan apa pun ke dalamnya.
 | Menulis apiKey dsb. langsung di sini berarti kredensial ikut ter-commit
 | ke GitHub, tepat yang harus dihindari.
 |
 | Jadi konfigurasinya dititipkan saat pendaftaran, sebagai query string:
 |   /firebase-messaging-sw.js?apiKey=...&projectId=...
 | dan dibaca di bawah dari self.location. Nilai-nilai ini memang tidak
 | rahasia (Firebase merancangnya untuk dikirim ke browser), tapi tetap
 | tidak ditulis di kode — supaya satu berkas .env tetap menjadi
 | satu-satunya tempat ganti project Firebase.
 | ========================================================================
 */

/* global importScripts, firebase */

// Versi DIPATOK, bukan "latest". Service worker sangat awet di browser
// pengguna; sebuah rilis Firebase yang mengubah perilaku akan langsung
// terpasang di semua HP tanpa satu pun perubahan di sisi kita.
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js');

var params = new URL(self.location.href).searchParams;

var konfig = {
    apiKey: params.get('apiKey') || '',
    authDomain: params.get('authDomain') || '',
    projectId: params.get('projectId') || '',
    storageBucket: params.get('storageBucket') || '',
    messagingSenderId: params.get('messagingSenderId') || '',
    appId: params.get('appId') || '',
};

// Ikon dititipkan lewat query juga supaya logonya bisa diganti dari .env
// tanpa menyunting berkas ini.
var IKON = params.get('icon') || '/images/logo/icon-192x192.png';
var LENCANA = params.get('badge') || IKON;

/*
 | Aktifkan service worker BARU seketika, tanpa menunggu semua tab lama
 | ditutup.
 |
 | Tanpa dua baris ini, perbaikan apa pun pada berkas ini baru berlaku
 | setelah pengguna menutup SELURUH tab SIMAGAS di HP-nya — sesuatu yang
 | praktis tidak pernah terjadi pada aplikasi yang dipasang di layar utama.
 */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (event) { event.waitUntil(self.clients.claim()); });

if (konfig.apiKey && konfig.projectId) {
    firebase.initializeApp(konfig);

    var messaging = firebase.messaging();

    /*
     | ============ MENGGAMBAR NOTIFIKASINYA SENDIRI ============
     | Server mengirim pesan DATA-ONLY (lihat App\Services\FirebasePushService),
     | jadi SDK tidak menampilkan apa pun sendiri dan seluruh tampilannya
     | ditentukan di sini. Itu disengaja: kalau server ikut mengirim kunci
     | `notification`, SDK menampilkan satu notifikasi DAN handler ini
     | menampilkan satu lagi — dua notifikasi kembar untuk satu kejadian.
     | ==========================================================
     */
    messaging.onBackgroundMessage(function (payload) {
        var data = payload.data || {};

        var judul = data.judul || 'SIMAGAS';

        var opsi = {
            body: data.isi || '',
            icon: IKON,
            badge: LENCANA,

            /*
             | ============ SOAL SUARA NOTIFIKASI ============
             | Tidak ada opsi `sound` di sini, dan itu BUKAN kelalaian:
             | Web Notification API tidak punya parameter suara. Field
             | `sound: 'default'` yang banyak beredar di internet adalah
             | milik FCM untuk aplikasi Android/iOS NATIVE, dan diabaikan
             | sepenuhnya di web.
             |
             | Kabar baiknya: suaranya TETAP BERBUNYI. Begitu notifikasi
             | ini digambar, Android/Chrome menampilkannya lewat sistem
             | notifikasi HP dan memutar NADA NOTIFIKASI BAWAAN HP —
             | persis seperti DANA atau BRImo. Nada itu diatur pengguna di
             | Setelan HP, dan memang di situlah tempatnya: aplikasi web
             | tidak boleh, dan tidak seharusnya, memaksakan bunyi sendiri
             | di layar kunci orang.
             | ==============================================
             |
             | vibrate hanya berpengaruh di Android. iOS mengabaikannya
             | tanpa error, jadi aman ditulis untuk semua.
             */
            vibrate: [200, 100, 200],

            /*
             | tag + renotify: notifikasi dari jenis yang sama saling
             | MENIMPA, bukan menumpuk. Kalau tiga anak di satu keluarga
             | absen dalam dua menit, orang tuanya melihat satu baris
             | terbaru — bukan tiga baris yang harus digeser satu per satu.
             | renotify tetap membuat HP berbunyi walau notifikasinya
             | menimpa yang lama.
             */
            tag: data.jenis || 'simagas',
            renotify: true,

            // Dibawa ke handler klik di bawah.
            data: {
                url: data.url || '/',
                jenis: data.jenis || '',
            },

            // requireInteraction false: notifikasi kehadiran adalah kabar
            // sekilas. Memaksa pengguna menutupnya manual hanya membuat
            // layar kuncinya penuh.
            requireInteraction: false,
        };

        return self.registration.showNotification(judul, opsi);
    });
}

/*
 | Diketuk -> buka SIMAGAS.
 |
 | Kalau tabnya sudah terbuka, tab ITU yang difokuskan — bukan membuka tab
 | baru. Pengguna yang mengetuk lima notifikasi tidak seharusnya berakhir
 | dengan lima tab SIMAGAS.
 */
self.addEventListener('notificationclick', function (event) {
    event.notification.close();

    var tujuan = (event.notification.data && event.notification.data.url) || '/';

    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then(function (daftar) {
                for (var i = 0; i < daftar.length; i++) {
                    var klien = daftar[i];

                    // Dibandingkan per ORIGIN saja, bukan URL penuh: tab
                    // SIMAGAS yang sedang membuka halaman lain tetap tab
                    // yang benar untuk difokuskan.
                    if (new URL(klien.url).origin === self.location.origin && 'focus' in klien) {
                        if ('navigate' in klien) {
                            return klien.navigate(tujuan).then(function (k) { return k.focus(); })
                                .catch(function () { return klien.focus(); });
                        }

                        return klien.focus();
                    }
                }

                return self.clients.openWindow(tujuan);
            })
    );
});
