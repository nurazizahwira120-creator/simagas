<?php

namespace Tests\Feature;

use App\Enums\Hari;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Bug "Input Nilai macet diam-diam": mapel yang ditambahkan ke jadwal
 * SESUDAH migrasi 000031 tidak pernah masuk master `mapels`, sehingga tidak
 * muncul di pilihan Input Nilai guru.
 */
class SinkronMapelTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private Pegawai $pegawai;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guru = User::factory()->guru()->create();
        $this->pegawai = Pegawai::factory()->create(['user_id' => $this->guru->id]);
        $this->kelas = Kelas::factory()->create(['nama_kelas' => 'X RPL 1']);
    }

    private function buatJadwal(string $mapel): JadwalPelajaran
    {
        return JadwalPelajaran::create([
            'hari' => Hari::Senin->value, 'jam_mulai' => '07:00', 'jam_selesai' => '08:30',
            'mata_pelajaran' => $mapel, 'kelas_id' => $this->kelas->id, 'guru_id' => $this->pegawai->id,
        ]);
    }

    public function test_jadwal_baru_langsung_mendaftarkan_mapelnya(): void
    {
        $this->assertSame(0, Mapel::where('nama', 'Informatika')->count());

        $this->buatJadwal('Informatika');

        $this->assertSame(1, Mapel::where('nama', 'Informatika')->count());
    }

    public function test_mapel_baru_muncul_di_pilihan_input_nilai_guru(): void
    {
        $this->buatJadwal('Informatika');

        $this->actingAs($this->guru)->get('/guru/nilai')
            ->assertOk()
            ->assertSee('Informatika');
    }

    public function test_data_lama_yang_terlewat_disembuhkan_saat_input_nilai_dibuka(): void
    {
        // Disisipkan langsung lewat DB — meniru jadwal lama yang dibuat
        // sebelum perbaikan ini (tidak memicu event model apa pun).
        DB::table('jadwal_pelajaran')->insert([
            'hari' => Hari::Selasa->value, 'jam_mulai' => '09:00', 'jam_selesai' => '10:30',
            'mata_pelajaran' => 'Seni Budaya', 'kelas_id' => $this->kelas->id, 'guru_id' => $this->pegawai->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertSame(0, Mapel::where('nama', 'Seni Budaya')->count());

        $this->actingAs($this->guru)->get('/guru/nilai')
            ->assertOk()
            ->assertSee('Seni Budaya');

        $this->assertSame(1, Mapel::where('nama', 'Seni Budaya')->count());
    }

    public function test_ejaan_beda_huruf_dan_spasi_tidak_menggandakan_mapel(): void
    {
        $this->buatJadwal('Matematika');
        $this->buatJadwal('matematika ');
        $this->buatJadwal('MATEMATIKA');
        $this->buatJadwal('Bahasa  Inggris');
        $this->buatJadwal('Bahasa Inggris');

        $this->assertSame(['Bahasa Inggris', 'Matematika'], Mapel::orderBy('nama')->pluck('nama')->all());
    }

    public function test_mengganti_nama_mapel_di_jadwal_menambah_tanpa_menghapus_yang_lama(): void
    {
        $jadwal = $this->buatJadwal('Kimia');
        $jadwal->update(['mata_pelajaran' => 'Kimia Industri']);

        // Mapel lama tetap ada: bisa saja sudah punya nilai siswa.
        $this->assertSame(['Kimia', 'Kimia Industri'], Mapel::orderBy('nama')->pluck('nama')->all());
    }

    public function test_sinkron_aman_dijalankan_berulang(): void
    {
        $this->assertSame(2, Mapel::sinkron(['Fisika', 'Biologi']));
        $this->assertSame(0, Mapel::sinkron(['Fisika', 'biologi', ' Biologi ']));
        $this->assertSame(2, Mapel::count());
    }

    public function test_nama_terlalu_panjang_dipotong_bukan_digagalkan(): void
    {
        Mapel::sinkron([str_repeat('A', 150)]);

        $this->assertSame(120, mb_strlen(Mapel::first()->nama));
    }

    public function test_gagal_mendaftarkan_mapel_tidak_membatalkan_penyimpanan_jadwal(): void
    {
        // Kegagalan disimulasikan dengan membuang tabel mapels. Hanya di
        // SQLite: di MySQL, DROP TABLE ditolak foreign key `nilais` DAN
        // memaksa COMMIT yang merusak isolasi tes lain.
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('Simulasi kegagalan hanya aman di SQLite.');
        }

        Schema::drop('mapels');

        $jadwal = $this->buatJadwal('Sejarah');

        $this->assertTrue($jadwal->exists);
        $this->assertDatabaseHas('jadwal_pelajaran', ['mata_pelajaran' => 'Sejarah']);
    }

    public function test_migrasi_penyusul_mengisi_mapel_yang_terlewat(): void
    {
        DB::table('jadwal_pelajaran')->insert([
            'hari' => Hari::Rabu->value, 'jam_mulai' => '09:00', 'jam_selesai' => '10:30',
            'mata_pelajaran' => 'Prakarya', 'kelas_id' => $this->kelas->id, 'guru_id' => $this->pegawai->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migrasi = require database_path('migrations/2025_01_01_000042_sinkron_mapels_dari_jadwal.php');
        $migrasi->up();
        $migrasi->up(); // dijalankan ulang tetap aman

        $this->assertSame(1, Mapel::where('nama', 'Prakarya')->count());
    }
}
