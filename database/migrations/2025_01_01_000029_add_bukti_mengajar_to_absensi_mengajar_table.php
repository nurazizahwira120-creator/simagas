<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Melengkapi `absensi_mengajar` untuk Validasi Silang KBM.
 *
 * Sebelum ini, satu baris absensi_mengajar hanya membuktikan guru MASUK ke
 * ruangan (scan QR). Tidak ada apa pun yang membuktikan ia benar-benar
 * mengajar sampai selesai — dan tidak ada penanda kapan sesinya berakhir.
 *
 * Dua kolom di bawah menutup celah itu:
 *
 *   foto_bukti    Jalur foto di disk 'public'. Diunggah guru dari dalam
 *                 kelas. Inilah syarat yang membuka tombol "Akhiri Sesi".
 *   waktu_selesai Diisi saat tombol itu ditekan. Selisihnya dengan
 *                 waktu_mulai = durasi mengajar yang tercatat, dan itulah
 *                 angka yang dipakai laporan bulanan.
 *
 * KEDUANYA NULLABLE, dan itu wajib: tabel ini sudah berisi riwayat dari
 * sebelum fitur ini ada. Kolom NOT NULL akan menolak migrasi di server yang
 * datanya sudah jalan — gagal justru di tempat yang paling merepotkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $table) {
            if (! Schema::hasColumn('absensi_mengajar', 'foto_bukti')) {
                $table->string('foto_bukti')->nullable()->after('kode_kelas');
            }

            if (! Schema::hasColumn('absensi_mengajar', 'waktu_selesai')) {
                $table->timestamp('waktu_selesai')->nullable()->after('waktu_mulai');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi_mengajar', function (Blueprint $table) {
            $table->dropColumn(['foto_bukti', 'waktu_selesai']);
        });
    }
};
