<?php

namespace Tests\Feature;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiSiswa;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Filter tanggal hemat indeks (App\Providers\QueryTanggalServiceProvider).
 *
 * Yang dijaga: hasilnya HARUS sama dengan whereDate() lama untuk semua
 * bentuk simpanan tanggal — 'Y-m-d' (MySQL / sisipan mentah) maupun
 * 'Y-m-d 00:00:00' (Eloquent di SQLite) — termasuk hari terakhir rentang.
 */
class QueryTanggalTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, Siswa> */
    private array $siswa = [];

    protected function setUp(): void
    {
        parent::setUp();

        $kelas = Kelas::factory()->create();
        foreach (range(1, 6) as $i) {
            $this->siswa[$i] = Siswa::factory()->create(['kelas_id' => $kelas->id]);
        }

        // 30 Sep, 1 Okt, 2 Okt — campuran Eloquent (berjam di SQLite) dan
        // sisipan mentah (tanpa jam).
        $this->eloquent(1, '2026-09-30');
        $this->mentah(2, '2026-09-30');
        $this->eloquent(3, '2026-10-01');
        $this->mentah(4, '2026-10-01');
        $this->eloquent(5, '2026-10-02');
        $this->mentah(6, '2026-10-02');
    }

    private function eloquent(int $i, string $tanggal): void
    {
        AbsensiSiswa::create(['siswa_id' => $this->siswa[$i]->id, 'tanggal' => $tanggal, 'status' => AbsensiStatus::Hadir]);
    }

    private function mentah(int $i, string $tanggal): void
    {
        DB::table('absensi_siswa')->insert([
            'siswa_id' => $this->siswa[$i]->id, 'tanggal' => $tanggal, 'status' => AbsensiStatus::Hadir->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** @return array<int, int> nomor siswa (1–6) yang terpilih */
    private function terpilih($query): array
    {
        $id = $query->pluck('siswa_id')->all();

        return collect($this->siswa)->filter(fn ($s) => in_array($s->id, $id))->keys()->sort()->values()->all();
    }

    public function test_pada_tanggal_menangkap_kedua_bentuk_simpanan_dan_sama_dengan_where_date(): void
    {
        $baru = $this->terpilih(AbsensiSiswa::wherePadaTanggal('tanggal', '2026-10-01'));
        $lama = $this->terpilih(AbsensiSiswa::whereDate('tanggal', '2026-10-01'));

        $this->assertSame([3, 4], $baru);
        $this->assertSame($lama, $baru);
    }

    public function test_menerima_objek_carbon(): void
    {
        $this->assertSame([5, 6], $this->terpilih(AbsensiSiswa::wherePadaTanggal('tanggal', Carbon::parse('2026-10-02 15:30'))));
    }

    public function test_rentang_menyertakan_hari_pertama_dan_terakhir(): void
    {
        $this->assertSame([1, 2, 3, 4], $this->terpilih(
            AbsensiSiswa::whereAntaraTanggal('tanggal', '2026-09-30', '2026-10-01')));
    }

    public function test_pada_bulan_dan_sebelum_tanggal(): void
    {
        $this->assertSame([3, 4, 5, 6], $this->terpilih(AbsensiSiswa::wherePadaBulan('tanggal', 2026, 10)));
        $this->assertSame([1, 2], $this->terpilih(AbsensiSiswa::wherePadaBulan('tanggal', 2026, 9)));

        // "Sebelum 2 Okt" = hari itu sendiri TIDAK ikut.
        $this->assertSame([3, 4], $this->terpilih(
            AbsensiSiswa::wherePadaBulan('tanggal', 2026, 10)->whereSebelumTanggal('tanggal', '2026-10-02')));
    }

    public function test_kolom_datetime_menangkap_sampai_detik_terakhir_hari_itu(): void
    {
        $guru = User::factory()->guru()->create();
        foreach (['2026-10-01 00:00:00', '2026-10-01 23:59:59', '2026-10-02 00:00:00', '2026-09-30 23:59:59'] as $w) {
            AbsensiMengajar::create(['user_id' => $guru->id, 'kode_kelas' => 'X', 'waktu_mulai' => $w]);
        }

        $this->assertSame(2, AbsensiMengajar::wherePadaTanggal('waktu_mulai', '2026-10-01')->count());
        $this->assertSame(
            AbsensiMengajar::whereDate('waktu_mulai', '2026-10-01')->count(),
            AbsensiMengajar::wherePadaTanggal('waktu_mulai', '2026-10-01')->count(),
        );
    }

    public function test_sql_tidak_membungkus_kolom_dengan_fungsi_tanggal(): void
    {
        $sql = strtolower(AbsensiSiswa::wherePadaTanggal('tanggal', '2026-10-01')->toSql());

        $this->assertStringContainsString('between', $sql);
        $this->assertStringNotContainsString('date(', $sql);
        $this->assertStringNotContainsString('strftime', $sql);
    }

    public function test_bisa_dipakai_di_dalam_relasi_eager_load(): void
    {
        $hasil = Siswa::with(['absensi' => fn ($q) => $q->wherePadaTanggal('tanggal', '2026-10-01')])
            ->orderBy('id')->get()
            ->filter(fn (Siswa $s) => $s->absensi->isNotEmpty())
            ->keys()->map(fn ($k) => $k + 1)->values()->all();

        $this->assertSame([3, 4], $hasil);
    }
}
