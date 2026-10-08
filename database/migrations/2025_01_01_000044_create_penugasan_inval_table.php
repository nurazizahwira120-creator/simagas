<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan GURU INVAL: siapa yang menggantikan satu jadwal pada satu tanggal.
 *
 * ============ DARI "KELAS PENGGANTI" KE "GURU INVAL" ============
 * Sebelumnya, kelas yang gurunya berhalangan boleh diisi absensinya oleh
 * SIAPA SAJA dari guru, wali kelas, guru piket — tanpa penunjukan. Dalam
 * praktik, dua orang bisa sama-sama masuk ke kelas yang sama, atau justru
 * tidak ada yang masuk karena masing-masing mengira orang lain yang pergi.
 *
 * Sekarang Kepala Sekolah / Super Admin MENUNJUK satu orang (guru atau
 * staf) per jam pelajaran. Hanya orang itu — ditambah Kepsek & Super Admin
 * untuk koreksi — yang bisa mengisi absensi kelasnya.
 * ================================================================
 *
 * unique(jadwal_id, tanggal): satu jam pelajaran pada satu hari hanya punya
 * SATU inval. Menunjuk ulang berarti mengganti orangnya, bukan menambah.
 *
 * cascadeOnDelete pada jadwal: jadwal yang dihapus admin tidak lagi punya
 * jam yang perlu digantikan. cascadeOnDelete pada inval_user_id: akun yang
 * dihapus tidak bisa lagi mengisi apa pun. nullOnDelete pada ditunjuk_oleh:
 * catatan penugasan tetap ada walau akun penunjuknya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('penugasan_inval')) {
            return;
        }

        Schema::create('penugasan_inval', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_id')->constrained('jadwal_pelajaran')->cascadeOnDelete();
            $table->date('tanggal');
            $table->foreignId('inval_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ditunjuk_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['jadwal_id', 'tanggal'], 'penugasan_inval_jadwal_tanggal_unik');
            // "Tugas inval saya hari ini" — dicari per orang per tanggal.
            $table->index(['inval_user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_inval');
    }
};
