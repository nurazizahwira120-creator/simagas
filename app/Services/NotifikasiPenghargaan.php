<?php

namespace App\Services;

use App\Jobs\KirimPushNotifikasi;
use App\Models\Penghargaan;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim apresiasi ke HP penerimanya lewat Web Push (FCM).
 *
 * ============ ATURAN UTAMA: KEGAGALAN DI SINI TIDAK PERNAH FATAL ============
 * Pemberitahuan adalah pelengkap, bukan inti. Kalau Firebase sedang mati,
 * kredensialnya belum dipasang, atau HP penerimanya belum pernah mengizinkan
 * notifikasi, yang boleh terjadi hanyalah: penghargaannya tetap tersimpan dan
 * tetap tampil di aplikasi, hanya HP-nya tidak berbunyi.
 *
 * Karena itu SELURUH isi kelas ini dibungkus try/catch. Kepala sekolah yang
 * menekan "Setujui & Terbitkan" tidak boleh melihat error 500 gara-gara
 * layanan pihak ketiga — rapornya sudah sah terbit sebelum baris ini dipanggil.
 * ===========================================================================
 */
class NotifikasiPenghargaan
{
    public function kirim(Penghargaan $penghargaan): void
    {
        try {
            $this->jalankan($penghargaan);
        } catch (\Throwable $e) {
            Log::warning('Push penghargaan dilewati.', [
                'penghargaan_id' => $penghargaan->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function jalankan(Penghargaan $penghargaan): void
    {
        $push = app(FirebasePushService::class);

        if (! $push->aktif()) {
            return;
        }

        $user = $penghargaan->user;

        if (! $user || ! $user->bisaMenerimaPush()) {
            return;
        }

        [$judul, $isi] = $this->susunPesan($penghargaan);

        // 'jenis' & 'url' ikut sebagai data tambahan: service worker memakai
        // 'url' untuk menentukan halaman yang dibuka saat notifikasinya
        // disentuh (lihat public/firebase-messaging-sw.js).
        $data = [
            'jenis' => 'penghargaan',
            'url' => $this->urlTujuan($penghargaan),
        ];

        /*
         | Dua jalur, sesuai setelan antrean:
         |   - queue 'sync'  -> dikirim langsung. Dipakai hosting ini karena
         |     tidak ada worker antrean yang berjalan terus-menerus.
         |   - selain itu    -> dilempar ke job supaya permintaan web tidak
         |     menunggu jaringan Firebase.
         | Polanya mengikuti NotifikasiPengumuman yang sudah ada, supaya tidak
         | ada dua cara berbeda mengirim push di satu aplikasi.
         */
        if (config('firebase.queue_connection', 'sync') === 'sync') {
            $push->kirimKe($user, $judul, $isi, $data);

            return;
        }

        KirimPushNotifikasi::dispatch((int) $user->id, $judul, $isi, $data);
    }

    /** @return array{0: string, 1: string} */
    private function susunPesan(Penghargaan $penghargaan): array
    {
        if ($penghargaan->peran === Penghargaan::PERAN_GURU) {
            return [
                'Selamat, Anda Guru Teladan!',
                'Anda terpilih sebagai Guru Teladan & Tertib Administrasi bulan '
                    . $penghargaan->labelPeriode() . '. Terima kasih atas kedisiplinannya.',
            ];
        }

        $nama = $penghargaan->siswa?->nama ?? 'Ananda';

        return [
            'Selamat! ' . $nama . ' Bintang Kelas',
            $nama . ' meraih Peringkat 1 di kelasnya pada semester '
                . $penghargaan->labelPeriode() . '. Buka aplikasi untuk melihat rapornya.',
        ];
    }

    /**
     * Tujuan saat notifikasinya disentuh.
     *
     * Dibuat dari nama rute dan dijaga Route::has(): rute yang salah ketik
     * akan melempar RouteNotFoundException DI DALAM proses persetujuan rapor —
     * kegagalan yang sama sekali tidak ada hubungannya dengan notifikasi.
     */
    private function urlTujuan(Penghargaan $penghargaan): string
    {
        $rute = $penghargaan->peran === Penghargaan::PERAN_GURU
            ? 'guru.dashboard'
            : 'wali-murid.rapor';

        return \Illuminate\Support\Facades\Route::has($rute) ? route($rute) : url('/');
    }
}
