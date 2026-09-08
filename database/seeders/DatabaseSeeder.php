<?php

namespace Database\Seeders;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder SIAP HOSTING — hanya isi minimum supaya aplikasi bisa dipakai.
 *
 * Jalankan:  php artisan migrate:fresh --seed
 *
 * Yang dibuat:
 *   1. SATU akun Super Admin (satu-satunya akun di sistem)
 *   2. Baris pengaturan_sistem (identitas sekolah, WA nonaktif, titik GPS)
 *   3. Aturan waktu absensi bawaan
 *   4. Satu tahun ajaran aktif
 *
 * TIDAK ADA data contoh: tidak ada guru, siswa, kelas, jadwal, atau riwayat
 * absensi. Semua diisi sendiri lewat aplikasi (menu Master Data Sekolah,
 * atau Import Excel).
 *
 * ========== KENAPA DATA CONTOH TIDAK DIHAPUS, TAPI DIPINDAH ==========
 * Seluruh data contoh yang dulu ada di berkas ini masih hidup di
 * Database\Seeders\DataContohSeeder. Menghapusnya sama sekali akan membuat
 * pengembangan berikutnya jauh lebih repot — setiap kali ingin mencoba
 * dashboard Kepsek atau Pantauan KBM, seseorang harus membuat puluhan baris
 * data lewat tangan.
 *
 * Untuk memuat data contoh di komputer lokal:
 *
 *     php artisan migrate:fresh
 *     php artisan db:seed --class=DataContohSeeder
 *
 * Penggabungan itu SENGAJA tidak dilakukan: di server hosting,
 * `migrate:fresh --seed` harus menghasilkan sistem yang bersih — bukan sistem
 * berisi delapan akun demo berpassword "password" yang bisa dipakai siapa pun
 * yang pernah membaca dokumentasi ini.
 * =====================================================================
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PengaturanSistemSeeder::class);

        $this->seedAturanWaktu();
        $this->seedTahunAjaranAktif();
        $this->seedSuperAdmin();
    }

    /**
     * Satu-satunya akun yang dibuat.
     *
     * Kredensialnya bisa disetel lewat .env supaya tidak ada kata sandi yang
     * dipaku di dalam kode yang ikut terunggah ke server:
     *
     *     SEEDER_ADMIN_NAMA="Super Admin SIMAGAS"
     *     SEEDER_ADMIN_EMAIL=admin@simagas.com
     *     SEEDER_ADMIN_PASSWORD=KataSandiYangPanjangDanUnik
     *
     * Kalau tidak disetel, dipakai nilai bawaan sesuai brief — DAN seeder
     * mencetak peringatan besar. Kata sandi "password" di server yang bisa
     * diakses dari internet berarti siapa pun yang menebak satu alamat email
     * memegang kendali penuh atas seluruh data sekolah, termasuk data pribadi
     * ratusan siswa.
     */
    private function seedSuperAdmin(): void
    {
        $nama = env('SEEDER_ADMIN_NAMA', 'Super Admin SIMAGAS');
        $email = env('SEEDER_ADMIN_EMAIL', 'admin@simagas.com');
        $sandi = env('SEEDER_ADMIN_PASSWORD', 'password');

        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $nama,
                'password' => Hash::make($sandi),
                'role' => UserRole::SuperAdmin,

                // Status HARUS 'active'. Akun yang dibuat lewat halaman
                // Registrasi berstatus 'pending' dan menunggu persetujuan —
                // dan tidak akan ada yang bisa menyetujui akun pertama ini
                // kalau ia sendiri ikut pending.
                'status' => StatusAkun::Active,
                'email_verified_at' => now(),
            ]
        );

        // Baris `pegawai` untuk akun ini. Dibuat supaya halaman Profil Pribadi
        // punya data yang lengkap dan Super Admin muncul di Data Pegawai
        // sebagai staf yang memang ada — bukan akun tanpa identitas.
        Pegawai::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'nama' => $nama,
                'jabatan' => 'Administrator Sistem',

                // NIP dikosongkan (kolomnya sudah nullable sejak migration
                // 000016), bukan diisi angka karangan: nomor pegawai palsu
                // yang terlanjur tersimpan sulit dibedakan dari yang asli
                // begitu data pegawai sungguhan mulai dimasukkan.
                'nip' => null,
            ]
        );

        $this->command?->newLine();
        $this->command?->info('Akun Super Admin siap:');
        $this->command?->line('  Email    : ' . $email);
        $this->command?->line('  Password : ' . (env('SEEDER_ADMIN_PASSWORD') ? '(diambil dari .env)' : $sandi));

        if (! env('SEEDER_ADMIN_PASSWORD')) {
            $this->command?->newLine();
            $this->command?->error('  PERINGATAN KEAMANAN');
            $this->command?->warn('  Kata sandi di atas adalah bawaan dan diketahui umum.');
            $this->command?->warn('  GANTI SEGERA setelah login pertama lewat menu Profil Pribadi,');
            $this->command?->warn('  atau setel SEEDER_ADMIN_PASSWORD di .env sebelum menjalankan seeder.');
        }

        $this->command?->newLine();
    }

    /**
     * Aturan waktu & titik GPS bawaan (tab "Aturan Absensi" dan "Lokasi &
     * Radius GPS" di halaman Pengaturan).
     *
     * Tetap diisi walau semuanya bisa diubah dari aplikasi: halaman pengaturan
     * yang kolomnya kosong di instalasi baru membuat Super Admin harus menebak
     * format apa yang diharapkan tiap kolom.
     */
    private function seedAturanWaktu(): void
    {
        foreach ([
            'jam_masuk_siswa' => '07:00',
            'batas_terlambat_siswa' => '07:15',
            'jam_masuk_pegawai' => '06:45',
            'batas_terlambat_pegawai' => '07:00',

            // Kunci GPS lama. Sejak migration 000020, yang dipakai fitur Absen
            // Radius adalah kolom di `pengaturan_sistem`; tiga kunci ini
            // tinggal sebagai cadangan untuk instalasi yang belum bermigrasi.
            'lat_sekolah' => '-6.175392',
            'lng_sekolah' => '106.827153',
            'radius_gps' => '50',
        ] as $kunci => $nilai) {
            Pengaturan::simpan($kunci, $nilai);
        }
    }

    /**
     * Satu tahun ajaran aktif.
     *
     * Wajib ada: beberapa halaman (mis. Kenaikan Kelas) mengacu ke tahun
     * ajaran yang sedang aktif, dan tanpa satu baris pun di tabel ini
     * halaman-halaman itu tidak punya konteks untuk bekerja.
     *
     * Semesternya ditebak dari bulan berjalan — Juli s.d. Desember dianggap
     * ganjil, Januari s.d. Juni genap — supaya nilai bawaannya masuk akal
     * kapan pun sistem ini dipasang.
     */
    private function seedTahunAjaranAktif(): void
    {
        $bulan = (int) now()->format('n');
        $tahun = (int) now()->format('Y');

        $ganjil = $bulan >= 7;
        $awal = $ganjil ? $tahun : $tahun - 1;

        TahunAjaran::query()->updateOrCreate(
            ['tahun' => $awal . '/' . ($awal + 1), 'semester' => $ganjil ? 'ganjil' : 'genap'],
            ['aktif' => true],
        );
    }
}
