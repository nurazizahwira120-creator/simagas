<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use App\Jobs\SendWhatsAppNotification;
use App\Livewire\Guru\GuruInval;
use App\Models\AbsensiPegawai;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PengajuanIzinGuru;
use App\Models\PenugasanInval;
use App\Models\User;
use App\Services\StatusBerhalanganGuru;
use App\Services\TugasInval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * TUGAS IDT SAMPAI KE GURU INVAL.
 *
 * Kamis, 24 September 2026 pukul 09.00:
 *   - Citra  : IDT disetujui (deskripsi + lampiran), 08.30–10.00 XI RPL 1
 *   - Dedi   : ITT disetujui, 10.15–11.00 XI RPL 1
 *   - Eka    : sakit (absensi harian, tanpa pengajuan), 11.00–11.45 X TKJ 2
 *   - Gita   : staf, calon inval
 *   - Hana   : guru lain yang TIDAK ditunjuk
 */
class TugasInvalTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24';

    private const TUGAS = "Kerjakan LKS halaman 20-25.\nKumpulkan ke ketua kelas.";

    /** @var array<string, User> */
    private array $akun = [];

    /** @var array<string, JadwalPelajaran> */
    private array $jadwal = [];

    private User $kepsek;

    private PengajuanIzinGuru $idt;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TANGGAL . ' 09:00:00'));
        Bus::fake([SendWhatsAppNotification::class]);
        Storage::fake('local');

        $rpl = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);
        $tkj = Kelas::factory()->create(['nama_kelas' => 'X TKJ 2']);

        foreach (['citra', 'dedi', 'eka', 'hana'] as $k) {
            $this->akun[$k] = User::factory()->guru()->create(['name' => ucfirst($k)]);
            Pegawai::factory()->create(['user_id' => $this->akun[$k]->id, 'nama' => ucfirst($k)]);
        }
        $this->akun['gita'] = User::factory()->staff()->create(['name' => 'Gita Staf']);
        Pegawai::factory()->create(['user_id' => $this->akun['gita']->id, 'nama' => 'Gita Staf']);
        $this->kepsek = User::factory()->kepsek()->create(['name' => 'Bu Kepsek']);

        $buat = fn (string $g, Kelas $kelas, string $mulai, string $selesai, string $mapel) => JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => $mulai, 'jam_selesai' => $selesai,
            'mata_pelajaran' => $mapel, 'kelas_id' => $kelas->id, 'guru_id' => $this->akun[$g]->pegawai->id,
        ]);
        $this->jadwal['citra'] = $buat('citra', $rpl, '08:30', '10:00', 'Bahasa Inggris');
        $this->jadwal['dedi'] = $buat('dedi', $rpl, '10:15', '11:00', 'Matematika');
        $this->jadwal['eka'] = $buat('eka', $tkj, '11:00', '11:45', 'Pemrograman Web');

        Storage::disk('local')->put('tugas-izin-guru/soal.pdf', '%PDF-1.4 soal');
        $this->idt = $this->izin('citra', JenisIzinGuru::Idt, self::TUGAS, 'tugas-izin-guru/soal.pdf');
        $this->izin('dedi', JenisIzinGuru::Itt);
        AbsensiPegawai::create(['pegawai_id' => $this->akun['eka']->pegawai->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Sakit]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function izin(string $g, JenisIzinGuru $jenis, ?string $tugas = null, ?string $berkas = null,
        StatusApproval $status = StatusApproval::Disetujui, string $mulai = self::TANGGAL, string $selesai = self::TANGGAL): PengajuanIzinGuru
    {
        return PengajuanIzinGuru::create([
            'guru_id' => $this->akun[$g]->id, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai,
            'jenis_izin' => $jenis, 'alasan' => 'Keperluan dinas', 'detail_tugas' => $tugas, 'file_tugas' => $berkas,
            'status_approval' => $status,
        ]);
    }

    private function kepsekTunjuk(string $jadwal, ?string $catatan = null)
    {
        $guruId = $this->jadwal[$jadwal]->guru_id;

        return Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->set("pilihan.{$this->jadwal[$jadwal]->id}", $this->akun['gita']->id)
            ->set("catatanInval.{$guruId}", $catatan)
            ->call('tunjuk', $this->jadwal[$jadwal]->id);
    }

    private function lampiranUrl(string $prefix, ?PengajuanIzinGuru $izin = null): string
    {
        return "/{$prefix}/guru-inval/tugas/" . ($izin ?? $this->idt)->id . '/lampiran';
    }

    // ===================== TAMPILAN =====================

    public function test_inval_melihat_tugas_idt_dan_tombol_lampiran(): void
    {
        $this->kepsekTunjuk('citra');

        Livewire::actingAs($this->akun['gita'])->test(GuruInval::class)
            ->assertSee('Tugas dari Citra (IDT)')
            ->assertSee('Kerjakan LKS halaman 20-25.')
            ->assertSee('Kumpulkan ke ketua kelas.')
            ->assertSee('Buka lampiran tugas')
            ->assertSee($this->lampiranUrl('staff'), false)
            // Saat membuka form absensi, tugasnya tetap terlihat.
            ->call('pilih', $this->jadwal['citra']->id)
            ->assertSee('Daftar Hadir Siswa')
            ->assertSee('Kerjakan LKS halaman 20-25.');
    }

    public function test_itt_dan_sakit_harian_diberi_keterangan_yang_jujur(): void
    {
        $this->kepsekTunjuk('dedi');
        $this->kepsekTunjuk('eka');

        Livewire::actingAs($this->akun['gita'])->test(GuruInval::class)
            ->assertSee('Tanpa tugas (ITT).')
            ->assertSee('Guru tidak meninggalkan tugas tertulis.')
            ->assertDontSee('Buka lampiran tugas');
    }

    public function test_penunjuk_melihat_tugas_di_halaman_guru_inval(): void
    {
        Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->assertSee('Yang diterima guru inval')
            ->assertSee('Kerjakan LKS halaman 20-25.')
            ->assertSee($this->lampiranUrl('kepsek'), false)
            ->assertSee('Catatan untuk inval');
    }

    public function test_notifikasi_penunjukan_memuat_tugas_dan_catatan(): void
    {
        $this->kepsekTunjuk('citra', '  Bawa spidol dari ruang guru.  ');

        $n = $this->akun['gita']->notifications()->sole();
        $this->assertSame('Anda ditunjuk menjadi guru inval', $n->data['judul']);
        $this->assertStringContainsString('Tugas dari guru: Kerjakan LKS halaman 20-25. Kumpulkan ke ketua kelas.', $n->data['cuplikan']);
        $this->assertStringContainsString('Catatan: Bawa spidol dari ruang guru.', $n->data['cuplikan']);

        // Catatan dirapikan (spasi ujung dibuang) dan tampil untuk inval.
        $this->assertSame('Bawa spidol dari ruang guru.', PenugasanInval::sole()->catatan);
        Livewire::actingAs($this->akun['gita'])->test(GuruInval::class)
            ->assertSee('Catatan dari Kepala Sekolah / Admin')
            ->assertSee('Bawa spidol dari ruang guru.');
    }

    // ===================== CATATAN SUSULAN =====================

    public function test_catatan_susulan_disimpan_dan_inval_dikabari(): void
    {
        $this->kepsekTunjuk('eka');
        $guruId = $this->jadwal['eka']->guru_id;

        $kepsek = Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->set("catatanInval.{$guruId}", 'Bu Eka kirim tugas lewat WA: buat flowchart login.')
            ->call('simpanCatatan', $guruId)
            ->assertSet('notif.tipe', 'ok');

        $this->assertSame('Bu Eka kirim tugas lewat WA: buat flowchart login.', PenugasanInval::sole()->catatan);
        $terakhir = $this->akun['gita']->notifications()->latest('created_at')->get()->firstWhere('data.judul', 'Catatan tugas inval diperbarui');
        $this->assertNotNull($terakhir);
        $this->assertStringContainsString('buat flowchart login', $terakhir->data['pesan']);

        // Menyimpan catatan yang sama: tidak ada perubahan, tidak ada notifikasi baru.
        $jumlah = $this->akun['gita']->notifications()->count();
        $kepsek->call('simpanCatatan', $guruId)->assertSet('notif.tipe', 'warn');
        $this->assertSame($jumlah, $this->akun['gita']->notifications()->count());
    }

    public function test_catatan_terlalu_panjang_ditolak_dan_tanpa_inval_tidak_disimpan(): void
    {
        $guruId = $this->jadwal['eka']->guru_id;

        // Belum ada inval -> diminta menunjuk dulu.
        Livewire::actingAs($this->kepsek)->test(GuruInval::class)
            ->set("catatanInval.{$guruId}", 'Catatan')
            ->call('simpanCatatan', $guruId)
            ->assertSet('notif.tipe', 'warn');

        // Lebih dari batas -> tidak menunjuk sama sekali.
        $this->kepsekTunjuk('eka', str_repeat('a', TugasInval::MAKS_CATATAN + 1))
            ->assertSet('notif.judul', 'Catatan terlalu panjang');
        $this->assertSame(0, PenugasanInval::count());
    }

    // ===================== HAK MEMBUKA LAMPIRAN =====================

    public function test_lampiran_hanya_untuk_inval_yang_ditunjuk_dan_penunjuk(): void
    {
        // Belum ditunjuk -> ditolak.
        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertForbidden();

        $this->kepsekTunjuk('citra');

        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertOk();
        $this->actingAs($this->kepsek)->get($this->lampiranUrl('kepsek'))->assertOk();
        // Guru lain yang tidak ditunjuk tetap ditolak walau menebak id.
        $this->actingAs($this->akun['hana'])->get($this->lampiranUrl('guru'))->assertForbidden();
        // Guru pemilik izin boleh membuka lampirannya sendiri.
        $this->actingAs($this->akun['citra'])->get($this->lampiranUrl('guru'))->assertOk();
    }

    public function test_lampiran_ditolak_bila_izin_bukan_milik_guru_yang_digantikan_atau_di_luar_rentang(): void
    {
        // Gita menggantikan EKA, bukan Citra.
        $this->kepsekTunjuk('eka');
        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertForbidden();

        // Penugasan menggantikan Citra pada tanggal DI LUAR rentang izinnya.
        PenugasanInval::create(['jadwal_id' => $this->jadwal['citra']->id, 'tanggal' => '2026-10-01',
            'inval_user_id' => $this->akun['gita']->id, 'ditunjuk_oleh' => $this->kepsek->id]);
        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertForbidden();

        // Izin yang dibatalkan/ditolak sesudah penunjukan.
        PenugasanInval::create(['jadwal_id' => $this->jadwal['citra']->id, 'tanggal' => self::TANGGAL,
            'inval_user_id' => $this->akun['gita']->id, 'ditunjuk_oleh' => $this->kepsek->id]);
        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertOk();
        $this->idt->update(['status_approval' => StatusApproval::Ditolak]);
        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertForbidden();
    }

    public function test_berkas_hilang_dari_disk_404(): void
    {
        $this->kepsekTunjuk('citra');
        Storage::disk('local')->delete('tugas-izin-guru/soal.pdf');

        $this->actingAs($this->akun['gita'])->get($this->lampiranUrl('staff'))->assertNotFound();
    }

    // ===================== TUGAS MENDATANG =====================

    public function test_tugas_mendatang_ikut_menampilkan_tugas_idt(): void
    {
        // Izin IDT Citra Kamis depan (1 Oktober), inval sudah ditunjuk.
        $depan = $this->izin('citra', JenisIzinGuru::Idt, 'Presentasi kelompok bab 3.', null, StatusApproval::Disetujui, '2026-10-01', '2026-10-01');
        PenugasanInval::create(['jadwal_id' => $this->jadwal['citra']->id, 'tanggal' => '2026-10-01',
            'inval_user_id' => $this->akun['gita']->id, 'ditunjuk_oleh' => $this->kepsek->id, 'catatan' => 'Siapkan proyektor.']);

        Livewire::actingAs($this->akun['gita'])->test(GuruInval::class)
            ->assertSee('Tugas inval mendatang')
            ->assertSee('Presentasi kelompok bab 3.')
            ->assertSee('Siapkan proyektor.');

        $this->assertSame($depan->id, app(StatusBerhalanganGuru::class)
            ->pada([$this->akun['citra']->pegawai], Carbon::parse('2026-10-01'))->first()['izin_id']);
    }
}
