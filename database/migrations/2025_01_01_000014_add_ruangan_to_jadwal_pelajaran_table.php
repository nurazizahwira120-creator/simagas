<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `ruangan` untuk jadwal pelajaran.
     *
     * Halaman Jadwal Pelajaran menampilkan kolom Ruangan, dan tanpa kolom ini
     * isinya hanya bisa dikosongkan selamanya. Nullable karena tabelnya sudah
     * berisi data — jadwal lama tampil dengan tanda "—" sampai diisi lewat
     * menu Manajemen Akademik > Jadwal Pelajaran.
     */
    public function up(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->string('ruangan', 50)->nullable()->after('mata_pelajaran');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropColumn('ruangan');
        });
    }
};
