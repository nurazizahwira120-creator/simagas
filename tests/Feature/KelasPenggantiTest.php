<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Enums\StatusKbm;
use App\Jobs\SendWhatsAppNotification;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Livewire\Guru\KelasPengganti;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\PengajuanIzinGuru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kelas Pengganti — absensi KBM siswa TIDAK terkunci saat gurunya berhalangan.
 *
 * Waktu dikunci ke Kamis, 24 September 2026 pukul 09.00:
 *   - Citra (izin ITT disetujui)  : 08.30–10.00 XI RPL 1 -> terbuka
 *   - Eka   (sakit, absensi harian): 10.15–11.45 XI RPL 1 -> belum waktunya (buka 10.00)
 *   - Fajar (izin MENUNGGU)        : 07.00–08.10 X TKJ 2  -> tidak tampil
 *   - Budi  (hadir)                : 07.00–08.10 XI RPL 1 -> tidak tampil
 */
class KelasPenggantiTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24';

    /** @var array<string, array{akun: User, pegawai: Pegawai}> */
    private array $guru = [];

    /** @var array<string, JadwalPelajaran> */
    private array $jadwal = [];

    /** @var array<string, Siswa> */
    private array $siswa = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TANGGAL . ' 09:00:00'));
        Bus::fake([SendWhatsAppNotification::class]);

        $rpl = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        $tkj = Kelas::factory()->create(['nama_kelas' => 'X TKJ 2']);

        foreach (['budi', 'citra', 'dedi', 'eka', 'fajar'] as $k) {
            $akun = User::factory()->guru()->create(['name' => ucfirst($k)]);
            $this->guru[$k] = ['akun' => $akun, 'pegawai' => Pegawai::factory()->create(['user_id' => $akun->id, 'nama' => ucfirst($k)])];
        }

        $buat = fn (string $g, Kelas $kelas, string $mulai, string $selesai, string $mapel) => JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
            'mata_pelajaran' => $mapel, 'kelas_id' => $kelas->id, 'guru_id' => $this->guru[$g]['pegawai']->id,
        ]);

        $this->jadwal['budi'] = $buat('budi', $rpl, '07:00', '08:10', 'Basis Data');
        $this->jadwal['citra'] = $buat('citra', $rpl, '08:30', '10:00', 'Bahasa Inggris');
        $this->jadwal['eka'] = $buat('eka', $rpl, '10:15', '11:45', 'Pemrograman Web');
        $this->jadwal['fajar'] = $buat('fajar', $tkj, '07:00', '08:10', 'PPKn');

        foreach (['andi' => 'Andi', 'bela' => 'Bela', 'dodi' => 'Dodi'] as $k => $nama) {
            $this->siswa[$k] = Siswa::factory()->create(['nama' => $nama, 'kelas_id' => $rpl->id]);
        }
        AbsensiSiswa::create(['siswa_id' => $this->siswa['dodi']->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:40:00']);

        $this->izin('citra', StatusApproval::Disetujui);
        $this->izin('fajar', StatusApproval::Pending);
        AbsensiPegawai::create(['pegawai_id' => $this->guru['eka']['pegawai']->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Sakit]);
        AbsensiPegawai::create(['pegawai_id' => $this->guru['budi']['pegawai']->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:50:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function izin(string $g, StatusApproval $status): PengajuanIzinGuru
    {
        return PengajuanIzinGuru::create([
            'guru_id' => $this->guru[$g]['akun']->id,
            'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => self::TANGGAL,
            'jenis_izin' => JenisIzinGuru::Itt, 'alasan' => 'Keperluan keluarga',
            'status_approval' => $status,
        ]);
    }

    public function test_hanya_kelas_yang_gurunya_berhalangan_yang_tampil(): void
    {
        $this->actingAs($this->guru['dedi']['akun']);

        $daftar = Livewire::test(KelasPengganti::class)->instance()->daftarKelas;

        $this->assertEqualsCanonicalizing(
            [$this->jadwal['citra']->id, $this->jadwal['eka']->id],
            $daftar->keys()->all(),
        );
        $this->assertSame('GURU IZIN', $daftar[$this->jadwal['citra']->id]['alasan']['label']);
        $this->assertSame('GURU SAKIT', $daftar[$this->jadwal['eka']->id]['alasan']['label']);
        $this->assertTrue($daftar[$this->jadwal['citra']->id]['terbuka']);
        $this->assertFalse($daftar[$this->jadwal['eka']->id]['terbuka']);
    }

    public function test_guru_pengganti_bisa_mengisi_dan_namanya_tercatat(): void
    {
        $dedi = $this->guru['dedi']['akun'];
        $this->actingAs($dedi);

        Livewire::test(KelasPengganti::class)
            ->call('pilih', $this->jadwal['citra']->id)
            ->assertSet('jadwalId', $this->jadwal['citra']->id)
            ->assertSee('Daftar Hadir Siswa')
            ->set('status.' . $this->siswa['bela']->id, StatusKbm::Alpa->value)
            ->set('status.' . $this->siswa['dodi']->id, StatusKbm::Alpa->value)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok')
            ->assertSet('jadwalId', null);

        $hasil = AbsensiKbmSiswa::where('jadwal_id', $this->jadwal['citra']->id)->get()->keyBy('siswa_id');

        $this->assertCount(3, $hasil);
        $this->assertTrue($hasil->every(fn ($b) => $b->diisi_oleh === $dedi->id));
        $this->assertSame(StatusKbm::Alpa, $hasil[$this->siswa['bela']->id]->status);

        // Aturan yang sama dengan jurnal guru: alpa + masuk gerbang = bolos,
        // dan wali murid diberi tahu.
        $this->assertSame(StatusKbm::Bolos, $hasil[$this->siswa['dodi']->id]->status);
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 2);
    }

    public function test_guru_piket_bisa_membuka_halaman_dan_mengisi(): void
    {
        $piket = User::factory()->guruPiket()->create();

        $this->actingAs($piket)->get('/piket/kelas-pengganti')->assertOk()->assertSee('XI RPL 1');

        Livewire::actingAs($piket)->test(KelasPengganti::class)
            ->call('pilih', $this->jadwal['citra']->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok');

        $this->assertSame(3, AbsensiKbmSiswa::where('diisi_oleh', $piket->id)->count());
    }

    public function test_super_admin_bisa_membuka_halaman_dan_mengisi(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->get('/super-admin/kelas-pengganti')->assertOk()->assertSee('XI RPL 1');

        Livewire::actingAs($admin)->test(KelasPengganti::class)
            ->call('pilih', $this->jadwal['citra']->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok');

        $this->assertSame(3, AbsensiKbmSiswa::where('diisi_oleh', $admin->id)->count());
    }

    public function test_peran_lain_ditolak(): void
    {
        Livewire::actingAs(User::factory()->waliMurid()->create())
            ->test(KelasPengganti::class)
            ->assertForbidden();

        Livewire::actingAs(User::factory()->staff()->create())
            ->test(KelasPengganti::class)
            ->assertForbidden();
    }

    public function test_kelas_yang_belum_waktunya_tidak_bisa_diisi_walau_dipaksa(): void
    {
        $this->actingAs($this->guru['dedi']['akun']);

        Livewire::test(KelasPengganti::class)
            ->call('pilih', $this->jadwal['eka']->id)
            ->assertSet('notif.tipe', 'warn')
            ->assertSet('jadwalId', null)
            // Memalsukan properti publik dari browser:
            ->set('jadwalId', $this->jadwal['eka']->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_kelas_yang_gurunya_hadir_tidak_bisa_diisi_pengganti(): void
    {
        $this->actingAs($this->guru['dedi']['akun']);

        Livewire::test(KelasPengganti::class)
            ->set('jadwalId', $this->jadwal['budi']->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_guru_yang_berhalangan_tidak_mengisi_kelasnya_sendiri(): void
    {
        $this->actingAs($this->guru['citra']['akun']);

        $daftar = Livewire::test(KelasPengganti::class)->instance()->daftarKelas;

        $this->assertFalse($daftar->has($this->jadwal['citra']->id));
        $this->assertTrue($daftar->has($this->jadwal['eka']->id));
    }

    /**
     * Izin dibatalkan (gurunya ternyata datang) di antara saat form dibuka
     * dan saat disimpan. Sejak itu kelasnya kembali milik gurunya.
     */
    public function test_status_berhalangan_diperiksa_ulang_saat_menyimpan(): void
    {
        $this->actingAs($this->guru['dedi']['akun']);

        $komponen = Livewire::test(KelasPengganti::class)->call('pilih', $this->jadwal['citra']->id);

        PengajuanIzinGuru::query()->update(['status_approval' => StatusApproval::Ditolak->value]);

        $komponen->call('simpan')->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_hari_libur_tidak_menampilkan_kelas(): void
    {
        Pengaturan::simpan('hari_kbm', 'senin,selasa,rabu,sabtu,minggu'); // Kamis libur

        $this->actingAs($this->guru['dedi']['akun']);

        Livewire::test(KelasPengganti::class)
            ->assertSee('bukan hari KBM')
            ->assertSet('jadwalId', null);
    }

    public function test_daftar_menunjukkan_siapa_yang_sudah_mengisi(): void
    {
        $this->actingAs($this->guru['dedi']['akun']);
        Livewire::test(KelasPengganti::class)->call('pilih', $this->jadwal['citra']->id)->call('simpan');

        $daftar = Livewire::actingAs(User::factory()->guruPiket()->create())
            ->test(KelasPengganti::class)
            ->assertSee('Sudah diisi 3 siswa')
            ->assertSee('Dedi')
            ->instance()->daftarKelas;

        $this->assertTrue($daftar[$this->jadwal['citra']->id]['terisi']);
    }

    /** Jurnal guru biasa TIDAK ikut terbuka untuk kelas guru lain. */
    public function test_jurnal_guru_biasa_tetap_hanya_untuk_jadwalnya_sendiri(): void
    {
        $this->actingAs($this->guru['dedi']['akun']);

        Livewire::test(JurnalAbsenKelas::class)
            ->call('simpan')
            ->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_live_monitoring_menandai_kelas_guru_berhalangan(): void
    {
        $kepsek = User::factory()->kepsek()->create();

        $this->actingAs($kepsek)
            ->get('/kepsek/live-monitoring')
            ->assertOk()
            ->assertSee('Guru berhalangan')
            ->assertSee('absensi siswa belum diisi pengganti')
            ->assertSee('/kepsek/kelas-pengganti', false);
    }
}
