<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sebelumnya bernama `absensi` — diganti `absensi_siswa` supaya jelas
     * berpasangan dengan `absensi_pegawai` sejak arsitektur database
     * diperluas untuk mencakup kehadiran pegawai juga.
     */
    public function up(): void
    {
        Schema::create('absensi_siswa', function (Blueprint $table) {
            $table->id();

            // Jika siswa dihapus, riwayat absensinya ikut terhapus.
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnDelete();

            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpha'])
                ->default('hadir');
            $table->text('keterangan')->nullable();

            $table->timestamps();

            // Satu siswa hanya boleh punya satu catatan absensi per tanggal.
            $table->unique(['siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_siswa');
    }
};
