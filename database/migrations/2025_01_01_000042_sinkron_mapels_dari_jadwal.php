<?php

use App\Models\Mapel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengisi master `mapels` dengan mata pelajaran di jadwal yang TERLEWAT.
 *
 * Migrasi 000031 hanya menyemai master ini sekali. Mapel yang ditambahkan ke
 * jadwal sesudahnya tidak pernah masuk, sehingga tidak muncul di Input Nilai
 * (lihat Mapel::sinkron()). Sejak perbaikan ini, jadwal baru mendaftarkan
 * mapelnya sendiri; migrasi ini menyusulkan yang sudah telanjur terlewat.
 *
 * Aman dijalankan ulang: Mapel::sinkron() hanya menyisipkan nama yang belum
 * ada. Tidak ada down(): baris master yang ditambahkan bisa saja sudah
 * dipakai nilai siswa, dan menghapusnya akan ikut menghapus nilai itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mapels') || ! Schema::hasTable('jadwal_pelajaran')) {
            return;
        }

        Mapel::sinkron(
            DB::table('jadwal_pelajaran')->distinct()->pluck('mata_pelajaran')
        );
    }

    public function down(): void
    {
        //
    }
};
