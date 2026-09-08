<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biodata pelengkap untuk siswa & pegawai.
     *
     * Semua kolom nullable dengan sengaja: tabel ini sudah berisi data, dan
     * memaksa kolom baru wajib diisi akan membuat migrasi gagal atau
     * mengisi ratusan baris lama dengan nilai karangan. Kolomnya diisi
     * bertahap lewat form Edit Data Lengkap.
     */
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('tempat_lahir')->nullable()->after('nama');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->after('tanggal_lahir');
            $table->string('agama')->nullable()->after('jenis_kelamin');
            $table->text('alamat')->nullable()->after('agama');

            // KENAPA SISWA PUNYA KOLOM `foto` SENDIRI, sedangkan pegawai tidak:
            // siswa TIDAK punya akun login di aplikasi ini — yang punya akun
            // adalah wali muridnya. Kalau foto siswa ikut disimpan di
            // users.foto milik wali murid, maka wajah anak akan muncul sebagai
            // foto profil orang tuanya di sidebar dan di Kartu Pegawai. Jadi
            // foto siswa harus punya tempatnya sendiri.
            $table->string('foto')->nullable()->after('alamat');
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('tempat_lahir')->nullable()->after('nama');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->after('tanggal_lahir');
            $table->string('agama')->nullable()->after('jenis_kelamin');

            // Pegawai dapat kolom alamat sendiri (bukan hanya menumpang
            // users.alamat) karena user_id-nya nullable: ada pegawai yang
            // datanya tercatat di sekolah tanpa pernah dibuatkan akun login.
            // Kalau ada akun yang tertaut, keduanya disamakan saat disimpan.
            $table->text('alamat')->nullable()->after('agama');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn(['tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'alamat', 'foto']);
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn(['tempat_lahir', 'tanggal_lahir', 'jenis_kelamin', 'agama', 'alamat']);
        });
    }
};
