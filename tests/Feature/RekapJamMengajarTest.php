<?php

namespace Tests\Feature;

use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiMengajar;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\PengajuanIzinGuru;
use App\Models\Siswa;
use App\Models\User;
use App\Services\JamPelajaran;
use App\Services\LaporanBulananService;
use App\Services\RekapJamMengajar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Fitur 1 & 2: laporan KBM guru berbasis JAM PELAJARAN dengan durasi JP
 * yang bisa diatur.
 *
 * Periode uji: Senin 21 – Jumat 25 September 2026. Pola hari KBM bawaan
 * sekolah ini MELIBURKAN Jumat, jadi hari KBM-nya 4 (Senin–Kamis).
 *
 *   Budi  : Sen 07.00–08.10 XI RPL 1 (70' = 2 JP)
 *           Sen 08.10–10.30 X TKJ 1  (140' = 4 JP)
 *           Kam 07.00–07.35 XI RPL 1 (35' = 1 JP)
 *   Citra : Sel 07.00–08.45 X TKJ 1  (105' = 3 JP)
 *           Rab 07.00–08.10 X TKJ 1  (70' = 2 JP)  <- izin disetujui
 *   Dedi  : tidak punya jadwal; menggantikan kelas Citra hari Rabu.
 */
class RekapJamMengajarTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, array{akun: User, pegawai: Pegawai}> */
    private array $guru = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-26 12:00:00'));

        $rpl = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        $tkj = Kelas::factory()->create(['nama_kelas' => 'X TKJ 1']);

        foreach (['budi' => 'Budi', 'citra' => 'Citra', 'dedi' => 'Dedi'] as $k => $nama) {
            $akun = User::factory()->guru()->create(['name' => $nama]);
            $this->guru[$k] = ['akun' => $akun, 'pegawai' => Pegawai::factory()->create(['user_id' => $akun->id, 'nama' => $nama])];
        }

        $j = fn (string $g, Hari $hari, string $mulai, string $selesai, Kelas $kelas) => JadwalPelajaran::create([
            'hari' => $hari->value, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
            'mata_pelajaran' => 'Mapel ' . $g, 'kelas_id' => $kelas->id, 'guru_id' => $this->guru[$g]['pegawai']->id,
        ]);

        $seninBudi = $j('budi', Hari::Senin, '07:00', '08:10', $rpl);
        $j('budi', Hari::Senin, '08:10', '10:30', $tkj);
        $j('budi', Hari::Kamis, '07:00', '07:35', $rpl);
        $j('citra', Hari::Selasa, '07:00', '08:45', $tkj);
        $rabuCitra = $j('citra', Hari::Rabu, '07:00', '08:10', $tkj);

        $sesi = fn (string $g, string $kode, string $mulai, ?string $selesai, bool $foto = true) => AbsensiMengajar::create([
            'user_id' => $this->guru[$g]['akun']->id, 'kode_kelas' => $kode,
            'waktu_mulai' => $mulai, 'waktu_selesai' => $selesai, 'foto_bukti' => $foto ? 'bukti-mengajar/x.jpg' : null,
        ]);

        $sesi('budi', 'Ruang XI-RPL 1', '2026-09-21 07:02:00', '2026-09-21 08:05:00');
        $sesi('budi', 'X-TKJ-1', '2026-09-21 08:12:00', '2026-09-21 10:25:00');
        $sesi('budi', 'XI RPL 1', '2026-09-24 07:01:00', null);              // tidak diakhiri -> tidak dihitung
        $sesi('budi', 'XI RPL 1', '2026-09-25 07:01:00', '2026-09-25 07:30:00'); // Jumat libur -> luar jadwal
        $sesi('citra', 'X TKJ 1', '2026-09-22 07:03:00', '2026-09-22 08:40:00');

        PengajuanIzinGuru::create([
            'guru_id' => $this->guru['citra']['akun']->id,
            'tanggal_mulai' => '2026-09-23', 'tanggal_selesai' => '2026-09-23',
            'jenis_izin' => JenisIzinGuru::Idt, 'alasan' => 'Dinas luar',
            'status_approval' => StatusApproval::Disetujui,
        ]);

        // Budi mengisi absensi kelasnya SENDIRI hari Senin — ini BUKAN
        // menggantikan siapa pun dan tidak boleh muncul sebagai JP pengganti.
        $siswaRpl = Siswa::factory()->count(2)->create(['kelas_id' => $rpl->id]);
        foreach ($siswaRpl as $s) {
            AbsensiKbmSiswa::create([
                'jadwal_id' => $seninBudi->id, 'siswa_id' => $s->id, 'tanggal' => '2026-09-21',
                'status' => StatusKbm::Hadir, 'diisi_oleh' => $this->guru['budi']['akun']->id,
            ]);
        }

        // Dedi mengisi absensi kelas Citra hari Rabu sebagai pengganti.
        $siswa = Siswa::factory()->count(3)->create(['kelas_id' => $tkj->id]);
        foreach ($siswa as $s) {
            AbsensiKbmSiswa::create([
                'jadwal_id' => $rabuCitra->id, 'siswa_id' => $s->id, 'tanggal' => '2026-09-23',
                'status' => StatusKbm::Hadir, 'diisi_oleh' => $this->guru['dedi']['akun']->id,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function rekap(): array
    {
        return app(RekapJamMengajar::class)->hitung(Carbon::parse('2026-09-21'), Carbon::parse('2026-09-25'));
    }

    private function baris(array $data, string $nama): array
    {
        $b = collect($data['baris'])->firstWhere('nama', $nama);
        $this->assertNotNull($b, "Baris {$nama} tidak ada.");

        return $b;
    }

    /* ===================== JamPelajaran ===================== */

    public function test_durasi_bawaan_35_menit_dan_aturan_pembulatan(): void
    {
        $jp = app(JamPelajaran::class);

        $this->assertSame(35, $jp->durasiMenit());
        $this->assertSame(2, $jp->antara('07:00', '08:10'));  // 70'
        $this->assertSame(3, $jp->antara('07:00', '08:45'));  // 105'
        $this->assertSame(2, $jp->antara('07:00', '08:15'));  // 75' -> 2,14
        $this->assertSame(3, $jp->antara('07:00', '08:30'));  // 90' -> 2,57
        $this->assertSame(1, $jp->antara('07:00', '07:20'));  // 20' -> minimal 1
        $this->assertSame(0, $jp->antara('08:00', '08:00'));
    }

    public function test_durasi_bisa_diubah_dan_nilai_sampah_kembali_ke_bawaan(): void
    {
        Pengaturan::simpan(JamPelajaran::KUNCI, '45');
        $this->assertSame(45, app(JamPelajaran::class)->durasiMenit());
        $this->assertSame(2, app(JamPelajaran::class)->antara('07:00', '08:30'));

        foreach (['5', '500', 'tiga puluh', ''] as $sampah) {
            Pengaturan::simpan(JamPelajaran::KUNCI, $sampah);
            $this->assertSame(35, app(JamPelajaran::class)->durasiMenit(), "Nilai '{$sampah}' harus ditolak.");
        }
    }

    public function test_pengaturan_durasi_jp_tersimpan_lewat_form_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $form = [
            'jam_masuk_siswa' => '07:00', 'batas_terlambat_siswa' => '07:15', 'jam_pulang_siswa' => '13:00',
            'jam_masuk_pegawai' => '06:45', 'batas_terlambat_pegawai' => '07:00',
            'hari_kbm' => ['senin', 'selasa', 'rabu', 'kamis', 'sabtu', 'minggu'],
        ];

        $this->actingAs($admin)->put('/super-admin/pengaturan/waktu', $form + ['durasi_jp' => 40])->assertSessionHasNoErrors();
        $this->assertSame('40', Pengaturan::ambil(JamPelajaran::KUNCI));

        // Dibaca SEBELUM percobaan gagal di bawah: input lama (old()) dari
        // permintaan yang ditolak ikut tersimpan di sesi dan akan mengisi form.
        $this->actingAs($admin)->get('/super-admin/pengaturan?tab=waktu')->assertOk()->assertSee('Durasi 1 JP')->assertSee('value="40"', false);

        $this->actingAs($admin)->put('/super-admin/pengaturan/waktu', $form + ['durasi_jp' => 10])->assertSessionHasErrors('durasi_jp');
        $this->assertSame('40', Pengaturan::ambil(JamPelajaran::KUNCI));
    }

    /* ===================== RekapJamMengajar ===================== */

    public function test_hari_kbm_mengecualikan_libur_mingguan(): void
    {
        $this->assertSame(4, $this->rekap()['hari_kbm']); // Jumat libur
    }

    public function test_jp_guru_dihitung_per_jam_pelajaran_bukan_per_sesi(): void
    {
        $budi = $this->baris($this->rekap(), 'Budi');

        $this->assertSame(7, $budi['jp_per_minggu']);
        $this->assertSame(7, $budi['jp_terjadwal']);

        // Dua sesi Senin = 2 JP + 4 JP = 6 JP (bukan "2 sesi").
        $this->assertSame(6, $budi['jp_terlaksana']);
        $this->assertSame(1, $budi['jp_tidak_terlaksana']); // Kamis tidak diakhiri
        $this->assertSame(0, $budi['jp_berhalangan']);
        $this->assertSame(85.7, $budi['persen']);
        $this->assertSame(1, $budi['sesi_luar_jadwal']);    // sesi hari Jumat
    }

    public function test_jp_saat_guru_izin_masuk_kolom_berhalangan(): void
    {
        $citra = $this->baris($this->rekap(), 'Citra');

        $this->assertSame(5, $citra['jp_terjadwal']);
        $this->assertSame(3, $citra['jp_terlaksana']);
        $this->assertSame(2, $citra['jp_berhalangan']);
        $this->assertSame(0, $citra['jp_tidak_terlaksana']);
        $this->assertSame(60.0, $citra['persen']);
    }

    public function test_jp_pengganti_dikreditkan_ke_guru_yang_mengisi(): void
    {
        $data = $this->rekap();
        $dedi = $this->baris($data, 'Dedi');

        $this->assertSame(2, $dedi['jp_pengganti']);
        $this->assertSame(0, $dedi['jp_terjadwal']);
        $this->assertNull($dedi['persen']);
        $this->assertSame(0, $this->baris($data, 'Citra')['jp_pengganti']);

        // Mengisi kelas sendiri bukan "pengganti".
        $this->assertSame(0, $this->baris($data, 'Budi')['jp_pengganti']);
        $this->assertSame(2, $data['ringkas']['jp_pengganti']);
    }

    public function test_durasi_jp_baru_langsung_mengubah_laporan(): void
    {
        Pengaturan::simpan(JamPelajaran::KUNCI, '45');

        // 70' -> 2, 140' -> 3, 35' -> 1
        $budi = $this->baris($this->rekap(), 'Budi');
        $this->assertSame(6, $budi['jp_terjadwal']);
        $this->assertSame(5, $budi['jp_terlaksana']);
    }

    public function test_jumlah_query_tidak_bertambah_mengikuti_jadwal(): void
    {
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->rekap();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $sebelum = $hitung();

        $kelas = Kelas::factory()->create(['nama_kelas' => 'XII AKL 1']);
        for ($i = 0; $i < 30; $i++) {
            $akun = User::factory()->guru()->create();
            $p = Pegawai::factory()->create(['user_id' => $akun->id]);
            JadwalPelajaran::create([
                'hari' => Hari::Selasa->value, 'jam_mulai' => '10:00', 'jam_selesai' => '11:10',
                'mata_pelajaran' => 'M' . $i, 'kelas_id' => $kelas->id, 'guru_id' => $p->id,
            ]);
            AbsensiMengajar::create([
                'user_id' => $akun->id, 'kode_kelas' => 'XII AKL 1', 'foto_bukti' => 'x.jpg',
                'waktu_mulai' => '2026-09-22 10:01:00', 'waktu_selesai' => '2026-09-22 11:05:00',
            ]);
        }

        $this->assertSame($sebelum, $hitung(), 'Ada N+1: query bertambah mengikuti jumlah jadwal.');
    }

    /* ===================== Halaman, PDF, Laporan Bulanan ===================== */

    public function test_kepsek_membuka_halaman_dan_mengunduh_pdf(): void
    {
        $kepsek = User::factory()->kepsek()->create();

        $this->actingAs($kepsek)
            ->get('/kepsek/rekap-jam-mengajar?dari=2026-09-21&sampai=2026-09-25')
            ->assertOk()
            ->assertSee('Rekap Jam Mengajar Guru')
            ->assertSee('85.7%')
            ->assertSee('1 sesi di luar jadwal');

        $pdf = $this->actingAs($kepsek)->get('/kepsek/rekap-jam-mengajar/unduh?dari=2026-09-21&sampai=2026-09-25');
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $html = view('laporan.rekap-jam-mengajar-pdf', $this->rekap() + [
            'dicetak_pada' => now(), 'dicetak_oleh' => 'Kepsek', 'saringan_guru' => null,
        ])->render();
        $this->assertStringContainsString('1 JP = <strong>35 menit</strong>', $html);
    }

    /**
     * Rekap dibuka Kamis 24/9 pukul 07.10, saat jadwal Kamis Budi
     * (07.00–07.35) masih berlangsung. Jadwal itu belum boleh dihitung
     * "tidak terlaksana" — gurunya justru sedang di kelas.
     */
    public function test_jadwal_yang_belum_selesai_tidak_dihitung_tidak_terlaksana(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-24 07:10:00'));

        $data = app(RekapJamMengajar::class)->hitung(Carbon::parse('2026-09-21'), Carbon::parse('2026-09-30'));
        $budi = $this->baris($data, 'Budi');

        $this->assertSame(6, $budi['jp_terjadwal']);
        $this->assertSame(6, $budi['jp_terlaksana']);
        $this->assertSame(0, $budi['jp_tidak_terlaksana']);
        $this->assertSame(100.0, $budi['persen']);

        // Kamis 24 (1 JP) + Senin 28 (6 JP) + Selasa-Rabu tidak ada jadwal Budi.
        $this->assertSame(7, $budi['jp_akan_datang']);
    }

    public function test_saringan_satu_guru(): void
    {
        $data = app(RekapJamMengajar::class)->hitung(
            Carbon::parse('2026-09-21'), Carbon::parse('2026-09-25'), $this->guru['citra']['pegawai']->id,
        );

        $this->assertSame(['Citra'], array_column($data['baris'], 'nama'));
    }

    public function test_guru_biasa_tidak_bisa_membuka_rekap(): void
    {
        $this->actingAs($this->guru['budi']['akun'])->get('/kepsek/rekap-jam-mengajar')->assertForbidden();
    }

    public function test_rentang_terbalik_ditolak_dengan_pesan(): void
    {
        $this->actingAs(User::factory()->kepsek()->create())
            ->get('/kepsek/rekap-jam-mengajar?dari=2026-09-25&sampai=2026-09-21')
            ->assertOk()
            ->assertSee('Tanggal akhir tidak boleh lebih awal');
    }

    public function test_laporan_bulanan_memakai_jp(): void
    {
        $data = app(LaporanBulananService::class)->rekap(CarbonImmutable::parse('2026-09-01'));

        $budi = collect($data['pegawai'])->firstWhere('nama', 'Budi');

        // September: Budi mengajar 4 Senin (2+4 JP) + 4 Kamis (1 JP) minus
        // libur Kalender = setidaknya 7 JP per minggu x jumlah minggu.
        $this->assertSame(6, $budi['jp_terlaksana']);
        $this->assertGreaterThan(6, $budi['jp_terjadwal']);
        $this->assertSame(6, $data['ringkas']['total_jp_terlaksana'] - 3); // + Citra 3 JP

        $html = view('laporan.bulanan-pdf', $data)->render();
        $this->assertStringContainsString('JP Ajar', $html);
        $this->assertStringContainsString('6/' . $budi['jp_terjadwal'], $html);
    }
}
