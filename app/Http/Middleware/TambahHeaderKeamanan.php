<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan standar untuk setiap halaman.
 *
 *   X-Frame-Options: SAMEORIGIN
 *       Halaman SIMAGAS tidak bisa dibingkai (iframe) situs lain. Mencegah
 *       "clickjacking": situs jahat menaruh halaman Setujui Izin / Hapus
 *       secara transparan di atas tombolnya sendiri. Bingkai dari situs
 *       sendiri (pratinjau PDF) tetap boleh.
 *   X-Content-Type-Options: nosniff
 *       Browser tidak menebak-nebak jenis berkas. Berkas unggahan yang
 *       diberi nama .jpg tapi berisi skrip tidak akan dijalankan.
 *   Referrer-Policy: strict-origin-when-cross-origin
 *       Alamat lengkap halaman (yang bisa memuat nama/ID siswa) tidak ikut
 *       terkirim ke CDN atau situs luar yang ditautkan.
 *   Permissions-Policy
 *       Kamera (scan QR) dan lokasi (absen radius) HANYA untuk situs ini;
 *       mikrofon dan pembayaran dimatikan sama sekali.
 *   Strict-Transport-Security (hanya lewat HTTPS)
 *       Browser yang pernah membuka simagas.online selalu memakai HTTPS,
 *       walau pengguna mengetik http://.
 *
 * X-Powered-By dibuang: versi PHP server (mis. "PHP/8.3.35") tidak perlu
 * diumumkan ke setiap pengunjung — itu daftar belanja bagi penyerang yang
 * mencari celah versi tertentu.
 *
 * Content-Security-Policy SENGAJA belum dipasang: aplikasi ini memakai skrip
 * sebaris dan beberapa CDN (SweetAlert, Leaflet, pemindai QR, Firebase).
 * CSP yang terlalu ketat akan mematikan fitur tanpa pesan yang jelas.
 */
class TambahHeaderKeamanan
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $respons */
        $respons = $next($request);

        $h = $respons->headers;

        $h->set('X-Frame-Options', 'SAMEORIGIN', false);
        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=(), payment=()', false);

        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000', false);
        }

        $h->remove('X-Powered-By');

        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $respons;
    }
}
