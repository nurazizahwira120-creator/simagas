<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FirebasePushService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Perintah diagnosis untuk fitur Notifikasi HP.
 *
 * ============ KENAPA INI ADA ============
 * Web Push punya banyak titik gagal yang SEMUANYA diam: .env kurang satu
 * baris, jam server meleset, berkas kredensial tidak terbaca, token
 * perangkat sudah mati, izin notifikasi dicabut di HP. Tidak satu pun dari
 * itu memunculkan pesan di layar — yang terlihat pengguna hanya "HP saya
 * tidak bunyi".
 *
 * Perintah ini memeriksa rantainya dari ujung ke ujung DALAM SATU
 * PANGGILAN, dan menyebut dengan tepat mata rantai mana yang putus. Di
 * cPanel ia bisa dijalankan lewat Terminal atau Cron Jobs sekali jalan:
 *
 *   php artisan simagas:uji-push email@sekolah.sch.id
 *
 * Tanpa argumen, ia hanya memeriksa konfigurasi tanpa mengirim apa pun.
 * ========================================
 */
class UjiPushNotifikasi extends Command
{
    protected $signature = 'simagas:uji-push
                            {email? : Email akun tujuan. Kosongkan untuk hanya memeriksa konfigurasi.}
                            {--judul=Uji Coba SIMAGAS}
                            {--isi=Kalau notifikasi ini sampai di HP Anda, fitur push sudah berjalan.}';

    protected $description = 'Memeriksa konfigurasi Firebase dan (opsional) mengirim satu notifikasi uji.';

    public function handle(FirebasePushService $push): int
    {
        $this->newLine();
        $this->line('  <fg=cyan>SIMAGAS — pemeriksaan Notifikasi HP (Firebase Cloud Messaging)</>');
        $this->newLine();

        // ---------------------------------------------------------------
        // 1. Konfigurasi
        // ---------------------------------------------------------------
        $periksa = [
            'FIREBASE_PROJECT_ID' => config('firebase.project_id'),
            'FIREBASE_WEB_API_KEY' => config('firebase.web.apiKey'),
            'FIREBASE_WEB_SENDER_ID' => config('firebase.web.messagingSenderId'),
            'FIREBASE_WEB_APP_ID' => config('firebase.web.appId'),
            'FIREBASE_VAPID_KEY' => config('firebase.vapid_key'),
        ];

        $kurang = [];

        foreach ($periksa as $kunci => $nilai) {
            if (blank($nilai)) {
                $kurang[] = $kunci;
                $this->line("  <fg=red>✗</> {$kunci} — <fg=red>kosong</>");

                continue;
            }

            // Nilainya TIDAK ditampilkan utuh. Keluaran perintah ini sering
            // di-screenshot lalu dikirim saat minta bantuan.
            $this->line("  <fg=green>✓</> {$kunci} — " . $this->samarkan((string) $nilai));
        }

        // Kredensial service account: yang paling sering salah.
        $sumber = match (true) {
            filled(config('firebase.credentials')) => 'berkas: ' . config('firebase.credentials'),
            filled(config('firebase.credentials_base64')) => 'FIREBASE_CREDENTIALS_BASE64',
            filled(config('firebase.client_email')) => 'FIREBASE_CLIENT_EMAIL + FIREBASE_PRIVATE_KEY',
            default => null,
        };

        if ($sumber === null) {
            $kurang[] = 'kredensial service account';
            $this->line('  <fg=red>✗</> Kredensial service account — <fg=red>belum diatur</>');
        } else {
            $this->line("  <fg=green>✓</> Kredensial service account — {$sumber}");
        }

        $this->newLine();

        if ($kurang !== []) {
            $this->error('  Konfigurasi belum lengkap. Isi dulu di .env: ' . implode(', ', $kurang));
            $this->line('  <fg=gray>Sesudah mengubah .env jalankan: php artisan config:clear</>');
            $this->newLine();

            return self::FAILURE;
        }

        if (! FirebasePushService::aktif()) {
            $this->error('  FIREBASE_PUSH_ENABLED bernilai false — fitur push sengaja dimatikan.');
            $this->newLine();

            return self::FAILURE;
        }

        // ---------------------------------------------------------------
        // 2. Kredensialnya benar-benar diterima Google?
        // ---------------------------------------------------------------
        // Ini pemeriksaan yang paling berharga: ia membuktikan private key,
        // client_email, DAN jam server sudah benar sekaligus. Jam yang
        // meleset lebih dari beberapa menit membuat Google menolak dengan
        // "invalid_grant" — pesan yang sama persis dengan pesan untuk kunci
        // yang salah, jadi tanpa uji ini keduanya mustahil dibedakan.
        $this->line('  Menghubungi Google untuk menukar kredensial dengan access token…');

        try {
            $metode = new \ReflectionMethod($push, 'accessToken');
            $metode->setAccessible(true);
            $metode->invoke($push);

            $this->line('  <fg=green>✓</> Google menerima kredensial service account.');
        } catch (Throwable $e) {
            $this->newLine();
            $this->error('  ✗ Google MENOLAK kredensialnya:');
            $this->line('    ' . $e->getMessage());
            $this->newLine();
            $this->line('  <fg=gray>Kalau pesannya "invalid_grant", periksa juga JAM SERVER cPanel —</>');
            $this->line('  <fg=gray>selisih beberapa menit saja sudah cukup membuat JWT-nya ditolak.</>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->newLine();

        // ---------------------------------------------------------------
        // 3. Kirim uji coba (opsional)
        // ---------------------------------------------------------------
        $email = $this->argument('email');

        if (blank($email)) {
            $this->info('  Konfigurasi sehat. Untuk mengirim notifikasi uji:');
            $this->line('  <fg=gray>php artisan simagas:uji-push email@sekolah.sch.id</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("  Akun dengan email {$email} tidak ditemukan.");
            $this->newLine();

            return self::FAILURE;
        }

        if (! $user->bisaMenerimaPush()) {
            $this->error("  Akun {$user->name} belum punya token perangkat.");
            $this->newLine();
            $this->line('  <fg=gray>Artinya orangnya belum pernah menekan "Aktifkan" pada kartu ajakan</>');
            $this->line('  <fg=gray>notifikasi, atau membukanya lewat http:// biasa (bukan HTTPS).</>');
            $this->newLine();

            return self::FAILURE;
        }

        $this->line("  Mengirim ke <fg=cyan>{$user->name}</> ({$user->email})…");

        $berhasil = $push->kirimKe(
            $user,
            (string) $this->option('judul'),
            (string) $this->option('isi'),
            ['jenis' => 'uji-coba'],
        );

        $this->newLine();

        if (! $berhasil) {
            $this->error('  ✗ FCM tidak menerima pesannya. Alasannya sudah dicatat di storage/logs/laravel.log.');
            $this->newLine();

            return self::FAILURE;
        }

        $this->info('  ✓ Terkirim. Notifikasinya mestinya muncul di HP dalam hitungan detik.');
        $this->line('  <fg=gray>Kalau tidak muncul padahal baris ini hijau: periksa izin notifikasi</>');
        $this->line('  <fg=gray>SIMAGAS di setelan HP, dan pastikan aplikasinya tidak dibatasi baterai.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /** Tampilkan cukup untuk mengenali nilainya, tidak cukup untuk memakainya. */
    private function samarkan(string $nilai): string
    {
        if (mb_strlen($nilai) <= 12) {
            return str_repeat('*', mb_strlen($nilai));
        }

        return mb_substr($nilai, 0, 6) . str_repeat('*', 6) . mb_substr($nilai, -4);
    }
}
