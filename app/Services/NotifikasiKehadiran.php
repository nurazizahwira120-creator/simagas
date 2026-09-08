<?php

namespace App\Services;

use App\Jobs\KirimPushNotifikasi;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SATU tempat yang tahu "siapa dikabari apa" ketika sebuah kehadiran
 * tercatat.
 *
 * ============ KENAPA KELAS TERSENDIRI ============
 * Kejadian "siswa berhasil scan masuk" bisa datang dari TIGA jalur berbeda
 * yang sudah ada di aplikasi ini:
 *
 *   1. App\Http\Controllers\Piket\ScannerController  (layar gerbang, AJAX)
 *   2. App\Livewire\ScannerKameraSiswa               (scanner di dalam layout)
 *   3. App\Livewire\AbsenGuru                        (absen radius pegawai)
 *
 * Kalau isi pesannya dirangkai di masing-masing tempat, cepat atau lambat
 * ketiganya berbeda bunyi — dan yang paling sering terjadi: satu jalur
 * lupa diperbarui saat teksnya diubah, sehingga wali murid menerima pesan
 * yang berbeda tergantung siswa di-scan lewat pintu mana. Di sini semuanya
 * memanggil metode yang sama.
 * =================================================
 *
 * SELURUH metode di kelas ini TIDAK PERNAH melempar exception. Kehadiran
 * yang sudah tersimpan di database tidak boleh dibatalkan hanya karena
 * pemberitahuannya gagal terkirim.
 */
class NotifikasiKehadiran
{
    /** Judul yang muncul tebal di layar kunci HP. */
    public const JUDUL = 'Kehadiran Terdeteksi';

    /**
     * Siswa berhasil scan masuk -> kabari wali muridnya.
     *
     * Penerimanya adalah akun `users` bertaut lewat siswa.wali_murid_id.
     * Kalau siswa itu belum ditautkan ke akun wali mana pun, tidak ada yang
     * dikabari — dan itu bukan error: banyak siswa memang baru punya nomor
     * HP wali (untuk WhatsApp) tanpa akun aplikasi.
     */
    public function siswaMasuk(Siswa $siswa, AbsensiSiswa $absensi): void
    {
        $this->aman(function () use ($siswa, $absensi) {
            $wali = $siswa->getRelationValue('waliMurid');

            if (! $wali instanceof User || ! $wali->bisaMenerimaPush()) {
                return;
            }

            $jam = $absensi->jam_masuk?->format('H:i');

            KirimPushNotifikasi::dispatch(
                userId: (int) $wali->id,
                judul: self::JUDUL,
                // Bunyi pesannya SENGAJA menyebut nama anak lebih dulu:
                // di layar kunci HP, teks panjang terpotong, dan bagian
                // yang ingin dibaca orang tua adalah namanya.
                isi: "Siswa {$siswa->nama} berhasil scan masuk"
                    . ($jam ? " pukul {$jam}." : '.'),
                data: [
                    'jenis' => 'kehadiran-siswa',
                    'siswa_id' => (string) $siswa->id,
                    // Diketuk -> langsung ke halaman pantauan anaknya.
                    'url' => $this->urlDashboard($wali),
                ],
            )->onConnection(config('firebase.queue_connection', 'sync'));
        }, 'siswa', $siswa->id);
    }

    /**
     * Pegawai (guru/staf) berhasil absen masuk -> kabari dirinya sendiri.
     *
     * Terlihat aneh sekilas ("dia kan yang barusan menekan tombolnya"),
     * tapi inilah bukti terima yang diminta guru: notifikasi yang tetap
     * ada di riwayat HP-nya. Layar konfirmasi di aplikasi hilang begitu
     * halaman ditutup, dan pertanyaan "tadi absen saya masuk tidak ya?"
     * adalah keluhan yang paling sering muncul.
     */
    public function pegawaiMasuk(Pegawai $pegawai, AbsensiPegawai $absensi): void
    {
        $this->aman(function () use ($pegawai, $absensi) {
            $user = $pegawai->getRelationValue('user');

            if (! $user instanceof User || ! $user->bisaMenerimaPush()) {
                return;
            }

            $jam = $absensi->jam_masuk?->format('H:i');

            KirimPushNotifikasi::dispatch(
                userId: (int) $user->id,
                judul: self::JUDUL,
                isi: "Absen masuk Anda tercatat" . ($jam ? " pukul {$jam}." : '.')
                    . ' Selamat bekerja!',
                data: [
                    'jenis' => 'kehadiran-pegawai',
                    'url' => $this->urlDashboard($user),
                ],
            )->onConnection(config('firebase.queue_connection', 'sync'));
        }, 'pegawai', $pegawai->id);
    }

    /**
     * Pemberitahuan bebas ke satu akun — dipakai perintah uji
     * `php artisan simagas:uji-push`.
     */
    public function bebas(User $user, string $judul, string $isi, array $data = []): void
    {
        $this->aman(function () use ($user, $judul, $isi, $data) {
            KirimPushNotifikasi::dispatch((int) $user->id, $judul, $isi, $data)
                ->onConnection(config('firebase.queue_connection', 'sync'));
        }, 'user', $user->id);
    }

    /**
     * Bungkus try/catch bersama.
     *
     * Semua pemanggil metode-metode di atas berada TEPAT SESUDAH sebuah
     * baris kehadiran berhasil disimpan. Exception apa pun yang lolos dari
     * sini akan berubah menjadi layar error di HP guru piket untuk siswa
     * yang absensinya sebenarnya SUDAH tercatat — dan ia akan men-scan
     * ulang, lalu bingung karena dibilang "sudah absen".
     */
    private function aman(callable $aksi, string $konteks, int|string $id): void
    {
        try {
            $aksi();
        } catch (Throwable $e) {
            Log::warning('Gagal mengantrekan notifikasi push kehadiran.', [
                'konteks' => $konteks,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Alamat yang dibuka ketika notifikasinya diketuk: dashboard milik
     * peran penerima sendiri.
     *
     * Diambil dari UserRole::dashboardRouteName() — sumber pemetaan
     * peran -> dashboard yang SUDAH dipakai halaman login dan rute '/'.
     * Menulis nama rutenya langsung di sini berarti menambah salinan
     * kedua aturan yang sama, dan salinan kedua itulah yang lupa diubah
     * saat menu ditata ulang.
     *
     * Dibungkus try/catch: sebuah RouteNotFoundException di sini akan
     * menjatuhkan proses pencatatan kehadiran, akibat yang sama sekali
     * tidak sebanding dengan penyebabnya.
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
