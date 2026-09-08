<?php
// Dibuat dengan:  php artisan make:migration create_ekskul_siswa_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot keanggotaan ekskul: siswa mana ikut ekskul mana.
 *
 * cascadeOnDelete di KEDUA sisi, dan itu memang yang benar di sini: baris
 * pivot tanpa ekskul atau tanpa siswa tidak berarti apa-apa. Berbeda dengan
 * pembina_id di migration sebelumnya — di sana jadwalnya masih bermakna walau
 * pembinanya hilang.
 *
 * unique(jadwal_ekskul_id, siswa_id) menjaga satu siswa tidak terdaftar dua
 * kali di ekskul yang sama. Tanpa itu, tombol "Tambah" yang tertekan dua kali
 * menghasilkan siswa kembar di daftar absensi — dan dua baris kehadiran untuk
 * satu orang pada hari yang sama.
 *
 * timestamps() ditambahkan (di luar dua kolom yang diminta) supaya pertanyaan
 * "sejak kapan anak ini ikut ekskul" bisa dijawab. Kolomnya nullable secara
 * bawaan, jadi tidak ada yang rusak kalau tidak dipakai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekskul_siswa', function (Blueprint $table) {
            $table->id();

            $table->foreignId('jadwal_ekskul_id')->constrained('jadwal_ekskuls')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['jadwal_ekskul_id', 'siswa_id'], 'ekskul_siswa_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekskul_siswa');
    }
};
