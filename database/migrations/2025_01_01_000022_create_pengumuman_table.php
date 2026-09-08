<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengumuman broadcast dari Super Admin / Kepala Sekolah.
 *
 * Tabel ini menyimpan RIWAYAT pengumuman, bukan status baca per orang —
 * status baca sudah dipegang tabel `notifications` (satu baris per penerima).
 * Memisahkan keduanya membuat pertanyaan "apa saja yang pernah diumumkan"
 * bisa dijawab dengan satu baris per pengumuman, bukan ratusan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->id();

            // Pembuat pengumuman. nullOnDelete, BUKAN cascadeOnDelete:
            // menghapus akun kepala sekolah yang pensiun tidak boleh ikut
            // menghapus riwayat pengumuman yang pernah ia kirim — riwayat itu
            // catatan sekolah, bukan milik pribadi akunnya.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('judul');
            $table->text('isi_pesan');

            // 'pegawai' = seluruh guru & staff, 'wali_murid' = orang tua,
            // 'semua' = keduanya. Disimpan sebagai string, bukan enum
            // database: menambah target baru nanti (mis. per kelas) cukup
            // menyentuh PHP, tanpa migration ALTER yang mengunci tabel.
            $table->string('target_role', 30)->default('semua');

            $table->boolean('is_sent_wa')->default(false);

            // Berapa nomor yang benar-benar diantrekan ke gateway. Disimpan
            // supaya riwayat bisa menjawab "kenapa tagihan WA bulan ini besar"
            // tanpa menebak dari jumlah pengguna saat itu.
            $table->unsignedInteger('jumlah_penerima')->default(0);
            $table->unsignedInteger('jumlah_wa')->default(0);

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
