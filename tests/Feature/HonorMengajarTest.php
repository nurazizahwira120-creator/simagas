<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Jobs\SendWhatsAppNotification;
use App\Livewire\Guru\GuruInval;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Livewire\Honor\HonorGuru;
use App\Livewire\Honor\RincianPendapatan;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\HonorMengajar;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengajuanIzinGuru;
use App\Models\Siswa;
use App\Models\TarifHonorGuru;
use App\Models\User;
use App\Services\AturanHonor;
use App\Services\PencatatHonor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HONOR MENGAJAR (uji coba).
 *
 * Kamis, 24 September 2026 — 1 JP = 35 menit (bawaan):
 *   - Budi  : 07.00–08.10 XI RPL 1 = 70 menit = 2 JP, hadir & scan QR
 *   - Citra : 08.30–10.00 XI RPL 1 = 90 menit = 3 JP, izin ITT disetujui
 *   - Gita  : staf, calon guru inval
 */
class HonorMengajarTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24';

    private User $budi;

    private User $citra;

    private User $gita;

    private User $kepsek;

    private JadwalPelajaran $jadwalBudi;

    private JadwalPelajaran $jadwalCitra;

    private AbsensiMengajar $sesiBudi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pada('08:05');
        Bus::fake([SendWhatsAppNotification::class]);

        $kelas = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        Siswa::factory()->create(['nama' => 'Andi', 'kelas_id' => $kelas->id]);

        $this->budi = User::factory()->guru()->create(['name' => 'Budi']);
        $pBudi = Pegawai::factory()->create(['user_id' => $this->budi->id, 'nama' => 'Budi']);
        $this->citra = User::factory()->guru()->create(['name' => 'Citra']);
        $pCitra = Pegawai::factory()->create(['user_id' => $this->citra->id, 'nama' => 'Citra']);
        $this->gita = User::factory()->staff()->create(['name' => 'Gita Staf']);
        Pegawai::factory()->create(['user_id' => $this->gita->id, 'nama' => 'Gita Staf']);
        $this->kepsek = User::factory()->kepsek()->create(['name' => 'Bu Kepsek']);

        $buat = fn (Pegawai $p, string $mulai, string $selesai, string $mapel) => JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
            'mata_pelajaran' => $mapel, 'kelas_id' => $kelas->id, 'guru_id' => $p->id,
        ]);
        $this->jadwalBudi = $buat($pBudi, '07:00', '08:10', 'Basis Data');
        $this->jadwalCitra = $buat($pCitra, '08:30', '10:00', 'Bahasa Inggris');

        AbsensiPegawai::create(['pegawai_id' => $pBudi->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:50:00']);
        $this->sesiBudi = AbsensiMengajar::create([
            'user_id' => $this->budi->id, 'kode_kelas' => 'Ruang XI-RPL 1',
            'waktu_mulai' => self::TANGGAL . ' 07:02:00', 'foto_bukti' => 'bukti-mengajar/a.jpg',
        ]);

        PengajuanIzinGuru::create([
            'guru_id' => $this->citra->id, 'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => self::TANGGAL,
            'jenis_izin' => JenisIzinGuru::Itt, 'alasan' => 'Keperluan keluarga', 'status_approval' => StatusApproval::Disetujui,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pada(string $jam): void
    {
        Carbon::setTestNow(Carbon::parse(self::TANGGAL . ' ' . $jam . ':00'));
    }

    private function nyalakan(int $tarif = 25_000, int $persenInval = 70): void
    {
        app(AturanHonor::class)->simpanUmum(true, $tarif, $persenInval);
    }

    private function akhiriSesiBudi()
    {
        return Livewire::actingAs($this->budi)->test(JurnalAbsenKelas::class)->call('akhiriSesi');
    }

    /** Kepsek menunjuk $inval untuk jadwal Citra, lalu $pengisi menyimpan absensinya pukul 09.00. */
    private function invalMengisi(?User $inval, ?User $pengisi = null)
    {
        $this->pada('09:00');

        if ($inval) {
            Livewire::actingAs($this->kepsek)->test(GuruInval::class)
                ->set('pilihan.' . $this->jadwalCitra->id, $inval->id)
                ->call('tunjuk', $this->jadwalCitra->id)
                ->assertSet('notif.tipe', 'ok');
        }

        return Livewire::actingAs($pengisi ?? $inval)->test(GuruInval::class)
            ->call('pilih', $this->jadwalCitra->id)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok');
    }

    // ===================== MENGAJAR =====================

    public function test_fitur_mati_akhiri_sesi_tetap_jalan_tanpa_honor(): void
    {
        $this->akhiriSesiBudi()->assertSet('notif.tipe', 'ok')->assertDontSee('Rincian Pendapatan');

        $this->assertNotNull($this->sesiBudi->fresh()->waktu_selesai);
        $this->assertSame(0, HonorMengajar::count());
    }

    public function test_akhiri_sesi_menambah_honor_jp_kali_tarif(): void
    {
        $this->nyalakan(25_000);

        $this->akhiriSesiBudi()
            ->assertSet('notif.tipe', 'ok')
            ->assertSee('Rp 50.000 (2 JP) masuk ke Rincian Pendapatan');

        $h = HonorMengajar::sole();
        $this->assertSame($this->budi->id, $h->user_id);
        $this->assertSame(HonorMengajar::PERAN_MENGAJAR, $h->peran);
        $this->assertSame([2, 25_000, 100, 50_000], [$h->jp, $h->tarif_per_jp, $h->persen, $h->nominal]);
        $this->assertSame(self::TANGGAL, $h->tanggal->toDateString());
        $this->assertSame($this->sesiBudi->id, $h->absensi_mengajar_id);
        $this->assertStringContainsString('Basis Data · XI RPL 1', $h->rincian);
    }

    public function test_tarif_khusus_mengalahkan_tarif_umum(): void
    {
        $this->nyalakan(25_000);
        app(AturanHonor::class)->simpanTarifKhusus($this->budi->id, 40_000);

        $this->akhiriSesiBudi();

        $this->assertSame(80_000, HonorMengajar::sole()->nominal);
    }

    public function test_mengubah_tarif_tidak_mengubah_honor_yang_sudah_tercatat(): void
    {
        $this->nyalakan(25_000);
        $this->akhiriSesiBudi();

        $this->nyalakan(100_000);

        $this->assertSame(50_000, HonorMengajar::sole()->nominal);
        $rincian = app(PencatatHonor::class)->rincian($this->budi->id, now());
        $this->assertSame(50_000, $rincian['total']);
    }

    public function test_dicatat_dua_kali_tidak_menjadi_ganda(): void
    {
        $this->nyalakan(25_000);
        $this->akhiriSesiBudi();
        $this->akhiriSesiBudi()->assertSet('notif.tipe', 'warn'); // "Sudah diakhiri"

        // Pemanggilan ulang langsung ke pencatat pun hanya menimpa baris yang sama.
        app(PencatatHonor::class)->catatMengajar($this->sesiBudi->fresh(), $this->jadwalBudi);

        $this->assertSame(1, HonorMengajar::count());
    }

    public function test_gagal_mencatat_honor_tidak_menggagalkan_akhiri_sesi(): void
    {
        $this->nyalakan();
        $this->mock(PencatatHonor::class, fn ($m) => $m->shouldReceive('catatMengajar')->andThrow(new \RuntimeException('DB putus')));

        $this->akhiriSesiBudi()->assertSet('notif.tipe', 'ok');

        $this->assertNotNull($this->sesiBudi->fresh()->waktu_selesai);
    }

    // ===================== INVAL =====================

    public function test_honor_inval_dibagi_sesuai_persentase(): void
    {
        $this->nyalakan(25_000, 70);

        $this->invalMengisi($this->gita)->assertSee('Honor inval Rp 52.500 masuk ke Rincian Pendapatan Anda');

        // 3 JP × Rp25.000 = Rp75.000 -> inval 70% = 52.500, guru asli 30% = 22.500.
        $inval = HonorMengajar::where('peran', HonorMengajar::PERAN_INVAL)->sole();
        $asli = HonorMengajar::where('peran', HonorMengajar::PERAN_GURU_ASLI)->sole();

        $this->assertSame([$this->gita->id, 3, 70, 52_500], [$inval->user_id, $inval->jp, $inval->persen, $inval->nominal]);
        $this->assertSame([$this->citra->id, 30, 22_500], [$asli->user_id, $asli->persen, $asli->nominal]);
        $this->assertStringContainsString('menggantikan Citra', $inval->rincian);
        $this->assertStringContainsString('diinval Gita Staf', $asli->rincian);
    }

    public function test_pembulatan_tidak_menghilangkan_rupiah(): void
    {
        $this->nyalakan(33_335, 50);

        $this->invalMengisi($this->gita);

        // 3 × 33.335 = 100.005 -> separuhnya 50.002,5. Kalau kedua bagian
        // dibulatkan sendiri-sendiri jumlahnya 100.006 (lebih Rp1). Inval
        // dibulatkan 50.003, guru asli mendapat SISANYA 50.002.
        $this->assertSame(100_005, (int) HonorMengajar::sum('nominal'));
        $this->assertSame(50_002, HonorMengajar::where('peran', HonorMengajar::PERAN_GURU_ASLI)->value('nominal'));
    }

    public function test_honor_inval_memakai_tarif_guru_asli(): void
    {
        $this->nyalakan(25_000, 50);
        app(AturanHonor::class)->simpanTarifKhusus($this->citra->id, 40_000);
        app(AturanHonor::class)->simpanTarifKhusus($this->gita->id, 10_000);

        $this->invalMengisi($this->gita);

        // Yang dibagi honor jam Citra: 3 × 40.000 = 120.000.
        $this->assertSame(60_000, HonorMengajar::where('peran', HonorMengajar::PERAN_INVAL)->value('nominal'));
        $this->assertSame(60_000, HonorMengajar::where('peran', HonorMengajar::PERAN_GURU_ASLI)->value('nominal'));
    }

    public function test_persen_seratus_guru_asli_tidak_mendapat_bagian(): void
    {
        $this->nyalakan(25_000, 70);
        $this->invalMengisi($this->gita);
        $this->assertSame(2, HonorMengajar::count());

        // Diubah ke 100%, lalu inval menyimpan ulang absensinya.
        $this->nyalakan(25_000, 100);
        Livewire::actingAs($this->gita)->test(GuruInval::class)
            ->call('pilih', $this->jadwalCitra->id)->call('simpan')->assertSet('notif.tipe', 'ok');

        $this->assertSame(0, HonorMengajar::where('peran', HonorMengajar::PERAN_GURU_ASLI)->count());
        $this->assertSame(75_000, HonorMengajar::where('peran', HonorMengajar::PERAN_INVAL)->sole()->nominal);
    }

    public function test_menyimpan_ulang_absensi_inval_tidak_menggandakan(): void
    {
        $this->nyalakan();
        $this->invalMengisi($this->gita);
        $this->invalMengisi(null, $this->gita);

        $this->assertSame(2, HonorMengajar::count());
    }

    public function test_membatalkan_penunjukan_ikut_membatalkan_honor_inval(): void
    {
        $this->nyalakan();
        $this->invalMengisi($this->gita);
        $this->assertSame(2, HonorMengajar::count());

        Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->call('batalkan', $this->jadwalCitra->id)
            ->assertSet('notif.tipe', 'ok');

        $this->assertSame(0, HonorMengajar::count());
    }

    public function test_kepsek_yang_mengisi_tanpa_inval_tercatat_sebagai_inval(): void
    {
        $this->nyalakan();

        $this->invalMengisi(null, $this->kepsek);

        $this->assertSame($this->kepsek->id, HonorMengajar::where('peran', HonorMengajar::PERAN_INVAL)->value('user_id'));
    }

    // ===================== HALAMAN GURU =====================

    public function test_guru_hanya_melihat_honornya_sendiri(): void
    {
        $this->nyalakan(25_000, 70);
        $this->akhiriSesiBudi();
        $this->invalMengisi($this->gita);

        $this->actingAs($this->budi)->get('/guru/rincian-pendapatan')
            ->assertOk()
            ->assertSee('Rp 50.000')
            ->assertSee('Basis Data · XI RPL 1')
            ->assertDontSee('Bahasa Inggris');

        $this->actingAs($this->gita)->get('/staff/rincian-pendapatan')
            ->assertOk()
            ->assertSee('Rp 52.500')
            ->assertDontSee('Basis Data');
    }

    public function test_menu_rincian_pendapatan_selalu_tampil_untuk_guru_dan_staf(): void
    {
        // Fitur masih mati: menu tetap ada, halamannya menjelaskan keadaannya.
        $this->actingAs($this->budi)->get('/guru/dashboard')->assertOk()->assertSee('Rincian Pendapatan');
        $this->actingAs($this->budi)->get('/guru/rincian-pendapatan')->assertOk()
            ->assertSee('Pencatatan honor sedang tidak aktif')
            ->assertSee('Rp 0');
        $this->actingAs($this->gita)->get('/staff/dashboard')->assertOk()->assertSee('Rincian Pendapatan');

        // Kepsek memakai Honor Guru; wali murid tidak punya menu honor apa pun.
        $this->actingAs($this->kepsek)->get('/kepsek/dashboard')->assertOk()
            ->assertSee('Honor Guru')->assertDontSee('Rincian Pendapatan');
        $this->actingAs(User::factory()->waliMurid()->create())->get('/wali-murid/dashboard')->assertOk()
            ->assertDontSee('Rincian Pendapatan')->assertDontSee('Honor Guru');

        $this->nyalakan();
        $this->actingAs($this->budi)->get('/guru/rincian-pendapatan')->assertOk()
            ->assertDontSee('Pencatatan honor sedang tidak aktif');
    }

    public function test_bulan_palsu_kembali_ke_bulan_ini(): void
    {
        $this->nyalakan();
        $this->akhiriSesiBudi();

        Livewire::actingAs($this->budi)->test(RincianPendapatan::class)
            ->set('bulan', '2099-01')
            ->assertSee('September 2026')
            ->assertSee('Rp 50.000');
    }

    // ===================== HALAMAN KEPSEK / ADMIN =====================

    public function test_hanya_kepsek_dan_super_admin_yang_bisa_mengatur(): void
    {
        $this->actingAs($this->budi)->get('/guru/honor-guru')->assertNotFound();
        Livewire::actingAs($this->budi)->test(HonorGuru::class)->assertForbidden();

        $this->actingAs($this->kepsek)->get('/kepsek/honor-guru')->assertOk();
        $this->actingAs(User::factory()->superAdmin()->create())->get('/super-admin/honor-guru')->assertOk();
    }

    public function test_pengaturan_divalidasi_dan_disimpan(): void
    {
        Livewire::actingAs($this->kepsek)->test(HonorGuru::class)
            ->set('aktif', true)
            ->set('tarifUmum', '-5')
            ->set('persenInval', '150')
            ->call('simpanPengaturan')
            ->assertHasErrors(['tarifUmum', 'persenInval']);

        $this->assertFalse(app(AturanHonor::class)->aktif());

        Livewire::actingAs($this->kepsek)->test(HonorGuru::class)
            ->set('aktif', true)
            ->set('tarifUmum', '30000')
            ->set('persenInval', '60')
            ->call('simpanPengaturan')
            ->assertHasNoErrors()
            ->assertSet('notif.tipe', 'ok');

        $this->assertSame(['aktif' => true, 'tarif' => 30_000, 'persen_inval' => 60, 'tarif_ekskul' => null], app(AturanHonor::class)->umum());
    }

    public function test_tarif_khusus_disimpan_dikosongkan_dan_id_asing_diabaikan(): void
    {
        $waliMurid = User::factory()->waliMurid()->create();

        Livewire::actingAs($this->kepsek)->test(HonorGuru::class)
            ->set('tarifKhusus.' . $this->budi->id, '40000')
            ->set('tarifKhusus.' . $waliMurid->id, '99999')
            ->call('simpanTarifKhusus')
            ->assertHasNoErrors();

        $this->assertSame([$this->budi->id => 40_000], app(AturanHonor::class)->tarifKhusus());

        Livewire::actingAs($this->kepsek)->test(HonorGuru::class)
            ->assertSet('tarifKhusus.' . $this->budi->id, '40000')
            ->set('tarifKhusus.' . $this->budi->id, '')
            ->call('simpanTarifKhusus');

        $this->assertSame(0, TarifHonorGuru::count());
    }

    public function test_rekap_bulan_menjumlahkan_per_orang_dan_menampilkan_guru_tanpa_honor(): void
    {
        $this->nyalakan(25_000, 70);
        $this->akhiriSesiBudi();
        $this->invalMengisi($this->gita);

        $rekap = app(PencatatHonor::class)->rekapBulan(now());
        $baris = $rekap['baris']->keyBy(fn ($b) => $b['user']->id);

        $this->assertSame(50_000 + 52_500 + 22_500, $rekap['total']);
        $this->assertSame([2, 0, 50_000], [$baris[$this->budi->id]['jp_mengajar'], $baris[$this->budi->id]['jp_inval'], $baris[$this->budi->id]['total']]);
        $this->assertSame([3, 52_500], [$baris[$this->gita->id]['jp_inval'], $baris[$this->gita->id]['total']]);
        $this->assertSame(22_500, $baris[$this->citra->id]['honor_guru_asli']);

        // Guru lain tanpa honor tetap tampil dengan nol.
        $guruBaru = User::factory()->guru()->create(['name' => 'Dedi']);
        $baris = app(PencatatHonor::class)->rekapBulan(now())['baris']->keyBy(fn ($b) => $b['user']->id);
        $this->assertSame(0, $baris[$guruBaru->id]['total']);

        // Bulan lain kosong.
        $this->assertSame(0, app(PencatatHonor::class)->rekapBulan(now()->subMonth())['total']);

        Livewire::actingAs($this->kepsek)->test(HonorGuru::class)
            ->assertSee('Rp 125.000')
            ->call('lihat', $this->gita->id)
            ->assertSee('menggantikan Citra')
            ->assertSee('Rp 52.500');
    }
}
