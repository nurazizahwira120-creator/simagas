<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CATATAN UNTUK GURU INVAL.
 *
 * Tugas IDT yang ditulis guru saat mengajukan izin sudah otomatis tampil
 * di halaman guru inval. Kolom ini untuk pesan TAMBAHAN dari Kepala
 * Sekolah / Super Admin saat menunjuk, misalnya:
 *   - tugas susulan yang dikirim guru lewat WhatsApp;
 *   - guru yang izinnya tercatat lewat absensi harian (sakit/izin
 *     mendadak), sehingga tidak ada deskripsi tugas sama sekali.
 *
 * Kolom baru, nullable — penugasan lama tidak tersentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('penugasan_inval', 'catatan')) {
            return;
        }

        Schema::table('penugasan_inval', function (Blueprint $table) {
            $table->text('catatan')->nullable()->after('ditunjuk_oleh');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('penugasan_inval', 'catatan')) {
            Schema::table('penugasan_inval', function (Blueprint $table) {
                $table->dropColumn('catatan');
            });
        }
    }
};
