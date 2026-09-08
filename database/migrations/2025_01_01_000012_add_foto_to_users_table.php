<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom foto profil.
     *
     * Isinya BUKAN gambar, melainkan jalur relatif berkasnya di disk 'public',
     * mis. "profile_photos/abc123.jpg". Gambar sendiri disimpan di
     * storage/app/public/ dan diakses lewat /storage/... — sehingga:
     *   - baris database tetap ringan (kalau gambarnya ditaruh sebagai blob,
     *     setiap query users ikut menyeret berkas ratusan KB);
     *   - berkasnya bisa dicadangkan/dipindah terpisah dari database.
     *
     * PENTING: jalur /storage/... baru bisa dibuka browser setelah dijalankan
     *     php artisan storage:link
     * sekali saja. Tanpa itu foto tetap TERSIMPAN tapi tampil sebagai gambar
     * rusak. Halaman Profil mendeteksi kondisi ini dan memberi tahu.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('alamat');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('foto');
        });
    }
};
