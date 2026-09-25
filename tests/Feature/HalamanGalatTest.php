<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Menjaga halaman galat ramah di resources/views/errors/.
 *
 * ============ KENAPA UJIAN INI ADA ============
 * Halaman galat adalah satu-satunya bagian sistem yang justru dipakai saat
 * bagian lain rusak. Akibatnya ia punya satu cara gagal yang khas dan sangat
 * mudah terjadi tanpa disadari: seseorang merapikan tampilannya dengan
 * menambahkan @extends('layouts.app'), atau mengganti <img src="/logo-mark.png">
 * menjadi asset(...), atau memasukkan simagas.css supaya "seragam".
 *
 * Perubahan itu tidak akan terlihat salah — halaman tetap cantik saat diuji
 * manual, karena saat diuji manual databasenya hidup. Ia baru gagal pada satu
 * hari ketika database benar-benar mati, yaitu tepat hari ketika halaman ini
 * satu-satunya yang diharapkan masih bekerja. Yang muncul di layar guru
 * kembali menjadi "Server Error" polos.
 *
 * Karena itu ujian di bawah tidak hanya memeriksa tulisan yang tampil,
 * tetapi juga MEMBACA SUMBER berkasnya untuk memastikan ketergantungan
 * terlarang tidak pernah masuk kembali.
 * ==============================================
 */
class HalamanGalatTest extends TestCase
{
    /** Semua kode galat yang wajib punya halaman sendiri. */
    private const KODE_WAJIB = ['403', '404', '419', '429', '500', '503', '4xx', '5xx'];

    /**
     * Pola yang TIDAK BOLEH muncul di berkas halaman galat, beserta alasannya.
     *
     * Alasannya disimpan bersama polanya supaya ketika ujian ini merah, pesan
     * gagalnya langsung menjelaskan mengapa — bukan cuma "pola X ditemukan".
     */
    private const TERLARANG = [
        '@extends' => 'layout aplikasi memuat sidebar, dan sidebar membaca database',
        '@include(\'layouts' => 'sama seperti @extends: ikut menarik sidebar',
        'livewire' => 'Livewire butuh aplikasi berjalan normal dan JS termuat',
        'asset(' => 'helper asset() bisa gagal ikut rusak; pakai path statis "/..."',
        'route(' => 'cache rute yang rusak justru salah satu penyebab galat 500',
        'simagas.css' => 'berkas CSS bisa belum tersalin setelah deploy gagal',
        'auth()' => 'membaca pengguna login berarti menyentuh sesi dan database',
        'Auth::' => 'membaca pengguna login berarti menyentuh sesi dan database',
        'PengaturanSistem' => 'model apa pun berarti query database',
        '@vite' => 'manifest Vite yang hilang melempar exception baru',
    ];

    private function jalur(string $kode): string
    {
        return resource_path("views/errors/{$kode}.blade.php");
    }

    public function test_semua_berkas_halaman_galat_ada(): void
    {
        foreach (self::KODE_WAJIB as $kode) {
            $this->assertFileExists(
                $this->jalur($kode),
                "errors/{$kode}.blade.php tidak ada — pengguna akan melihat halaman putih bawaan Laravel."
            );
        }

        $this->assertFileExists(
            resource_path('views/errors/_tampilan.blade.php'),
            'Tampilan bersamanya hilang; seluruh halaman galat akan ikut gagal dirender.'
        );
    }

    public function test_halaman_galat_tidak_bergantung_pada_bagian_yang_mungkin_rusak(): void
    {
        $berkas = array_merge(
            array_map(fn ($k) => $this->jalur($k), self::KODE_WAJIB),
            [resource_path('views/errors/_tampilan.blade.php')]
        );

        foreach ($berkas as $jalur) {
            $isi = file_get_contents($jalur);

            // Komentar Blade dibuang lebih dulu. Tanpa langkah ini, catatan
            // penjelasan di dalam berkas ("... TIDAK memanggil route() ...")
            // ikut terbaca sebagai pelanggaran, dan ujian ini merah karena
            // dokumentasinya sendiri — kegagalan yang membingungkan.
            $isi = preg_replace('/\{\{--.*?--\}\}/s', '', $isi);

            foreach (self::TERLARANG as $pola => $alasan) {
                $this->assertStringNotContainsStringIgnoringCase(
                    $pola,
                    $isi,
                    basename($jalur)." memakai \"{$pola}\" — dilarang karena {$alasan}."
                );
            }
        }
    }

    public function test_halaman_galat_bisa_dirender_tanpa_menyentuh_database(): void
    {
        // Koneksi bawaan diarahkan ke tujuan yang pasti gagal. Kalau salah
        // satu halaman galat diam-diam melakukan query, ujian ini melempar
        // QueryException — persis kegagalan yang ingin dicegah.
        config([
            'database.default' => 'mati_sengaja',
            'database.connections.mati_sengaja' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 1,
                'database' => 'tidak_ada',
                'username' => 'tidak_ada',
                'password' => 'tidak_ada',
            ],
        ]);

        foreach (self::KODE_WAJIB as $kode) {
            $html = view("errors.{$kode}", ['exception' => null])->render();

            $this->assertStringContainsString('<h1>', $html, "errors/{$kode} tidak menghasilkan judul.");
            $this->assertStringNotContainsString('<script', $html, "errors/{$kode} memuat JS.");
        }
    }

    public function test_galat_server_menampilkan_pesan_sedang_dalam_perbaikan(): void
    {
        config(['app.debug' => false]);

        Route::get('/uji-galat-server-sementara', function () {
            throw new \RuntimeException('rahasia teknis yang tidak boleh tampil');
        });

        $respons = $this->get('/uji-galat-server-sementara');

        $respons->assertStatus(500);
        $respons->assertSee('Sistem sedang dalam perbaikan');

        // Pesan exception aslinya tetap masuk log, TIDAK ke layar pengguna.
        $respons->assertDontSee('rahasia teknis');
        $respons->assertDontSee('RuntimeException');
    }

    public function test_galat_server_menyertakan_kode_waktu_untuk_pelaporan(): void
    {
        config(['app.debug' => false]);

        $html = view('errors.500', ['exception' => null])->render();

        $this->assertStringContainsString('Sebutkan kode ini saat melapor', $html);
        $this->assertStringContainsString(now()->format('d/m H:i'), $html);
    }

    public function test_alamat_tidak_ada_tidak_disebut_sebagai_kerusakan_sistem(): void
    {
        config(['app.debug' => false]);

        $respons = $this->get('/alamat-yang-pasti-tidak-pernah-ada-'.uniqid());

        $respons->assertStatus(404);
        $respons->assertSee('Halaman tidak ditemukan');

        // Ini yang membedakan 404 dari 500. Kalau salah ketik URL pun
        // berbunyi "sistem sedang dalam perbaikan", Admin akan dikirim
        // memeriksa server yang sebenarnya sehat.
        $respons->assertDontSee('Sistem sedang dalam perbaikan');
    }

    public function test_akses_ditolak_tidak_disebut_sebagai_kerusakan_sistem(): void
    {
        config(['app.debug' => false]);

        Route::get('/uji-galat-403-sementara', fn () => abort(403));

        $respons = $this->get('/uji-galat-403-sementara');

        $respons->assertStatus(403);
        $respons->assertSee('Anda tidak punya akses');
        $respons->assertDontSee('Sistem sedang dalam perbaikan');
    }

    public function test_sesi_kedaluwarsa_diberi_pesan_yang_menenangkan(): void
    {
        config(['app.debug' => false]);

        Route::get('/uji-galat-419-sementara', fn () => abort(419));

        $respons = $this->get('/uji-galat-419-sementara');

        $respons->assertStatus(419);
        $respons->assertSee('Sesi berakhir');
        $respons->assertDontSee('Sistem sedang dalam perbaikan');
    }

    /**
     * Penadah 5xx adalah jaring terakhir untuk 502/504 — kegagalan yang khas
     * di hosting bersama ketika PHP-FPM tumbang. Tanpa berkas ini, yang
     * tampil kembali "Whoops, looks like something went wrong."
     */
    public function test_galat_gateway_tetap_mendapat_halaman_ramah(): void
    {
        config(['app.debug' => false]);

        Route::get('/uji-galat-502-sementara', fn () => abort(502));

        $respons = $this->get('/uji-galat-502-sementara');

        $respons->assertStatus(502);
        $respons->assertSee('Sistem sedang dalam perbaikan');
    }

    /**
     * 413 (berkas unggahan terlalu besar) memakai penadah 4xx. Ia TIDAK boleh
     * berbunyi "sistem sedang dalam perbaikan": yang perlu diperkecil adalah
     * fotonya, bukan diperbaiki servernya.
     */
    public function test_unggahan_terlalu_besar_mengarahkan_ke_ukuran_berkas(): void
    {
        config(['app.debug' => false]);

        Route::get('/uji-galat-413-sementara', fn () => abort(413));

        $respons = $this->get('/uji-galat-413-sementara');

        $respons->assertStatus(413);
        $respons->assertSee('ukuran lebih kecil');
        $respons->assertDontSee('Sistem sedang dalam perbaikan');
    }
}
