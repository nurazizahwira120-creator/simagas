<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiSiswa;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * LAPORAN ABSENSI HARIAN — saringan per kelas & status.
 *
 * Kamis, 24 September 2026 (batas terlambat siswa bawaan 07.15):
 *   XI RPL 1 : Andi hadir 06.50, Bela terlambat 07.30, Candra BELUM absen
 *   X TKJ 2  : Dewi izin, Eko alpa, Fani BELUM, Gilang BELUM
 *   X MM 1 : Hana hadir 07.00  -> lengkap
 *   Pegawai  : Pak Joko belum absen
 */
class LaporanHarianTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/super-admin/laporan-harian?tanggal=2026-09-24';

    private User $admin;

    /** @var array<string, Kelas> */
    private array $kelas = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-24 09:00:00'));

        $this->admin = User::factory()->superAdmin()->create();
        $this->kelas['rpl'] = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        $this->kelas['tkj'] = Kelas::factory()->create(['nama_kelas' => 'X TKJ 2']);
        $this->kelas['mm'] = Kelas::factory()->create(['nama_kelas' => 'X MM 1']);

        $this->siswa('Andi', 'rpl', AbsensiStatus::Hadir, '06:50');
        $this->siswa('Bela', 'rpl', AbsensiStatus::Hadir, '07:30');
        $this->siswa('Candra', 'rpl');
        $this->siswa('Dewi', 'tkj', AbsensiStatus::Izin);
        $this->siswa('Eko', 'tkj', AbsensiStatus::Alpha);
        $this->siswa('Fani', 'tkj');
        $this->siswa('Gilang', 'tkj');
        $this->siswa('Hana', 'mm', AbsensiStatus::Hadir, '07:00');
        Pegawai::factory()->create(['nama' => 'Pak Joko']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function siswa(string $nama, string $kelas, ?AbsensiStatus $status = null, ?string $jam = null): void
    {
        $s = Siswa::factory()->create(['nama' => $nama, 'kelas_id' => $this->kelas[$kelas]->id]);

        if ($status) {
            AbsensiSiswa::create(['siswa_id' => $s->id, 'tanggal' => '2026-09-24', 'status' => $status, 'jam_masuk' => $jam ? "{$jam}:00" : null]);
        }
    }

    private function buka(string $tambahan = '')
    {
        return $this->actingAs($this->admin)->get(self::URL . $tambahan)->assertOk();
    }

    public function test_tanpa_saringan_semua_tampil_dan_ringkasan_per_kelas_terurut(): void
    {
        $r = $this->buka()
            ->assertSee(['Andi', 'Dewi', 'Hana', 'Pak Joko'])
            ->assertSee('data-panel="pegawai"', false)
            // X TKJ 2 (2 belum) di depan XI RPL 1 (1 belum), X MM 1 lengkap paling akhir.
            ->assertSeeInOrder(['X TKJ 2', '2 belum', 'XI RPL 1', '1 belum', 'X MM 1', 'lengkap']);

        $this->assertSame(['belum' => 3, 'total' => 8], [
            'belum' => $r->viewData('siswa')['belum'],
            'total' => $r->viewData('siswa')['total'],
        ]);
    }

    public function test_saring_satu_kelas_hanya_siswa_kelas_itu_dan_pegawai_disembunyikan(): void
    {
        $r = $this->buka('&kelas=' . $this->kelas['rpl']->id)
            ->assertSee(['Andi', 'Bela', 'Candra', 'Siswa XI RPL 1'])
            ->assertDontSee(['Dewi', 'Fani', 'Hana', 'Pak Joko'])
            ->assertDontSee('data-panel="pegawai"', false)
            ->assertSee('1 belum absen')
            ->assertSee('3 total');

        $this->assertSame(2, $r->viewData('siswa')['hadir']); // terlambat tetap hadir
    }

    public function test_saring_kelas_dan_belum_absen_langsung_menunjukkan_siapa_yang_belum(): void
    {
        $this->buka('&kelas=' . $this->kelas['tkj']->id . '&status=belum')
            ->assertSee(['Fani', 'Gilang'])
            ->assertDontSee(['Dewi', 'Eko', 'Andi'])
            // Hitungan tetap seluruh kelas, bukan hanya yang tampil.
            ->assertSee('2 belum absen')
            ->assertSee('4 total');
    }

    public function test_saringan_status_lain(): void
    {
        $this->buka('&status=terlambat')->assertSee('Bela')->assertDontSee(['Andi', 'Candra', 'Hana']);
        $this->buka('&status=izin_sakit')->assertSee('Dewi')->assertDontSee(['Eko', 'Fani']);
        $this->buka('&status=alpa')->assertSee('Eko')->assertDontSee(['Dewi', 'Gilang']);
        // Pegawai ikut tersaring status: Pak Joko belum absen.
        $this->buka('&status=belum')->assertSee(['Candra', 'Fani', 'Pak Joko'])->assertDontSee(['Andi', 'Bela']);
    }

    public function test_chip_kelas_menuju_saringan_belum_absen_kelas_itu(): void
    {
        $this->buka()
            ->assertSee('kelas=' . $this->kelas['tkj']->id . '&amp;status=belum', false)
            // Kelas lengkap: tanpa saringan status (tidak ada yang belum).
            ->assertSee('tanggal=2026-09-24&amp;kelas=' . $this->kelas['mm']->id . '"', false);
    }

    public function test_kelas_lengkap_menampilkan_pesan_jujur(): void
    {
        $this->buka('&kelas=' . $this->kelas['mm']->id . '&status=belum')
            ->assertSee('Semua siswa sudah tercatat — tidak ada yang belum absen.');
    }

    public function test_nilai_saringan_tidak_sah_dianggap_semua(): void
    {
        $r = $this->buka('&kelas=99999&status=hapus-semua')
            ->assertSee(['Andi', 'Dewi', 'Pak Joko']);

        $this->assertNull($r->viewData('kelas'));
        $this->assertSame('semua', $r->viewData('status'));
    }
}
