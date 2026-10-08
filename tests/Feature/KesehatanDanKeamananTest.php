<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DenyutPenjadwal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * #8 Indikator kesehatan penjadwal di dashboard Super Admin.
 * #9 Header keamanan standar di setiap halaman.
 */
class KesehatanDanKeamananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Carbon::setTestNow('2026-10-08 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function dashboardAdmin()
    {
        return $this->actingAs(User::factory()->superAdmin()->create())->get('/super-admin/dashboard')->assertOk();
    }

    public function test_cron_belum_pernah_jalan_memunculkan_peringatan(): void
    {
        $this->dashboardAdmin()
            ->assertSee('data-kesehatan="masalah"', false)
            ->assertSee('belum pernah tercatat berjalan');
    }

    public function test_denyut_baru_menampilkan_satu_baris_normal(): void
    {
        DenyutPenjadwal::catat();
        Carbon::setTestNow('2026-10-08 09:03:00');

        $this->dashboardAdmin()
            ->assertSee('data-kesehatan="sehat"', false)
            ->assertSee('terakhir 09:00')
            ->assertDontSee('data-kesehatan="masalah"', false);
    }

    public function test_denyut_lebih_dari_lima_menit_dianggap_bermasalah(): void
    {
        DenyutPenjadwal::catat();
        Carbon::setTestNow('2026-10-08 09:06:00');

        $this->dashboardAdmin()
            ->assertSee('data-kesehatan="masalah"', false)
            ->assertSee('09:00');
    }

    public function test_antrean_gagal_dan_tertahan_ikut_dilaporkan(): void
    {
        DenyutPenjadwal::catat();
        DB::table('failed_jobs')->insert(['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDay()]);
        DB::table('jobs')->insert(['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->subMinutes(20)->getTimestamp(), 'created_at' => now()->subMinutes(20)->getTimestamp()]);

        $this->dashboardAdmin()
            ->assertSee('data-kesehatan="masalah"', false)
            ->assertSee('gagal dalam 7 hari terakhir')
            ->assertSee('menunggu lebih dari 10 menit');
    }

    public function test_kepala_sekolah_tidak_melihat_urusan_teknis(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())->get('/kepsek/dashboard')->assertOk()
            ->assertDontSee('data-kesehatan', false);
    }

    public function test_denyut_terjadwal_tiap_menit(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('simagas-denyut');
    }

    public function test_header_keamanan_terpasang_dan_versi_php_disembunyikan(): void
    {
        $r = $this->get('/login')->assertOk();

        $r->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)', $r->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $r->headers->get('Permissions-Policy'));
        $r->assertHeaderMissing('X-Powered-By');
        // HSTS hanya lewat HTTPS.
        $r->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_x_powered_by_dari_aplikasi_pun_dibuang(): void
    {
        \Illuminate\Support\Facades\Route::middleware('web')->get('/_uji-header', fn () => response('ok')->header('X-Powered-By', 'PHP/8.3.35'));

        $this->get('/_uji-header')->assertOk()->assertHeaderMissing('X-Powered-By');
    }

    public function test_hsts_dikirim_lewat_https(): void
    {
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
