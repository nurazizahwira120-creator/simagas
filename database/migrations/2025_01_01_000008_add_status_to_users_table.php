<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `status` untuk alur persetujuan akun baru.
     *
     * Default sengaja 'active', BUKAN 'pending': akun di aplikasi ini dibuat
     * oleh admin lewat menu Pengguna, dan akun yang baru saja dibuat admin
     * tidak masuk akal kalau harus disetujui admin lagi. Status 'pending'
     * diperuntukkan bagi akun yang mendaftar sendiri lewat halaman registrasi
     * publik — lihat catatan di SettingController tentang halaman itu.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', ['pending', 'active', 'rejected'])
                ->default('active')
                ->after('role');

            // Tab "Approval" menyaring users berdasarkan kolom ini.
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
