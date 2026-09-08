<?php

namespace Database\Seeders;

use App\Enums\Hari;
use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DataContohSeeder extends Seeder
{
    /**
     * DATA CONTOH — untuk pengembangan & uji coba, BUKAN untuk hosting.
     *
     * Isinya delapan akun (satu per peran), puluhan siswa, riwayat absensi,
     * jadwal pelajaran, dan dua akun menunggu persetujuan — supaya setiap
     * dashboard langsung ada isinya tanpa mengisi manual lewat Tinker.
     *
     * TIDAK LAGI dijalankan otomatis oleh `migrate:fresh --seed`. Sejak versi
     * siap-hosting, perintah itu hanya membuat satu akun Super Admin (lihat
     * DatabaseSeeder). Untuk memuat data contoh ini, panggil eksplisit:
     *
     *     php artisan migrate:fresh
     *     php artisan db:seed --class=DataContohSeeder
     *
     * Password SEMUA akun contoh: "password" — jangan pernah dipakai di
     * server yang bisa diakses dari internet.
     */
    public function run(): void
    {
        // Baris pengaturan sistem (identitas sekolah + gateway WA) dibuat
        // lebih dulu. Dipanggil di sini SELAIN bisa dijalankan sendiri lewat
        //     php artisan db:seed --class=PengaturanSistemSeeder
        // supaya `migrate:fresh --seed` menghasilkan aplikasi yang langsung
        // utuh, bukan yang halaman pengaturannya kosong sampai seseorang ingat
        // menjalankan satu perintah tambahan.
        $this->call(PengaturanSistemSeeder::class);

        // ------------------------------------------------------------------
        // Satu akun default per role, masing-masing langsung ditautkan ke
        // baris `pegawai` (kecuali wali_murid — itu orang tua, bukan
        // pegawai sekolah).
        // ------------------------------------------------------------------
        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Muhammad Ridwan, S.Kom.',
            'email' => 'superadmin@assyaroniyyah.sch.id',
        ]);
        $this->buatPegawai($superAdmin, 'Administrator Sistem');

        $kepsek = User::factory()->kepsek()->create([
            'name' => 'Ahmad Fauzi, S.Pd.',
            'email' => 'kepsek@assyaroniyyah.sch.id',
        ]);
        $this->buatPegawai($kepsek, 'Kepala Sekolah');

        $guru = User::factory()->guru()->create([
            'name' => 'Dewi Kartika, S.Pd.',
            'email' => 'guru@assyaroniyyah.sch.id',
        ]);
        // Disimpan ke variabel karena dipakai lagi di seedJadwal() — supaya
        // akun guru demo dijamin punya jadwal mengajar begitu login.
        $guruUtama = $this->buatPegawai($guru, 'Guru Mapel');

        $staff = User::factory()->staff()->create([
            'name' => 'Rudi Hartono',
            'email' => 'staff@assyaroniyyah.sch.id',
        ]);
        $this->buatPegawai($staff, 'Staff Perpustakaan');

        $adminTu = User::factory()->adminTu()->create([
            'name' => 'Sri Wahyuni',
            'email' => 'tu@assyaroniyyah.sch.id',
        ]);
        $this->buatPegawai($adminTu, 'Staff Tata Usaha');

        $guruPiket = User::factory()->guruPiket()->create([
            'name' => 'Budi Santoso',
            'email' => 'piket@assyaroniyyah.sch.id',
        ]);
        $this->buatPegawai($guruPiket, 'Guru Piket');

        // ------------------------------------------------------------------
        // Kelas + wali kelas (masing-masing wali kelas juga pegawai) +
        // siswa + riwayat absensi_siswa.
        // ------------------------------------------------------------------
        $daftarKelas = [];

        foreach (['X RPL 1', 'XI RPL 1', 'XII RPL 1'] as $namaKelas) {
            $waliKelas = User::factory()->waliKelas()->create([
                'name' => fake()->name(),
                'email' => 'wali.' . Str::slug($namaKelas) . '@assyaroniyyah.sch.id',
            ]);
            $this->buatPegawai($waliKelas, 'Wali Kelas ' . $namaKelas);

            $kelas = Kelas::create([
                'nama_kelas' => $namaKelas,
                'wali_kelas_id' => $waliKelas->id,
            ]);

            $daftarKelas[] = $kelas;

            $jumlahSiswa = fake()->numberBetween(15, 20);

            for ($i = 0; $i < $jumlahSiswa; $i++) {
                $namaSiswa = fake()->name();

                $waliMurid = User::factory()->waliMurid()->create([
                    'name' => 'Wali dari ' . $namaSiswa,
                    'email' => fake()->unique()->safeEmail(),
                ]);

                $siswa = Siswa::factory()->for($kelas)->create([
                    'nama' => $namaSiswa,
                    'wali_murid_id' => $waliMurid->id,
                ]);

                $this->seedRiwayatSiswa($siswa);
            }
        }

        // Riwayat absensi_pegawai untuk semua pegawai yang baru dibuat di
        // atas (super admin, kepsek, guru, staff, admin TU, guru piket,
        // dan tiap wali kelas) — dilakukan terakhir supaya query di bawah
        // menjangkau semuanya sekaligus.
        Pegawai::all()->each(fn (Pegawai $pegawai) => $this->seedRiwayatPegawai($pegawai));

        // Jadwal pelajaran — dibuat paling akhir karena butuh kelas & pegawai
        // yang sudah lengkap.
        $this->seedJadwal($daftarKelas, $guruUtama);

        $this->seedPengaturan();
        $this->seedTahunAjaran();
        $this->seedAkunMenungguPersetujuan();

        $this->command?->info('Seeding selesai. Login dengan salah satu email di atas, password: password');
    }

    /**
     * Nilai bawaan aturan waktu absensi (tab "Aturan Absensi" di Pengaturan).
     */
    private function seedPengaturan(): void
    {
        foreach ([
            'jam_masuk_siswa' => '07:00',
            'batas_terlambat_siswa' => '07:15',
            'jam_masuk_pegawai' => '06:45',
            'batas_terlambat_pegawai' => '07:00',

            // Titik pusat sekolah & radius toleransi GPS untuk fitur Absen
            // Radius. Nilai di bawah hanya PLACEHOLDER (Monas, Jakarta) —
            // wajib diganti lewat menu Pengaturan Sistem > Lokasi & Radius GPS
            // dengan koordinat sekolah sebenarnya, kalau tidak semua guru akan
            // dianggap berada di luar radius.
            'lat_sekolah' => '-6.175392',
            'lng_sekolah' => '106.827153',
            'radius_gps' => '50',
        ] as $kunci => $nilai) {
            Pengaturan::simpan($kunci, $nilai);
        }
    }

    /**
     * Tiga tahun ajaran contoh; yang terbaru (Ganjil) dijadikan aktif.
     */
    private function seedTahunAjaran(): void
    {
        $tahunIni = (int) now()->format('Y');

        $daftar = [
            [$tahunIni - 1 . '/' . $tahunIni, 'ganjil', false],
            [$tahunIni - 1 . '/' . $tahunIni, 'genap', false],
            [$tahunIni . '/' . ($tahunIni + 1), 'ganjil', true],
        ];

        foreach ($daftar as [$tahun, $semester, $aktif]) {
            TahunAjaran::updateOrCreate(
                ['tahun' => $tahun, 'semester' => $semester],
                ['aktif' => $aktif],
            );
        }
    }

    /**
     * Dua akun berstatus 'pending' supaya tab "Approval Akun Baru" langsung
     * ada isinya untuk dicoba.
     *
     * Di kondisi nyata antrean ini terisi dari halaman Registrasi Mandiri
     * (/daftar, lihat App\Livewire\Auth\Register) — setiap pendaftar masuk
     * dengan status 'pending' dan baru bisa login setelah disetujui.
     */
    private function seedAkunMenungguPersetujuan(): void
    {
        $calon = [
            ['Hendra Gunawan, S.Pd.', 'hendra.calon@assyaroniyyah.sch.id', UserRole::Guru],
            ['Siti Aminah', 'siti.calon@assyaroniyyah.sch.id', UserRole::Staff],
        ];

        foreach ($calon as [$nama, $email, $role]) {
            User::factory()->create([
                'name' => $nama,
                'email' => $email,
                'role' => $role,
                'status' => StatusAkun::Pending,
            ]);
        }
    }

    /**
     * Jadwal pelajaran contoh: 2 slot per hari (Senin–Jumat) untuk setiap
     * kelas, dibagi ke pegawai yang jabatannya mengandung "Guru" atau
     * "Wali Kelas".
     *
     * @param  array<int, Kelas>  $daftarKelas
     */
    private function seedJadwal(array $daftarKelas, Pegawai $guruUtama): void
    {
        // Slot jam tetap supaya jam_selesai selalu setelah jam_mulai.
        $slots = [
            ['07:00', '08:30'],
            ['08:30', '10:00'],
            ['10:15', '11:45'],
            ['12:30', '14:00'],
        ];

        $pengajar = Pegawai::query()
            ->where(fn ($query) => $query
                ->where('jabatan', 'like', '%Guru%')
                ->orWhere('jabatan', 'like', '%Wali Kelas%'))
            ->get();

        if ($pengajar->isEmpty() || empty($daftarKelas)) {
            return;
        }

        foreach ($daftarKelas as $indexKelas => $kelas) {
            foreach (Hari::hariSekolah() as $hari) {
                foreach (fake()->randomElements($slots, 2) as $indexSlot => [$mulai, $selesai]) {
                    // Slot pertama tiap hari di kelas pertama sengaja
                    // diberikan ke akun guru demo, supaya menu "Jadwal
                    // Mengajar" langsung ada isinya begitu login dengan
                    // guru@assyaroniyyah.sch.id.
                    $guru = ($indexKelas === 0 && $indexSlot === 0)
                        ? $guruUtama
                        : $pengajar->random();

                    JadwalPelajaran::factory()->create([
                        'kelas_id' => $kelas->id,
                        'guru_id' => $guru->id,
                        'hari' => $hari,
                        'jam_mulai' => $mulai,
                        'jam_selesai' => $selesai,
                    ]);
                }
            }
        }
    }

    /**
     * Buat baris `pegawai` yang ditautkan ke satu akun user, dengan NIP
     * acak (18 digit, meniru format NIP PNS) supaya tetap unik antar akun.
     */
    private function buatPegawai(User $user, string $jabatan): Pegawai
    {
        // nip & no_hp cukup mengandalkan default acak dari PegawaiFactory —
        // hanya nama, jabatan, dan tautan akun yang perlu disesuaikan di sini.
        return Pegawai::factory()->create([
            'nama' => $user->name,
            'jabatan' => $jabatan,
            'user_id' => $user->id,
        ]);
    }

    /**
     * Riwayat 9 hari sekolah terakhir (Senin–Jumat) untuk satu siswa. Hari
     * ini sengaja dikosongkan supaya fitur scan piket ada sesuatu untuk
     * didemokan dari nol.
     */
    private function seedRiwayatSiswa(Siswa $siswa): void
    {
        $tanggal = now()->subDay();
        $dibuat = 0;

        while ($dibuat < 9) {
            if (! $tanggal->isWeekend()) {
                AbsensiSiswa::factory()->for($siswa)->create([
                    'tanggal' => $tanggal->toDateString(),
                ]);
                $dibuat++;
            }

            $tanggal = $tanggal->copy()->subDay();
        }
    }

    /**
     * Sama seperti seedRiwayatSiswa(), tapi untuk pegawai. Hari ini juga
     * sengaja dikosongkan — begitu fitur scan-pegawai di halaman Piket
     * dicoba, datanya benar-benar baru, bukan menimpa data seed.
     */
    private function seedRiwayatPegawai(Pegawai $pegawai): void
    {
        $tanggal = now()->subDay();
        $dibuat = 0;

        while ($dibuat < 9) {
            if (! $tanggal->isWeekend()) {
                AbsensiPegawai::factory()->for($pegawai)->create([
                    'tanggal' => $tanggal->toDateString(),
                ]);
                $dibuat++;
            }

            $tanggal = $tanggal->copy()->subDay();
        }
    }
}
