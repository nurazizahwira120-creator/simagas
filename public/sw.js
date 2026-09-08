/*
 |----------------------------------------------------------------------
 | Service worker SIMAGAS
 |----------------------------------------------------------------------
 | Tugasnya SENGAJA sedikit: memenuhi syarat "installable" browser, dan
 | memberi halaman offline yang sopan kalau koneksi putus. TIDAK LEBIH.
 |
 | ================== KENAPA TIDAK MENYIMPAN HALAMAN ==================
 | Service worker yang agresif menyimpan halaman HTML adalah bencana untuk
 | aplikasi seperti ini, dan alasannya konkret:
 |
 |   1. TOKEN CSRF. Setiap halaman Laravel membawa token yang terikat sesi.
 |      Halaman yang disajikan dari cache membawa token basi, dan setiap
 |      pengiriman form berujung "419 Page Expired" — persis error yang
 |      sudah pernah membingungkan di project ini.
 |   2. DATA ABSENSI BASI. Kepala sekolah membuka rekap dan melihat angka
 |      kemarin tanpa tanda apa pun bahwa itu bukan angka hari ini.
 |   3. LIVEWIRE. /livewire/update adalah POST berisi state komponen;
 |      menyentuhnya dengan cache membuat komponen bereaksi pada data yang
 |      sudah tidak berlaku.
 |
 | Karena itu aturannya: HANYA aset statis ber-hash (public/build) yang
 | disimpan — berkas itu memang tidak pernah berubah isinya, namanya yang
 | berganti setiap build. Sisanya selalu diambil dari jaringan.
 | ====================================================================
 */

const VERSI = 'simagas-v2';
const HALAMAN_OFFLINE = '/offline.html';

// Hanya berkas yang aman disimpan selamanya.
const PRA_SIMPAN = [
    HALAMAN_OFFLINE,
    '/images/logo/icon-192x192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(VERSI)
            .then((cache) => cache.addAll(PRA_SIMPAN))
            // Gagal menyimpan satu berkas tidak boleh menggagalkan
            // pemasangan — service worker yang gagal install membuat
            // aplikasi kehilangan status "installable" tanpa sebab jelas.
            .catch(() => undefined)
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((kunci) => Promise.all(
                kunci.filter((k) => k !== VERSI).map((k) => caches.delete(k))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const req = event.request;

    // Selain GET (POST login, POST absensi, /livewire/update) TIDAK PERNAH
    // disentuh. Membiarkannya lewat apa adanya.
    if (req.method !== 'GET') return;

    const url = new URL(req.url);

    // Permintaan ke domain lain (ubin peta OpenStreetMap, font Google,
    // gateway WA) dibiarkan ditangani browser sendiri.
    if (url.origin !== self.location.origin) return;

    // Aset ber-hash hasil build: isinya tidak pernah berubah, jadi aman
    // disajikan dari cache dan itu yang membuat aplikasi terasa instan.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(req).then((tersimpan) => tersimpan || fetch(req).then((resp) => {
                if (resp.ok) {
                    const salinan = resp.clone();
                    caches.open(VERSI).then((cache) => cache.put(req, salinan));
                }
                return resp;
            }))
        );
        return;
    }

    // Navigasi halaman: SELALU dari jaringan. Kalau jaringannya mati,
    // barulah halaman offline ditampilkan — bukan halaman lama yang
    // menyesatkan.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(() => caches.match(HALAMAN_OFFLINE))
        );
    }

    // Sisanya (gambar profil, JSON, dsb) dibiarkan lewat tanpa campur tangan.
});
