<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Struktur sama persis dengan `absensi_siswa`, tapi untuk kehadiran
     * pegawai (guru, staff, admin TU, dst). Dipisah dari absensi_siswa
     * karena subjeknya beda (pegawai, bukan siswa) meski bentuk datanya
     * sama — memisahkan tabel menghindari kolom nullable ganda
     * (siswa_id/pegawai_id sama-sama nullable) yang gampang salah isi.
     */
    public function up(): void
    {
        Schema::create('absensi_pegawai', function (Blueprint $table) {
            $table->id();

            // Jika pegawai dihapus, riwayat absensinya ikut terhapus.
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->cascadeOnDelete();

            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpha'])
                ->default('hadir');
            $table->text('keterangan')->nullable();

            $table->timestamps();

            // Satu pegawai hanya boleh punya satu catatan absensi per tanggal.
            $table->unique(['pegawai_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_pegawai');
    }
};
