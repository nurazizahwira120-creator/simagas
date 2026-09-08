<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengaturan sistem berbentuk SATU BARIS (single-row config).
     *
     * ================== KENAPA TABEL BARU ==================
     * Sudah ada tabel `pengaturan` yang berbentuk kunci-nilai (jam masuk,
     * batas terlambat, koordinat sekolah, radius GPS). Tabel ini TIDAK
     * menggantikannya — keduanya hidup berdampingan dengan pembagian tugas:
     *
     *   pengaturan         : nilai-nilai aturan absensi yang bisa terus
     *                        bertambah jenisnya, cocok untuk kunci-nilai.
     *   pengaturan_sistem  : identitas sekolah & kredensial gateway WA,
     *                        yang jumlah kolomnya tetap dan bertipe beda-beda
     *                        (boolean, integer, string terenkripsi).
     *
     * Bentuk kunci-nilai memaksa semuanya jadi string, sehingga toggle
     * boolean dan angka delay harus di-cast manual di setiap pemakaian — dan
     * token yang butuh enkripsi tidak punya tempat yang wajar. Karena itu
     * bagian ini diberi tabel sendiri, sesuai brief.
     * =======================================================
     */
    public function up(): void
    {
        Schema::create('pengaturan_sistem', function (Blueprint $table) {
            $table->id();

            $table->string('nama_sekolah');

            // Sakelar utama notifikasi WA. Default FALSE supaya sekolah yang
            // baru memasang sistem ini tidak diam-diam mengirim pesan ke
            // ratusan nomor wali murid sebelum tokennya sempat diisi dan
            // teksnya sempat diperiksa.
            $table->boolean('wa_gateway_status')->default(false);

            // Disimpan TERENKRIPSI lewat cast di model PengaturanSistem.
            // Kolomnya text (bukan string 255) karena ciphertext Laravel jauh
            // lebih panjang daripada tokennya sendiri — dengan string(255)
            // token Fonnte yang wajar pun bisa terpotong saat disimpan.
            $table->text('fonnte_token')->nullable();

            // Jeda antar pesan (detik) — fitur bawaan Fonnte untuk mengurangi
            // risiko nomor diblokir karena mengirim beruntun.
            $table->integer('wa_delay')->default(2);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_sistem');
    }
};
