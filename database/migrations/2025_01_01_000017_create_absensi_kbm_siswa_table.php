<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Absensi KBM — kehadiran siswa PER JAM PELAJARAN, diisi guru lewat menu
     * Jurnal & Absen Kelas.
     *
     * Bedanya dengan `absensi_siswa`: tabel itu mencatat kehadiran di GERBANG
     * (satu baris per siswa per hari, dari scan QR). Tabel ini mencatat
     * kehadiran di KELAS (satu baris per siswa per jam pelajaran). Keduanya
     * dibutuhkan justru supaya selisihnya kelihatan: anak yang masuk gerbang
     * pagi tapi hilang di jam ke-3 hanya bisa ketahuan kalau dua-duanya ada.
     *
     * CATATAN nilai enum: kolom `status` memakai 'alpa' (tanpa h), berbeda
     * dari 'alpha' di tabel absensi_siswa/absensi_pegawai. Lihat peringatan
     * lengkapnya di App\Enums\StatusKbm.
     */
    public function up(): void
    {
        Schema::create('absensi_kbm_siswa', function (Blueprint $table) {
            $table->id();

            // Jadwal dihapus -> catatan absensinya ikut terhapus. Tanpa
            // jadwalnya, baris ini tidak punya arti (tidak diketahui mata
            // pelajaran maupun jamnya).
            $table->foreignId('jadwal_id')
                ->constrained('jadwal_pelajaran')
                ->cascadeOnDelete();

            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnDelete();

            $table->date('tanggal');

            $table->enum('status', ['hadir', 'sakit', 'izin', 'alpa', 'bolos'])
                ->default('hadir');

            $table->string('keterangan')->nullable();

            $table->timestamps();

            // Satu siswa hanya boleh punya SATU catatan untuk satu jadwal di
            // satu tanggal. Ini yang membuat tombol "Simpan Absensi KBM" aman
            // ditekan berkali-kali: penyimpanannya memakai upsert, sehingga
            // guru yang mengoreksi status seorang siswa memperbarui baris yang
            // sama, bukan menumpuk baris baru. Tanpa constraint ini, dua klik
            // beruntun menghasilkan dua kebenaran yang berbeda untuk satu jam
            // pelajaran.
            $table->unique(['jadwal_id', 'siswa_id', 'tanggal'], 'kbm_jadwal_siswa_tanggal_unik');

            // Dipakai halaman Pantauan KBM wali murid, yang selalu bertanya
            // "semua catatan anak ini pada tanggal sekian".
            $table->index(['siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_kbm_siswa');
    }
};
