<?php

namespace App\Services;

use App\Jobs\KirimPushNotifikasi;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mendorong sebuah pengumuman ke LAYAR KUNCI HP penerimanya.
 *
 * ============ TIGA SALURAN, TIGA TUGAS BERBEDA ============
 * Sebuah pengumuman sekarang berjalan lewat tiga jalur yang saling
 * melengkapi — dan ketiganya memang perlu:
 *
 *   1. Lonceng (database)  -> catatan permanen. Bisa dibuka kapan saja,
 *                             tidak hilang, tidak butuh izin apa pun.
 *   2. Pusher              -> berbunyi SEKETIKA untuk yang aplikasinya
 *                             sedang terbuka. Mati begitu tab ditutup.
 *   3. Web Push (kelas ini)-> muncul di layar kunci HP walau aplikasinya
 *                             SUDAH DITUTUP. Inilah satu-satunya jalur
 *                             yang benar-benar sampai ke orang yang tidak
 *                             sedang memegang aplikasinya.
 *
 * Kegagalan salah satu tidak boleh menjatuhkan yang lain, dan tidak satu
 * pun boleh menjatuhkan penyimpanan pengumumannya sendiri.
 * ==========================================================
 *
 * ============ SOAL JUMLAH PENERIMA — dan kenapa ada BATAS ============
 * Ini satu-satunya tempat di aplikasi yang bisa mengirim push ke ratusan
 * orang sekaligus, dan FCM HTTP v1 TIDAK punya endpoint multicast: satu
 * penerima = satu permintaan HTTP ke Google.
 *
 * Dengan FIREBASE_PUSH_QUEUE=sync (bawaan, karena cPanel tidak menjalankan
 * queue worker), seluruh panggilan itu terjadi DI DALAM permintaan web yang
 * sama — artinya kepala sekolah menatap layar sampai semuanya selesai. Pada
 * ~300 ms per panggilan, 200 HP berarti satu menit, dan PHP di hosting
 * umumnya memutus proses jauh sebelum itu. Yang terlihat: pengumuman
 * "gagal" padahal sudah tersimpan dan separuh HP sudah berbunyi.
 *
 * Karena itu ada batas keras yang bisa diatur lewat .env, dan jumlah yang
 * BENAR-BENAR terkirim dilaporkan balik ke layar — bukan diam-diam.
 *
 * Kalau suatu saat penerimanya melampaui batas itu, jawabannya bukan
 * menaikkan angkanya, melainkan menjalankan queue worker (cron cPanel:
 * `php artisan queue:work --stop-when-empty`) lalu mengisi
 * FIREBASE_PUSH_QUEUE=database. Sesudah itu batasnya tidak lagi relevan.
 * ====================================================================
 */
class NotifikasiPengumuman
{
    /**
     * Kirim pengumuman ke seluruh penerima yang HP-nya sudah terdaftar.
     *
     * @param  iterable<User>  $penerima  Daftar penerima yang sama persis
     *                                    dengan yang dipakai lonceng — jangan
     *                                    query ulang di sini, supaya tidak
     *                                    mungkin ada orang yang dapat
     *                                    notifikasi HP tapi tidak dapat
     *                                    loncengnya (atau sebaliknya).
     * @return array{terkirim: int, punya_hp: int, dilewati: int}
     */
    public function siarkan(Pengumuman $pengumuman, iterable $penerima): array
    {
        $hasil = ['terkirim' => 0, 'punya_hp' => 0, 'dilewati' => 0];

        if (! FirebasePushService::aktif()) {
            // .env Firebase belum diisi. Bukan error — fiturnya memang
            // dimatikan, dan pengumumannya tetap masuk lonceng.
            return $hasil;
        }

        $batas = max(1, (int) config('firebase.batas_siaran', 200));
        $langsung = config('firebase.queue_connection', 'sync') === 'sync';
        $push = app(FirebasePushService::class);

        // Judul notifikasi = judul pengumumannya sendiri. Bukan kata umum
        // seperti "Pengumuman Baru": di layar kunci, baris judul itulah
        // satu-satunya yang pasti terbaca sebelum orang memutuskan membuka
        // atau mengabaikannya.
        $judul = Str::limit($pengumuman->judul, 60);

        // strip_tags sebagai pengaman: isi pengumuman diketik manusia di
        // textarea, tapi kalau suatu saat editor-nya diganti jadi rich text,
        // tag HTML mentah akan tampil apa adanya di notifikasi HP.
        $isi = Str::limit(strip_tags($pengumuman->isi_pesan), 160);

        foreach ($penerima as $orang) {
            if (! $orang instanceof User || ! $orang->bisaMenerimaPush()) {
                continue;
            }

            $hasil['punya_hp']++;

            if ($hasil['punya_hp'] > $batas) {
                $hasil['dilewati']++;

                continue;
            }

            try {
                $data = [
                    // tag notifikasi di service worker diambil dari sini.
                    // Memakai id pengumuman, BUKAN kata "pengumuman" saja:
                    // dua pengumuman berbeda harus tampil sebagai dua baris
                    // di layar kunci, bukan saling menimpa. Sementara KIRIM
                    // ULANG pengumuman yang sama memang seharusnya menimpa
                    // yang lama, dan itu terjadi dengan sendirinya.
                    'jenis' => 'pengumuman-' . $pengumuman->id,
                    'pengumuman_id' => (string) $pengumuman->id,
                    'url' => $this->urlDashboard($orang),
                ];

                if ($langsung) {
                    // Dipanggil LANGSUNG, bukan lewat job, supaya nilai
                    // baliknya terbaca — dengan begitu angka yang dilaporkan
                    // ke layar kepala sekolah adalah jumlah yang benar-benar
                    // diterima Google, bukan sekadar jumlah yang dicoba.
                    if ($push->kirimKe($orang, $judul, $isi, $data)) {
                        $hasil['terkirim']++;
                    }

                    continue;
                }

                KirimPushNotifikasi::dispatch((int) $orang->id, $judul, $isi, $data)
                    ->onConnection(config('firebase.queue_connection'));

                $hasil['terkirim']++;
            } catch (Throwable $e) {
                // Ditelan per orang, bukan per pengumuman: satu token rusak
                // tidak boleh menghentikan pengiriman ke 199 orang lainnya.
                Log::warning('Push pengumuman gagal untuk satu penerima.', [
                    'pengumuman_id' => $pengumuman->id,
                    'user_id' => $orang->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($hasil['dilewati'] > 0) {
            Log::info('Sebagian penerima push pengumuman dilewati karena batas aman.', [
                'pengumuman_id' => $pengumuman->id,
                'batas' => $batas,
                'dilewati' => $hasil['dilewati'],
            ]);
        }

        return $hasil;
    }

    /**
     * Kalimat siap tempel untuk pesan sukses di layar.
     *
     * Ditaruh di sini, bukan dirakit di komponen Livewire, supaya kalau
     * suatu saat ada tempat kedua yang menyiarkan pengumuman, bunyinya
     * tidak berbeda.
     *
     * @param  array{terkirim: int, punya_hp: int, dilewati: int}  $hasil
     */
    public function ringkasan(array $hasil): string
    {
        if ($hasil['punya_hp'] === 0) {
            return ' Belum ada penerima yang mengaktifkan notifikasi HP,'
                . ' jadi belum ada yang berbunyi di layar kunci.';
        }

        $teks = ' ' . $hasil['terkirim'] . ' dari ' . $hasil['punya_hp']
            . ' HP yang terdaftar menerima notifikasi di layar kunci.';

        if ($hasil['dilewati'] > 0) {
            $teks .= ' ' . $hasil['dilewati'] . ' sisanya dilewati karena batas'
                . ' aman sekali kirim — jalankan queue worker bila ingin semuanya sekaligus.';
        }

        return $teks;
    }

    /**
     * Alamat yang dibuka saat notifikasinya diketuk.
     *
     * Pengumuman tidak punya halaman detail sendiri (isinya sudah tampil
     * utuh di lonceng), jadi tujuannya dashboard milik peran penerima —
     * di sana loncengnya langsung terlihat.
     *
     * Diambil dari UserRole::dashboardRouteName(), sumber pemetaan yang
     * sama dengan halaman login dan rute '/'.
     */
    private function urlDashboard(User $user): string
    {
        try {
            return route($user->role->dashboardRouteName());
        } catch (Throwable) {
            return url('/');
        }
    }
}
