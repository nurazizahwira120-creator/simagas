<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengirim Web Push lewat Firebase Cloud Messaging (FCM HTTP v1).
 *
 * ============ KENAPA TANPA PAKET COMPOSER TAMBAHAN ============
 * Cara yang lazim disarankan adalah memasang kreait/laravel-firebase. Di
 * sini SENGAJA tidak — seluruhnya memakai Http client bawaan Laravel plus
 * ekstensi openssl yang sudah pasti ada di setiap hosting PHP.
 *
 * Alasannya menyangkut cara project ini dipasang: berkas dikirim ke cPanel
 * lewat FTP. Menambah satu paket berarti menambah puluhan MB folder vendor
 * yang harus ikut terkirim setiap deploy, dan satu ketidakcocokan versi di
 * PHP hosting membuat SELURUH aplikasi mati (bukan cuma notifikasinya).
 * Seluruh isi berkas ini hanya ±3 hal: menandatangani JWT, menukarnya
 * dengan access token, lalu satu POST. Itu tidak sepadan dengan risikonya.
 * ==============================================================
 *
 * ============ KENAPA PESANNYA "DATA-ONLY" ============
 * Payload yang dikirim TIDAK memakai kunci `notification`, melainkan
 * `data` saja. Ini disengaja.
 *
 * Kalau `notification` ikut dikirim, SDK Firebase di service worker
 * MENAMPILKAN SENDIRI notifikasinya, DAN tetap memanggil
 * onBackgroundMessage(). Kode yang menampilkan notifikasi di sana akan
 * menghasilkan DUA notifikasi kembar untuk satu kejadian — gejala klasik
 * yang sangat sering muncul dan sulit ditebak sebabnya.
 *
 * Dengan data-only, satu-satunya yang menggambar notifikasi adalah
 * public/firebase-messaging-sw.js. Bonusnya: getaran, ikon, tag, dan
 * tombol aksi sepenuhnya bisa diatur dari sana.
 * =====================================================
 */
class FirebasePushService
{
    /** Alamat penukaran JWT menjadi access token. */
    private const URL_TOKEN = 'https://oauth2.googleapis.com/token';

    /** Cakupan izin minimum yang dibutuhkan untuk mengirim pesan FCM. */
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    /** Kunci cache untuk access token. */
    private const CACHE_TOKEN = 'simagas.fcm.access_token';

    /**
     * Apakah fitur push siap dipakai?
     *
     * Dicek SEBELUM merangkai apa pun, dan dipakai juga oleh Blade untuk
     * memutuskan apakah skrip Firebase perlu dimuat di browser. Server yang
     * .env-nya belum diisi tidak akan memuat SDK, tidak akan meminta izin
     * notifikasi, dan tidak akan mencetak error apa pun di konsol.
     */
    public static function aktif(): bool
    {
        return (bool) config('firebase.enabled')
            && filled(config('firebase.project_id'))
            && filled(config('firebase.vapid_key'))
            && filled(config('firebase.web.apiKey'))
            && static::adaKredensialServer();
    }

    /**
     * Konfigurasi yang aman dikirim ke browser (bukan rahasia).
     *
     * @return array<string, string>
     */
    public static function konfigWeb(): array
    {
        return array_map(
            static fn ($nilai) => (string) $nilai,
            array_filter(config('firebase.web', []), static fn ($n) => filled($n)),
        );
    }

    /**
     * Kirim satu notifikasi ke satu pengguna.
     *
     * Mengembalikan true HANYA kalau FCM benar-benar menerima pesannya.
     * Pemanggil TIDAK WAJIB memeriksa nilai baliknya — metode ini tidak
     * pernah melempar exception, karena tidak ada satu pun kejadian di
     * aplikasi ini yang boleh gagal gara-gara notifikasi gagal terkirim.
     *
     * @param  array<string, string|int|null>  $data  Muatan tambahan; nilainya
     *                                                akan dipaksa jadi string
     *                                                (FCM menolak tipe lain).
     */
    public function kirimKe(User $user, string $judul, string $isi, array $data = []): bool
    {
        if (! static::aktif() || ! $user->bisaMenerimaPush()) {
            return false;
        }

        try {
            return $this->kirimMentah($user, (string) $user->fcm_token, $judul, $isi, $data);
        } catch (Throwable $e) {
            // Ditelan, DENGAN CATATAN di log. Yang memanggil metode ini
            // adalah proses pencatatan kehadiran; kehadiran yang sudah
            // tersimpan tidak boleh dibatalkan hanya karena Google sedang
            // tidak bisa dihubungi.
            Log::warning('Push FCM gagal dikirim.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Kirim ke banyak pengguna sekaligus.
     *
     * FCM HTTP v1 tidak punya endpoint multicast (yang lama, /batch, sudah
     * dimatikan Google), jadi ini memang perulangan berisi satu panggilan
     * per penerima. Dibatasi jumlahnya supaya satu kejadian tidak pernah
     * berubah menjadi ratusan panggilan HTTP berurutan di dalam satu
     * permintaan web.
     *
     * @param  iterable<User>  $penerima
     * @param  array<string, string|int|null>  $data
     * @return int Jumlah yang berhasil terkirim.
     */
    public function kirimKeBanyak(iterable $penerima, string $judul, string $isi, array $data = [], int $batas = 50): int
    {
        $berhasil = 0;
        $dikirim = 0;

        foreach ($penerima as $user) {
            if ($dikirim >= $batas) {
                Log::info('Pengiriman push dihentikan di batas aman.', ['batas' => $batas]);
                break;
            }

            if (! $user instanceof User || ! $user->bisaMenerimaPush()) {
                continue;
            }

            $dikirim++;

            if ($this->kirimKe($user, $judul, $isi, $data)) {
                $berhasil++;
            }
        }

        return $berhasil;
    }

    // =====================================================================
    // Bagian dalam
    // =====================================================================

    /**
     * Satu panggilan POST ke FCM, lengkap dengan penanganan token mati.
     *
     * @param  array<string, string|int|null>  $data
     */
    private function kirimMentah(User $user, string $token, string $judul, string $isi, array $data): bool
    {
        $projectId = (string) config('firebase.project_id');

        /*
         | Seluruh nilai di dalam `data` HARUS berupa string. FCM menolak
         | angka maupun null dengan pesan "Invalid JSON payload received"
         | yang tidak menyebut kunci mana yang salah — sangat melelahkan
         | untuk dilacak. Karena itu dipaksa di sini, satu tempat.
         */
        $muatan = [
            'judul' => $judul,
            'isi' => $isi,
        ] + array_map(
            static fn ($nilai) => (string) $nilai,
            array_filter($data, static fn ($n) => $n !== null && $n !== ''),
        );

        $respons = Http::withToken($this->accessToken())
            ->timeout((int) config('firebase.timeout', 8))
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $token,
                    'data' => $muatan,

                    'webpush' => [
                        /*
                         | Urgency "high" meminta browser membangunkan
                         | service worker SEKARANG, bukan menundanya sampai
                         | perangkat aktif. Untuk pemberitahuan "anak Anda
                         | sudah sampai di sekolah", terlambat 20 menit sama
                         | saja dengan tidak terkirim.
                         |
                         | TTL 1 jam: notifikasi kehadiran yang baru sampai
                         | sore hari hanya membingungkan. Lewat satu jam,
                         | biarkan hilang.
                         */
                        'headers' => [
                            'Urgency' => 'high',
                            'TTL' => '3600',
                        ],

                        // Halaman yang dibuka saat notifikasinya diketuk.
                        // Dibaca service worker dari data, bukan dari
                        // fcm_options, supaya perilakunya seragam antara
                        // notifikasi latar belakang dan latar depan.
                        'fcm_options' => [
                            'link' => (string) ($data['url'] ?? url('/')),
                        ],
                    ],
                ],
            ]);

        if ($respons->successful()) {
            return true;
        }

        $kodeError = (string) data_get($respons->json(), 'error.details.0.errorCode', '');
        $status = (string) data_get($respons->json(), 'error.status', '');

        /*
         | UNREGISTERED / INVALID_ARGUMENT = tokennya sudah mati: aplikasi
         | dicopot, cache situs dibersihkan, atau izin notifikasinya dicabut.
         |
         | Token seperti ini HARUS dibuang. Kalau dibiarkan, setiap absen
         | anak itu memicu satu panggilan HTTP ke Google yang sudah pasti
         | gagal — dan di gerbang sekolah pagi hari, itu ratusan panggilan
         | sia-sia yang memperlambat semua orang.
         */
        if (in_array($kodeError, ['UNREGISTERED', 'INVALID_ARGUMENT'], true)
            || in_array($status, ['NOT_FOUND'], true)) {
            $this->lupakanToken($user, $token);

            Log::info('Token FCM dihapus karena sudah tidak berlaku.', [
                'user_id' => $user->id,
                'kode' => $kodeError ?: $status,
            ]);

            return false;
        }

        Log::warning('FCM menolak pengiriman.', [
            'user_id' => $user->id,
            'http' => $respons->status(),
            // Isi respons dipotong: pesan error Google bisa sangat panjang
            // dan log yang membengkak justru menyulitkan pembacaannya.
            'respons' => mb_substr($respons->body(), 0, 500),
        ]);

        return false;
    }

    /**
     * Lepaskan token dari akun, tapi HANYA kalau tokennya masih yang sama.
     *
     * Penjagaan "masih yang sama" itu penting: antara saat pesan dikirim
     * dan saat jawabannya datang, pengguna bisa saja sudah membuka aplikasi
     * dan mendaftarkan token BARU yang sehat. Menghapus tanpa memeriksa
     * akan membuang token baru itu, dan HP-nya diam sampai ia membuka
     * aplikasi lagi.
     */
    private function lupakanToken(User $user, string $token): void
    {
        User::query()
            ->whereKey($user->getKey())
            ->where('fcm_token', $token)
            ->update([
                'fcm_token' => null,
                'fcm_token_updated_at' => null,
            ]);

        if ($user->fcm_token === $token) {
            $user->fcm_token = null;
            $user->fcm_token_updated_at = null;
            $user->syncOriginal();
        }
    }

    /**
     * Access token OAuth2 untuk memanggil FCM, diambil dari cache.
     *
     * Google memberi token berumur 1 jam. Disimpan 55 menit — sisa 5 menit
     * adalah jaminan supaya token tidak kedaluwarsa TEPAT di tengah
     * permintaan yang sedang berjalan.
     */
    private function accessToken(): string
    {
        return Cache::remember(self::CACHE_TOKEN, now()->addMinutes(55), function (): string {
            $kredensial = $this->kredensial();

            $respons = Http::asForm()
                ->timeout((int) config('firebase.timeout', 8))
                ->post(self::URL_TOKEN, [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $this->buatAssertion($kredensial),
                ]);

            if (! $respons->successful() || blank($respons->json('access_token'))) {
                throw new \RuntimeException(
                    'Google menolak kredensial service account: ' . mb_substr($respons->body(), 0, 300)
                );
            }

            return (string) $respons->json('access_token');
        });
    }

    /**
     * Rangkai JWT yang ditandatangani private key service account.
     *
     * Ini "kata sandi sekali pakai" berumur 1 jam yang ditukar Google
     * dengan access token. Formatnya baku: base64url(header) . '.' .
     * base64url(payload) . '.' . base64url(tanda tangan RS256).
     *
     * @param  array{client_email: string, private_key: string}  $kredensial
     */
    private function buatAssertion(array $kredensial): string
    {
        $sekarang = time();

        $header = ['alg' => 'RS256', 'typ' => 'JWT'];

        $klaim = [
            'iss' => $kredensial['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::URL_TOKEN,
            'iat' => $sekarang,
            // 1 jam adalah batas maksimum yang diterima Google. Lebih dari
            // itu ditolak dengan "invalid_grant", pesan yang sama persis
            // dengan pesan untuk jam server yang meleset — jadi kalau error
            // itu muncul, periksa juga jam server cPanel-nya.
            'exp' => $sekarang + 3600,
        ];

        $bagian = $this->base64url(json_encode($header, JSON_THROW_ON_ERROR))
            . '.' . $this->base64url(json_encode($klaim, JSON_THROW_ON_ERROR));

        $kunci = openssl_pkey_get_private($kredensial['private_key']);

        if ($kunci === false) {
            throw new \RuntimeException(
                'FIREBASE private key tidak bisa dibaca. Paling sering: baris '
                . '"-----BEGIN PRIVATE KEY-----" hilang, atau \\n di .env tidak '
                . 'ditulis di dalam tanda kutip GANDA.'
            );
        }

        $tandaTangan = '';

        if (! openssl_sign($bagian, $tandaTangan, $kunci, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Gagal menandatangani JWT untuk Firebase.');
        }

        return $bagian . '.' . $this->base64url($tandaTangan);
    }

    /**
     * Baca kredensial service account dari salah satu dari tiga sumber
     * yang didukung (lihat config/firebase.php untuk penjelasannya).
     *
     * @return array{client_email: string, private_key: string}
     */
    private function kredensial(): array
    {
        $json = null;

        $jalur = config('firebase.credentials');

        if (filled($jalur)) {
            if (! is_readable($jalur)) {
                throw new \RuntimeException(
                    "Berkas kredensial Firebase tidak terbaca di: {$jalur}. "
                    . 'Periksa jalurnya (harus ABSOLUT) dan hak akses berkasnya (chmod 600 sudah cukup).'
                );
            }

            $json = (string) file_get_contents($jalur);
        } elseif (filled(config('firebase.credentials_base64'))) {
            $json = base64_decode((string) config('firebase.credentials_base64'), true) ?: null;

            if ($json === null) {
                throw new \RuntimeException('FIREBASE_CREDENTIALS_BASE64 bukan base64 yang sah.');
            }
        }

        if ($json !== null) {
            $isi = json_decode($json, true);

            if (! is_array($isi) || blank($isi['client_email'] ?? null) || blank($isi['private_key'] ?? null)) {
                throw new \RuntimeException(
                    'Isi kredensial Firebase tidak berisi client_email/private_key. '
                    . 'Pastikan yang dipakai adalah berkas "service account", bukan "google-services.json".'
                );
            }

            return [
                'client_email' => (string) $isi['client_email'],
                'private_key' => (string) $isi['private_key'],
            ];
        }

        $email = (string) config('firebase.client_email');
        $kunci = (string) config('firebase.private_key');

        if (blank($email) || blank($kunci)) {
            throw new \RuntimeException(
                'Kredensial Firebase belum diatur. Isi SALAH SATU di .env: '
                . 'FIREBASE_CREDENTIALS (jalur berkas), FIREBASE_CREDENTIALS_BASE64, '
                . 'atau pasangan FIREBASE_CLIENT_EMAIL + FIREBASE_PRIVATE_KEY.'
            );
        }

        /*
         | Di .env, private key ditulis dalam satu baris dengan \n literal.
         | Kalau nilainya diapit tanda kutip GANDA, Laravel sudah mengubah
         | \n itu menjadi baris baru sungguhan. Kalau diapit kutip TUNGGAL
         | (atau tanpa kutip), \n tetap dua karakter dan openssl menolak
         | kuncinya. Penggantian di bawah menutup kedua kemungkinan itu.
         */
        return [
            'client_email' => $email,
            'private_key' => str_replace('\\n', "\n", $kunci),
        ];
    }

    /** Apakah setidaknya satu sumber kredensial server terisi? */
    private static function adaKredensialServer(): bool
    {
        return filled(config('firebase.credentials'))
            || filled(config('firebase.credentials_base64'))
            || (filled(config('firebase.client_email')) && filled(config('firebase.private_key')));
    }

    /** Base64 varian URL tanpa padding — format wajib untuk JWT. */
    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
