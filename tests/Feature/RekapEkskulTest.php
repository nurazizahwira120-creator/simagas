<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\JenisAgenda;
use App\Livewire\Ekskul\AbsensiEkskul;
use App\Livewire\WaliMurid\RekapAkademik;
use App\Models\AbsensiEkskul as AbsensiEkskulModel;
use App\Models\AgendaAkademik;
use App\Models\JadwalEkskul;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\SesiEkskul;
use App\Models\Siswa;
use App\Models\User;
use App\Services\RekapEkskulBulanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * REKAP ABSENSI EKSKUL — Sabtu, 24 Oktober 2026 pukul 18.00.
 *
 * Pramuka (Sabtu, pembina Dedi). Sabtu bulan ini sampai hari ini: 3, 10,
 * 17, 24 — tanggal 17 libur Kalender Pendidikan -> terjadwal 3.
 *   3 Okt  : sesi diakhiri; Andi H, Bela A, Caca H (Caca lalu keluar)
 *   10 Okt : TANPA sesi (susulan); Andi I, Bela H
 *   24 Okt : sesi dimulai, belum diakhiri; belum ada absensi
 * Rohis (Jumat, pembina Eka): tidak ada data.
 */
class RekapEkskulTest extends TestCase
{
    use RefreshDatabase;

    private User $dedi;

    private User $eka;

    private User $kepsek;

    private JadwalEkskul $pramuka;

    private JadwalEkskul $rohis;

    /** @var array<string, Siswa> */
    private array $siswa = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-24 18:00:00'));

        $this->dedi = User::factory()->guru()->create();
        $pDedi = Pegawai::factory()->create(['user_id' => $this->dedi->id, 'nama' => 'Dedi Pembina']);
        $this->eka = User::factory()->guru()->create();
        $pEka = Pegawai::factory()->create(['user_id' => $this->eka->id, 'nama' => 'Eka']);
        $this->kepsek = User::factory()->kepsek()->create();

        $this->pramuka = JadwalEkskul::create(['nama_ekskul' => 'Pramuka', 'hari' => 'Sabtu', 'jam_mulai' => '13:30', 'jam_selesai' => '15:30', 'pembina_id' => $pDedi->id]);
        $this->rohis = JadwalEkskul::create(['nama_ekskul' => 'Rohis', 'hari' => 'Jumat', 'jam_mulai' => '14:00', 'jam_selesai' => '15:00', 'pembina_id' => $pEka->id]);

        $kelas = Kelas::factory()->create(['nama_kelas' => 'X RPL 1']);
        foreach (['andi' => 'Andi', 'bela' => 'Bela', 'caca' => 'Caca'] as $k => $nama) {
            $this->siswa[$k] = Siswa::factory()->create(['nama' => $nama, 'kelas_id' => $kelas->id]);
            $this->pramuka->anggota()->attach($this->siswa[$k]->id);
        }

        AgendaAkademik::create(['judul' => 'Libur', 'tanggal_mulai' => '2026-10-17', 'tanggal_selesai' => '2026-10-17', 'jenis' => JenisAgenda::Libur]);

        $this->sesi('2026-10-03', true);
        $this->absen('2026-10-03', ['andi' => AbsensiStatus::Hadir, 'bela' => AbsensiStatus::Alpha, 'caca' => AbsensiStatus::Hadir]);
        $this->absen('2026-10-10', ['andi' => AbsensiStatus::Izin, 'bela' => AbsensiStatus::Hadir]);
        $this->sesi('2026-10-24', false);
        // Bulan lalu — tidak boleh ikut terhitung.
        $this->absen('2026-09-26', ['andi' => AbsensiStatus::Alpha]);

        $this->pramuka->anggota()->detach($this->siswa['caca']->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sesi(string $tgl, bool $selesai): void
    {
        SesiEkskul::create(['jadwal_ekskul_id' => $this->pramuka->id, 'user_id' => $this->dedi->id, 'tanggal' => $tgl,
            'waktu_mulai' => $tgl . ' 13:30:00', 'waktu_selesai' => $selesai ? $tgl . ' 15:30:00' : null, 'foto_bukti' => 'x.jpg']);
    }

    private function absen(string $tgl, array $status): void
    {
        foreach ($status as $k => $s) {
            AbsensiEkskulModel::create(['jadwal_ekskul_id' => $this->pramuka->id, 'siswa_id' => $this->siswa[$k]->id, 'tanggal' => $tgl, 'status_kehadiran' => $s]);
        }
    }

    private function rekap(): RekapEkskulBulanan
    {
        return app(RekapEkskulBulanan::class);
    }

    public function test_ringkasan_per_ekskul(): void
    {
        $baris = $this->rekap()->ringkasan(now())['baris']->keyBy(fn ($b) => $b['ekskul']->nama_ekskul);

        $p = $baris['Pramuka'];
        $this->assertSame([2, 3, 1, 1, 2, 60, 33], [$p['anggota'], $p['terjadwal'], $p['terlaksana'], $p['sesi_terbuka'], $p['pertemuan_diisi'], $p['persen_hadir'], $p['persen_terlaksana']]);

        // Jumat bulan ini s.d. 24 Okt: 2, 9, 16, 23.
        $this->assertSame([4, 0, null], [$baris['Rohis']['terjadwal'], $baris['Rohis']['terlaksana'], $baris['Rohis']['persen_hadir']]);
    }

    public function test_rincian_per_anggota_dan_matriks(): void
    {
        $r = $this->rekap()->rincian($this->pramuka, now());

        $this->assertSame(['2026-10-03', '2026-10-10', '2026-10-24'], $r['pertemuan']->pluck('kunci')->all());
        $this->assertSame([true, false, true], $r['pertemuan']->map(fn ($p) => $p['sesi'] !== null)->all());
        $this->assertSame([], $r['kosong']);

        $s = $r['siswa']->keyBy(fn ($b) => $b['siswa']->nama);
        $this->assertSame(['Andi', 'Bela', 'Caca'], $s->keys()->all());
        $this->assertSame(['hadir' => 1, 'izin' => 1, 'sakit' => 0, 'alpha' => 0], $s['Andi']['jumlah']);
        $this->assertSame(50, $s['Andi']['persen']);
        $this->assertSame(AbsensiStatus::Alpha, $s['Bela']['per_tanggal']['2026-10-03']);
        $this->assertFalse($s['Caca']['anggota_aktif']);
        $this->assertSame([3, 1, 3, 60], [$r['total']['terjadwal'], $r['total']['terlaksana'], $r['total']['pertemuan'], $r['total']['persen_hadir']]);
    }

    public function test_hari_terjadwal_tanpa_kegiatan_ditandai(): void
    {
        SesiEkskul::query()->whereDate('tanggal', '2026-10-24')->delete();

        $this->assertSame(['2026-10-24'], $this->rekap()->rincian($this->pramuka, now())['kosong']);
    }

    public function test_kepsek_melihat_semua_dan_rincian(): void
    {
        $this->actingAs($this->kepsek)->get('/kepsek/rekap-ekskul')->assertOk()
            ->assertSee('Pramuka')->assertSee('Rohis')->assertSee('60%');

        $this->actingAs($this->kepsek)->get('/kepsek/rekap-ekskul?ekskul=' . $this->pramuka->id . '&bulan=2026-10')->assertOk()
            ->assertSee('Andi')->assertSee('(sudah keluar)')->assertSee('Susulan')->assertSee('Sesi ✓');

        $this->actingAs($this->kepsek)->get('/kepsek/dashboard')->assertOk()->assertSee('Rekap Absensi Ekskul');
    }

    public function test_pembina_hanya_melihat_ekskulnya(): void
    {
        // Satu ekskul -> langsung rincian.
        $this->actingAs($this->dedi)->get('/guru/rekap-ekskul')->assertOk()
            ->assertSee('Andi')->assertDontSee('Rohis');

        $this->actingAs($this->dedi)->get('/guru/rekap-ekskul?ekskul=' . $this->rohis->id)->assertForbidden();
        $this->actingAs($this->dedi)->get('/guru/rekap-ekskul/unduh?ekskul=' . $this->rohis->id)->assertForbidden();

        // Bukan pembina & wali murid ditolak.
        $this->actingAs(User::factory()->guru()->create())->get('/guru/rekap-ekskul')->assertForbidden();
        $this->actingAs(User::factory()->waliMurid()->create())->get('/wali-murid/rekap-ekskul')->assertForbidden();
    }

    public function test_bulan_masa_depan_kembali_ke_bulan_ini(): void
    {
        $this->actingAs($this->kepsek)->get('/kepsek/rekap-ekskul?bulan=2099-01')->assertOk()->assertSee('60%');
    }

    public function test_unduh_pdf_satu_dan_semua(): void
    {
        $r = $this->actingAs($this->kepsek)->get('/kepsek/rekap-ekskul/unduh?ekskul=' . $this->pramuka->id . '&bulan=2026-10');
        $r->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertStringContainsString('Rekap-Absensi-Ekskul-2026-10-Pramuka.pdf', $r->headers->get('Content-Disposition'));

        $semua = $this->actingAs($this->kepsek)->get('/kepsek/rekap-ekskul/unduh?bulan=2026-10');
        $semua->assertOk();
        $this->assertStringStartsWith('%PDF', $semua->getContent());
    }

    public function test_wali_murid_melihat_ringkasan_ekskul_anaknya(): void
    {
        $wali = User::factory()->waliMurid()->create();
        $this->siswa['andi']->update(['wali_murid_id' => $wali->id]);

        Livewire::actingAs($wali)->test(RekapAkademik::class)
            ->assertSee('Kehadiran Ekstrakurikuler')
            ->assertSee('Pramuka')
            ->assertSee('Hadir 1 dari 2 pertemuan')
            ->assertDontSee('Rohis');
    }

    public function test_rekap_baca_saja_hanya_bulan_berjalan(): void
    {
        $k = Livewire::actingAs(User::factory()->staff()->create())
            ->test(AbsensiEkskul::class, ['jadwal' => $this->pramuka->id])
            ->assertSee('Rekap Kehadiran Oktober 2026');

        // 5 catatan Oktober; catatan September tidak ikut.
        $this->assertSame(['hadir' => 3, 'izin' => 1, 'sakit' => 0, 'alpha' => 1], $k->instance()->rekap);
    }
}
