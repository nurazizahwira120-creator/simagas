<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HONOR MENGAJAR (uji coba) — dua tabel:
 *
 *   honor_mengajar    : BUKU CATATAN honor. Satu baris = satu bagian honor
 *                       untuk satu orang dari satu jam pelajaran pada satu
 *                       tanggal. Baris ditulis otomatis saat guru menekan
 *                       "Akhiri Sesi", atau saat guru inval menyimpan absensi.
 *   tarif_honor_guru  : tarif KHUSUS per orang. Yang tidak punya baris di
 *                       sini memakai tarif umum (tabel pengaturan).
 *
 * ============ KENAPA TARIF & PERSEN DISALIN KE SETIAP BARIS ============
 * Kepala sekolah memilih "tarif terkunci saat mengajar": kalau tarif
 * dinaikkan tanggal 15, honor tanggal 1–14 TIDAK ikut berubah. Karena itu
 * tarif_per_jp, persen, dan nominal disimpan apa adanya pada saat dicatat —
 * bukan dihitung ulang dari pengaturan terbaru setiap kali halaman dibuka.
 * Begitu juga `rincian` (mapel, kelas, jam): jadwal bisa diubah admin
 * semester depan, tetapi catatan honor bulan lalu harus tetap terbaca benar.
 * =======================================================================
 *
 * unique(jadwal_id, tanggal, peran): satu jam pelajaran pada satu hari
 * hanya punya SATU baris per peran ('mengajar', 'inval', 'guru_asli').
 * Menekan tombol dua kali, atau inval menyimpan ulang absensi, MENGGANTI
 * baris yang sama — honor tidak pernah tercatat ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('honor_mengajar')) {
            Schema::create('honor_mengajar', function (Blueprint $table) {
                $table->id();
                // Penerima honor. Akun dihapus -> catatannya ikut terhapus.
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                // Jadwal dihapus -> catatan TETAP ada (rincian sudah tersalin).
                $table->foreignId('jadwal_id')->nullable()->constrained('jadwal_pelajaran')->nullOnDelete();
                $table->date('tanggal');
                $table->string('peran', 20); // mengajar | inval | guru_asli
                $table->unsignedTinyInteger('jp');
                $table->unsignedInteger('tarif_per_jp');
                $table->unsignedTinyInteger('persen');
                $table->unsignedInteger('nominal');
                $table->string('rincian', 255);
                $table->foreignId('absensi_mengajar_id')->nullable()->constrained('absensi_mengajar')->nullOnDelete();
                $table->timestamps();

                $table->unique(['jadwal_id', 'tanggal', 'peran'], 'honor_mengajar_jadwal_tanggal_peran_unik');
                // "Rincian pendapatan saya bulan ini" — dicari per orang per tanggal.
                $table->index(['user_id', 'tanggal']);
                // Rekap semua guru satu bulan.
                $table->index('tanggal');
            });
        }

        if (! Schema::hasTable('tarif_honor_guru')) {
            Schema::create('tarif_honor_guru', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('tarif_per_jp');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_honor_guru');
        Schema::dropIfExists('honor_mengajar');
    }
};
