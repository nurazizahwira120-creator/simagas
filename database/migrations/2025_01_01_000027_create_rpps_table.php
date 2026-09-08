<?php
// Dibuat dengan:  php artisan make:migration create_rpps_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana Pelaksanaan Pembelajaran (RPP) — berkas PDF milik masing-masing guru.
 *
 * `file_path` menyimpan jalur RELATIF terhadap disk 'public'
 * (mis. "rpp/9f2c1a...pdf"), bukan URL dan bukan jalur absolut. Menyimpan URL
 * penuh berarti seluruh baris rusak begitu domainnya berganti atau aplikasinya
 * pindah dari http ke https; jalur absolut rusak begitu foldernya berpindah —
 * dan keduanya baru ketahuan saat berkasnya dibuka, bukan saat disimpan.
 *
 * cascadeOnDelete: menghapus akun guru ikut menghapus baris RPP-nya. Ini
 * berbeda dari `pembina_id` di jadwal ekskul (yang nullOnDelete) dan memang
 * disengaja — RPP adalah dokumen MILIK guru itu, bukan catatan sekolah yang
 * tetap bermakna tanpa pemiliknya. Berkas fisiknya dibersihkan lewat
 * RppController::destroy(); lihat catatan di sana soal sisa berkas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rpps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('judul_rpp', 150);
            $table->string('mata_pelajaran', 100);
            $table->string('file_path', 255);

            $table->timestamps();

            // Halaman guru selalu bertanya "RPP milik saya, terbaru dulu".
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rpps');
    }
};
