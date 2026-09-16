<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membandingkan struktur database yang SEDANG DIPAKAI dengan struktur yang
 * seharusnya dihasilkan oleh seluruh berkas migration.
 *
 * ============ MASALAH YANG DIJAGA PERINTAH INI ============
 * Laravel mencatat migration yang sudah jalan berdasarkan NAMA BERKAS, bukan
 * isinya. Begitu sebuah migration tercatat "sudah jalan", mengubah isinya
 * tidak berpengaruh apa pun terhadap database itu.
 *
 * Maka menambahkan satu kolom ke dalam Schema::create() milik migration lama
 * menghasilkan keadaan yang sangat sulit dilihat:
 *
 *   - Di komputer pengembang SELALU benar. Database di sana dibuat dari nol
 *     sesudah barisnya ditambahkan, jadi kolomnya ada.
 *   - Di server SELALU salah. Migration-nya sudah tercatat jalan sejak dulu.
 *   - `php artisan migrate` menjawab "Nothing to migrate" — perintah yang
 *     biasanya memperbaiki justru menyatakan tidak ada yang perlu dikerjakan.
 *   - Seluruh uji otomatis tetap hijau, karena uji memakai database yang
 *     juga dibuat dari nol.
 *
 * Satu-satunya yang melihat kesalahannya adalah PENGGUNA, dalam bentuk galat
 * 500 di halaman yang kemarin baik-baik saja.
 *
 * Itu bukan kejadian hipotetis di project ini: kolom `keterangan` pada tabel
 * `absensi_siswa` persis mengalaminya, dan yang mati adalah halaman Pantau
 * Kehadiran Siswa milik kepala sekolah.
 * ==========================================================
 *
 * ============ CARA KERJANYA ============
 * Seluruh migration dijalankan ulang ke sebuah database SQLite sementara —
 * database "acuan" yang isinya persis seperti yang dimaksud berkas-berkas
 * migration hari ini. Struktur acuan itu lalu dibandingkan kolom demi kolom
 * dengan database yang sebenarnya dipakai.
 *
 * Dijalankan DI DALAM proses yang sama, bukan lewat proses terpisah: hosting
 * project ini mematikan proc_open, jadi apa pun yang memanggil perintah lain
 * sebagai proses baru tidak akan pernah berjalan di server.
 * =======================================
 */
class CekSkemaDatabase extends Command
{
    protected $signature = 'simagas:cek-skema';

    protected $description = 'Membandingkan struktur database yang dipakai dengan struktur yang dimaksud berkas migration.';

    public function handle(): int
    {
        $koneksiAsli = DB::getDefaultConnection();
        $berkasAcuan = storage_path('app/skema-acuan-' . uniqid() . '.sqlite');

        try {
            $acuan = $this->bangunAcuan($berkasAcuan, $koneksiAsli);
        } catch (\Throwable $e) {
            DB::setDefaultConnection($koneksiAsli);
            $this->hapusBerkas($berkasAcuan);

            $this->error('Gagal membangun skema acuan: ' . $e->getMessage());

            return self::FAILURE;
        }

        DB::setDefaultConnection($koneksiAsli);
        $this->hapusBerkas($berkasAcuan);

        return $this->laporkan($acuan, $koneksiAsli);
    }

    /**
     * Menjalankan seluruh migration ke SQLite sementara, lalu mengembalikan
     * daftar kolom per tabel.
     *
     * @return array<string, array<int, string>>
     */
    private function bangunAcuan(string $berkas, string $koneksiAsli): array
    {
        touch($berkas);

        config([
            'database.connections.skema_acuan' => [
                'driver' => 'sqlite',
                'database' => $berkas,
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);

        /*
         | Koneksi bawaan ikut DIPINDAH, bukan hanya dioper lewat --database.
         |
         | Migration di project ini memakai facade Schema secara langsung
         | (termasuk penjagaan Schema::hasColumn pada migration 000039).
         | Facade itu membaca koneksi BAWAAN, bukan koneksi yang dioper ke
         | perintah migrate. Tanpa baris ini, penjagaannya akan memeriksa
         | database sungguhan sementara tabelnya dibuat di database
         | sementara — dan hasilnya terlihat seperti kolom yang hilang
         | padahal tidak.
         */
        DB::setDefaultConnection('skema_acuan');
        DB::purge('skema_acuan');

        Artisan::call('migrate', [
            '--database' => 'skema_acuan',
            '--force' => true,
        ]);

        $hasil = [];

        foreach (Schema::connection('skema_acuan')->getTableListing() as $tabel) {
            $nama = str_contains($tabel, '.') ? explode('.', $tabel)[1] : $tabel;

            if ($nama === 'migrations') {
                continue;
            }

            $hasil[$nama] = Schema::connection('skema_acuan')->getColumnListing($nama);
        }

        DB::setDefaultConnection($koneksiAsli);

        return $hasil;
    }

    /**
     * @param  array<string, array<int, string>>  $acuan
     */
    private function laporkan(array $acuan, string $koneksi): int
    {
        $tabelHilang = [];
        $kolomHilang = [];

        foreach ($acuan as $tabel => $kolom) {
            if (! Schema::hasTable($tabel)) {
                $tabelHilang[] = $tabel;

                continue;
            }

            $nyata = Schema::getColumnListing($tabel);
            $selisih = array_values(array_diff($kolom, $nyata));

            if ($selisih) {
                $kolomHilang[$tabel] = $selisih;
            }
        }

        $this->line('Koneksi diperiksa : ' . $koneksi);
        $this->line('Tabel dibandingkan: ' . count($acuan));
        $this->newLine();

        if (! $tabelHilang && ! $kolomHilang) {
            $this->info('Struktur database SUDAH SESUAI dengan berkas migration.');

            return self::SUCCESS;
        }

        foreach ($tabelHilang as $t) {
            $this->error('TABEL HILANG  : ' . $t);
        }

        foreach ($kolomHilang as $t => $k) {
            $this->error('KOLOM HILANG  : ' . $t . ' -> ' . implode(', ', $k));
        }

        $this->newLine();
        $this->warn('Yang perlu dikerjakan:');
        $this->line('  Buat SATU migration baru yang menambahkan tabel/kolom di atas.');
        $this->line('  JANGAN mengubah migration lama — Laravel tidak akan menjalankannya');
        $this->line('  ulang, dan "migrate" akan tetap menjawab "Nothing to migrate".');

        /*
         | Keluar dengan kode GAGAL supaya perbedaan ini bisa menghentikan
         | proses deploy kalau suatu saat perintah ini dipasang di
         | .cpanel.yml. Peringatan yang hanya tercetak akan terlewat.
         */
        return self::FAILURE;
    }

    private function hapusBerkas(string $berkas): void
    {
        if (is_file($berkas)) {
            @unlink($berkas);
        }
    }
}
