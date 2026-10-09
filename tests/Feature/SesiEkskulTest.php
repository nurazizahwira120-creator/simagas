<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\JenisAgenda;
use App\Livewire\Ekskul\AbsensiEkskul;
use App\Livewire\Ekskul\KelolaEkskul;
use App\Livewire\Guru\AbsenMengajarQr;
use App\Models\AbsensiEkskul as AbsensiEkskulModel;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\AgendaAkademik;
use App\Models\HonorMengajar;
use App\Models\JadwalEkskul;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\SesiEkskul;
use App\Models\Siswa;
use App\Models\User;
use App\Services\AturanHonor;
use App\Services\PencatatHonor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * SESI EKSKUL — Mulai/Akhiri Sesi pembina seperti KBM + Live Monitoring.
 *
 * Sabtu, 26 September 2026. Pramuka Sabtu 13.30–15.30 (120 menit = 3 JP),
 * pembina Dedi (guru). Rohis Sabtu 13.30–15.00, pembina Eka.
 *   Scan QR dibuka 13.15 · pengingat 15.35 · batas Akhiri 15.45
 */
class SesiEkskulTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-26';

    private User $dedi;

    private User $eka;

    private User $admin;

    private JadwalEkskul $pramuka;

    private JadwalEkskul $rohis;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->pada('14:00');

        $this->dedi = User::factory()->guru()->create(['name' => 'Dedi']);
        $pDedi = Pegawai::factory()->create(['user_id' => $this->dedi->id, 'nama' => 'Dedi Pembina']);
        $this->eka = User::factory()->guru()->create(['name' => 'Eka']);
        $pEka = Pegawai::factory()->create(['user_id' => $this->eka->id, 'nama' => 'Eka']);
        $this->admin = User::factory()->superAdmin()->create();

        $this->pramuka = JadwalEkskul::create(['nama_ekskul' => 'Pramuka', 'hari' => 'Sabtu', 'jam_mulai' => '13:30', 'jam_selesai' => '15:30', 'pembina_id' => $pDedi->id]);
        $this->rohis = JadwalEkskul::create(['nama_ekskul' => 'Rohis', 'hari' => 'Sabtu', 'jam_mulai' => '13:30', 'jam_selesai' => '15:00', 'pembina_id' => $pEka->id]);

        $kelas = Kelas::factory()->create(['nama_kelas' => 'X RPL 1']);
        foreach (['Andi', 'Bela'] as $nama) {
            $this->pramuka->anggota()->attach(Siswa::factory()->create(['nama' => $nama, 'kelas_id' => $kelas->id])->id);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pada(string $jam, string $tanggal = self::TANGGAL): void
    {
        Carbon::setTestNow(Carbon::parse($tanggal . ' ' . $jam . ':00'));
    }

    private function halaman(?User $siapa = null)
    {
        return Livewire::actingAs($siapa ?? $this->dedi)->test(AbsensiEkskul::class, ['jadwal' => $this->pramuka->id]);
    }

    /** Alur lengkap sampai siap diakhiri: scan, absensi, foto. */
    private function siapAkhiri()
    {
        return $this->halaman()
            ->call('mulaiSesi', $this->pramuka->fresh()->kode_qr)
            ->call('simpan')
            ->set('fotoBukti', UploadedFile::fake()->image('bukti.jpg', 800, 600))
            ->call('unggahBukti')
            ->assertSet('notif.tipe', 'ok');
    }

    // ===================== KODE QR =====================

    public function test_ekskul_baru_otomatis_punya_kode_qr_unik(): void
    {
        $this->assertMatchesRegularExpression('/^EKSKUL-' . $this->pramuka->id . '-[A-Z0-9]{6}$/', $this->pramuka->fresh()->kode_qr);
        $this->assertNotSame($this->pramuka->fresh()->kode_qr, $this->rohis->fresh()->kode_qr);
    }

    public function test_stiker_qr_hanya_untuk_admin_dan_pembinanya(): void
    {
        $this->actingAs($this->dedi)->get("/guru/ekskul/{$this->pramuka->id}/qr")->assertOk()->assertSee($this->pramuka->fresh()->kode_qr);
        $this->actingAs($this->admin)->get("/super-admin/ekskul/{$this->pramuka->id}/qr")->assertOk();
        $this->actingAs($this->eka)->get("/guru/ekskul/{$this->pramuka->id}/qr")->assertForbidden();
    }

    // ===================== MULAI SESI =====================

    public function test_pembina_scan_qr_memulai_sesi(): void
    {
        $this->halaman()
            ->assertSee('Sesi Ekskul Hari Ini')
            ->call('mulaiSesi', strtolower($this->pramuka->fresh()->kode_qr))
            ->assertSet('notif.tipe', 'ok')
            ->assertDispatched('hasil-scan', tipe: 'ok')
            ->assertSee('Berlangsung sejak 14:00');

        $sesi = SesiEkskul::sole();
        $this->assertSame([$this->pramuka->id, $this->dedi->id, self::TANGGAL], [$sesi->jadwal_ekskul_id, $sesi->user_id, $sesi->tanggal->toDateString()]);
    }

    public function test_scan_ulang_melanjutkan_sesi_yang_sama(): void
    {
        $kode = $this->pramuka->fresh()->kode_qr;
        $this->halaman()->call('mulaiSesi', $kode)->call('mulaiSesi', $kode)->assertSet('notif.tipe', 'warn');

        $this->assertSame(1, SesiEkskul::count());
    }

    public function test_kode_salah_atau_qr_ekskul_lain_ditolak(): void
    {
        $this->halaman()->call('mulaiSesi', 'RUANG-X-RPL-1')->assertSet('notif.tipe', 'error');
        $this->halaman()->call('mulaiSesi', $this->rohis->fresh()->kode_qr)
            ->assertSet('notif.tipe', 'error')
            ->assertSee('QR ekskul Rohis');

        $this->assertSame(0, SesiEkskul::count());
    }

    public function test_hanya_pembina_yang_bisa_memulai(): void
    {
        $kode = $this->pramuka->fresh()->kode_qr;

        // Super Admin boleh mengisi absensi, tapi BUKAN memulai sesi pembina.
        $this->halaman($this->admin)->assertDontSee('Sesi Ekskul Hari Ini')
            ->call('mulaiSesi', $kode)->assertSet('notif.tipe', 'error');
        $this->halaman($this->eka)->call('mulaiSesi', $kode)->assertSet('notif.tipe', 'error');

        $this->assertSame(0, SesiEkskul::count());
    }

    public function test_jendela_waktu_sama_dengan_kbm(): void
    {
        $kode = $this->pramuka->fresh()->kode_qr;

        $this->pada('13:14');
        $this->halaman()->assertSee('baru bisa dimulai pukul 13:15')->call('mulaiSesi', $kode)->assertSet('notif.tipe', 'error');

        $this->pada('15:46');
        $this->halaman()->call('mulaiSesi', $kode)->assertSet('notif.tipe', 'error');
        $this->assertSame(0, SesiEkskul::count());

        $this->pada('13:15');
        $this->halaman()->call('mulaiSesi', $kode)->assertSet('notif.tipe', 'ok');
    }

    public function test_bukan_hari_ekskul_tidak_ada_kartu_sesi(): void
    {
        $this->pada('14:00', '2026-09-25'); // Jumat

        $this->halaman()->assertDontSee('Sesi Ekskul Hari Ini')
            ->call('mulaiSesi', $this->pramuka->fresh()->kode_qr)->assertSet('notif.tipe', 'error');
    }

    public function test_hari_libur_kalender_menolak_sesi(): void
    {
        AgendaAkademik::create(['judul' => 'Libur Maulid', 'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => self::TANGGAL, 'jenis' => JenisAgenda::Libur]);

        $this->halaman()->assertSee('Libur Maulid')
            ->call('mulaiSesi', $this->pramuka->fresh()->kode_qr)->assertSet('notif.tipe', 'error');
    }

    // ===================== AKHIRI SESI =====================

    public function test_akhiri_butuh_absensi_dan_foto(): void
    {
        $k = $this->halaman()->call('mulaiSesi', $this->pramuka->fresh()->kode_qr);

        $k->call('akhiriSesi')->assertSet('notif.tipe', 'error')->assertSee('Simpan dulu absensi anggota');
        $k->call('simpan')->call('akhiriSesi')->assertSet('notif.tipe', 'error')->assertSee('Unggah dulu foto bukti');

        $this->assertNull(SesiEkskul::sole()->waktu_selesai);
    }

    public function test_alur_lengkap_mengakhiri_sesi(): void
    {
        $k = $this->siapAkhiri();
        $sesi = SesiEkskul::sole();

        $this->assertStringStartsWith('bukti-ekskul/', $sesi->foto_bukti);
        Storage::disk('public')->assertExists($sesi->foto_bukti);

        $this->pada('15:40');
        $k->call('akhiriSesi')
            ->assertSet('notif.tipe', 'ok')
            ->assertDispatched('sesi-diakhiri', id: 'e' . $sesi->id)
            ->assertSee('Sesi hari ini sudah diakhiri pukul 15:40');

        $this->assertSame('15:40', $sesi->fresh()->waktu_selesai->format('H:i'));
    }

    public function test_akhiri_lewat_batas_ditolak(): void
    {
        $k = $this->siapAkhiri();

        $this->pada('15:46');
        $k->call('akhiriSesi')->assertSet('notif.tipe', 'error')->assertSee('sudah lewat');
        $this->assertNull(SesiEkskul::sole()->waktu_selesai);
    }

    public function test_koreksi_absensi_tanggal_lampau_tetap_bisa_tanpa_sesi(): void
    {
        $this->halaman()->set('tanggal', '2026-09-19')->call('simpan')->assertSet('notif.tipe', 'ok');

        $this->assertSame(2, AbsensiEkskulModel::count());
        $this->assertSame(0, SesiEkskul::count());
    }

    // ===================== HONOR =====================

    public function test_honor_ekskul_tercatat_saat_akhiri(): void
    {
        app(AturanHonor::class)->simpanUmum(true, 25_000, 70);
        $k = $this->siapAkhiri();

        $this->pada('15:40');
        $k->call('akhiriSesi')->assertSee('Honor Rp 75.000 (3 JP) masuk ke Rincian Pendapatan');

        $h = HonorMengajar::sole();
        $this->assertSame([HonorMengajar::PERAN_EKSKUL, $this->dedi->id, $this->pramuka->id, 3, 75_000], [$h->peran, $h->user_id, $h->jadwal_ekskul_id, $h->jp, $h->nominal]);
        $this->assertNull($h->jadwal_id);

        // Rincian guru & rekap kepsek ikut menghitungnya.
        $this->assertSame(75_000, app(PencatatHonor::class)->rincian($this->dedi->id, now())['per_peran']['ekskul']['nominal']);
        $baris = app(PencatatHonor::class)->rekapBulan(now())['baris']->firstWhere('user.id', $this->dedi->id);
        $this->assertSame([3, 75_000, 75_000], [$baris['jp_ekskul'], $baris['honor_ekskul'], $baris['total']]);
    }

    public function test_jp_manual_dan_tarif_ekskul_khusus(): void
    {
        $this->pramuka->update(['jp_honor' => 2]);
        app(AturanHonor::class)->simpanUmum(true, 25_000, 70, 20_000);
        $k = $this->siapAkhiri();

        $this->pada('15:40');
        $k->call('akhiriSesi');

        $this->assertSame([2, 20_000, 40_000], [HonorMengajar::sole()->jp, HonorMengajar::sole()->tarif_per_jp, HonorMengajar::sole()->nominal]);
    }

    public function test_fitur_honor_mati_tidak_mencatat(): void
    {
        $k = $this->siapAkhiri();
        $this->pada('15:40');
        $k->call('akhiriSesi')->assertSet('notif.tipe', 'ok');

        $this->assertSame(0, HonorMengajar::count());
    }

    public function test_kelola_ekskul_menyimpan_jp_honor(): void
    {
        Livewire::actingAs($this->admin)->test(KelolaEkskul::class)
            ->call('edit', $this->pramuka->id)
            ->set('jp_honor', '4')
            ->call('simpan')
            ->assertHasNoErrors();

        $this->assertSame(4, (int) $this->pramuka->fresh()->jp_honor);
    }

    // ===================== LIVE MONITORING =====================

    public function test_ekskul_berjalan_masuk_live_monitoring(): void
    {
        $kepsek = User::factory()->kepsek()->create();

        $this->actingAs($kepsek)->get('/kepsek/live-monitoring')->assertOk()
            ->assertSee('Pramuka')->assertSee('Rohis')
            ->assertSee('Sesi belum dimulai')
            ->assertSee('pembina belum men-scan QR ekskul');

        $this->halaman()->call('mulaiSesi', $this->pramuka->fresh()->kode_qr);

        $this->actingAs($kepsek)->get('/kepsek/live-monitoring')->assertOk()
            ->assertSee('Sesi berjalan')
            ->assertSee('Scan QR 14:00');

        // Di luar jamnya, ekskul tidak tampil.
        $this->pada('16:00');
        $this->actingAs($kepsek)->get('/kepsek/live-monitoring')->assertOk()->assertDontSee('Pramuka');
    }

    // ===================== PENGINGAT AKHIRI SESI =====================

    public function test_pengingat_dikirim_sekali_ke_pembina(): void
    {
        $this->siapAkhiri();

        $this->pada('15:34');
        Artisan::call('simagas:pengingat-akhiri-sesi');
        $this->assertSame(0, $this->dedi->notifications()->count());

        $this->pada('15:35');
        Artisan::call('simagas:pengingat-akhiri-sesi');
        Artisan::call('simagas:pengingat-akhiri-sesi');

        $this->assertSame(1, $this->dedi->notifications()->count());
        $this->assertSame('Sesi ekskul belum diakhiri', $this->dedi->notifications()->first()->data['judul']);
        $this->assertNotNull(SesiEkskul::sole()->pengingat_akhiri_pada);
    }

    public function test_alarm_layar_muncul_untuk_sesi_ekskul_terbuka(): void
    {
        $this->halaman()->call('mulaiSesi', $this->pramuka->fresh()->kode_qr);

        $this->actingAs($this->dedi)->get('/guru/dashboard')->assertOk()
            ->assertSee('id="pengingat-akhiri-sesi"', false)
            ->assertSee('Ekskul Pramuka')
            ->assertSee('Akhiri Sesi Ekskul');

        $this->actingAs($this->eka)->get('/guru/dashboard')->assertOk()
            ->assertDontSee('id="pengingat-akhiri-sesi"', false);
    }

    // ===================== LAIN-LAIN =====================

    public function test_qr_ekskul_di_absen_mengajar_tidak_dicatat_sebagai_kbm(): void
    {
        AbsensiPegawai::create(['pegawai_id' => $this->dedi->pegawai->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:50:00']);

        Livewire::actingAs($this->dedi)->test(AbsenMengajarQr::class)
            ->call('prosesAbsenMengajar', $this->pramuka->fresh()->kode_qr)
            ->assertSet('notif.tipe', 'warn');

        $this->assertSame(0, AbsensiMengajar::count());
    }

    public function test_foto_bukti_ekskul_lama_ikut_dibersihkan(): void
    {
        Storage::disk('public')->put('bukti-ekskul/lama.jpg', 'x');
        $lama = SesiEkskul::create(['jadwal_ekskul_id' => $this->pramuka->id, 'user_id' => $this->dedi->id, 'tanggal' => '2026-07-04',
            'waktu_mulai' => '2026-07-04 13:30:00', 'waktu_selesai' => '2026-07-04 15:30:00', 'foto_bukti' => 'bukti-ekskul/lama.jpg']);

        Artisan::call('simagas:bersihkan-bukti', ['--bulan' => 1]);

        Storage::disk('public')->assertMissing('bukti-ekskul/lama.jpg');
        $this->assertNotNull($lama->fresh()->bukti_dihapus_pada);
        $this->assertSame('bukti-ekskul/lama.jpg', $lama->fresh()->foto_bukti);
    }
}
