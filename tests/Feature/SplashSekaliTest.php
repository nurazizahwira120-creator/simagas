<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layar pembuka (splash) hanya tampil SEKALI per sesi login — bukan di
 * setiap perpindahan halaman.
 */
class SplashSekaliTest extends TestCase
{
    use RefreshDatabase;

    public function test_splash_hanya_di_halaman_pertama_sesudah_login(): void
    {
        $this->actingAs(User::factory()->kepsek()->create());

        $this->get('/kepsek/dashboard')->assertOk()->assertSee('id="splash-screen"', false);
        $this->get('/kepsek/pantauan-siswa')->assertOk()->assertDontSee('id="splash-screen"', false);
        $this->get('/kepsek/dashboard')->assertOk()->assertDontSee('id="splash-screen"', false);
    }

    public function test_sesi_baru_menampilkan_splash_lagi_sekali(): void
    {
        $this->actingAs(User::factory()->kepsek()->create());
        $this->get('/kepsek/dashboard')->assertSee('id="splash-screen"', false);

        // Sesi baru (mis. login ulang besok pagi).
        $this->flushSession();

        $this->get('/kepsek/dashboard')->assertSee('id="splash-screen"', false);
        $this->get('/kepsek/dashboard')->assertDontSee('id="splash-screen"', false);
    }

    public function test_splash_langsung_menutup_tanpa_jeda_satu_detik(): void
    {
        $html = $this->actingAs(User::factory()->kepsek()->create())->get('/kepsek/dashboard')->getContent();

        $this->assertStringContainsString('const tutup = () => show = false;', $html);
        $this->assertStringNotContainsString('setTimeout(() => show = false, 1000)', $html);
        $this->assertStringContainsString('duration-300', $html);
    }
}
