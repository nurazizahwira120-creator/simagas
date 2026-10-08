<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BersihkanDataLamaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-08 02:30:00');
        $this->user = User::factory()->guru()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function notif(string $judul, int $hariLalu, bool $dibaca): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'Uji', 'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id, 'data' => json_encode(['judul' => $judul]),
            'read_at' => $dibaca ? now()->subDays($hariLalu) : null,
            'created_at' => now()->subDays($hariLalu), 'updated_at' => now()->subDays($hariLalu),
        ]);
    }

    private function judulTersisa(): array
    {
        return DB::table('notifications')->pluck('data')
            ->map(fn ($d) => json_decode($d, true)['judul'])->sort()->values()->all();
    }

    private function siapkan(): void
    {
        $this->notif('baru-dibaca', 10, true);
        $this->notif('lama-dibaca', 91, true);
        $this->notif('lama-belum-dibaca', 200, false);
        $this->notif('sangat-lama-belum-dibaca', 400, false);

        DB::table('failed_jobs')->insert([
            ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(31)],
            ['uuid' => (string) Str::uuid(), 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()->subDays(2)],
        ]);

        DB::table('cache')->insert([
            ['key' => 'kedaluwarsa', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'masih-berlaku', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
        ]);
    }

    public function test_membersihkan_hanya_yang_lama_sesuai_aturan(): void
    {
        $this->siapkan();

        $this->assertSame(0, Artisan::call('simagas:bersihkan-data'));

        $this->assertSame(['baru-dibaca', 'lama-belum-dibaca'], $this->judulTersisa());
        $this->assertSame(1, DB::table('failed_jobs')->count());
        $this->assertSame(['masih-berlaku'], DB::table('cache')->pluck('key')->all());
    }

    public function test_uji_coba_hanya_menghitung(): void
    {
        $this->siapkan();

        Artisan::call('simagas:bersihkan-data', ['--uji-coba' => true]);

        $this->assertStringContainsString('Akan dihapus notifikasi: 2', Artisan::output());
        $this->assertSame(4, DB::table('notifications')->count());
        $this->assertSame(2, DB::table('failed_jobs')->count());
        $this->assertSame(2, DB::table('cache')->count());
    }

    public function test_menghapus_bertahap_lebih_dari_seribu_baris(): void
    {
        $baris = [];
        foreach (range(1, 2500) as $i) {
            $baris[] = [
                'id' => (string) Str::uuid(), 'type' => 'Uji', 'notifiable_type' => User::class,
                'notifiable_id' => $this->user->id, 'data' => '{"judul":"x"}', 'read_at' => now()->subDays(100),
                'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100),
            ];
        }
        foreach (array_chunk($baris, 500) as $potong) {
            DB::table('notifications')->insert($potong);
        }
        $this->notif('baru-dibaca', 1, true);

        Artisan::call('simagas:bersihkan-data');

        $this->assertSame(['baru-dibaca'], $this->judulTersisa());
    }

    public function test_terjadwal_tiap_malam(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('simagas-bersihkan-data');
    }
}
