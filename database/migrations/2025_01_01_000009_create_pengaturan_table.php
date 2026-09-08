<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan aplikasi dalam bentuk kunci-nilai.
     *
     * Dipilih model kunci-nilai (bukan satu kolom per pengaturan) supaya
     * menambah pengaturan baru nanti tidak perlu migration lagi — cukup
     * simpan kunci baru. Nilainya disimpan sebagai string; pemanggilnya yang
     * menafsirkan (mis. '07:00' untuk jam).
     */
    public function up(): void
    {
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('kunci')->primary();
            $table->string('nilai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
