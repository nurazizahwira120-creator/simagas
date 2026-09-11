<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda kapan foto bukti mengajar dihapus otomatis oleh pembersih
     * bulanan (App\Console\Commands\BersihkanBuktiMengajar).
     *
     * ============ KENAPA PERLU KOLOM TERSENDIRI ============
     * Pembersihnya menghapus BERKAS FOTO tetapi MEMBIARKAN kolom `foto_bukti`
     * apa adanya — alasannya dijelaskan panjang di perintah itu. Akibatnya,
     * tanpa penanda ini, baris yang fotonya sudah dibuang tidak bisa dibedakan
     * dari baris yang fotonya masih ada:
     *
     *   foto_bukti terisi + berkas ada    -> sesi lengkap, fotonya masih bisa dilihat
     *   foto_bukti terisi + berkas hilang -> ??? dihapus otomatis, atau hilang karena
     *                                        folder storage sempat terhapus saat deploy?
     *
     * Dua keadaan itu perlu dibedakan. Yang pertama wajar dan sudah diniatkan;
     * yang kedua tanda ada yang rusak. Tanpa kolom ini, enam bulan kemudian
     * tidak ada seorang pun yang bisa menjawab yang mana.
     * ======================================================
     */
    public function up(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $table) {
            // hasColumn dijaga supaya migrasi ini aman dijalankan ulang pada
            // database yang sebagian sudah ter-migrate.
            if (! Schema::hasColumn('absensi_mengajar', 'bukti_dihapus_pada')) {
                $table->timestamp('bukti_dihapus_pada')
                    ->nullable()
                    ->after('foto_bukti');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $table) {
            if (Schema::hasColumn('absensi_mengajar', 'bukti_dihapus_pada')) {
                $table->dropColumn('bukti_dihapus_pada');
            }
        });
    }
};
