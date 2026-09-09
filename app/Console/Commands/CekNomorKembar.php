<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\PencariAkunLogin;
use Illuminate\Console\Command;

/**
 * Mendaftar nomor HP yang dipakai lebih dari satu akun.
 *
 * ============ KENAPA PERINTAH INI ADA ============
 * Login multi-kredensial MENOLAK masuk kalau sebuah nomor cocok dengan dua
 * akun — sistem tidak boleh menebak yang mana. Penolakan itu benar, tapi
 * bagi wali murid yang mengalaminya sama sekali tidak bisa ditindaklanjuti:
 * ia hanya melihat pesan "hubungi admin sekolah".
 *
 * Perintah ini yang membuat pesan itu ada artinya. Admin menjalankannya,
 * melihat persis akun mana yang bertabrakan, lalu memperbaiki datanya lewat
 * menu Manajemen Pengguna.
 *
 * Nomornya dibandingkan setelah DIBAKUKAN (lihat PencariAkunLogin), bukan
 * apa adanya — "0812-3456-7890" dan "+62 812 3456 7890" adalah nomor yang
 * sama, dan query GROUP BY biasa tidak akan pernah melihatnya begitu.
 * ================================================
 */
class CekNomorKembar extends Command
{
    protected $signature = 'simagas:cek-hp-kembar';

    protected $description = 'Mendaftar nomor HP yang dipakai lebih dari satu akun (menghalangi login lewat nomor HP).';

    public function handle(PencariAkunLogin $pencari): int
    {
        $akun = User::query()
            ->whereNotNull('no_hp')
            ->where('no_hp', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'no_hp', 'role']);

        $kelompok = $akun
            ->groupBy(fn (User $u) => $pencari->bakukanHp((string) $u->no_hp))
            ->filter(fn ($grup, $nomor) => $nomor !== '' && $grup->count() > 1);

        if ($kelompok->isEmpty()) {
            $this->info('Tidak ada nomor HP kembar. Semua akun bisa memakai nomornya untuk login.');

            return self::SUCCESS;
        }

        $this->warn($kelompok->count() . ' nomor dipakai lebih dari satu akun.');
        $this->line('Selama masih kembar, nomor-nomor ini TIDAK bisa dipakai untuk login;');
        $this->line('pemiliknya harus memakai email atau NIS anaknya.');
        $this->newLine();

        foreach ($kelompok as $nomor => $grup) {
            $this->line("<comment>{$nomor}</comment> — {$grup->count()} akun:");

            $this->table(
                ['ID', 'Nama', 'Email', 'Peran', 'Ditulis sebagai'],
                $grup->map(fn (User $u) => [
                    $u->id,
                    $u->name,
                    $u->email,
                    $u->role->value,
                    $u->no_hp,
                ])->all(),
            );
        }

        $this->line('Perbaiki lewat menu Manajemen Pengguna: kosongkan nomor pada akun yang');
        $this->line('bukan pemilik utamanya, atau perbaiki nomor yang salah ketik.');

        // Bukan kegagalan program — ini temuan data. Exit code tetap 0 supaya
        // aman dipanggil dari skrip pemeriksaan rutin tanpa menghentikannya.
        return self::SUCCESS;
    }
}
