<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Absensi Mengajar — catatan guru masuk kelas, dibuat dengan men-scan
     * stiker QR yang ditempel di meja guru tiap ruangan.
     *
     * BEDA dengan `absensi_pegawai`:
     *   absensi_pegawai  = kehadiran di sekolah, SATU baris per hari
     *                      (unique pegawai_id + tanggal).
     *   absensi_mengajar = kehadiran di kelas, BANYAK baris per hari —
     *                      seorang guru bisa mengajar 4 jam di 4 ruangan
     *                      berbeda, dan tiap kali masuk ia scan lagi.
     * Karena itu tabel ini TIDAK punya unique per tanggal.
     *
     * Kolom `user_id` (bukan pegawai_id) mengikuti brief. Konsekuensinya:
     * laporan yang ingin menampilkan NIP/jabatan perlu satu lompatan lagi
     * lewat relasi User::pegawai. Ditulis di sini supaya tidak terlihat
     * seperti kelalaian nanti.
     */
    public function up(): void
    {
        Schema::create('absensi_mengajar', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Isi mentah QR ruangan, mis. "RUANG-X-RPL-1". Disimpan apa
            // adanya — bukan foreign key ke tabel kelas — supaya stiker
            // tetap berlaku walau kelasnya nanti diganti nama atau dihapus,
            // dan supaya riwayat lama tidak ikut berubah artinya.
            $table->string('kode_kelas', 60);

            $table->dateTime('waktu_mulai');

            $table->timestamps();

            // Dipakai dua hal: menampilkan riwayat "mengajar hari ini", dan
            // pengecekan scan ganda (kode yang sama dalam beberapa menit).
            $table->index(['user_id', 'waktu_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_mengajar');
    }
};
