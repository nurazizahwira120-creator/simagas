<?php

namespace Tests\Feature;

use App\Livewire\NotificationBell;
use App\Livewire\Pengumuman\KelolaPengumuman;
use App\Models\Pengumuman;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use App\Support\TeksBertaut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pengumuman harus bisa dibaca UTUH oleh penerimanya.
 *
 * Bug yang dijaga: lonceng hanya menyimpan cuplikan 120 karakter, dan
 * tombol "Lihat selengkapnya" membentangkan cuplikan itu saja — sisa
 * pengumuman yang panjang tidak pernah bisa dibaca guru / wali murid.
 */
class PengumumanTerbacaUtuhTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create(['name' => 'Admin Sekolah']);
    }

    private function kirim(string $isi, User $penerima, string $judul = 'Rapat Wali Murid'): Pengumuman
    {
        $p = Pengumuman::create([
            'user_id' => $this->admin->id, 'judul' => $judul, 'isi_pesan' => $isi, 'target_role' => 'semua',
        ]);

        $penerima->notify(PengumumanBaru::dari($p));

        return $p;
    }

    private function isiPanjang(): string
    {
        return "Yth. Bapak/Ibu wali murid,\n\n"
            . str_repeat('Kegiatan sekolah berjalan seperti biasa dan mohon diperhatikan. ', 6)
            . "\n\nBAGIAN AKHIR: rapat dimulai pukul 08.00 di aula.";
    }

    public function test_lonceng_menampilkan_isi_pengumuman_lengkap_bukan_cuplikan_120_karakter(): void
    {
        $wali = User::factory()->guru()->create();
        $this->kirim($this->isiPanjang(), $wali);

        // Prasyarat: cuplikan yang tersimpan memang terpotong.
        $this->assertStringNotContainsString('BAGIAN AKHIR', $wali->notifications()->first()->data['cuplikan']);

        Livewire::actingAs($wali)->test(NotificationBell::class)
            ->assertSee('BAGIAN AKHIR: rapat dimulai pukul 08.00 di aula.')
            ->assertSee('Lihat selengkapnya');
    }

    public function test_pengumuman_yang_disunting_tampil_versi_terbaru(): void
    {
        $guru = User::factory()->guru()->create();
        $p = $this->kirim('Rapat hari Senin pukul 08.00 di aula sekolah.', $guru);

        $p->update(['isi_pesan' => 'RALAT: rapat dipindah ke hari Selasa pukul 09.00.']);

        Livewire::actingAs($guru)->test(NotificationBell::class)
            ->assertSee('RALAT: rapat dipindah ke hari Selasa')
            ->assertDontSee('hari Senin pukul 08.00');
    }

    public function test_pengumuman_yang_sudah_tidak_ada_jatuh_ke_cuplikan(): void
    {
        $guru = User::factory()->guru()->create();
        $p = $this->kirim('Pengumuman lama tentang jadwal ujian semester.', $guru);

        // Hapus induknya saja (mis. dibersihkan langsung di database).
        DB::table('pengumuman')->where('id', $p->id)->delete();

        Livewire::actingAs($guru)->test(NotificationBell::class)
            ->assertOk()
            ->assertSee('Pengumuman lama tentang jadwal ujian semester.');
    }

    /**
     * Isi diambil hanya untuk pengumuman yang ADA di notifikasi milik
     * pengguna. Pengumuman untuk peran lain tidak ikut terbaca.
     */
    public function test_isi_pengumuman_orang_lain_tidak_ikut_termuat(): void
    {
        $guru = User::factory()->guru()->create();
        $lain = User::factory()->guru()->create();

        $this->kirim('Pengumuman untuk guru ini saja.', $guru);
        $this->kirim('RAHASIA khusus orang lain, tidak boleh terbaca.', $lain);

        $lonceng = Livewire::actingAs($guru)->test(NotificationBell::class);

        $lonceng->assertSee('Pengumuman untuk guru ini saja.')
            ->assertDontSee('RAHASIA khusus orang lain');
        $this->assertCount(1, $lonceng->instance()->isiPengumuman);
    }

    public function test_isi_berbahaya_tetap_teks_dan_tautan_bisa_diklik(): void
    {
        $guru = User::factory()->guru()->create();
        $this->kirim("Isi formulir di https://forms.gle/AbC123?x=1&y=2.\n<script>alert('x')</script> javascript:alert(1)", $guru);

        Livewire::actingAs($guru)->test(NotificationBell::class)
            ->assertDontSee("<script>alert('x')</script>", false)
            ->assertSee('&lt;script&gt;', false)
            ->assertSee('<a href="https://forms.gle/AbC123?x=1&amp;y=2" target="_blank" rel="noopener noreferrer nofollow"', false)
            ->assertDontSee('href="javascript:', false);
    }

    public function test_jumlah_query_tetap_walau_notifikasi_pengumuman_banyak(): void
    {
        $guru = User::factory()->guru()->create();

        $hitung = function () use ($guru): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($guru)->test(NotificationBell::class);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $this->kirim('Pengumuman pertama yang cukup panjang isinya.', $guru);
        $sebelum = $hitung();

        foreach (range(1, 7) as $i) {
            $this->kirim("Pengumuman ke-{$i} dengan isi yang cukup panjang.", $guru, "Judul {$i}");
        }

        $this->assertSame($sebelum, $hitung(), 'Isi pengumuman diambil per notifikasi — ada N+1.');
    }

    public function test_riwayat_kelola_pengumuman_memuat_isi_lengkap(): void
    {
        Pengumuman::create(['user_id' => $this->admin->id, 'judul' => 'Panjang', 'isi_pesan' => $this->isiPanjang(), 'target_role' => 'semua']);

        Livewire::actingAs($this->admin)->test(KelolaPengumuman::class)
            ->assertSee('BAGIAN AKHIR: rapat dimulai pukul 08.00 di aula.')
            ->assertSee('Lihat selengkapnya');
    }

    public function test_cuplikan_alasan_izin_yang_terpotong_menunjuk_halaman_tujuan(): void
    {
        $kepsek = User::factory()->kepsek()->create();
        $kepsek->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($n): array { return ['database']; }
            public function toArray($n): array
            {
                return ['judul' => 'Pengajuan izin baru', 'pesan' => 'Budi mengajukan izin.',
                    'cuplikan' => \Illuminate\Support\Str::limit(str_repeat('Alasan panjang sekali. ', 10), 90),
                    'rute' => '.daftar-izin-pegawai'];
            }
        });

        Livewire::actingAs($kepsek)->test(NotificationBell::class)
            ->assertSee('Isi lengkap ada di halaman tujuan');
    }

    /* ===================== TeksBertaut ===================== */

    public function test_tanda_baca_di_ujung_kalimat_tidak_ikut_menjadi_alamat(): void
    {
        $html = (string) TeksBertaut::html('Buka (https://simagas.online/info). Lalu https://a.id/b, selesai!');

        $this->assertStringContainsString('<a href="https://simagas.online/info"', $html);
        $this->assertStringContainsString('</a>).', $html);
        $this->assertStringContainsString('<a href="https://a.id/b"', $html);
        $this->assertStringContainsString('</a>, selesai!', $html);
    }

    public function test_alamat_dalam_kurung_sudut_dan_kutip(): void
    {
        $html = (string) TeksBertaut::html('Lihat <https://x.id/a> dan "https://y.id/b"');

        $this->assertStringContainsString('&lt;<a href="https://x.id/a"', $html);
        $this->assertStringContainsString('</a>&gt;', $html);
        $this->assertStringContainsString('&quot;<a href="https://y.id/b"', $html);
        $this->assertStringContainsString('</a>&quot;', $html);
    }

    public function test_teks_tanpa_alamat_hanya_di_escape(): void
    {
        $this->assertSame('a &lt;b&gt; &amp; c', (string) TeksBertaut::html('a <b> & c'));
        $this->assertSame('', (string) TeksBertaut::html(null));
        $this->assertSame('http:// saja', (string) TeksBertaut::html('http:// saja'));
    }
}
