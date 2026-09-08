<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kolom pendukung fitur Pengajuan Izin pegawai.
 *
 * KENAPA `alasan` DIPISAH DARI `keterangan` YANG SUDAH ADA:
 * kolom `keterangan` sekarang dipakai bermacam-macam — catatan admin saat
 * mengoreksi absensi, penanda sumber ("scan gerbang", "input manual"), dan
 * sebagainya. Kalau alasan izin yang ditulis pegawai ikut ditumpuk ke sana,
 * halaman Kepala Sekolah tidak punya cara membedakan mana kalimat yang
 * benar-benar berasal dari pegawai yang bersangkutan dan mana yang catatan
 * internal. Kolom terpisah membuat pertanyaan "apa alasan yang DIAJUKAN
 * pegawai ini" bisa dijawab tanpa menebak.
 *
 * `bukti_foto` menyimpan PATH relatif di disk `public`, bukan URL penuh —
 * supaya berkasnya tetap ketemu kalau domain sekolah berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_pegawai', function (Blueprint $table) {
            $table->text('alasan')->nullable()->after('keterangan');
            $table->string('bukti_foto')->nullable()->after('alasan');
        });
    }

    public function down(): void
    {
        Schema::table('absensi_pegawai', function (Blueprint $table) {
            $table->dropColumn(['alasan', 'bukti_foto']);
        });
    }
};
