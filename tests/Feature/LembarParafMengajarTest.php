<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengajuanIzinGuru;
use App\Models\User;
use App\Services\LembarParafMengajar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Lembar Paraf Guru Mengajar — cetakan A4 harian.
 *
 * Tanggal uji dikunci ke Kamis, 24 September 2026 pukul 11.00. Pukul 11.00
 * dipilih supaya ketiga keadaan "Catatan Sistem" muncul sekaligus: jadwal
 * pagi yang sudah lewat (tercatat / tidak tercatat), dan jadwal siang yang
 * belum waktunya.
 */
class LembarParafMengajarTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24'; // Kamis

    private User $kepsek;

    /** @var array<string, array{akun: User, pegawai: Pegawai}> */
    private array $guru = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TANGGAL . ' 11:00:00'));

        $this->kepsek = User::factory()->kepsek()->create(['name' => 'Drs. Kepala Uji']);
        Pegawai::factory()->create(['user_id' => $this->kepsek->id, 'nama' => 'Drs. Kepala Uji', 'nip' => '197001012000031001']);

        $rpl = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        $tkj = Kelas::factory()->create(['nama_kelas' => 'X TKJ 2']);

        foreach (['budi' => 'Budi Santoso', 'citra' => 'Citra Lestari', 'dedi' => 'Dedi Kurnia', 'eka' => 'Eka Putri', 'fajar' => 'Fajar Nugroho'] as $kunci => $nama) {
            $akun = User::factory()->guru()->create(['name' => $nama]);
            $this->guru[$kunci] = [
                'akun' => $akun,
                'pegawai' => Pegawai::factory()->create(['user_id' => $akun->id, 'nama' => $nama]),
            ];
        }

        $jadwal = fn (string $guru, Kelas $kelas, string $mulai, string $selesai, string $mapel, Hari $hari = Hari::Kamis) => JadwalPelajaran::create([
            'hari' => $hari->value,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'mata_pelajaran' => $mapel,
            'kelas_id' => $kelas->id,
            'guru_id' => $this->guru[$guru]['pegawai']->id,
        ]);

        // Budi mengajar kelas YANG SAMA dua kali sehari — kasus yang
        // menguji pencocokan sesi berdasarkan jam terdekat.
        $jadwal('budi', $rpl, '07:00', '08:30', 'Matematika');
        $jadwal('budi', $rpl, '09:30', '10:30', 'Matematika');
        $jadwal('citra', $tkj, '08:30', '10:00', 'Bahasa Inggris');
        $jadwal('dedi', $tkj, '07:00', '08:30', 'Basis Data');
        $jadwal('eka', $rpl, '12:30', '14:00', 'Pemrograman Web');
        $jadwal('fajar', $tkj, '10:15', '11:45', 'PPKn');

        // Jadwal hari lain TIDAK boleh ikut tercetak.
        $jadwal('budi', $tkj, '07:00', '08:30', 'Matematika Jumat', Hari::Jumat);

        // Sesi Budi: stiker QR ditulis dengan dua gaya berbeda.
        AbsensiMengajar::create([
            'user_id' => $this->guru['budi']['akun']->id,
            'kode_kelas' => 'Ruang XI-RPL 1',
            'waktu_mulai' => self::TANGGAL . ' 07:03:00',
            'waktu_selesai' => self::TANGGAL . ' 08:25:00',
        ]);
        AbsensiMengajar::create([
            'user_id' => $this->guru['budi']['akun']->id,
            'kode_kelas' => 'xi_rpl_1',
            'waktu_mulai' => self::TANGGAL . ' 09:34:00',
        ]);

        // Citra: izin ITT DISETUJUI mencakup tanggal ini.
        $this->izin('citra', StatusApproval::Disetujui, JenisIzinGuru::Itt, '2026-09-23', '2026-09-25');

        // Dedi: izin masih MENUNGGU persetujuan.
        $this->izin('dedi', StatusApproval::Pending, JenisIzinGuru::Idt, self::TANGGAL, self::TANGGAL);

        // Fajar: izinnya DITOLAK — tidak boleh tercetak sebagai izin.
        $this->izin('fajar', StatusApproval::Ditolak, JenisIzinGuru::Itt, self::TANGGAL, self::TANGGAL);

        // Eka: tercatat SAKIT lewat absensi harian (bukan lewat pengajuan).
        AbsensiPegawai::create([
            'pegawai_id' => $this->guru['eka']['pegawai']->id,
            'tanggal' => self::TANGGAL,
            'status' => AbsensiStatus::Sakit,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function izin(string $guru, StatusApproval $status, JenisIzinGuru $jenis, string $mulai, string $selesai): void
    {
        PengajuanIzinGuru::create([
            'guru_id' => $this->guru[$guru]['akun']->id,
            'tanggal_mulai' => $mulai,
            'tanggal_selesai' => $selesai,
            'jenis_izin' => $jenis,
            'alasan' => 'Keperluan keluarga',
            'status_approval' => $status,
        ]);
    }

    private function susun(string $urut = 'jam'): array
    {
        return app(LembarParafMengajar::class)->susun(Carbon::parse(self::TANGGAL), $urut);
    }

    /** Baris pertama milik guru $nama dengan jam mulai $jam. */
    private function baris(array $data, string $nama, string $jam): array
    {
        $ketemu = collect($data['baris'])->first(fn ($b) => $b['guru'] === $nama && $b['jam_mulai'] === $jam);
        $this->assertNotNull($ketemu, "Baris {$nama} jam {$jam} tidak ada.");

        return $ketemu;
    }

    /* ===================== ISI LEMBAR ===================== */

    public function test_hanya_jadwal_hari_itu_yang_tercetak(): void
    {
        $data = $this->susun();

        $this->assertCount(6, $data['baris']);
        $this->assertSame('Kamis', $data['hari']);
        $this->assertNotContains('Matematika Jumat', array_column($data['baris'], 'mapel'));
    }

    public function test_izin_yang_disetujui_mengisi_kolom_paraf_dengan_guru_izin(): void
    {
        $citra = $this->baris($this->susun(), 'Citra Lestari', '08:30');

        $this->assertSame('GURU IZIN', $citra['izin']['label']);
        $this->assertSame(JenisIzinGuru::Itt->label(), $citra['izin']['rinci']);
    }

    public function test_sakit_dari_absensi_harian_mengisi_kolom_paraf_dengan_guru_sakit(): void
    {
        $eka = $this->baris($this->susun(), 'Eka Putri', '12:30');

        $this->assertSame('GURU SAKIT', $eka['izin']['label']);
    }

    /**
     * Pengajuan yang belum disetujui masih bisa ditolak. Kalau kolomnya
     * sudah tercetak "GURU IZIN", lembar bukti memuat keterangan yang
     * belum sah.
     */
    public function test_izin_yang_belum_disetujui_tidak_mengisi_kolom_paraf(): void
    {
        $dedi = $this->baris($this->susun(), 'Dedi Kurnia', '07:00');

        $this->assertNull($dedi['izin']);
        $this->assertTrue($dedi['izin_menunggu']);
    }

    public function test_izin_yang_ditolak_diperlakukan_seperti_tidak_ada_izin(): void
    {
        $fajar = $this->baris($this->susun(), 'Fajar Nugroho', '10:15');

        $this->assertNull($fajar['izin']);
        $this->assertFalse($fajar['izin_menunggu']);
    }

    /**
     * Budi mengajar XI RPL 1 dua kali. Tanpa pemilihan jam terdekat, kedua
     * barisnya tercetak "Scan 07:03" — keterangan yang keliru pada dokumen
     * yang justru dibuat untuk pembuktian.
     */
    public function test_guru_yang_mengajar_kelas_sama_dua_kali_mendapat_jam_scan_masing_masing(): void
    {
        $data = $this->susun();

        $this->assertSame('Scan 07:03–08:25', $this->baris($data, 'Budi Santoso', '07:00')['sistem']['teks']);
        $this->assertSame('Scan 09:34, belum diakhiri', $this->baris($data, 'Budi Santoso', '09:30')['sistem']['teks']);
    }

    public function test_jadwal_yang_belum_dimulai_tidak_disebut_tidak_ada_scan(): void
    {
        $data = $this->susun();

        // Pukul 11.00: jadwal 12.30 belum waktunya, jadwal 07.00 sudah lewat.
        $this->assertSame('Belum waktunya', $this->baris($data, 'Eka Putri', '12:30')['sistem']['teks']);
        $this->assertSame('Tidak ada scan', $this->baris($data, 'Dedi Kurnia', '07:00')['sistem']['teks']);
    }

    public function test_ringkasan_menghitung_guru_izin_per_orang_bukan_per_baris(): void
    {
        $r = $this->susun()['ringkas'];

        $this->assertSame(6, $r['jadwal']);
        $this->assertSame(5, $r['guru']);
        $this->assertSame(2, $r['guru_izin']); // Citra (izin) + Eka (sakit)
        $this->assertSame(2, $r['tercatat']);  // dua sesi Budi
    }

    public function test_urutan_jam_dan_urutan_guru(): void
    {
        $perJam = array_column($this->susun('jam')['baris'], 'jam_mulai');
        $this->assertSame(['07:00', '07:00', '08:30', '09:30', '10:15', '12:30'], $perJam);

        $perGuru = array_column($this->susun('guru')['baris'], 'guru');
        $this->assertSame(['Budi Santoso', 'Budi Santoso', 'Citra Lestari', 'Dedi Kurnia', 'Eka Putri', 'Fajar Nugroho'], $perGuru);

        // Nomor urut selalu 1..n mengikuti urutan akhir.
        $this->assertSame([1, 2, 3, 4, 5, 6], array_column($this->susun('guru')['baris'], 'no'));
    }

    public function test_nama_kepala_sekolah_diambil_untuk_blok_tanda_tangan(): void
    {
        $kepsek = $this->susun()['kepsek'];

        $this->assertSame('Drs. Kepala Uji', $kepsek['nama']);
        $this->assertSame('197001012000031001', $kepsek['nip']);
    }

    /* ===================== PERFORMA ===================== */

    /**
     * Jumlah query TIDAK boleh bertambah mengikuti jumlah jadwal. Diuji
     * dengan menambah 40 jadwal + 40 guru baru: kalau ada relasi yang lupa
     * di-eager-load, selisih query-nya langsung puluhan.
     */
    public function test_jumlah_query_tetap_walau_jadwal_bertambah_banyak(): void
    {
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->susun();
            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $sebelum = $hitung();

        $kelas = Kelas::factory()->create(['nama_kelas' => 'XII AKL 3']);
        for ($i = 0; $i < 40; $i++) {
            $akun = User::factory()->guru()->create();
            $pegawai = Pegawai::factory()->create(['user_id' => $akun->id]);
            JadwalPelajaran::create([
                'hari' => Hari::Kamis->value, 'jam_mulai' => '13:00', 'jam_selesai' => '14:00',
                'mata_pelajaran' => 'Mapel ' . $i, 'kelas_id' => $kelas->id, 'guru_id' => $pegawai->id,
            ]);
            AbsensiMengajar::create([
                'user_id' => $akun->id, 'kode_kelas' => 'XII AKL 3',
                'waktu_mulai' => self::TANGGAL . ' 10:00:00',
            ]);
        }

        $sesudah = $hitung();

        $this->assertSame($sebelum, $sesudah, "Query bertambah dari {$sebelum} menjadi {$sesudah} — ada N+1.");
        // Rinciannya 11: jadwal + kelas + guru (eager load), izin disetujui,
        // izin menunggu, absensi harian, sesi mengajar, kepala sekolah +
        // pegawainya, pengaturan hari KBM + agenda kalender. Angka ini
        // boleh naik SEDIKIT kalau ada sumber data baru, tapi tidak pernah
        // boleh ikut naik bersama jumlah jadwal.
        $this->assertLessThanOrEqual(12, $sesudah);
    }

    /* ===================== HALAMAN & PDF ===================== */

    public function test_kepsek_dan_super_admin_bisa_membuka_halaman(): void
    {
        $this->actingAs($this->kepsek)
            ->get('/kepsek/lembar-paraf-mengajar?tanggal=' . self::TANGGAL)
            ->assertOk()
            ->assertSee('Lembar Paraf Guru Mengajar')
            ->assertSee('Citra Lestari')
            ->assertSee('GURU IZIN');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->get('/super-admin/lembar-paraf-mengajar?tanggal=' . self::TANGGAL)
            ->assertOk();
    }

    public function test_guru_biasa_tidak_bisa_membuka_lembar_paraf(): void
    {
        $guru = $this->guru['budi']['akun'];

        $this->actingAs($guru)->get('/kepsek/lembar-paraf-mengajar')->assertForbidden();
        $this->actingAs($guru)->get('/kepsek/lembar-paraf-mengajar/cetak')->assertForbidden();
    }

    public function test_pdf_berukuran_a4_potrait_dan_bisa_dibuka_di_tab(): void
    {
        $respons = $this->actingAs($this->kepsek)
            ->get('/kepsek/lembar-paraf-mengajar/cetak?lihat=1&tanggal=' . self::TANGGAL);

        $respons->assertOk();
        $respons->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('inline;', $respons->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Lembar-Paraf-Mengajar-2026-09-24.pdf', $respons->headers->get('Content-Disposition'));

        $isi = $respons->getContent();
        $this->assertStringStartsWith('%PDF', $isi);

        // A4 potrait = 595.28 x 841.89 pt. Kalau orientasinya berubah ke
        // landscape, angka ini tertukar.
        $this->assertMatchesRegularExpression('/MediaBox\s*\[\s*0(\.0+)?\s+0(\.0+)?\s+595\.\d+\s+841\.\d+\s*\]/', $isi);
    }

    public function test_tanpa_lihat_pdf_diunduh_sebagai_berkas(): void
    {
        $respons = $this->actingAs($this->kepsek)
            ->get('/kepsek/lembar-paraf-mengajar/cetak?tanggal=' . self::TANGGAL);

        $this->assertStringStartsWith('attachment;', $respons->headers->get('Content-Disposition'));
    }

    /**
     * Isi PDF dirender dari view ini. Diuji lewat HTML-nya karena teks di
     * dalam PDF terkompresi dan tidak bisa dicari langsung.
     */
    public function test_isi_cetakan_memuat_tanggal_cetak_paraf_izin_dan_tanda_tangan(): void
    {
        $data = $this->susun() + [
            'dicetak_pada' => now(),
            'dicetak_oleh' => 'Drs. Kepala Uji',
            'peran_pencetak' => 'Kepala Sekolah',
        ];

        $html = view('laporan.lembar-paraf-mengajar-pdf', $data)->render();

        $this->assertStringContainsString('LEMBAR PARAF GURU MENGAJAR', $html);
        $this->assertStringContainsString('Kamis, 24 September 2026', $html);
        $this->assertStringContainsString('pukul 11.00 WIB', $html);
        $this->assertStringContainsString('Dicetak:', $html);
        $this->assertStringContainsString('GURU IZIN', $html);
        $this->assertStringContainsString('GURU SAKIT', $html);
        $this->assertStringContainsString('izin diajukan, belum disetujui', $html);
        $this->assertStringContainsString('Drs. Kepala Uji', $html);

        // Tepat satu stempel izin per baris izin: Citra (1 baris) + Eka (1 baris).
        $this->assertSame(2, substr_count($html, 'class="cap"'));
    }

    public function test_tanggal_tidak_valid_ditolak(): void
    {
        $this->actingAs($this->kepsek)
            ->get('/kepsek/lembar-paraf-mengajar/cetak?tanggal=24-09-2026')
            ->assertSessionHasErrors('tanggal');
    }

    public function test_hari_tanpa_jadwal_tetap_bisa_dicetak(): void
    {
        // Minggu, 27 September 2026 — tidak ada jadwal di data uji.
        $this->actingAs($this->kepsek)
            ->get('/kepsek/lembar-paraf-mengajar?tanggal=2026-09-27')
            ->assertOk()
            ->assertSee('Tidak ada jadwal pelajaran');

        $this->actingAs($this->kepsek)
            ->get('/kepsek/lembar-paraf-mengajar/cetak?tanggal=2026-09-27')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
