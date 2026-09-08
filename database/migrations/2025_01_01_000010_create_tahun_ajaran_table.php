<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tahun ajaran + semester. Tepat satu baris yang boleh `aktif` pada satu
     * waktu — aturan itu ditegakkan di SettingController::updateTahunAjaran()
     * lewat transaksi (menonaktifkan semua dulu, baru mengaktifkan yang
     * dipilih), karena constraint "hanya satu true" tidak bisa dinyatakan
     * secara portabel di MySQL maupun SQLite.
     */
    public function up(): void
    {
        Schema::create('tahun_ajaran', function (Blueprint $table) {
            $table->id();
            $table->string('tahun');                       // mis. "2026/2027"
            $table->enum('semester', ['ganjil', 'genap']);
            $table->boolean('aktif')->default(false);
            $table->timestamps();

            $table->unique(['tahun', 'semester']);
            $table->index('aktif');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tahun_ajaran');
    }
};
