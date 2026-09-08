<?php

namespace App\Services;

use App\Models\PengaturanSistem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pengirim pesan WhatsApp lewat gateway Fonnte.
 *
 * Dipanggil HANYA dari App\Jobs\SendWhatsAppNotification, yaitu dari dalam
 * queue worker — bukan langsung dari controller. Itu disengaja: satu panggilan
 * HTTP ke Fonnte bisa memakan beberapa detik, dan kalau dilakukan di tengah
 * request scan gerbang, guru piket akan menatap layar menunggu tiap kali satu
 * siswa lewat. Dengan antrean, scan-nya selesai seketika dan pesannya dikirim
 * di belakang layar.
 */
class WhatsAppService
{
    private const ENDPOINT = 'https://api.fonnte.com/send';

    /** Batas waktu tunggu ke Fonnte (detik). */
    private const TIMEOUT = 15;

    /**
     * Kirim satu pesan.
     *
     * @return bool true kalau gateway menerima pesannya.
     */
    public static function send($target, $message): bool
    {
        $pengaturan = PengaturanSistem::ambil();

        // ---- Sakelar utama -------------------------------------------
        // Kalau notifikasi dimatikan, berhenti DI SINI — sebelum menyentuh
        // jaringan sama sekali. Ini yang membuat sekolah bisa memakai seluruh
        // sistem absensi tanpa mengirim satu pun pesan, mis. saat masa uji
        // coba atau ketika kuota gateway habis.
        if (! $pengaturan->wa_gateway_status) {
            Log::info('Notifikasi WhatsApp dilewati: gateway dimatikan di Pengaturan Sistem.', [
                'target' => self::samarkan($target),
            ]);

            return false;
        }

        $token = $pengaturan->fonnte_token;

        if (blank($token)) {
            // Dibedakan dari kasus "dimatikan" karena tindakannya berbeda:
            // yang ini berarti sakelarnya ON tapi tokennya belum diisi —
            // sekolah mengira notifikasi jalan padahal tidak.
            Log::warning('Notifikasi WhatsApp gagal: gateway aktif tapi Token Fonnte belum diisi.', [
                'target' => self::samarkan($target),
            ]);

            return false;
        }

        $tujuan = self::normalkanNomor($target);

        if ($tujuan === null) {
            Log::warning('Notifikasi WhatsApp dilewati: nomor tujuan tidak masuk akal.', [
                'target' => self::samarkan($target),
            ]);

            return false;
        }

        try {
            $respons = Http::withHeaders(['Authorization' => $token])
                ->timeout(self::TIMEOUT)
                ->asForm()
                ->post(self::ENDPOINT, [
                    'target' => $tujuan,
                    'message' => $message,
                    // Delay bawaan Fonnte: gateway menahan pengiriman selama
                    // sekian detik. Dipakai untuk mengurangi risiko nomor
                    // sekolah diblokir WhatsApp karena mengirim beruntun —
                    // yang persis terjadi saat 200 siswa scan di gerbang
                    // dalam sepuluh menit yang sama.
                    'delay' => (string) max(0, $pengaturan->wa_delay),
                ]);
        } catch (Throwable $e) {
            // Sengaja dilempar ulang: job punya tries=3 + backoff, jadi
            // gangguan jaringan sesaat masih punya kesempatan berhasil.
            // Menelannya di sini berarti pesan hilang tanpa pernah dicoba lagi.
            Log::error('Gagal menghubungi gateway WhatsApp.', [
                'target' => self::samarkan($tujuan),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($respons->failed()) {
            Log::error('Gateway WhatsApp menolak permintaan.', [
                'target' => self::samarkan($tujuan),
                'status' => $respons->status(),
                'body' => mb_substr($respons->body(), 0, 500),
            ]);

            $respons->throw();
        }

        // Fonnte membalas HTTP 200 walau pengirimannya GAGAL — kegagalannya
        // ada di field "status": false pada body JSON. Tanpa pemeriksaan ini,
        // pesan yang ditolak (nomor tidak terdaftar WhatsApp, kuota habis,
        // token salah) akan tercatat sukses dan tidak pernah dicoba ulang.
        $isi = $respons->json();

        if (is_array($isi) && array_key_exists('status', $isi) && $isi['status'] === false) {
            Log::error('Gateway WhatsApp membalas gagal.', [
                'target' => self::samarkan($tujuan),
                'reason' => $isi['reason'] ?? $isi['detail'] ?? '(tidak disebutkan)',
            ]);

            return false;
        }

        Log::info('Notifikasi WhatsApp terkirim.', ['target' => self::samarkan($tujuan)]);

        return true;
    }

    /**
     * Ubah nomor Indonesia ke bentuk yang diterima Fonnte: 62xxxxxxxxxx.
     *
     * INI BUKAN KERAPIAN, TAPI SYARAT JALANNYA FITUR. Nomor di database
     * ditulis manusia dengan bentuk yang berbeda-beda — "0812-3456-7890",
     * "+62 812 3456 7890", "(0812) 34567890". Dikirim apa adanya, Fonnte
     * menolak sebagian besar di antaranya, dan gejalanya membingungkan:
     * sebagian wali murid menerima pesan, sebagian tidak, tanpa pola yang
     * jelas.
     *
     * @return string|null null kalau nomornya tidak bisa dipercaya.
     */
    public static function normalkanNomor($nomor): ?string
    {
        $angka = preg_replace('/\D+/', '', (string) $nomor);

        if ($angka === '' || $angka === null) {
            return null;
        }

        // 08xxx -> 628xxx
        if (str_starts_with($angka, '0')) {
            $angka = '62' . substr($angka, 1);
        }

        // 8xxx (nol depannya hilang, sering terjadi kalau nomor pernah
        // tersimpan sebagai angka di Excel) -> 628xxx
        if (str_starts_with($angka, '8')) {
            $angka = '62' . $angka;
        }

        // Nomor seluler Indonesia yang sah: 62 + 9..13 digit.
        if (! preg_match('/^62\d{9,13}$/', $angka)) {
            return null;
        }

        return $angka;
    }

    /**
     * Nomor untuk ditulis ke log — bagian tengahnya disamarkan.
     * Log aplikasi sering ikut terkirim saat minta bantuan teknis, dan nomor
     * HP wali murid adalah data pribadi yang tidak perlu ikut tersebar.
     */
    private static function samarkan($nomor): string
    {
        $teks = (string) $nomor;

        if (mb_strlen($teks) < 7) {
            return '***';
        }

        return mb_substr($teks, 0, 4) . str_repeat('*', mb_strlen($teks) - 7) . mb_substr($teks, -3);
    }
}
