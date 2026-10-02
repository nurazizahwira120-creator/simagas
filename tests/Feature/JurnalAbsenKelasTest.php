<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Jobs\SendWhatsAppNotification;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Jurnal & Absen Kelas — alur guru biasa.
 *
 * Ditulis SEBELUM logika penyimpanannya dipindah ke
 * App\Services\PencatatAbsensiKbm, lalu dijalankan lagi sesudahnya. Kalau
 * pemindahan itu mengubah perilaku sekecil apa pun (bolos, izin gerbang,
 * peringatan WhatsApp, siapa yang mengisi), ujian di sini yang merah.
 */
class JurnalAbsenKelasTest extends TestCase
{
    use RefreshDatabase;

    private const TANGGAL = '2026-09-24'; // Kamis

    private User $guru;

    private JadwalPelajaran $jadwal;

    /** @var array<string, Siswa> */
    private array $siswa = [];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TANGGAL . ' 07:20:00'));
        Bus::fake([SendWhatsAppNotification::class]);

        $this->guru = User::factory()->guru()->create(['name' => 'Budi Guru']);
        $pegawai = Pegawai::factory()->create(['user_id' => $this->guru->id, 'nama' => 'Budi Guru']);
        $kelas = Kelas::factory()->create(['nama_kelas' => 'XI RPL 1']);

        $this->jadwal = JadwalPelajaran::create([
            'hari' => Hari::Kamis->value, 'jam_mulai' => '07:00', 'jam_selesai' => '08:10',
            'mata_pelajaran' => 'Basis Data', 'kelas_id' => $kelas->id, 'guru_id' => $pegawai->id,
        ]);

        foreach (['andi' => 'Andi', 'bela' => 'Bela', 'caca' => 'Caca', 'dodi' => 'Dodi'] as $k => $nama) {
            $this->siswa[$k] = Siswa::factory()->create(['nama' => $nama, 'kelas_id' => $kelas->id]);
        }

        AbsensiPegawai::create([
            'pegawai_id' => $pegawai->id, 'tanggal' => self::TANGGAL,
            'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:50:00',
        ]);

        AbsensiMengajar::create([
            'user_id' => $this->guru->id, 'kode_kelas' => 'Ruang XI-RPL 1',
            'waktu_mulai' => self::TANGGAL . ' 07:02:00',
        ]);

        // Dodi masuk gerbang pagi ini; Caca sudah dicatat sakit di gerbang.
        AbsensiSiswa::create(['siswa_id' => $this->siswa['dodi']->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Hadir, 'jam_masuk' => '06:40:00']);
        AbsensiSiswa::create(['siswa_id' => $this->siswa['caca']->id, 'tanggal' => self::TANGGAL, 'status' => AbsensiStatus::Sakit]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_status_awal_mengikuti_izin_dari_gerbang(): void
    {
        $this->actingAs($this->guru);

        Livewire::test(JurnalAbsenKelas::class)
            ->assertSet('status.' . $this->siswa['andi']->id, StatusKbm::Hadir->value)
            ->assertSet('status.' . $this->siswa['caca']->id, StatusKbm::Sakit->value);
    }

    public function test_simpan_mencatat_status_mengubah_alpa_jadi_bolos_dan_memberi_tahu_wali(): void
    {
        $this->actingAs($this->guru);

        Livewire::test(JurnalAbsenKelas::class)
            ->set('status.' . $this->siswa['bela']->id, StatusKbm::Alpa->value)
            ->set('keterangan.' . $this->siswa['bela']->id, 'Tanpa kabar')
            ->set('status.' . $this->siswa['dodi']->id, StatusKbm::Alpa->value)
            ->call('simpan')
            ->assertSet('notif.tipe', 'ok');

        $hasil = AbsensiKbmSiswa::where('jadwal_id', $this->jadwal->id)->get()->keyBy('siswa_id');

        $this->assertCount(4, $hasil);

        // Pengisinya tercatat: guru jadwalnya sendiri (kolom diisi_oleh,
        // migration 000040). Pembeda dari catatan yang diisi pengganti.
        $this->assertTrue($hasil->every(fn ($b) => $b->diisi_oleh === $this->guru->id));
        $this->assertSame(StatusKbm::Hadir, $hasil[$this->siswa['andi']->id]->status);
        $this->assertSame(StatusKbm::Alpa, $hasil[$this->siswa['bela']->id]->status);
        $this->assertSame('Tanpa kabar', $hasil[$this->siswa['bela']->id]->keterangan);
        $this->assertSame(StatusKbm::Sakit, $hasil[$this->siswa['caca']->id]->status);

        // Masuk gerbang tapi ditandai alpa -> bolos.
        $this->assertSame(StatusKbm::Bolos, $hasil[$this->siswa['dodi']->id]->status);

        // Dua peringatan: Bela (alpa) dan Dodi (bolos).
        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 2);
        Bus::assertDispatched(SendWhatsAppNotification::class,
            fn ($job) => str_contains($job->message, 'Dodi') && str_contains($job->message, 'BOLOS') && str_contains($job->message, 'jam ke-1'));
    }

    public function test_simpan_ulang_tanpa_perubahan_tidak_mengirim_peringatan_kedua(): void
    {
        $this->actingAs($this->guru);

        $komponen = Livewire::test(JurnalAbsenKelas::class)
            ->set('status.' . $this->siswa['bela']->id, StatusKbm::Alpa->value)
            ->call('simpan');

        $komponen->call('simpan');

        Bus::assertDispatchedTimes(SendWhatsAppNotification::class, 1);
        $this->assertSame(4, AbsensiKbmSiswa::count());
    }

    public function test_tanpa_scan_qr_jurnal_tetap_terkunci(): void
    {
        AbsensiMengajar::query()->delete();
        $this->actingAs($this->guru);

        Livewire::test(JurnalAbsenKelas::class)
            ->call('simpan')
            ->assertSet('notif.tipe', 'error');

        $this->assertSame(0, AbsensiKbmSiswa::count());
    }
}
