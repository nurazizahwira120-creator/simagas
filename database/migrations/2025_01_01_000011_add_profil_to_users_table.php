<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom kontak untuk halaman Registrasi Mandiri.
     *
     * KENAPA DI TABEL `users`, BUKAN `pegawai`:
     * Tabel `pegawai` sudah punya kolom `no_hp`, tapi baris `pegawai` hanya
     * dibuat untuk role yang memang pegawai sekolah (kepsek, guru, staff).
     * Seorang WALI MURID mendaftar tanpa baris `pegawai` sama sekali — kalau
     * no_hp & alamat hanya ada di sana, dua data itu hilang begitu saja
     * untuk pendaftar wali murid, padahal justru merekalah yang paling perlu
     * dihubungi (notifikasi WhatsApp kehadiran anak).
     *
     * Untuk role pegawai, `pegawai.no_hp` tetap diisi juga saat registrasi,
     * supaya modul kepegawaian yang sudah ada tidak perlu diubah.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('no_hp', 30)->nullable()->after('email');
            $table->text('alamat')->nullable()->after('no_hp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['no_hp', 'alamat']);
        });
    }
};
