<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Titik koordinat sekolah & radius toleransi GPS pindah ke `pengaturan_sistem`.
 *
 * ============ KENAPA INI BUKAN SEKADAR "TAMBAH TIGA KOLOM" ============
 * Koordinat sekolah SUDAH ADA di project ini, tersimpan di tabel kunci-nilai
 * `pengaturan` dengan kunci lat_sekolah / lng_sekolah / radius_gps. Itulah
 * yang dibaca App\Livewire\AbsenGuru saat menghitung jarak.
 *
 * Kalau tiga kolom baru ini sekadar ditambahkan dan diisi nilai bawaan,
 * hasilnya DUA sumber kebenaran untuk satu pertanyaan ("di mana sekolahnya").
 * Akibatnya bukan error, melainkan sesuatu yang jauh lebih buruk: Super Admin
 * menggeser penanda peta, halaman bilang tersimpan, tapi Absen Radius tetap
 * memakai koordinat lama — dan tidak ada satu pun pesan yang memberi tahu.
 *
 * Karena itu migration ini MEMINDAHKAN nilainya, bukan menduplikasi:
 *   1. tiga kolom ditambahkan,
 *   2. nilai yang sudah ada di `pengaturan` DISALIN ke kolom baru,
 *   3. sejak sekarang `pengaturan_sistem` adalah satu-satunya sumber, dan
 *      AbsenGuru dibuat membacanya dari sini.
 *
 * Baris di `pengaturan` sengaja TIDAK dihapus — kalau migration ini di-rollback,
 * nilainya masih utuh di tempat lamanya.
 * =====================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_sistem', function (Blueprint $table) {
            // Disimpan sebagai string, bukan decimal, sesuai brief — dan itu
            // memang pilihan yang aman di sini: nilai dari peta bisa punya
            // 15 angka di belakang koma, dan decimal(10,8) akan memotongnya
            // secara diam-diam.
            $table->string('latitude')->nullable()->after('nama_sekolah');
            $table->string('longitude')->nullable()->after('latitude');
            $table->integer('radius_meter')->default(50)->after('longitude');
        });

        // ---- Pindahkan nilai yang sudah ada ----------------------------
        // Dibungkus try/catch: di instalasi yang benar-benar baru, tabel
        // `pengaturan` bisa saja masih kosong atau belum terisi seeder.
        // Gagal menyalin bukan alasan untuk menggagalkan seluruh migration.
        try {
            $lama = DB::table('pengaturan')
                ->whereIn('kunci', ['lat_sekolah', 'lng_sekolah', 'radius_gps'])
                ->pluck('nilai', 'kunci');

            if ($lama->isNotEmpty()) {
                DB::table('pengaturan_sistem')->update([
                    'latitude' => $lama['lat_sekolah'] ?? null,
                    'longitude' => $lama['lng_sekolah'] ?? null,
                    'radius_meter' => (int) ($lama['radius_gps'] ?? 50),
                ]);
            }
        } catch (\Throwable $e) {
            // Biarkan kolomnya kosong; komponen Pengaturan Sistem sudah
            // menyiapkan nilai bawaan saat form dibuka.
        }
    }

    public function down(): void
    {
        Schema::table('pengaturan_sistem', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_meter']);
        });
    }
};
