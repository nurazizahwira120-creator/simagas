<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Enums\StatusKbm;
use App\Jobs\SendWhatsAppNotification;
use App\Livewire\Guru\GuruInval;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\PengajuanIzinGuru;
use App\Models\PenugasanInval;
use App\Models\Siswa;
use App\Models\User;
use App\Notifications\DitunjukJadiInval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * GURU INVAL — Kepsek/Admin menunjuk pengganti; hanya inval yang ditunjuk
 * (plus Kepsek/Admin) yang bisa mengisi absensi kelasnya.
 *
 * Kamis, 24 September 2026 pukul 09.00:
 *   - Citra (izin ITT disetujui)  : 08.30–10.00 XI RPL 1 + 10.15–11.00 X TKJ 2
 *   - Eka   (sakit, absensi harian): 10.15–11.45 XI RPL 1 -> belum waktunya
 *   - Fajar (izin MENUNGGU)        : 07.00–08.10 X TKJ 2  -> tidak tampil
 *   - Budi  (hadir)                : 07.00–08.10 XI RPL 1 -> tidak tampil
 *   - Dedi  (hadir) mengajar sendiri 09.00–09.45 X TKJ 2  -> bentrok dg jam Citra
 *   - Gita  (staf, tanpa jadwal)   -> calon inval
 */
class GuruInvalTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24';

    /** @var array<string, array{akun: User, pegawai: Pegawai}> */
    private array $guru = [];

    /** @var array<string, JadwalPelajaran> */
    private array $jadwal = [];

    /** @var array<string, Siswa> */
    private array $siswa = [];

    private User $kepsek;

    private User $gita;

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

        $this->gita = User::factory()->staff()->create(['name' => 'Gita Staf']);
        Pegawai::factory()->create(['user_id' => $this->gita->id, 'nama' => 'Gita Staf']);
        $this->kepsek = User::factory()->kepsek()->create(['name' => 'Bu Kepsek']);

        $buat = fn (string $g, Kelas $kelas, string $mulai, string $selesai, string $mapel) => JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
            'mata_pelajaran' => $mapel, 'kelas_id' => $kelas->id, 'guru_id' => $this->guru[$g]['pegawai']->id,
        ]);

        $this->jadwal['budi'] = $buat('budi', $rpl, '07:00', '08:10', 'Basis Data');
        $this->jadwal['citra'] = $buat('citra', $rpl, '08:30', '10:00', 'Bahasa Inggris');
        $this->jadwal['citra2'] = $buat('citra', $tkj, '10:15', '11:00', 'Bahasa Inggris');
        $this->jadwal['eka'] = $buat('eka', $rpl, '10:15', '11:45', 'Pemrograman Web');
        $this->jadwal['fajar'] = $buat('fajar', $tkj, '07:00', '08:10', 'PPKn');
        $this->jadwal['dedi'] = $buat('dedi', $tkj, '09:00', '09:45', 'Matematika');

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

    private function izin(string $g, StatusApproval $status, string $tanggal = self::TANGGAL): PengajuanIzinGuru
    {
        return PengajuanIzinGuru::create([
            'guru_id' => $this->guru[$g]['akun']->id,
            'tanggal_mulai' => $tanggal, 'tanggal_selesai' => $tanggal,
            'jenis_izin' => JenisIzinGuru::Itt, 'alasan' => 'Keperluan keluarga',
            'status_approval' => $status,
        ]);
    }

    /** Kepsek menunjuk $inval untuk satu jadwal lewat komponen. */
    private function tunjuk(string $jadwal, User $inval, ?User $oleh = null)
    {
        return Livewire::actingAs($oleh ?? $this->kepsek)->test(GuruInval::class)
            ->set('pilihan.' . $this->jadwal[$jadwal]->id, $inval->id)
            ->call('tunjuk', $this->jadwal[$jadwal]->id);
    }

    // ===================== PENUNJUK =====================

    public function test_penunjuk_melihat_semua_jam_guru_berhalangan(): void
    {
        $daftar = Livewire::actingAs($this->kepsek)->test(GuruInval::class)->instance()->daftarKelas;

        $this->assertEqualsCanonicalizing(
            [$this->jadwal['citra']->id, $this->jadwal['citra2']->id, $this->jadwal['eka']->id],
            $daftar->keys()->all(),
        );
        $this->assertSame('GURU IZIN', $daftar[$this->jadwal['citra']->id]['alasan']['label']);
        $this->assertSame('GURU SAKIT', $daftar[$this->jadwal['eka']->id]['alasan']['label']);
    }

    public function test_menunjuk_per_jam_menyimpan_dan_mengabari_inval(): void
    {
        $this->tunjuk('citra', $this->gita)->assertSet('notif.tipe', 'ok');

        $p = PenugasanInval::sole();
        $this->assertSame($this->jadwal['citra']->id, $p->jadwal_id);
        $this->assertSame($this->gita->id, $p->inval_user_id);
        $this->assertSame($this->kepsek->id, $p->ditunjuk_oleh);

        $notif = $this->gita->notifications()->sole();
        $this->assertSame(DitunjukJadiInval::class, $notif->type);
        $this->assertSame('.guru-inval', $notif->data['rute']);
        $this->assertStringContainsString('Bahasa Inggris (XI RPL 1)', $notif->data['pesan']);
        $this->assertStringContainsString('menggantikan Citra', $notif->data['pesan']);
    }

    public function test_menunjuk_ulang_mengganti_orang_bukan_menambah(): void
    {
        $this->tunjuk('citra', $this->gita);
        $this->tunjuk('citra', $this->guru['budi']['akun']);

        $this->assertSame(1, PenugasanInval::count());
        $this->assertSame($this->guru['budi']['akun']->id, PenugasanInval::sole()->inval_user_id);
    }

    public function test_menunjuk_ulang_orang_yang_sama_tidak_mengabari_lagi(): void
    {
        $this->tunjuk('citra', $this->gita);
        $this->tunjuk('citra', $this->gita)->assertSet('notif.tipe', 'ok');

        $this->assertSame(1, $this->gita->notifications()->count());

        // "Semua jam" sesudahnya hanya mengabarkan jam yang BARU (citra2).
        Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->set('pilihanSemua.' . $this->guru['citra']['pegawai']->id, $this->gita->id)
            ->call('tunjukSemua', $this->guru['citra']['pegawai']->id);

        $this->assertSame(2, $this->gita->notifications()->count());
        $pesan = $this->gita->notifications()->get()->pluck('data.pesan');
        $this->assertSame(1, $pesan->filter(fn ($p) => str_contains($p, 'X TKJ 2'))->count());
    }

    public function test_satu_inval_untuk_semua_jam_satu_notifikasi(): void
    {
        Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->set('pilihanSemua.' . $this->guru['citra']['pegawai']->id, $this->gita->id)
            ->call('tunjukSemua', $this->guru['citra']['pegawai']->id)
            ->assertSet('notif.tipe', 'ok');

        $this->assertEqualsCanonicalizing(
            [$this->jadwal['citra']->id, $this->jadwal['citra2']->id],
            PenugasanInval::pluck('jadwal_id')->all(),
        );
        $this->assertSame(1, $this->gita->notifications()->count());
        $this->assertStringContainsString('2 jam pelajaran', $this->gita->notifications()->first()->data['pesan']);
    }

    public function test_tidak_bisa_menunjuk_guru_yang_sedang_mengajar_di_jam_itu(): void
    {
        $this->tunjuk('citra', $this->guru['dedi']['akun'])
            ->assertSet('notif.tipe', 'error')
            ->assertSee('Bentrok: mengajar X TKJ 2');

        $this->assertSame(0, PenugasanInval::count());
    }

    public function test_tidak_bisa_menunjuk_inval_yang_sudah_memegang_jam_yang_sama(): void
    {
        $this->tunjuk('citra', $this->gita);

        // Fajar ternyata izin juga, jamnya digeser ke 09.00 — tumpang tindih dengan tugas Gita.
        PengajuanIzinGuru::query()->update(['status_approval' => StatusApproval::Disetujui->value]);
        $this->jadwal['fajar']->update(['jam_mulai' => '09:00', 'jam_selesai' => '09:45']);

        $this->tunjuk('fajar', $this->gita)->assertSet('notif.tipe', 'error')->assertSee('Bentrok: inval');
        $this->assertSame(1, PenugasanInval::count());
    }

    public function test_calon_hanya_guru_dan_staf_aktif_yang_tidak_berhalangan(): void
    {
        User::factory()->waliMurid()->create(['name' => 'Ortu']);
        User::factory()->guru()->create(['name' => 'Belum Disetujui', 'status' => \App\Enums\StatusAkun::Pending]);

        $nama = Livewire::actingAs($this->kepsek)->test(GuruInval::class)->instance()->calon
            ->map(fn ($c) => $c['user']->name)->values()->all();

        $this->assertContains('Gita Staf', $nama);
        $this->assertContains('Budi', $nama);
        $this->assertNotContains('Citra', $nama);      // sedang izin
        $this->assertNotContains('Eka', $nama);        // sedang sakit
        $this->assertNotContains('Ortu', $nama);
        $this->assertNotContains('Bu Kepsek', $nama);
        $this->assertNotContains('Belum Disetujui', $nama);
    }

    public function test_penunjukan_hanya_oleh_kepsek_dan_admin(): void
    {
        Livewire::actingAs($this->guru['budi']['akun'])->test(GuruInval::class)
            ->set('pilihan.' . $this->jadwal['citra']->id, $this->guru['budi']['akun']->id)
            ->call('tunjuk', $this->jadwal['citra']->id)
            ->assertForbidden();

        $this->assertSame(0, PenugasanInval::count());

        $this->tunjuk('citra', $this->gita, User::factory()->superAdmin()->create())->assertSet('notif.tipe', 'ok');
        $this->assertSame(1, PenugasanInval::count());
    }

    // ===================== INVAL MENGISI =====================

    public function test_inval_hanya_melihat_tugasnya_dan_bisa_mengisi(): void
    {
        $this->tunjuk('citra', $this->gita);

        $komponen = Livewire::actingAs($this->gita)->test(GuruInval::class);
        $this->assertSame([$this->jadwal['citra']->id], $komponen->instance()->daftarKelas->keys()->all());

        $komponen->call('pilih', $this->jadwal['citra']->id)
            ->assertSee('Daftar Hadir Siswa')
            ->set('status.' . $this->siswa['bela']->id, StatusKbm::Alpa->value)
            ->set('status.' . $this->siswa['dodi']->id, StatusKbm::Alpa->value)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok');

        $hasil = AbsensiKbmSiswa::where('jadwal_id', $this->jadwal['citra']->id)->get()->keyBy('siswa_id');
        $this->assertCount(3, $hasil);
        $this->assertTrue($hasil->every(fn ($b) => $b->diisi_oleh === $this->gita->id));
        // Aturan sama dengan jurnal guru: alpa + masuk gerbang = bolos.
        $this->assertSame(StatusKbm::Bolos, $hasil[$this->siswa['dodi']->id]->status);
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 2);
    }

    public function test_guru_dan_piket_yang_tidak_ditunjuk_tidak_bisa_mengisi(): void
    {
        $this->tunjuk('citra', $this->gita);

        foreach ([$this->guru['budi']['akun'], User::factory()->guruPiket()->create()] as $orang) {
            $k = Livewire::actingAs($orang)->test(GuruInval::class);
            $this->assertTrue($k->instance()->daftarKelas->isEmpty());

            $k->call('pilih', $this->jadwal['citra']->id)->assertSet('notif.tipe', 'error')
                // Memalsukan properti publik dari browser:
                ->set('jadwalId', $this->jadwal['citra']->id)
                ->call('simpan')->assertSet('notif.tipe', 'error');
        }

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_kepsek_bisa_mengisi_walau_belum_ada_inval(): void
    {
        Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->call('pilih', $this->jadwal['citra']->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok');

        $this->assertSame(3, AbsensiKbmSiswa::where('diisi_oleh', $this->kepsek->id)->count());
    }

    public function test_belum_waktunya_tidak_bisa_diisi_walau_dipaksa(): void
    {
        $this->tunjuk('eka', $this->gita);

        Livewire::actingAs($this->gita)->test(GuruInval::class)
            ->call('pilih', $this->jadwal['eka']->id)
            ->assertSet('notif.tipe', 'warn')
            ->set('jadwalId', $this->jadwal['eka']->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_penunjukan_dibatalkan_saat_form_terbuka_tidak_bisa_disimpan(): void
    {
        $this->tunjuk('citra', $this->gita);
        $form = Livewire::actingAs($this->gita)->test(GuruInval::class)->call('pilih', $this->jadwal['citra']->id);

        Livewire::actingAs($this->kepsek)->test(GuruInval::class)->call('batalkan', $this->jadwal['citra']->id)->assertSet('notif.tipe', 'ok');

        // Kembali sebagai Gita (Livewire::actingAs di atas mengganti akun aktif).
        $this->actingAs($this->gita);
        $form->call('simpan')->assertSet('notif.tipe', 'error');
        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_izin_dicabut_tugas_inval_tidak_berlaku(): void
    {
        $this->tunjuk('citra', $this->gita);
        PengajuanIzinGuru::query()->update(['status_approval' => StatusApproval::Ditolak->value]);

        $k = Livewire::actingAs($this->gita)->test(GuruInval::class);
        $this->assertTrue($k->instance()->daftarKelas->isEmpty());

        $k->set('jadwalId', $this->jadwal['citra']->id)->call('simpan')->assertSet('notif.tipe', 'error');
        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    // ===================== TANGGAL MENDATANG =====================

    public function test_penunjukan_bisa_direncanakan_untuk_tanggal_mendatang(): void
    {
        $kamisDepan = '2026-10-01';
        $this->izin('citra', StatusApproval::Disetujui, $kamisDepan);

        $k = Livewire::actingAs($this->kepsek)->test(GuruInval::class)->set('tanggal', $kamisDepan);
        $this->assertTrue($k->instance()->daftarKelas->has($this->jadwal['citra']->id));

        $k->set('pilihan.' . $this->jadwal['citra']->id, $this->gita->id)
            ->call('tunjuk', $this->jadwal['citra']->id)->assertSet('notif.tipe', 'ok')
            // Absensinya belum bisa diisi sebelum harinya tiba.
            ->call('pilih', $this->jadwal['citra']->id)->assertSet('notif.tipe', 'warn');

        $this->assertSame($kamisDepan, PenugasanInval::sole()->tanggal->toDateString());

        // Gita melihatnya di "Tugas inval mendatang".
        Livewire::actingAs($this->gita)->test(GuruInval::class)
            ->assertSee('Tugas inval mendatang')
            ->assertSee('01 Okt');
    }

    public function test_tanggal_di_luar_rentang_dijepit_ke_hari_ini(): void
    {
        $k = Livewire::actingAs($this->kepsek)->test(GuruInval::class);

        foreach (['2026-09-20', '2026-12-31', 'bukan-tanggal'] as $t) {
            $k->set('tanggal', $t);
            $this->assertSame(self::TANGGAL, $k->instance()->tanggalDipakai->toDateString());
        }
    }

    // ===================== HALAMAN, MENU, & FITUR LAMA =====================

    public function test_halaman_dan_menu_untuk_setiap_peran(): void
    {
        $this->actingAs($this->kepsek)->get('/kepsek/guru-inval')->assertOk()->assertSee('Guru Inval')->assertSee('Tunjuk semua jam');
        $this->actingAs(User::factory()->superAdmin()->create())->get('/super-admin/guru-inval')->assertOk();

        $this->tunjuk('citra', $this->gita);
        $this->actingAs($this->gita)->get('/staff/guru-inval')->assertOk()->assertSee('Tugas Inval')->assertSee('XI RPL 1');
        $this->actingAs(User::factory()->adminTu()->create())->get('/admin-tu/guru-inval')->assertOk();
        $this->actingAs(User::factory()->guruPiket()->create())->get('/piket/guru-inval')->assertOk();

        $this->actingAs(User::factory()->waliMurid()->create())->get('/wali-murid/guru-inval')->assertNotFound();
    }

    public function test_alamat_lama_kelas_pengganti_dialihkan(): void
    {
        $this->actingAs($this->kepsek)->get('/kepsek/kelas-pengganti')->assertRedirect('/kepsek/guru-inval');
    }

    public function test_hari_libur_tidak_menampilkan_kelas(): void
    {
        Pengaturan::simpan('hari_kbm', 'senin,selasa,rabu,sabtu,minggu'); // Kamis libur

        Livewire::actingAs($this->kepsek)->test(GuruInval::class)->assertSee('bukan hari KBM');
    }

    public function test_jurnal_guru_biasa_tetap_hanya_untuk_jadwalnya_sendiri(): void
    {
        Livewire::actingAs($this->guru['budi']['akun'])->test(JurnalAbsenKelas::class)
            ->call('simpan')
            ->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }

    public function test_live_monitoring_menautkan_ke_guru_inval(): void
    {
        $this->actingAs($this->kepsek)
            ->get('/kepsek/live-monitoring')
            ->assertOk()
            ->assertSee('absensi siswa belum diisi guru inval')
            ->assertSee('/kepsek/guru-inval', false);
    }
}
