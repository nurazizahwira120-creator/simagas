<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Melarang browser & proxy menyimpan HALAMAN HTML.
 *
 * ==================== KENAPA INI YANG PALING MENENTUKAN ====================
 * Berkas CSS/JS hasil Vite sudah ber-hash (app-CQ-xVDHC.css), jadi begitu
 * isinya berubah NAMANYA ikut berubah dan browser pasti mengunduh yang baru.
 * Yang TIDAK ber-hash adalah halaman HTML-nya sendiri — dan halaman itulah
 * yang menyebut nama berkas mana yang harus dimuat.
 *
 * Kalau HTML-nya tersimpan di cache browser, pengguna tetap membaca halaman
 * LAMA yang menunjuk nama berkas LAMA. Aset barunya sudah ada di server,
 * tapi tidak ada yang memintanya. Inilah sebab sesungguhnya orang harus
 * menekan Ctrl+Shift+R setelah setiap deploy — bukan CSS-nya yang nyangkut,
 * melainkan HTML yang menyebutkannya.
 *
 * Dengan no-store, setiap kali halaman dibuka browser bertanya ulang ke
 * server, mendapat HTML terbaru, lalu menarik aset ber-hash yang baru. Tanpa
 * hard reload, tanpa membersihkan cache manual.
 * ==========================================================================
 *
 * Yang TIDAK disentuh:
 *   - berkas statis (CSS/JS/gambar/PDF) — Apache yang melayaninya, tidak
 *     lewat middleware ini, dan justru BAIK kalau di-cache lama karena
 *     namanya ber-hash;
 *   - respons unduhan (Content-Disposition: attachment) — sebagian browser
 *     lama gagal menyimpan berkas yang ditandai no-store;
 *   - respons non-HTML seperti JSON Livewire, yang memang sudah tidak
 *     di-cache.
 */
class CegahCacheHalaman
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $tipe = (string) $response->headers->get('Content-Type', '');
        $disposisi = (string) $response->headers->get('Content-Disposition', '');

        $htmlBiasa = $tipe === '' || str_contains($tipe, 'text/html');
        $unduhan = str_contains($disposisi, 'attachment');

        if ($htmlBiasa && ! $unduhan) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
