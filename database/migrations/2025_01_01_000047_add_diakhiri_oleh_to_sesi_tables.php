<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * JEJAK "BANTU AKHIRI SESI" OLEH KEPALA SEKOLAH / SUPER ADMIN.
 *
 * Sesi yang lupa diakhiri guru (atau pembina ekskul) bisa ditutup dari
 * Live Monitoring. Karena tindakan itu ikut menentukan honor, setiap
 * penutupan oleh orang lain WAJIB meninggalkan jejak:
 *   diakhiri_oleh    : akun yang menekan tombol (null = ditutup sendiri)
 *   alasan_diakhiri  : alasan yang diketik saat itu
 *
 * Kolom baru, nullable, tanpa mengubah kolom lama — data yang sudah ada
 * tidak tersentuh sama sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['absensi_mengajar', 'sesi_ekskul'] as $tabel) {
            if (Schema::hasColumn($tabel, 'diakhiri_oleh')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->foreignId('diakhiri_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->string('alasan_diakhiri', 255)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['absensi_mengajar', 'sesi_ekskul'] as $tabel) {
            if (! Schema::hasColumn($tabel, 'diakhiri_oleh')) {
                continue;
            }

            Schema::table($tabel, function (Blueprint $table) {
                $table->dropConstrainedForeignId('diakhiri_oleh');
                $table->dropColumn('alasan_diakhiri');
            });
        }
    }
};
