<?php

namespace Tests\Feature;

use App\Console\Commands\TandaiAlpaSiswa;
use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\JenisAgenda;
use App\Enums\StatusKbm;
use App\Livewire\WaliMurid\PantauanKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\AgendaAkademik;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\Pengumuman;
use App\Models\Siswa;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use App\Services\StatusKedatanganAnak;
use App\Support\AnakTerpilih;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Beranda wali murid, kartu status kedatangan, dan bilah menu bawah.
 *
 * Tanggal uji: Kamis, 24 September 2026 (hari KBM). Jam masuk 07.00,
 * batas terlambat 07.15, jam pulang 14.00.
 */
class BerandaWaliMuridTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24'; // Kamis

    private User $wali;

    private Siswa $andi;

    private Siswa $bela;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->jam('06:10');

        Pengaturan::simpan('jam_masuk_siswa', '07:00');
        Pengaturan::simpan('batas_terlambat_siswa', '07:15');
        Pengaturan::simpan('jam_pulang_siswa', '14:00');

        $waliKelas = User::factory()->waliKelas()->create(['name' => 'Bu Rina', 'no_hp' => '0812-3456-7890']);
        $this->kelas = Kelas::factory()->create(['nama_kelas' => 'X RPL 1', 'wali_kelas_id' => $waliKelas->id]);

        $this->wali = User::factory()->waliMurid()->create(['name' => 'Pak Harun']);
        $this->andi = Siswa::factory()->for($this->kelas)->create(['nama' => 'Andi Saputra', 'wali_murid_id' => $this->wali->id]);
        $this->bela = Siswa::factory()->for($this->kelas)->create(['nama' => 'Bela Saputri', 'wali_murid_id' => $this->wali->id]);

        $guru = Pegawai::factory()->create(['nama' => 'Budi Santoso']);
        foreach ([['07:00', '08:30', 'Matematika'], ['09:00', '10:30', 'Fisika'], ['12:30', '14:00', 'Bahasa Indonesia']] as [$m, $s, $mapel]) {
            JadwalPelajaran::create([
                'hari' => Hari::Kamis->value, 'jam_mulai' => $m, 'jam_selesai' => $s,
                'mata_pelajaran' => $mapel, 'kelas_id' => $this->kelas->id, 'guru_id' => $guru->id,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function jam(string $jam, string $tanggal = self::TANGGAL): void
    {
        Carbon::setTestNow(Carbon::parse("{$tanggal} {$jam}:00"));
    }

    private function kartu(?AbsensiSiswa $a = null): array
    {
        return app(StatusKedatanganAnak::class)->untuk($a);
    }

    private function absen(Siswa $s, AbsensiStatus $status, ?string $jam = null, ?string $ket = null, string $tanggal = self::TANGGAL): AbsensiSiswa
    {
        return AbsensiSiswa::create(['siswa_id' => $s->id, 'tanggal' => $tanggal, 'jam_masuk' => $jam, 'status' => $status, 'keterangan' => $ket]);
    }

    /* ===================== KARTU STATUS ===================== */

    public function test_pagi_sebelum_batas_masuk_belum_tiba_bukan_merah(): void
    {
        $s = $this->kartu();

        $this->assertSame('belum', $s['jenis']);
        $this->assertSame('Belum tiba di sekolah', $s['judul']);
        $this->assertStringContainsString('07.15', $s['keterangan']);
        $this->assertFalse($s['hubungi']);

        // 07.10: jam masuk lewat, tapi batas terlambat belum — masih "belum tiba".
        $this->jam('07:10');
        $this->assertSame('belum', $this->kartu()['jenis']);
    }

    public function test_lewat_batas_tanpa_catatan_kuning_dan_ada_tombol_hubungi(): void
    {
        $this->jam('08:00');
        $s = $this->kartu();

        $this->assertSame('telat', $s['jenis']);
        $this->assertSame('Belum tercatat di gerbang', $s['judul']);
        $this->assertTrue($s['hubungi']);
    }

    public function test_lewat_jam_pulang_tanpa_catatan(): void
    {
        $this->jam('15:00');

        $this->assertSame('Tidak tercatat hadir hari ini', $this->kartu()['judul']);
        $this->assertSame('telat', $this->kartu()['jenis']);
    }

    public function test_hari_libur_mingguan_dan_agenda_libur(): void
    {
        // Jumat 25 Sep: libur mingguan bawaan sekolah.
        $this->jam('09:00', '2026-09-25');
        $this->assertSame('libur', $this->kartu()['jenis']);

        // Kamis dengan agenda libur di Kalender Pendidikan.
        $this->jam('09:00');
        AgendaAkademik::create(['judul' => 'Libur Maulid', 'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => self::TANGGAL, 'jenis' => JenisAgenda::Libur]);
        $s = app(StatusKedatanganAnak::class)->untuk(null);

        $this->assertSame('libur', $s['jenis']);
        $this->assertStringContainsString('Libur Maulid', $s['keterangan']);
    }

    public function test_hadir_tepat_waktu_dan_terlambat(): void
    {
        $this->jam('09:00');

        $s = $this->kartu($this->absen($this->andi, AbsensiStatus::Hadir, '06:52'));
        $this->assertSame('hadir', $s['jenis']);
        $this->assertSame('Sudah tiba · 06.52', $s['judul']);
        $this->assertStringNotContainsString('terlambat', $s['keterangan']);

        $s = $this->kartu($this->absen($this->bela, AbsensiStatus::Hadir, '07:20'));
        $this->assertStringContainsString('terlambat', $s['keterangan']);
    }

    public function test_hanya_alpa_resmi_yang_merah_dan_penanda_otomatis_diterjemahkan(): void
    {
        $this->jam('15:00');

        $s = $this->kartu($this->absen($this->andi, AbsensiStatus::Alpha, null, TandaiAlpaSiswa::PENANDA));

        $this->assertSame('alpa', $s['jenis']);
        $this->assertTrue($s['hubungi']);
        $this->assertStringNotContainsString('Ditandai otomatis', $s['keterangan']);
        $this->assertStringContainsString('jam pulang', $s['keterangan']);
    }

    public function test_izin_menampilkan_keterangan_dari_sekolah(): void
    {
        $s = $this->kartu($this->absen($this->andi, AbsensiStatus::Sakit, null, 'Demam, surat dokter menyusul'));

        $this->assertSame('sakit', $s['jenis']);
        $this->assertSame('Demam, surat dokter menyusul', $s['keterangan']);
    }

    public function test_tautan_whatsapp_wali_kelas_dan_tanpa_nomor(): void
    {
        $anak = $this->andi->load('kelas.waliKelas.pegawai');
        $kontak = app(StatusKedatanganAnak::class)->kontakWaliKelas($anak);

        $this->assertSame('Bu Rina', $kontak['nama']);
        $this->assertStringStartsWith('https://wa.me/6281234567890?text=', $kontak['url']);
        $this->assertStringContainsString(rawurlencode('Andi Saputra'), $kontak['url']);

        $this->kelas->waliKelas->update(['no_hp' => null]);
        $this->assertNull(app(StatusKedatanganAnak::class)->kontakWaliKelas($this->andi->fresh()->load('kelas.waliKelas.pegawai')));
    }

    /* ===================== HALAMAN BERANDA ===================== */

    public function test_beranda_pagi_tidak_lagi_menampilkan_belum_absen_merah(): void
    {
        $this->actingAs($this->wali)->get('/wali-murid/dashboard')
            ->assertOk()
            ->assertSee('Belum tiba di sekolah')
            ->assertSee('data-status-kedatangan="belum"', false)
            ->assertDontSee('Belum Absen')
            // Pemilih anak (dua anak).
            ->assertSee('aria-label="Pilih anak"', false)
            ->assertSee('Andi')->assertSee('Bela')
            // Pelajaran hari ini.
            ->assertSee('Matematika')->assertSee('Belum mulai');
    }

    public function test_pelajaran_hari_ini_mengikuti_jam_dan_catatan_guru(): void
    {
        $this->jam('09:30');
        $jadwalMtk = JadwalPelajaran::where('mata_pelajaran', 'Matematika')->first();
        AbsensiKbmSiswa::create(['jadwal_id' => $jadwalMtk->id, 'siswa_id' => $this->andi->id, 'tanggal' => self::TANGGAL, 'status' => StatusKbm::Hadir]);

        $data = $this->actingAs($this->wali)->get('/wali-murid/dashboard')->assertOk()->viewData('pelajaran');

        $this->assertSame(['Hadir', 'Berlangsung', 'Belum mulai'], array_column($data, 'status'));

        $this->jam('15:00');
        $data = $this->actingAs($this->wali)->get('/wali-murid/dashboard')->viewData('pelajaran');
        // Jurnal yang tidak diisi guru: abu-abu "Belum diisi guru", bukan alpa.
        $this->assertSame(['Hadir', 'Belum diisi guru', 'Belum diisi guru'], array_column($data, 'status'));
    }

    public function test_hari_libur_tidak_menampilkan_jadwal(): void
    {
        $this->jam('09:00');
        AgendaAkademik::create(['judul' => 'Libur Nasional', 'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => self::TANGGAL, 'jenis' => JenisAgenda::Libur]);

        $this->actingAs($this->wali)->get('/wali-murid/dashboard')
            ->assertOk()
            ->assertSee('Hari ini libur')
            ->assertSee('Tidak ada pelajaran — hari ini libur.')
            ->assertDontSee('Matematika');
    }

    public function test_kehadiran_bulan_ini_memakai_rumus_rekap_akademik(): void
    {
        $this->jam('09:00');
        foreach (['2026-09-01', '2026-09-02', '2026-09-03'] as $t) {
            $this->absen($this->andi, AbsensiStatus::Hadir, '06:50', null, $t);
        }
        $this->absen($this->andi, AbsensiStatus::Sakit, null, 'Flu', '2026-09-07');
        // Bulan lalu tidak ikut.
        $this->absen($this->andi, AbsensiStatus::Alpha, null, null, '2026-08-31');

        $bulan = $this->actingAs($this->wali)->get('/wali-murid/dashboard')->viewData('bulan');

        $this->assertSame(4, $bulan['total']);
        $this->assertSame(3, $bulan['hadir']);
        $this->assertSame(1, $bulan['sakit']);
        $this->assertSame(0, $bulan['alpa']);
        $this->assertSame(75, $bulan['persen']);
    }

    public function test_pengumuman_terbaru_tampil_lengkap_dan_yang_lama_tidak(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $p = Pengumuman::create(['user_id' => $admin->id, 'judul' => 'Rapat Wali Murid', 'isi_pesan' => str_repeat('Isi pengumuman. ', 12) . 'AKHIR PENGUMUMAN', 'target_role' => 'semua']);
        $this->wali->notify(PengumumanBaru::dari($p));

        $this->actingAs($this->wali)->get('/wali-murid/dashboard')
            ->assertSee('Rapat Wali Murid')
            ->assertSee('AKHIR PENGUMUMAN')
            ->assertSee('Baru');

        DB::table('notifications')->update(['created_at' => now()->subDays(40)]);

        $this->assertNull($this->actingAs($this->wali)->get('/wali-murid/dashboard')->viewData('pengumuman'));
    }

    public function test_wali_tanpa_anak_tetap_bisa_membuka_beranda(): void
    {
        $this->actingAs(User::factory()->waliMurid()->create())
            ->get('/wali-murid/dashboard')
            ->assertOk()
            ->assertSee('Belum ada anak yang tertaut');
    }

    public function test_jumlah_query_beranda_tetap_walau_jadwal_bertambah(): void
    {
        $this->jam('09:30');
        $hitung = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->wali)->get('/wali-murid/dashboard')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        // Pemanasan: permintaan pertama dalam satu tes membawa query
        // kerangka sekali-jalan (profil pegawai di topbar) yang tidak
        // ada hubungannya dengan Beranda.
        $hitung();
        $sebelum = $hitung();

        $guru = Pegawai::factory()->create();
        for ($i = 0; $i < 6; $i++) {
            $j = JadwalPelajaran::create(['hari' => Hari::Kamis->value, 'jam_mulai' => '15:00', 'jam_selesai' => '16:00',
                'mata_pelajaran' => 'Tambahan ' . $i, 'kelas_id' => $this->kelas->id, 'guru_id' => $guru->id]);
            AbsensiKbmSiswa::create(['jadwal_id' => $j->id, 'siswa_id' => $this->andi->id, 'tanggal' => self::TANGGAL, 'status' => StatusKbm::Hadir]);
        }

        $this->assertSame($sebelum, $hitung(), 'Query Beranda bertambah mengikuti jadwal — ada N+1.');
    }

    /* ===================== ANAK DIINGAT ANTARHALAMAN ===================== */

    public function test_anak_yang_dipilih_diingat_di_halaman_lain(): void
    {
        $this->actingAs($this->wali)->get('/wali-murid/dashboard?anak=' . $this->bela->id)->assertOk();

        $this->assertSame($this->bela->id, session(AnakTerpilih::KUNCI_SESSION));

        $this->actingAs($this->wali)->get('/wali-murid/absensi-kedatangan')
            ->assertOk()
            ->assertViewHas('anak', fn ($a) => $a->id === $this->bela->id);

        $this->actingAs($this->wali)->get('/wali-murid/rapor')
            ->assertOk()
            ->assertViewHas('anak', fn ($a) => $a->id === $this->bela->id);

        Livewire::actingAs($this->wali)->test(PantauanKbm::class)
            ->assertSet('anak_id', $this->bela->id);
    }

    public function test_id_anak_keluarga_lain_tidak_pernah_tersimpan_atau_tampil(): void
    {
        $lain = Siswa::factory()->for($this->kelas)->create(['nama' => 'Anak Orang Lain', 'wali_murid_id' => User::factory()->waliMurid()->create()->id]);

        $this->actingAs($this->wali)->get('/wali-murid/dashboard?anak=' . $lain->id)
            ->assertOk()
            ->assertDontSee('Anak Orang Lain')
            ->assertViewHas('anak', fn ($a) => $a->id === $this->andi->id);

        $this->assertNull(session(AnakTerpilih::KUNCI_SESSION));

        // Lewat properti Livewire dari konsol browser.
        Livewire::actingAs($this->wali)->test(PantauanKbm::class)
            ->set('anak_id', $lain->id)
            ->assertSet('anak_id', $this->andi->id);
    }

    /* ===================== BILAH MENU BAWAH ===================== */

    public function test_menu_bawah_muncul_untuk_wali_murid_dengan_butir_aktif(): void
    {
        $this->actingAs($this->wali)->get('/wali-murid/dashboard')
            ->assertSee('aria-label="Menu utama"', false)
            ->assertSeeInOrder(['Beranda', 'Kehadiran', 'Jadwal', 'Rapor', 'Akun']);

        // "Kehadiran" menyala juga di Pantauan KBM.
        $html = $this->actingAs($this->wali)->get('/wali-murid/pantauan-kbm')->assertOk()->getContent();
        $menu = substr($html, strpos($html, 'aria-label="Menu utama"'));
        $this->assertMatchesRegularExpression('~href="[^"]*/wali-murid/absensi-kedatangan"\s+aria-current="page"~', $menu);
    }

    public function test_menu_bawah_tidak_muncul_untuk_peran_lain(): void
    {
        $guru = User::factory()->guru()->create();
        Pegawai::factory()->create(['user_id' => $guru->id]);

        $this->actingAs($guru)->get('/guru/dashboard')
            ->assertOk()
            ->assertDontSee('aria-label="Menu utama"', false)
            // Ruang kosong untuk bilah bawah juga tidak ikut dipasang.
            ->assertDontSee('pb-28 md:pb-28', false);
    }
}
