<?php
// Dibuat dengan:  php artisan make:migration create_absensi_ekskuls_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kehadiran siswa per pertemuan ekskul.
 *
 * `status_kehadiran` disimpan STRING, bukan enum database — sama seperti
 * tabel absensi lain di project ini, yang nilainya di-cast ke
 * App\Enums\AbsensiStatus di sisi PHP. Satu tempat definisi (enum PHP)
 * jauh lebih mudah dijaga daripada dua (enum PHP + enum kolom), dan
 * menambah status baru tidak butuh migration ALTER yang mengunci tabel.
 *
 * unique(jadwal_ekskul_id, siswa_id, tanggal) adalah pengaman terpenting di
 * tabel ini. Form absensi bisa terkirim dua kali (koneksi lambat lalu tombol
 * ditekan lagi, atau dua pembina membuka halaman yang sama). Tanpa kunci unik
 * itu, satu siswa punya dua baris untuk hari yang sama dengan status berbeda,
 * dan rekapnya menghitung dua kali tanpa ada yang menyadari.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_ekskuls', function (Blueprint $table) {
            $table->id();

            $table->foreignId('jadwal_ekskul_id')->constrained('jadwal_ekskuls')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();

            $table->date('tanggal');
            $table->string('status_kehadiran', 20);
            $table->string('keterangan', 255)->nullable();

            $table->timestamps();

            $table->unique(['jadwal_ekskul_id', 'siswa_id', 'tanggal'], 'absensi_ekskul_unik');

            // Rekap per anak ("riwayat kehadiran anak saya") memakai jalur ini.
            $table->index(['siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_ekskuls');
    }
};
