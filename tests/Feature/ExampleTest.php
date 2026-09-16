<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Pemeriksaan paling dasar: aplikasinya menyala dan halaman depannya bekerja.
 *
 * ============ KENAPA BUKAN "200" ============
 * Berkas bawaan Laravel memeriksa GET / menghasilkan 200. Di project ini
 * jawabannya SELALU 302, dan itu memang yang benar: halaman depan tidak
 * menampilkan apa pun sendiri, ia mengarahkan tamu ke halaman login dan
 * pengguna yang sudah masuk ke dashboard sesuai perannya (routes/web.php,
 * rute bernama 'home').
 *
 * Selama uji bawaannya dibiarkan, `php artisan test` selalu berakhir merah
 * dengan satu kegagalan. Suite yang selalu merah satu baris akhirnya dibaca
 * sebagai "ya memang segitu" — dan kegagalan yang SUNGGUHAN, saat ia muncul,
 * ikut tenggelam di baris yang sama.
 * ============================================
 */
class ExampleTest extends TestCase
{
    public function test_halaman_depan_mengarahkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_login_bisa_dibuka(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('SIMAGAS', false);
    }
}
