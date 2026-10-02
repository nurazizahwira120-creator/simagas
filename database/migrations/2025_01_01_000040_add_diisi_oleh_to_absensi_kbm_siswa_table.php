<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mencatat SIAPA yang mengisi absensi KBM siswa.
 *
 * ============ KENAPA SEKARANG DIBUTUHKAN ============
 * Sebelum ada fitur Kelas Pengganti, pengisi absensi KBM selalu guru pemilik
 * jadwalnya sendiri — jadi "siapa yang mengisi" cukup dibaca dari
 * jadwal_pelajaran.guru_id.
 *
 * Sekarang, ketika guru jadwalnya berhalangan (izin/sakit), absensi kelasnya
 * bisa diisi guru lain, wali kelas, kepala sekolah, atau guru piket. Tanpa
 * kolom ini, catatan yang diisi pengganti tidak bisa dibedakan dari catatan
 * yang diisi guru aslinya — dan rekap jam mengajar akan diam-diam
 * mengkreditkan jam itu kepada guru yang justru sedang izin.
 * ====================================================
 *
 * nullOnDelete: akun pengisi yang dihapus tidak boleh ikut menghapus daftar
 * hadir siswanya. Data kehadiran anak lebih penting daripada keterangan
 * siapa yang mencatatnya.
 *
 * Baris lama dibiarkan NULL dan diperlakukan sebagai "diisi guru jadwalnya
 * sendiri" — satu-satunya kemungkinan sebelum fitur ini ada.
 *
 * Dijaga hasColumn() supaya aman dijalankan ulang (lihat catatan panjang di
 * migration 000039 tentang migration yang dianggap sudah jalan).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('absensi_kbm_siswa', 'diisi_oleh')) {
            return;
        }

        Schema::table('absensi_kbm_siswa', function (Blueprint $table) {
            $table->foreignId('diisi_oleh')
                ->nullable()
                ->after('keterangan')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('absensi_kbm_siswa', 'diisi_oleh')) {
            return;
        }

        Schema::table('absensi_kbm_siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diisi_oleh');
        });
    }
};
