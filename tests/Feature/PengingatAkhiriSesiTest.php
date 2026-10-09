<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\User;
use App\Notifications\AkhiriSesiBelumDitekan;
use App\Services\PengingatAkhiriSesi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengingat "Akhiri Sesi": 5 menit sesudah KBM selesai, guru yang belum
 * mengakhiri sesinya diingatkan (push + lonceng + alarm di layar). Batas
 * menekan tombolnya 15 menit.
 *
 * Jadwal uji: Basis Data XI RPL 1, Kamis 07:00–08:10.
 *   08:14 -> belum waktunya
 *   08:15 -> pengingat (+5)
 *   08:25 -> batas (+15), tidak lagi diingatkan
 */
class PengingatAkhiriSesiTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24'; // Kamis

    private User $guru;

    private Pegawai $pegawai;

    private Kelas $kelas;

    private JadwalPelajaran $jadwal;

    private AbsensiMengajar $sesi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pada('07:10');
        $this->siapkanFirebasePalsu();

        $this->guru = User::factory()->guru()->create(['name' => 'Budi Guru', 'fcm_token' => 'TOKEN-HP-BUDI']);
        $this->pegawai = Pegawai::factory()->create(['user_id' => $this->guru->id, 'nama' => 'Budi Guru']);
        $this->kelas = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);

        $this->jadwal = JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => '07:00', 'jam_selesai' => '08:10',
            'mata_pelajaran' => 'Basis Data', 'kelas_id' => $this->kelas->id, 'guru_id' => $this->pegawai->id,
        ]);

        AbsensiPegawai::create([
            'pegawai_id' => $this->pegawai->id, 'tanggal' => self::TANGGAL,
            'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:50:00',
        ]);

        $this->sesi = AbsensiMengajar::create([
            'user_id' => $this->guru->id, 'kode_kelas' => 'Ruang XI-RPL 1',
            'waktu_mulai' => self::TANGGAL . ' 07:02:00',
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

    /** Firebase dianggap aktif, server Google dipalsukan. */
    private function siapkanFirebasePalsu(): void
    {
        // Kunci RSA sementara untuk menandatangani token palsu. Berkas
        // konfigurasi disertakan karena PHP di Windows (Laragon) sering tidak
        // menemukan openssl.cnf bawaan — tanpa itu openssl_pkey_new() gagal
        // dan SELURUH tes di kelas ini ikut gagal.
        $opsi = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'config' => base_path('tests/Fixtures/openssl.cnf')];
        $kunci = openssl_pkey_new($opsi);

        if ($kunci === false || ! openssl_pkey_export($kunci, $pem, null, $opsi)) {
            $this->markTestSkipped('OpenSSL di komputer ini tidak bisa membuat kunci RSA.');
        }

        config([
            'firebase.enabled' => true, 'firebase.project_id' => 'uji', 'firebase.vapid_key' => 'v',
            'firebase.web.apiKey' => 'a', 'firebase.client_email' => 'x@uji.iam.gserviceaccount.com',
            'firebase.private_key' => $pem, 'firebase.queue_connection' => 'sync',
        ]);

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'ok']),
        ]);
    }

    private function jumlahPushTerkirim(): int
    {
        return collect(Http::recorded())
            ->filter(fn ($pasangan) => str_contains($pasangan[0]->url(), 'fcm.googleapis.com'))
            ->count();
    }

    private function jalankanPerintah(): void
    {
        $this->assertSame(0, Artisan::call('simagas:pengingat-akhiri-sesi'));
    }

    // ================= PERINTAH TERJADWAL (push + lonceng) =================

    public function test_belum_lewat_lima_menit_tidak_ada_pengingat(): void
    {
        $this->pada('08:14');
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
        $this->assertSame(0, $this->jumlahPushTerkirim());
        $this->assertNull($this->sesi->fresh()->pengingat_akhiri_pada);
    }

    public function test_lima_menit_sesudah_selesai_guru_diingatkan_lewat_push_dan_lonceng(): void
    {
        $this->pada('08:15');
        $this->jalankanPerintah();

        // Lonceng
        $notif = $this->guru->notifications()->first();
        $this->assertNotNull($notif);
        $this->assertSame(AkhiriSesiBelumDitekan::class, $notif->type);
        $this->assertSame('.jurnal-kelas', $notif->data['rute']);
        $this->assertStringContainsString('Basis Data (XI RPL 1)', $notif->data['pesan']);
        $this->assertStringContainsString('08:25', $notif->data['pesan']);

        // Push ke HP, dengan prioritas tinggi supaya langsung berbunyi.
        Http::assertSent(function ($r) {
            if (! str_contains($r->url(), 'fcm.googleapis.com')) {
                return false;
            }
            $m = $r['message'];

            return $m['token'] === 'TOKEN-HP-BUDI'
                && $m['data']['judul'] === 'Sesi kelas belum diakhiri'
                && $m['data']['jenis'] === 'pengingat-akhiri-sesi'
                && str_ends_with($m['data']['url'], '/guru/jurnal-kelas')
                && $m['webpush']['headers']['Urgency'] === 'high';
        });

        $this->assertNotNull($this->sesi->fresh()->pengingat_akhiri_pada);
    }

    public function test_pengingat_hanya_dikirim_sekali_walau_perintah_berjalan_tiap_menit(): void
    {
        foreach (['08:15', '08:16', '08:17', '08:20', '08:24'] as $jam) {
            $this->pada($jam);
            $this->jalankanPerintah();
        }

        $this->assertSame(1, $this->guru->notifications()->count());
        $this->assertSame(1, $this->jumlahPushTerkirim());
    }

    public function test_cron_terlambat_tetap_mengingatkan_selama_belum_lewat_batas(): void
    {
        $this->pada('08:21'); // cron hosting baru jalan 6 menit sesudah jadwal pengingat
        $this->jalankanPerintah();

        $this->assertSame(1, $this->guru->notifications()->count());
    }

    public function test_lewat_batas_lima_belas_menit_tidak_diingatkan_lagi(): void
    {
        $this->pada('08:25');
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
        $this->assertSame(0, $this->jumlahPushTerkirim());
    }

    public function test_sesi_yang_sudah_diakhiri_tidak_diingatkan(): void
    {
        $this->sesi->forceFill(['waktu_selesai' => self::TANGGAL . ' 08:11:00'])->save();

        $this->pada('08:15');
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
    }

    public function test_sesi_hari_lain_yang_terbuka_tidak_ikut_dihitung(): void
    {
        // Kemarin (Rabu) guru lupa mengakhiri sesi di ruangan yang sama;
        // hari ini sesinya sudah diakhiri dengan benar.
        AbsensiMengajar::create([
            'user_id' => $this->guru->id, 'kode_kelas' => 'XI RPL 1',
            'waktu_mulai' => '2026-09-23 07:02:00',
        ]);
        $this->sesi->forceFill(['waktu_selesai' => self::TANGGAL . ' 08:11:00'])->save();

        $this->pada('08:15');
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
    }

    public function test_dua_proses_bersamaan_hanya_satu_yang_mengirim(): void
    {
        // Simulasi balapan: tepat sesudah perintah ini membaca daftar sesi,
        // proses LAIN (cron yang menumpuk) sudah lebih dulu menandainya.
        AbsensiMengajar::retrieved(function (AbsensiMengajar $a) {
            DB::table('absensi_mengajar')->where('id', $a->id)
                ->update(['pengingat_akhiri_pada' => now()]);
        });

        $this->pada('08:15');
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
        $this->assertSame(0, $this->jumlahPushTerkirim());
    }

    public function test_guru_yang_tidak_scan_qr_tidak_diingatkan(): void
    {
        $this->sesi->delete();

        $this->pada('08:15');
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
    }

    public function test_tanpa_token_hp_tetap_dapat_lonceng_tanpa_push(): void
    {
        $this->guru->forceFill(['fcm_token' => null])->save();

        $this->pada('08:15');
        $this->jalankanPerintah();

        $this->assertSame(1, $this->guru->notifications()->count());
        $this->assertSame(0, $this->jumlahPushTerkirim());
    }

    public function test_hari_lain_tidak_diingatkan(): void
    {
        // Jumat — jadwal Kamis tidak berlaku, walau guru kebetulan scan
        // ruangan yang sama hari itu dan sesinya belum diakhiri.
        AbsensiMengajar::create([
            'user_id' => $this->guru->id, 'kode_kelas' => 'XI RPL 1',
            'waktu_mulai' => '2026-09-25 07:02:00',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-25 08:15:00'));
        $this->jalankanPerintah();

        $this->assertSame(0, $this->guru->notifications()->count());
    }

    public function test_sesi_guru_lain_di_ruangan_sama_tidak_dihitung(): void
    {
        $guruLain = User::factory()->guru()->create();
        Pegawai::factory()->create(['user_id' => $guruLain->id]);

        // Budi sudah mengakhiri sesinya; guru lain scan ruangan yang sama
        // tapi tidak punya jadwal di sana.
        $this->sesi->forceFill(['waktu_selesai' => self::TANGGAL . ' 08:11:00'])->save();
        AbsensiMengajar::create([
            'user_id' => $guruLain->id, 'kode_kelas' => 'XI RPL 1',
            'waktu_mulai' => self::TANGGAL . ' 07:05:00',
        ]);

        $this->pada('08:15');
        $this->jalankanPerintah();

        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_dua_jadwal_berurutan_di_ruangan_sama_hanya_satu_pengingat(): void
    {
        JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => '08:10', 'jam_selesai' => '08:12',
            'mata_pelajaran' => 'Basis Data', 'kelas_id' => $this->kelas->id, 'guru_id' => $this->pegawai->id,
        ]);

        $this->pada('08:17');
        $this->jalankanPerintah();

        $this->assertSame(1, $this->guru->notifications()->count());
    }

    public function test_uji_coba_tidak_mengirim_dan_tidak_menandai(): void
    {
        $this->pada('08:15');
        Artisan::call('simagas:pengingat-akhiri-sesi', ['--uji-coba' => true]);

        $this->assertStringContainsString('Akan diingatkan: Basis Data', Artisan::output());
        $this->assertSame(0, $this->guru->notifications()->count());
        $this->assertNull($this->sesi->fresh()->pengingat_akhiri_pada);
    }

    public function test_jumlah_query_tetap_walau_gurunya_banyak(): void
    {
        foreach (range(1, 8) as $i) {
            $u = User::factory()->guru()->create();
            $p = Pegawai::factory()->create(['user_id' => $u->id]);
            $k = Kelas::factory()->create(['nama_kelas' => "X TKJ {$i}"]);
            JadwalPelajaran::create([
                'hari' => Hari::Kamis->value, 'jam_mulai' => '07:00', 'jam_selesai' => '08:10',
                'mata_pelajaran' => 'Jaringan', 'kelas_id' => $k->id, 'guru_id' => $p->id,
            ]);
            AbsensiMengajar::create(['user_id' => $u->id, 'kode_kelas' => "X TKJ {$i}", 'waktu_mulai' => self::TANGGAL . ' 07:03:00']);
        }

        $this->pada('08:15');
        DB::enableQueryLog();
        $hasil = app(PengingatAkhiriSesi::class)->jatuhTempo(now());
        $jumlahQuery = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(9, $hasil);
        // jadwal + kelas + guru (eager load) + sesi = 4, berapa pun gurunya.
        $this->assertLessThanOrEqual(4, $jumlahQuery);
    }

    // ================= ALARM DI LAYAR (layout) =================

    public function test_layar_guru_memuat_alarm_dengan_waktu_pengingat_dan_batas(): void
    {
        $this->pada('08:00');

        $html = $this->actingAs($this->guru)->get('/guru/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('id="pengingat-akhiri-sesi"', $html);
        $this->assertStringContainsString('"batasJam":"08:25"', $html);
        $this->assertStringContainsString(
            '"pengingat":' . Carbon::parse(self::TANGGAL . ' 08:15:00')->getTimestampMs(), $html);
    }

    public function test_alarm_tidak_dimuat_bila_sesi_sudah_diakhiri_atau_lewat_batas(): void
    {
        $this->pada('08:26');
        $this->actingAs($this->guru)->get('/guru/dashboard')->assertOk()
            ->assertDontSee('id="pengingat-akhiri-sesi"', false);

        $this->pada('08:16');
        $this->sesi->forceFill(['waktu_selesai' => now()])->save();
        $this->actingAs($this->guru)->get('/guru/dashboard')->assertOk()
            ->assertDontSee('id="pengingat-akhiri-sesi"', false);
    }

    public function test_peran_tanpa_jurnal_tidak_memuat_alarm(): void
    {
        $this->pada('08:16');

        $this->actingAs(User::factory()->waliMurid()->create())->get('/wali-murid/dashboard')->assertOk()
            ->assertDontSee('id="pengingat-akhiri-sesi"', false);
    }

    public function test_akhiri_sesi_mematikan_alarm_lewat_event_browser(): void
    {
        $this->sesi->forceFill(['foto_bukti' => 'bukti-mengajar/a.jpg'])->save();
        $this->pada('08:16');

        Livewire::actingAs($this->guru)
            ->test(JurnalAbsenKelas::class)
            ->call('akhiriSesi')
            ->assertSet('notif.tipe', 'ok')
            ->assertDispatched('sesi-diakhiri', id: $this->sesi->id);

        // Sesudah diakhiri, perintah terjadwal pun tidak mengingatkan.
        $this->jalankanPerintah();
        $this->assertSame(0, $this->guru->notifications()->count());
    }

    public function test_akhiri_sesi_masih_bisa_sebelum_batas_dan_terkunci_sesudahnya(): void
    {
        $this->sesi->forceFill(['foto_bukti' => 'bukti-mengajar/a.jpg'])->save();

        // Fitur lama tidak berubah: menit ke-15 masih boleh, menit ke-16 tidak.
        $this->pada('08:26');
        Livewire::actingAs($this->guru)->test(JurnalAbsenKelas::class)
            ->call('akhiriSesi')->assertSet('notif.tipe', 'error');
        $this->assertNull($this->sesi->fresh()->waktu_selesai);

        $this->pada('08:24');
        Livewire::actingAs($this->guru)->test(JurnalAbsenKelas::class)
            ->call('akhiriSesi')->assertSet('notif.tipe', 'ok');
        $this->assertNotNull($this->sesi->fresh()->waktu_selesai);
    }
}
