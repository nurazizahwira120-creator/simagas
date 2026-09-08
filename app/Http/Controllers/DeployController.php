<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Endpoint yang dipanggil GitHub Actions sesudah FTP selesai, untuk
 * membersihkan cache server tanpa perlu membuka Terminal cPanel.
 *
 * ==================== KENAPA BUKAN SEKADAR RUTE RAHASIA ====================
 * "Rute rahasia" saja (URL panjang yang sulit ditebak) bukan pengaman: URL
 * bocor lewat access log server, header Referer, riwayat browser, dan log
 * proxy. Yang benar-benar menjaga endpoint ini ada empat lapis:
 *
 *   1. TOKEN DIBANDINGKAN hash_equals(). Perbandingan biasa (===) berhenti
 *      di karakter pertama yang berbeda, dan selisih waktunya bisa diukur
 *      untuk menebak token satu karakter demi satu. hash_equals() selalu
 *      memakan waktu sama.
 *   2. TOKEN LEWAT HEADER, bukan query string. Query string ikut tercatat di
 *      access log Apache/LiteSpeed apa adanya; header tidak. Query string
 *      tetap diterima sebagai cadangan (sebagian panel cPanel menyulitkan
 *      pengiriman header), tapi header yang dianjurkan.
 *   3. MATI KALAU TOKENNYA KOSONG. Tanpa DEPLOY_TOKEN di .env, endpoint ini
 *      menjawab 404 seolah tidak pernah ada. Ini penting: kalau berkasnya
 *      terlanjur ter-deploy sebelum .env diisi, jangan sampai ada rute
 *      pembersih cache yang terbuka untuk umum.
 *   4. THROTTLE. Dipasang di routes/web.php (6 permintaan per menit per IP)
 *      supaya tokennya tidak bisa ditebak dengan mencoba ribuan kali.
 *
 * Setiap percobaan yang GAGAL dicatat ke log beserta IP-nya — kalau suatu
 * saat ada yang mengetuk-ngetuk endpoint ini, jejaknya ada.
 * ==========================================================================
 */
class DeployController extends Controller
{
    /** Berkas penanda versi rilis; dibaca /versi.json untuk memberi tahu tab yang terbuka. */
    public const BERKAS_VERSI = 'deploy-versi.txt';

    public function bersihkan(Request $request): JsonResponse
    {
        $token = (string) config('deploy.token');

        // Fitur mati total kalau tokennya belum diisi.
        abort_if($token === '', 404);

        $dikirim = (string) ($request->header('X-Deploy-Token')
            ?? $request->query('token', ''));

        if (! hash_equals($token, $dikirim)) {
            Log::warning('Percobaan akses endpoint deploy ditolak.', [
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 200),
            ]);

            abort(403, 'Token deploy tidak cocok.');
        }

        $hasil = [];

        /*
         | optimize:clear SUDAH mencakup cache, config, route, view, dan
         | compiled. view:clear & config:clear tetap dipanggil terpisah
         | sesudahnya BUKAN karena mubazir, melainkan karena kalau salah satu
         | gagal (mis. satu berkas cache view sedang dipakai proses lain),
         | perintah gabungan berhenti di situ dan sisanya tidak ikut jalan.
         | Dipanggil satu per satu dengan try/catch, jadi kegagalan satu
         | perintah tidak membatalkan yang lain — dan yang gagal terlihat
         | jelas di respons, bukan tersembunyi.
         */
        foreach (['optimize:clear', 'view:clear', 'config:clear', 'route:clear', 'cache:clear'] as $perintah) {
            try {
                Artisan::call($perintah);
                $hasil[$perintah] = 'ok';
            } catch (\Throwable $e) {
                $hasil[$perintah] = 'gagal: ' . $e->getMessage();
            }
        }

        // Penanda versi baru. Inilah yang membuat tab yang SEDANG TERBUKA
        // tahu ada pembaruan (lihat /versi.json + pemantau di layouts/app).
        $versi = (string) now()->timestamp;

        try {
            Storage::disk('local')->put(self::BERKAS_VERSI, $versi);
        } catch (\Throwable $e) {
            $hasil['tulis-versi'] = 'gagal: ' . $e->getMessage();
        }

        Log::info('Cache server dibersihkan lewat endpoint deploy.', [
            'ip' => $request->ip(),
            'versi' => $versi,
        ]);

        return response()->json([
            'status' => 'ok',
            'versi' => $versi,
            'waktu' => now()->toDateTimeString(),
            'perintah' => $hasil,
        ]);
    }

    /**
     * Penanda versi rilis yang sedang berjalan — dipanggil berkala oleh
     * halaman yang sedang terbuka di browser guru/wali murid.
     *
     * Sengaja TANPA autentikasi: isinya hanya satu angka waktu, tidak
     * membocorkan apa pun. Memasang auth di sini justru merusak tujuannya —
     * pemantaunya harus tetap bekerja di halaman login sekalipun.
     */
    public function versi(): JsonResponse
    {
        return response()
            ->json(['versi' => self::versiSekarang()])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Versi rilis: waktu deploy terakhir, atau — kalau belum pernah ada
     * deploy lewat endpoint ini — waktu berkas aset terakhir berubah.
     *
     * Cadangan itu penting supaya pemantau versinya tetap berguna sejak hari
     * pertama, sebelum GitHub Actions pernah sekali pun memanggil endpoint.
     */
    public static function versiSekarang(): string
    {
        try {
            if (Storage::disk('local')->exists(self::BERKAS_VERSI)) {
                return trim(Storage::disk('local')->get(self::BERKAS_VERSI));
            }
        } catch (\Throwable $e) {
            // jatuh ke cadangan di bawah
        }

        $kandidat = [
            public_path('build/manifest.json'),
            public_path('simagas.css'),
        ];

        foreach ($kandidat as $berkas) {
            $waktu = @filemtime($berkas);

            if ($waktu) {
                return (string) $waktu;
            }
        }

        return '0';
    }
}
