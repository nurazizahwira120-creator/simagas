<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nis')->unique();
            $table->string('nama');
            $table->string('no_hp_wali')->nullable();

            // Setiap siswa wajib punya kelas. Kelas tidak boleh dihapus
            // selama masih ada siswa terdaftar di dalamnya.
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->restrictOnDelete();

            // Akun wali murid (opsional: boleh belum terhubung ke akun user).
            $table->foreignId('wali_murid_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa');
    }
};
