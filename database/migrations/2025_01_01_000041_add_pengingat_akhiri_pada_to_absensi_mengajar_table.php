<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mencatat KAPAN guru diingatkan untuk menekan "Akhiri Sesi".
 *
 * ============ KENAPA PERLU KOLOM, BUKAN CACHE ============
 * Perintah pengingat berjalan SETIAP MENIT selama jendela +5 s.d. +15 menit
 * sesudah KBM selesai. Tanpa penanda, guru yang sama menerima hingga sepuluh
 * notifikasi untuk satu sesi.
 *
 * Penandanya sengaja di database, bukan di cache: `php artisan
 * optimize:clear` yang dijalankan di setiap deploy IKUT mengosongkan cache.
 * Deploy yang kebetulan jatuh di jendela itu akan membuat semua guru
 * diingatkan dua kali. Kolom ini juga meninggalkan jejak yang bisa dibaca:
 * "sudah diingatkan pukul 09.05, tetap tidak diakhiri".
 *
 * NULL = belum pernah diingatkan (termasuk semua baris lama).
 * Dijaga hasColumn() supaya aman dijalankan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('absensi_mengajar', 'pengingat_akhiri_pada')) {
            return;
        }

        Schema::table('absensi_mengajar', function (Blueprint $table) {
            $table->timestamp('pengingat_akhiri_pada')->nullable()->after('waktu_selesai');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('absensi_mengajar', 'pengingat_akhiri_pada')) {
            return;
        }

        Schema::table('absensi_mengajar', function (Blueprint $table) {
            $table->dropColumn('pengingat_akhiri_pada');
        });
    }
};
