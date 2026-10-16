<?php

namespace Tests\Feature;

use App\Livewire\Pengumuman\KelolaPengumuman;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengumuman;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Penjaga hasil audit tampilan responsif.
 *
 * Yang dijaga di sini adalah markup yang bisa diperiksa tanpa browser:
 * pagination SIMAGAS (bukan template bawaan yang kelas responsifnya tak
 * pernah ter-build), penanda tabel-kartu, dan tautan pemilih anak.
 */
class TampilanResponsifTest extends TestCase
{
    use RefreshDatabase;

    public function test_pagination_halaman_biasa_memakai_template_simagas(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $kelas = Kelas::factory()->create();
        Siswa::factory()->count(20)->create(['kelas_id' => $kelas->id]);

        $this->actingAs($admin)->get('/super-admin/siswa?page=2')
            ->assertOk()
            ->assertSee('aria-label="Navigasi halaman"', false)
            ->assertSee('Menampilkan')
            ->assertSee('aria-current="page"', false)
            ->assertSee('Halaman')
            // Template bawaan Laravel tidak boleh muncul lagi.
            ->assertDontSee('Pagination Navigation')
            ->assertDontSee('Previous');
    }

    public function test_pagination_livewire_memakai_template_simagas(): void
    {
        $admin = User::factory()->superAdmin()->create();
        foreach (range(1, 12) as $i) {
            Pengumuman::create(['user_id' => $admin->id, 'judul' => "Pengumuman {$i}", 'isi_pesan' => 'Isi', 'target_role' => 'semua']);
        }

        Livewire::actingAs($admin)->test(KelolaPengumuman::class)
            ->assertSee('Navigasi halaman')
            ->assertSee('wire:click="nextPage(', false)
            ->assertSee('gotoPage(2', false)
            ->assertDontSee('Pagination Navigation');
    }

    public function test_tabel_master_data_menjadi_kartu_di_hp(): void
    {
        $admin = User::factory()->superAdmin()->create();
        Siswa::factory()->create(['kelas_id' => Kelas::factory()->create()->id]);
        Pegawai::factory()->create();

        foreach (['/super-admin/siswa', '/super-admin/users', '/super-admin/kelas', '/super-admin/pegawai'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('tabel-kartu', false)
                ->assertSee('data-utama', false)
                ->assertSee('data-aksi', false);
        }
    }

    public function test_pemilih_anak_absensi_kedatangan_tetap_di_halamannya(): void
    {
        $wali = User::factory()->waliMurid()->create();
        $kelas = Kelas::factory()->create();
        [$a, $b] = Siswa::factory()->count(2)->create(['kelas_id' => $kelas->id, 'wali_murid_id' => $wali->id]);

        $this->actingAs($wali)->get('/wali-murid/absensi-kedatangan?anak=' . $b->id)
            ->assertOk()
            // Dulu tautannya ke Dashboard: memilih anak memindahkan halaman.
            ->assertSee('/wali-murid/absensi-kedatangan?anak=' . $a->id, false)
            ->assertDontSee('/wali-murid/dashboard?anak=' . $a->id, false)
            ->assertSee('aria-current="page"', false);
    }
}
