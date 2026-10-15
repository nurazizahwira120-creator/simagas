<?php

namespace Tests\Feature;

use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Livewire\Monitoring\DetailSesi;
use App\Models\AbsensiMengajar;
use App\Models\HonorMengajar;
use App\Models\JadwalEkskul;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengajuanIzinGuru;
use App\Models\SesiEkskul;
use App\Models\User;
use App\Services\AturanHonor;
use App\Services\MonitoringKbmHarian;
use App\Services\PencocokSesiMengajar;
use App\Services\PengingatAkhiriSesi;
use App\Services\PenutupSesi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * MONITORING KBM HARI INI + BANTU AKHIRI SESI.
 *
 * Kamis, 24 September 2026 (1 JP = 35 menit):
 *   Budi  07.00–08.10 XI RPL 1 (2 JP) — scan 07.02, LUPA diakhiri
 *   Budi  12.00–13.10 XI RPL 1 (2 JP) — ruangan SAMA, sesi kedua
 *   Citra 08.30–10.00 X TKJ 1        — scan 08.31, diakhiri sendiri 10.01
 *   Dedi  10.15–11.45 X TKJ 1        — belum scan
 *   Eka   07.00–08.10 X TKJ 1        — tidak pernah scan
 *   Fajar 08.30–09.15 XI RPL 1       — izin disetujui
 */
class MonitoringKbmHarianTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24';

    /** @var array<string, User> */
    private array $guru = [];

    /** @var array<string, JadwalPelajaran> */
    private array $jadwal = [];

    private User $kepsek;

    private AbsensiMengajar $sesiBudi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pada('10:30');

        $rpl = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        $tkj = Kelas::factory()->create(['nama_kelas' => 'X TKJ 1']);

        foreach (['budi', 'citra', 'dedi', 'eka', 'fajar'] as $k) {
            $this->guru[$k] = User::factory()->guru()->create(['name' => ucfirst($k)]);
            Pegawai::factory()->create(['user_id' => $this->guru[$k]->id, 'nama' => ucfirst($k)]);
        }
        $this->kepsek = User::factory()->kepsek()->create(['name' => 'Bu Kepsek']);

        $buat = fn (string $g, Kelas $k, string $m, string $s, string $mapel) => JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => $m, 'jam_selesai' => $s, 'mata_pelajaran' => $mapel,
            'kelas_id' => $k->id, 'guru_id' => $this->guru[$g]->pegawai->id,
        ]);
        $this->jadwal['budi1'] = $buat('budi', $rpl, '07:00', '08:10', 'Basis Data');
        $this->jadwal['budi2'] = $buat('budi', $rpl, '12:00', '13:10', 'Basis Data');
        $this->jadwal['citra'] = $buat('citra', $tkj, '08:30', '10:00', 'Bahasa Inggris');
        $this->jadwal['dedi'] = $buat('dedi', $tkj, '10:15', '11:45', 'Matematika');
        $this->jadwal['eka'] = $buat('eka', $tkj, '07:00', '08:10', 'PPKn');
        $this->jadwal['fajar'] = $buat('fajar', $rpl, '08:30', '09:15', 'Fisika');

        $this->sesiBudi = $this->scan('budi', 'Ruang XI-RPL 1', '07:02');
        $this->scan('citra', 'X TKJ 1', '08:31', '10:01');

        PengajuanIzinGuru::create(['guru_id' => $this->guru['fajar']->id, 'tanggal_mulai' => self::TANGGAL, 'tanggal_selesai' => self::TANGGAL,
            'jenis_izin' => JenisIzinGuru::Itt, 'alasan' => 'Keluarga', 'status_approval' => StatusApproval::Disetujui]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function pada(string $jam, string $tanggal = self::TANGGAL): void
    {
        Carbon::setTestNow(Carbon::parse("{$tanggal} {$jam}:00"));
    }

    private function scan(string $g, string $kode, string $mulai, ?string $selesai = null, string $tanggal = self::TANGGAL): AbsensiMengajar
    {
        return AbsensiMengajar::create(['user_id' => $this->guru[$g]->id, 'kode_kelas' => $kode,
            'waktu_mulai' => "{$tanggal} {$mulai}:00", 'waktu_selesai' => $selesai ? "{$tanggal} {$selesai}:00" : null, 'foto_bukti' => 'x.jpg']);
    }

    private function petaStatus(): array
    {
        // Kunci = nama jadwal di fixture (dicari lewat id agar tidak membandingkan objek).
        $namaPerId = collect($this->jadwal)->mapWithKeys(fn ($j, $k) => [$j->id => $k]);

        return collect(app(MonitoringKbmHarian::class)->hariIni()['baris'])
            ->where('jenis', 'kbm')
            ->mapWithKeys(fn ($b) => [$namaPerId[$b['jadwal']->id] => $b['status']])->all();
    }

    private function akhiri(string $jenis, int $id, ?User $oleh = null, string $alasan = 'Guru lupa menekan tombol')
    {
        $oleh ??= $this->kepsek;

        return $this->actingAs($oleh)->from('/' . $oleh->role->routePrefix() . '/live-monitoring')
            ->post('/' . $oleh->role->routePrefix() . "/live-monitoring/sesi/{$jenis}/{$id}/akhiri", ['alasan' => $alasan]);
    }

    // ===================== TAMPILAN SEHARIAN =====================

    public function test_seluruh_jadwal_hari_ini_tampil_dengan_statusnya(): void
    {
        $this->assertSame([
            // Urut jam mulai, lalu nama kelas (X TKJ 1 sebelum XI RPL 1).
            'eka' => 'tidak_ada_sesi',
            'budi1' => 'belum_diakhiri',
            'citra' => 'selesai',
            'fajar' => 'berhalangan',
            'dedi' => 'kosong',
            'budi2' => 'belum_mulai',
        ], $this->petaStatus());

        $r = app(MonitoringKbmHarian::class)->hariIni()['ringkas'];
        $this->assertSame([6, 0, 1, 1, 1, 3], [$r['total'], $r['berjalan'], $r['belum_diakhiri'], $r['selesai'], $r['belum_mulai'], $r['bermasalah']]);
    }

    public function test_dua_sesi_di_ruangan_sama_dipasangkan_ke_jadwalnya_masing_masing(): void
    {
        $this->pada('12:30');
        $kedua = $this->scan('budi', 'XI RPL 1', '12:01');

        $baris = collect(app(MonitoringKbmHarian::class)->hariIni()['baris'])->keyBy(fn ($b) => $b['jadwal']->id);

        $this->assertSame('belum_diakhiri', $baris[$this->jadwal['budi1']->id]['status']);
        $this->assertSame($this->sesiBudi->id, $baris[$this->jadwal['budi1']->id]['sesi']->id);
        $this->assertSame('berjalan', $baris[$this->jadwal['budi2']->id]['status']);
        $this->assertSame($kedua->id, $baris[$this->jadwal['budi2']->id]['sesi']->id);
    }

    public function test_halaman_menampilkan_seharian_dan_tombol_hanya_untuk_sesi_menggantung(): void
    {
        $r = $this->actingAs($this->kepsek)->get('/kepsek/live-monitoring')->assertOk()
            ->assertSee('Monitoring KBM Hari Ini')
            ->assertSee('PPKn')->assertSee('Bahasa Inggris')->assertSee('Matematika')
            ->assertSee('Belum diakhiri')->assertSee('Selesai')->assertSee('Belum mulai');

        // Satu sesi menggantung -> tepat satu tombol.
        $this->assertSame(1, substr_count($r->getContent(), '/kepsek/live-monitoring/sesi/kbm/'));
        $r->assertSee('/kepsek/live-monitoring/sesi/kbm/' . $this->sesiBudi->id . '/akhiri', false);
    }

    // ===================== HAK AKSES =====================

    public function test_hanya_kepsek_dan_super_admin(): void
    {
        $this->actingAs($this->guru['budi'])->get('/guru/live-monitoring')->assertNotFound();
        $this->akhiri('kbm', $this->sesiBudi->id, $this->guru['citra'])->assertNotFound();
        $this->assertNull($this->sesiBudi->fresh()->waktu_selesai);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin)->get('/super-admin/live-monitoring')->assertOk();
        $this->akhiri('kbm', $this->sesiBudi->id, $admin)->assertRedirect();
        $this->assertNotNull($this->sesiBudi->fresh()->waktu_selesai);
    }

    // ===================== BANTU AKHIRI SESI =====================

    public function test_bantu_akhiri_sama_dengan_guru_mengakhiri_dan_tercatat(): void
    {
        app(AturanHonor::class)->simpanUmum(true, 25_000, 70);

        $this->akhiri('kbm', $this->sesiBudi->id)->assertRedirect('/kepsek/live-monitoring')
            ->assertSessionHas('notif_monitoring.tipe', 'ok');

        $s = $this->sesiBudi->fresh();
        // Jam selesai JADWAL (08.10), bukan jam tombol ditekan (10.30).
        $this->assertSame('08:10', $s->waktu_selesai->format('H:i'));
        $this->assertSame([$this->kepsek->id, 'Guru lupa menekan tombol'], [$s->diakhiri_oleh, $s->alasan_diakhiri]);

        // Honor: JP dari panjang jadwal (70 menit = 2 JP), rumus yang sama dengan guru.
        $h = HonorMengajar::sole();
        $this->assertSame([$this->guru['budi']->id, $this->jadwal['budi1']->id, 2, 50_000], [$h->user_id, $h->jadwal_id, $h->jp, $h->nominal]);
        $this->assertStringContainsString('diakhiri oleh Bu Kepsek', $h->rincian);

        $n = $this->guru['budi']->notifications()->sole();
        $this->assertSame('Sesi Anda diakhiri oleh pimpinan', $n->data['judul']);
        $this->assertStringContainsString('Guru lupa menekan tombol', $n->data['pesan']);

        // Kartunya kini "Selesai (dibantu ...)".
        $this->assertSame('selesai', $this->petaStatus()['budi1']);
        $this->actingAs($this->kepsek)->get('/kepsek/live-monitoring')->assertSee('dibantu Bu Kepsek');

        // Panel detail kartu menyebut penutup & alasannya.
        Livewire::actingAs($this->kepsek)->test(DetailSesi::class)
            ->call('buka', $this->jadwal['budi1']->id)
            ->assertSee('Dibantu diakhiri oleh Bu Kepsek')
            ->assertSee('Guru lupa menekan tombol');
    }

    public function test_diakhiri_sebelum_jadwal_selesai_memakai_jam_sekarang(): void
    {
        $this->pada('07:40');
        $this->akhiri('kbm', $this->sesiBudi->id);

        $this->assertSame('07:40', $this->sesiBudi->fresh()->waktu_selesai->format('H:i'));
    }

    public function test_alasan_wajib(): void
    {
        $this->akhiri('kbm', $this->sesiBudi->id, null, '')->assertSessionHasErrors('alasan');
        $this->akhiri('kbm', $this->sesiBudi->id, null, 'ok')->assertSessionHasErrors('alasan');

        $this->assertNull($this->sesiBudi->fresh()->waktu_selesai);
    }

    public function test_sesi_yang_sudah_selesai_tidak_diubah(): void
    {
        $citra = AbsensiMengajar::where('user_id', $this->guru['citra']->id)->sole();

        $this->akhiri('kbm', $citra->id)->assertSessionHas('notif_monitoring.tipe', 'warn');

        $this->assertSame(['10:01', null], [$citra->fresh()->waktu_selesai->format('H:i'), $citra->fresh()->diakhiri_oleh]);
    }

    public function test_penutupan_atomik_tidak_bisa_dua_kali(): void
    {
        $jadwal = $this->jadwal['budi1'];
        $p = app(PenutupSesi::class);

        $this->assertTrue($p->akhiriKbm(AbsensiMengajar::find($this->sesiBudi->id), $jadwal, $this->kepsek, 'pertama')['berhasil']);
        $this->assertFalse($p->akhiriKbm(AbsensiMengajar::find($this->sesiBudi->id), $jadwal, $this->kepsek, 'kedua')['berhasil']);
        $this->assertSame('pertama', $this->sesiBudi->fresh()->alasan_diakhiri);
    }

    public function test_sesi_kedua_di_ruangan_sama_honor_ke_jadwalnya_sendiri(): void
    {
        app(AturanHonor::class)->simpanUmum(true, 25_000, 70);
        $this->pada('13:40');
        $kedua = $this->scan('budi', 'XI RPL 1', '12:01');

        $this->akhiri('kbm', $kedua->id);

        $this->assertSame('13:10', $kedua->fresh()->waktu_selesai->format('H:i'));
        $this->assertSame($this->jadwal['budi2']->id, HonorMengajar::sole()->jadwal_id);
    }

    // ===================== HARI-HARI SEBELUMNYA =====================

    public function test_sesi_menggantung_kemarin_tampil_dan_bisa_ditutup(): void
    {
        // Kamis pekan lalu, Budi lupa mengakhiri; Selasa lalu scan ruangan
        // yang tidak cocok jadwal mana pun.
        $lalu = $this->scan('budi', 'XI RPL 1', '07:03', null, '2026-09-17');
        $nyasar = $this->scan('budi', 'Lab Bahasa', '09:00', null, '2026-09-22');
        $kuno = $this->scan('budi', 'XI RPL 1', '07:03', null, '2026-09-10');

        $daftar = app(MonitoringKbmHarian::class)->menggantung();
        $this->assertSame([$lalu->id, $nyasar->id], $daftar->pluck('id')->all());

        $this->actingAs($this->kepsek)->get('/kepsek/live-monitoring')
            ->assertSee('Sesi belum diakhiri dari hari sebelumnya (2)')
            ->assertSee('Ruangan Lab Bahasa (di luar jadwal)');

        $this->akhiri('kbm', $lalu->id);
        $this->assertSame('2026-09-17 08:10', $lalu->fresh()->waktu_selesai->format('Y-m-d H:i'));

        // Di luar jadwal: ditutup tepat di jam scan, tanpa honor.
        app(AturanHonor::class)->simpanUmum(true, 25_000, 70);
        $this->akhiri('kbm', $nyasar->id);
        $this->assertSame('2026-09-22 09:00', $nyasar->fresh()->waktu_selesai->format('Y-m-d H:i'));
        $this->assertSame(0, HonorMengajar::where('absensi_mengajar_id', $nyasar->id)->count());

        // Lebih dari 7 hari: tidak bisa lagi.
        $this->akhiri('kbm', $kuno->id)->assertNotFound();
    }

    // ===================== EKSKUL =====================

    public function test_bantu_akhiri_sesi_ekskul(): void
    {
        app(AturanHonor::class)->simpanUmum(true, 25_000, 70);
        $ekskul = JadwalEkskul::create(['nama_ekskul' => 'Pramuka', 'hari' => 'Kamis', 'jam_mulai' => '13:30', 'jam_selesai' => '15:30',
            'pembina_id' => $this->guru['dedi']->pegawai->id]);
        $sesi = SesiEkskul::create(['jadwal_ekskul_id' => $ekskul->id, 'user_id' => $this->guru['dedi']->id, 'tanggal' => self::TANGGAL,
            'waktu_mulai' => self::TANGGAL . ' 13:31:00']);

        $this->pada('17:00');
        $this->actingAs($this->kepsek)->get('/kepsek/live-monitoring')->assertSee('/kepsek/live-monitoring/sesi/ekskul/' . $sesi->id . '/akhiri', false);

        $this->akhiri('ekskul', $sesi->id)->assertSessionHas('notif_monitoring.tipe', 'ok');

        $this->assertSame('15:30', $sesi->fresh()->waktu_selesai->format('H:i'));
        $this->assertSame($this->kepsek->id, $sesi->fresh()->diakhiri_oleh);
        $this->assertSame([HonorMengajar::PERAN_EKSKUL, 3], [HonorMengajar::sole()->peran, HonorMengajar::sole()->jp]);
    }

    // ============ PASANGAN SESI ↔ JADWAL (SATU ATURAN UNTUK SEMUA LAYAR) ============

    public function test_jurnal_guru_memakai_sesi_kedua_untuk_jadwal_siang(): void
    {
        // Pagi diakhiri, siang scan lagi di ruangan yang sama.
        $this->sesiBudi->update(['waktu_selesai' => self::TANGGAL . ' 08:10:00']);
        $siang = $this->scan('budi', 'XI RPL 1', '12:01');
        $this->pada('12:05');

        $pencocok = app(PencocokSesiMengajar::class);
        $this->assertSame($this->sesiBudi->id, $pencocok->untukJadwal($this->jadwal['budi1'], $this->guru['budi']->id)?->id);
        // Dulu: sesi PAGI (sudah diakhiri) -> jurnal siang terkunci.
        $this->assertSame($siang->id, $pencocok->untukJadwal($this->jadwal['budi2'], $this->guru['budi']->id)?->id);

        $status = $this->petaStatus();
        $this->assertSame(['selesai', 'berjalan'], [$status['budi1'], $status['budi2']]);
    }

    public function test_satu_scan_siang_milik_jadwal_siang_bukan_jadwal_pagi(): void
    {
        $this->sesiBudi->delete();
        $siang = $this->scan('budi', 'XI RPL 1', '12:01');
        $this->pada('12:05');

        $pencocok = app(PencocokSesiMengajar::class);
        $this->assertNull($pencocok->untukJadwal($this->jadwal['budi1'], $this->guru['budi']->id));
        $this->assertSame($siang->id, $pencocok->untukJadwal($this->jadwal['budi2'], $this->guru['budi']->id)?->id);

        $status = $this->petaStatus();
        $this->assertSame(['tidak_ada_sesi', 'berjalan'], [$status['budi1'], $status['budi2']]);
    }

    public function test_pengingat_tidak_memindahkan_sesi_pagi_ke_jadwal_siang(): void
    {
        // Sesi 07.02 lupa diakhiri; siang Budi tidak scan. Pukul 13.15
        // (jendela pengingat jadwal 12.00–13.10) TIDAK boleh ada pengingat:
        // sesi 07.02 milik jadwal pagi yang batasnya sudah lewat.
        $this->pada('13:15');

        $this->assertCount(0, app(PengingatAkhiriSesi::class)->sesiTerbuka($this->guru['budi']->pegawai->id));

        // Kalau siang scan dan belum diakhiri -> diingatkan untuk sesi siang.
        $siang = $this->scan('budi', 'XI RPL 1', '12:01');
        $terbuka = app(PengingatAkhiriSesi::class)->sesiTerbuka($this->guru['budi']->pegawai->id);
        $this->assertSame([$siang->id], $terbuka->pluck('sesi.id')->all());
    }
}
