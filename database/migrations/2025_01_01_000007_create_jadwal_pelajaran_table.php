<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jadwal pelajaran: satu baris = satu slot mengajar (mata pelajaran
     * tertentu, di kelas tertentu, oleh guru tertentu, pada hari & jam
     * tertentu).
     *
     * Dibuat paling akhir karena bergantung pada dua tabel lain:
     * `kelas` (000001) dan `pegawai` (000003).
     */
    public function up(): void
    {
        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id();

            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('mata_pelajaran');

            // Kelas yang diajar. restrictOnDelete: kelas yang masih punya
            // jadwal tidak boleh dihapus begitu saja — konsisten dengan
            // aturan yang sama di tabel siswa.
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->restrictOnDelete();

            // Guru pengajar -> tabel `pegawai` (BUKAN `users`), karena yang
            // mengajar adalah orangnya, dan tidak semua pegawai tentu punya
            // akun login.
            //
            // Catatan: syarat "jabatannya guru" TIDAK bisa dipaksakan di
            // level database — kolom `jabatan` itu teks bebas. Penyaringannya
            // dilakukan di level aplikasi (lihat JadwalPelajaranController).
            $table->foreignId('guru_id')
                ->constrained('pegawai')
                ->restrictOnDelete();

            $table->timestamps();

            // Mempercepat dua query yang paling sering dipakai: "jadwal
            // kelas X pada hari Y" dan "jadwal mengajar saya hari ini".
            $table->index(['kelas_id', 'hari']);
            $table->index(['guru_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran');
    }
};
