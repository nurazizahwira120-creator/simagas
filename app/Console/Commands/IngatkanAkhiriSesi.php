<?php

namespace App\Console\Commands;

use App\Jobs\KirimPushNotifikasi;
use App\Models\AbsensiMengajar;
use App\Models\SesiEkskul;
use App\Models\User;
use App\Notifications\AkhiriSesiBelumDitekan;
use App\Services\AturanSesiEkskul;
use App\Services\PengingatAkhiriSesi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Mengingatkan guru yang belum menekan "Akhiri Sesi" 5 menit sesudah KBM
 * selesai — lewat push ke HP (berbunyi & bergetar walau aplikasi ditutup)
 * dan satu baris di lonceng.
 *
 * Dijalankan penjadwal SETIAP MENIT (routes/console.php). Aman dijalankan
 * berulang kali: setiap sesi hanya diingatkan SEKALI, dijaga kolom
 * absensi_mengajar.pengingat_akhiri_pada.
 *
 * ============ KLAIM ATOMIK SEBELUM MENGIRIM ============
 * Penandanya ditulis LEBIH DULU lewat satu UPDATE bersyarat
 * ("... WHERE pengingat_akhiri_pada IS NULL AND waktu_selesai IS NULL"),
 * barulah notifikasinya dikirim kalau UPDATE itu benar-benar mengubah satu
 * baris. Dua proses yang kebetulan berjalan bersamaan (cron terlambat lalu
 * menumpuk) tidak mungkin sama-sama mengirim — hanya satu yang menang.
 * Bonusnya: guru yang menekan Akhiri Sesi sepersekian detik sebelumnya juga
 * tidak lagi diingatkan.
 * ======================================================
 */
class IngatkanAkhiriSesi extends Command
{
    protected $signature = 'simagas:pengingat-akhiri-sesi
        {--uji-coba : Hanya menampilkan siapa yang akan diingatkan, tanpa mengirim apa pun.}';

    protected $description = 'Mengingatkan guru yang belum menekan "Akhiri Sesi" 5 menit sesudah KBM selesai.';

    public function handle(PengingatAkhiriSesi $pengingat, AturanSesiEkskul $ekskul): int
    {
        $daftar = $pengingat->jatuhTempo(now());

        // Sesi EKSKUL diproses di perintah yang sama, dengan aturan yang sama.
        $this->ingatkanEkskul($ekskul);

        if ($daftar->isEmpty()) {
            $this->info('Tidak ada sesi KBM yang perlu diingatkan.');

            return self::SUCCESS;
        }

        // Akun penerima diambil SEKALI untuk semua — bukan satu query per sesi.
        $pengguna = User::query()
            ->whereIn('id', $daftar->pluck('user_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $terkirim = 0;

        foreach ($daftar as $butir) {
            /** @var AbsensiMengajar $sesi */
            $sesi = $butir['sesi'];
            $jadwal = $butir['jadwal'];
            $user = $pengguna->get($butir['user_id']);

            $label = "{$jadwal->mata_pelajaran} ({$jadwal->kelas?->nama_kelas}) — {$user?->name}";

            if ($this->option('uji-coba')) {
                $this->line("Akan diingatkan: {$label}, batas {$butir['batas']->format('H:i')}");

                continue;
            }

            if (! $user || ! $this->klaim($sesi)) {
                continue;
            }

            $this->kirim($user, $butir);
            $terkirim++;
            $this->line("Diingatkan: {$label}");
        }

        if (! $this->option('uji-coba')) {
            $this->info("{$terkirim} guru diingatkan.");
        }

        return self::SUCCESS;
    }

    /** UPDATE bersyarat: true hanya bila proses INI yang berhak mengirim. */
    private function klaim(AbsensiMengajar $sesi): bool
    {
        return AbsensiMengajar::query()
            ->whereKey($sesi->id)
            ->whereNull('pengingat_akhiri_pada')
            ->whereNull('waktu_selesai')
            ->update(['pengingat_akhiri_pada' => now()]) === 1;
    }

    /**
     * Lonceng + push. Keduanya dibungkus try/catch TERPISAH: gagalnya satu
     * jalur (mis. Google sedang tidak bisa dihubungi) tidak boleh
     * menggagalkan jalur lain, apalagi menghentikan pengingat untuk guru
     * berikutnya di perulangan.
     *
     * @param  array{jadwal: \App\Models\JadwalPelajaran, batas: \Illuminate\Support\Carbon}  $butir
     */
    private function kirim(User $user, array $butir): void
    {
        try {
            $user->notify(AkhiriSesiBelumDitekan::untuk($butir['jadwal'], $butir['batas']));
        } catch (Throwable $e) {
            Log::warning('Pengingat Akhiri Sesi: gagal menulis lonceng.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! $user->bisaMenerimaPush()) {
            return;
        }

        try {
            KirimPushNotifikasi::dispatch(
                userId: (int) $user->id,
                judul: 'Sesi kelas belum diakhiri',
                isi: PengingatAkhiriSesi::kalimat($butir['jadwal'], $butir['batas']),
                data: [
                    'jenis' => 'pengingat-akhiri-sesi',
                    'url' => $this->urlJurnal($user),
                ],
            )->onConnection(config('firebase.queue_connection', 'sync'));
        } catch (Throwable $e) {
            Log::warning('Pengingat Akhiri Sesi: gagal mengirim push.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Pengingat untuk sesi ekskul yang belum diakhiri pembinanya. */
    private function ingatkanEkskul(AturanSesiEkskul $aturan): void
    {
        $daftar = $aturan->jatuhTempo(now());

        if ($daftar->isEmpty()) {
            return;
        }

        $pengguna = User::query()->whereIn('id', $daftar->pluck('user_id')->unique()->values())->get()->keyBy('id');

        foreach ($daftar as $butir) {
            /** @var SesiEkskul $sesi */
            $sesi = $butir['sesi'];
            $user = $pengguna->get($butir['user_id']);
            $label = "Ekskul {$butir['jadwal']->nama_ekskul} — {$user?->name}";

            if ($this->option('uji-coba')) {
                $this->line("Akan diingatkan: {$label}, batas {$butir['batas']->format('H:i')}");

                continue;
            }

            // Klaim atomik yang sama dengan sesi KBM (lihat catatan kelas).
            $menang = SesiEkskul::query()->whereKey($sesi->id)
                ->whereNull('pengingat_akhiri_pada')->whereNull('waktu_selesai')
                ->update(['pengingat_akhiri_pada' => now()]) === 1;

            if (! $user || ! $menang) {
                continue;
            }

            $kalimat = AturanSesiEkskul::kalimat($butir['jadwal'], $butir['batas']);

            try {
                $user->notify(AkhiriSesiBelumDitekan::untukEkskul($butir['jadwal'], $butir['batas']));
            } catch (Throwable $e) {
                Log::warning('Pengingat Akhiri Sesi ekskul: gagal menulis lonceng.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }

            if ($user->bisaMenerimaPush()) {
                try {
                    $prefix = $user->role?->routePrefix();
                    KirimPushNotifikasi::dispatch(
                        userId: (int) $user->id,
                        judul: 'Sesi ekskul belum diakhiri',
                        isi: $kalimat,
                        data: [
                            'jenis' => 'pengingat-akhiri-sesi',
                            'url' => $prefix && Route::has("{$prefix}.ekskul.absensi")
                                ? route("{$prefix}.ekskul.absensi", $butir['jadwal']->id) : url('/'),
                        ],
                    )->onConnection(config('firebase.queue_connection', 'sync'));
                } catch (Throwable $e) {
                    Log::warning('Pengingat Akhiri Sesi ekskul: gagal mengirim push.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                }
            }

            $this->line("Diingatkan: {$label}");
        }
    }

    /** Halaman Jurnal sesuai panel peran guru; dashboard kalau tidak ada. */
    private function urlJurnal(User $user): string
    {
        $prefix = $user->role?->routePrefix();

        if ($prefix && Route::has("{$prefix}.jurnal-kelas")) {
            return route("{$prefix}.jurnal-kelas");
        }

        return url('/');
    }
}
