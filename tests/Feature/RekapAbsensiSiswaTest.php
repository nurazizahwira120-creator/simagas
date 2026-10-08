<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\User;
use App\Services\RekapAbsensiSiswaBulanan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan Bulanan Kehadiran Siswa — gerbang (hari) + kelas (jam pelajaran).
 *
 * September 2026, batas terlambat 07:15, 1 JP = 35 menit.
 *   Andi (X RPL 1): gerbang hadir 07:00, hadir 07:20 (telat), hadir 07:15:40
 *                   (masih menit 07:15 = tepat), hadir tanpa jam (manual),
 *                   izin, sakit, alpa; + 31 Agt & 1 Okt (di luar bulan).
 *                   kelas: hadir di jadwal 2 JP, bolos di jadwal 1 JP,
 *                   alpa di jadwal 2 JP, izin & sakit di jadwal 1 JP.
 *   Bela (X RPL 1): tanpa catatan apa pun -> semua nol.
 *   Cici (X TKJ 2): gerbang hadir 1x.
 */
class RekapAbsensiSiswaTest extends TestCase
{
    use RefreshDatabase;

    private Siswa $andi;

    private Siswa $bela;

    private Siswa $cici;

    private Kelas $rpl;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 10:00:00');

        Pengaturan::simpan('batas_terlambat_siswa', '07:15');
        Pengaturan::simpan('durasi_jp', '35');

        $this->rpl = Kelas::factory()->create(['nama_kelas' => 'X RPL 1']);
        $tkj = Kelas::factory()->create(['nama_kelas' => 'X TKJ 2']);

        $this->andi = Siswa::factory()->create(['nama' => 'Andi', 'kelas_id' => $this->rpl->id]);
        $this->bela = Siswa::factory()->create(['nama' => 'Bela', 'kelas_id' => $this->rpl->id]);
        $this->cici = Siswa::factory()->create(['nama' => 'Cici', 'kelas_id' => $tkj->id]);

        $g = fn (Siswa $s, string $tgl, AbsensiStatus $st, ?string $jam = null) => AbsensiSiswa::create(
            ['siswa_id' => $s->id, 'tanggal' => $tgl, 'status' => $st, 'jam_masuk' => $jam]);

        $g($this->andi, '2026-09-01', AbsensiStatus::Hadir, '07:00:00');
        $g($this->andi, '2026-09-02', AbsensiStatus::Hadir, '07:20:00');
        $g($this->andi, '2026-09-03', AbsensiStatus::Hadir, '07:15:40');
        $g($this->andi, '2026-09-04', AbsensiStatus::Hadir);
        $g($this->andi, '2026-09-07', AbsensiStatus::Izin);
        $g($this->andi, '2026-09-08', AbsensiStatus::Sakit);
        $g($this->andi, '2026-09-30', AbsensiStatus::Alpha);
        $g($this->andi, '2026-08-31', AbsensiStatus::Alpha);   // bulan lalu
        $g($this->andi, '2026-10-01', AbsensiStatus::Alpha);   // bulan depan
        $g($this->cici, '2026-09-01', AbsensiStatus::Hadir, '06:50:00');

        $guru = Pegawai::factory()->create();
        $dua = JadwalPelajaran::create(['hari' => Hari::Selasa->value, 'jam_mulai' => '07:00', 'jam_selesai' => '08:10',
            'mata_pelajaran' => 'Basis Data', 'kelas_id' => $this->rpl->id, 'guru_id' => $guru->id]);   // 70 menit = 2 JP
        $satu = JadwalPelajaran::create(['hari' => Hari::Selasa->value, 'jam_mulai' => '08:10', 'jam_selesai' => '08:45',
            'mata_pelajaran' => 'PPKn', 'kelas_id' => $this->rpl->id, 'guru_id' => $guru->id]);         // 35 menit = 1 JP

        $k = fn (JadwalPelajaran $j, string $tgl, StatusKbm $st) => AbsensiKbmSiswa::create(
            ['jadwal_id' => $j->id, 'siswa_id' => $this->andi->id, 'tanggal' => $tgl, 'status' => $st]);

        $k($dua, '2026-09-01', StatusKbm::Hadir);
        $k($dua, '2026-09-08', StatusKbm::Alpa);
        $k($satu, '2026-09-01', StatusKbm::Bolos);
        $k($satu, '2026-09-15', StatusKbm::Izin);
        $k($satu, '2026-09-22', StatusKbm::Sakit);
        $k($dua, '2026-10-06', StatusKbm::Bolos);              // bulan depan
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rekap(?int $kelasId = null): array
    {
        return app(RekapAbsensiSiswaBulanan::class)->hitung(Carbon::parse('2026-09-01'), $kelasId);
    }

    private function baris(array $data, Siswa $s): array
    {
        return $data['baris']->first(fn ($b) => $b['siswa']->id === $s->id);
    }

    public function test_gerbang_memisahkan_hadir_tepat_waktu_dan_terlambat(): void
    {
        $g = $this->baris($this->rekap(), $this->andi)['gerbang'];

        // 07:00, 07:15:40 (masih menit batas), dan hadir tanpa jam = tepat waktu.
        $this->assertSame(['hadir' => 3, 'terlambat' => 1, 'izin' => 1, 'sakit' => 1, 'alpa' => 1, 'total' => 7], $g);
    }

    public function test_kelas_dihitung_per_jam_pelajaran(): void
    {
        $k = $this->baris($this->rekap(), $this->andi)['kelas'];

        $this->assertSame(['hadir' => 2, 'izin' => 1, 'sakit' => 1, 'bolos' => 1, 'alpa' => 2, 'total' => 7], $k);
    }

    public function test_siswa_tanpa_catatan_tetap_tampil_dengan_nol(): void
    {
        $b = $this->baris($this->rekap(), $this->bela);

        $this->assertSame(0, $b['gerbang']['total']);
        $this->assertSame(0, $b['kelas']['total']);
    }

    public function test_saringan_kelas_dan_total(): void
    {
        $semua = $this->rekap();
        $this->assertSame(['Andi', 'Bela', 'Cici'], $semua['baris']->map(fn ($b) => $b['siswa']->nama)->all());
        $this->assertSame(8, $semua['total']['gerbang']['total']);
        $this->assertSame(4, $semua['total']['gerbang']['hadir']);

        $rpl = $this->rekap($this->rpl->id);
        $this->assertSame(['Andi', 'Bela'], $rpl['baris']->map(fn ($b) => $b['siswa']->nama)->all());
        $this->assertSame(7, $rpl['total']['gerbang']['total']);
    }

    public function test_batas_terlambat_mengikuti_pengaturan(): void
    {
        Pengaturan::simpan('batas_terlambat_siswa', '07:30');

        $g = $this->baris($this->rekap(), $this->andi)['gerbang'];
        $this->assertSame(4, $g['hadir']);
        $this->assertSame(0, $g['terlambat']);
    }

    public function test_jumlah_query_tetap_walau_siswanya_banyak(): void
    {
        foreach (range(1, 25) as $i) {
            $s = Siswa::factory()->create(['kelas_id' => $this->rpl->id]);
            AbsensiSiswa::create(['siswa_id' => $s->id, 'tanggal' => '2026-09-01', 'status' => AbsensiStatus::Hadir, 'jam_masuk' => '07:30:00']);
        }

        DB::enableQueryLog();
        $data = $this->rekap();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(28, $data['baris']);
        $this->assertLessThanOrEqual(8, $jumlah);
    }

    public function test_halaman_menampilkan_kolom_gerbang_dan_kelas(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->get('/kepsek/laporan?bulan=2026-09&kelas_id=' . $this->rpl->id)
            ->assertOk()
            // Baris judul 1: dua kelompok; baris judul 2: kolom gerbang lalu kolom kelas.
            ->assertSeeInOrder(['Absensi Gerbang', 'Absensi di Kelas', 'Hadir', 'Terlambat', 'Izin', 'Sakit', 'Alpa', 'Jml', 'Hadir', 'Izin', 'Sakit', 'Bolos', 'Alpa', 'Jml'])
            ->assertSee('Andi')
            ->assertSee('Bela')
            ->assertDontSee('Cici')
            ->assertSee('scan setelah pukul 07:15')
            ->assertSee('/kepsek/laporan/unduh?bulan=2026-09', false);
    }

    public function test_unduh_pdf(): void
    {
        $r = $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super-admin/laporan/unduh?bulan=2026-09&kelas_id=' . $this->rpl->id)
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertStringStartsWith('%PDF', $r->getContent());
        $this->assertStringContainsString('Rekap-Absensi-Siswa-2026-09-X-RPL-1.pdf', $r->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
    }

    public function test_saringan_aneh_tidak_membuat_galat(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->get('/kepsek/laporan?bulan=2026-13&kelas_id=99999')
            ->assertOk()
            ->assertSee('Oktober 2026');
    }

    public function test_hanya_kepsek_dan_admin(): void
    {
        $this->actingAs(User::factory()->guru()->create())->get('/guru/laporan/unduh')->assertNotFound();
        $this->actingAs(User::factory()->waliKelas()->create())->get('/kepsek/laporan/unduh')->assertForbidden();
    }
}
