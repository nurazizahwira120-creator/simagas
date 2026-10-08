<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Peringatan "Foto akan tersimpan, tapi belum bisa ditampilkan" di halaman
 * Profil. Di cPanel symlink storage ada di public_html (DOCUMENT_ROOT), bukan
 * di backend/public — dulu peringatannya muncul palsu di server.
 */
class PeringatanStorageProfilTest extends TestCase
{
    use RefreshDatabase;

    private const PESAN = 'Foto akan tersimpan, tapi belum bisa ditampilkan.';

    private string $akarPublic;

    private string $akarWeb;

    protected function setUp(): void
    {
        parent::setUp();

        // Dua folder palsu: "public" milik Laravel dan "public_html" milik hosting.
        $dasar = sys_get_temp_dir() . '/simagas-storage-' . uniqid();
        $this->akarPublic = $dasar . '/public';
        $this->akarWeb = $dasar . '/public_html';
        File::ensureDirectoryExists($this->akarPublic);
        File::ensureDirectoryExists($this->akarWeb);

        $this->app->usePublicPath($this->akarPublic);

        $this->actingAs(User::factory()->guru()->create());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->akarPublic));

        parent::tearDown();
    }

    /** Buka halaman Profil lewat HTTP sungguhan, dengan DOCUMENT_ROOT tertentu. */
    private function buka(?string $akarWeb = null): TestResponse
    {
        return $this->withServerVariables(['DOCUMENT_ROOT' => $akarWeb ?? $this->akarWeb])
            ->get('/guru/profil')
            ->assertOk();
    }

    public function test_peringatan_muncul_kalau_symlink_belum_ada_di_mana_pun(): void
    {
        $this->buka()->assertSee(self::PESAN);
    }

    public function test_symlink_di_public_laravel_dianggap_siap(): void
    {
        File::ensureDirectoryExists($this->akarPublic . '/storage');

        $this->buka()->assertDontSee(self::PESAN);
    }

    public function test_symlink_di_document_root_cpanel_dianggap_siap(): void
    {
        // Kondisi server simagas.online: public_html/storage ada,
        // backend_simagas/public/storage tidak ada.
        $tujuan = dirname($this->akarWeb) . '/storage-app-public';
        File::ensureDirectoryExists($tujuan);
        symlink($tujuan, $this->akarWeb . '/storage');

        $this->buka()->assertDontSee(self::PESAN);
    }

    public function test_symlink_yang_tujuannya_hilang_tetap_diperingatkan(): void
    {
        // Symlink "rusak": foto memang tidak akan tampil, jadi peringatan benar.
        symlink(dirname($this->akarWeb) . '/tidak-ada', $this->akarWeb . '/storage');

        $this->buka()->assertSee(self::PESAN);
    }

    public function test_document_root_kosong_tidak_membuat_error(): void
    {
        $this->buka('')->assertSee(self::PESAN);
    }
}
