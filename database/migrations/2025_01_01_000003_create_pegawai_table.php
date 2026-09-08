<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Data induk pegawai/staf sekolah (guru, wali kelas, staff, admin TU,
     * guru piket, kepsek, super admin) — terpisah dari `users` karena tidak
     * semua pegawai tentu punya akun login, dan `users` fokus pada
     * autentikasi/role, bukan data kepegawaian (NIP, jabatan, dsb).
     */
    public function up(): void
    {
        Schema::create('pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('nip')->unique();
            $table->string('nama');
            $table->string('jabatan');
            $table->string('no_hp')->nullable();

            // Akun login terkait (opsional). unique() -> satu akun user
            // hanya boleh terhubung ke maksimal satu baris pegawai.
            $table->foreignId('user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pegawai');
    }
};
