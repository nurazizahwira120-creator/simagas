<?php
// Dibuat dengan:  php artisan make:migration create_jadwal_ekskuls_table

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal kegiatan ekstrakurikuler.
 *
 * Kolom `hari` sengaja STRING, bukan enum database maupun relasi ke tabel
 * hari. Ekskul tidak selalu jatuh pada satu hari tetap — ada yang ditulis
 * "Sabtu", ada "Jumat & Sabtu", ada "Menyesuaikan jadwal lomba". Enum
 * database mengunci daftar itu dan menambah satu nilai baru butuh migration
 * ALTER yang mengunci tabel; string membuat penambahan cukup di dropdown
 * Blade. Pengurutan per hari tetap rapi karena dilakukan di PHP lewat peta
 * urutan hari (lihat App\Livewire\Ekskul\KelolaEkskul::urutanHari()).
 *
 * `pembina` juga string, bukan foreign key ke tabel pegawai. Pembina ekskul
 * di sekolah ini sering BUKAN guru yang terdaftar sebagai pegawai — pelatih
 * silat, pembina pramuka dari luar, atau alumni. Foreign key akan memaksa
 * mereka dibuatkan akun pegawai hanya supaya namanya bisa ditulis di jadwal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_ekskuls', function (Blueprint $table) {
            $table->id();

            $table->string('nama_ekskul', 100);
            $table->string('hari', 40);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('pembina', 100);
            $table->string('keterangan', 255)->nullable();

            $table->timestamps();

            // Halaman ini hampir selalu dibaca dalam urutan hari lalu jam.
            $table->index(['hari', 'jam_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ekskuls');
    }
};
