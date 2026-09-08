<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel cache bawaan Laravel — dibutuhkan karena Laravel 11/12 memakai
     * CACHE_STORE=database sebagai default di .env, sehingga setiap request
     * yang menyentuh cache akan query ke tabel ini.
     *
     * Nomornya sengaja 0001_01_01_000001 (persis seperti skeleton Laravel):
     * dijalankan setelah users dan sebelum tabel-tabel aplikasi. Tidak ada
     * foreign key di sini, jadi urutannya aman.
     *
     * Catatan: tabel jobs/job_batches/failed_jobs TIDAK dibuat di sini.
     * Laravel biasanya menaruhnya di 0001_01_01_000002_create_jobs_table.php,
     * tapi di project ini sudah ditangani oleh
     * 2025_01_01_000006_create_jobs_table.php. Jangan tambahkan file bawaan
     * itu — tabelnya akan dibuat dua kali dan migrate akan gagal.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
