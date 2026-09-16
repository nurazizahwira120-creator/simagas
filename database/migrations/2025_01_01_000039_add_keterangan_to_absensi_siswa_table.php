<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambahkan kolom `keterangan` ke `absensi_siswa` — untuk database yang
 * SUDAH terlanjur dibuat sebelum kolom itu ada.
 *
 * ============ KENAPA MIGRATION BARU, BUKAN MEMPERBAIKI YANG LAMA ============
 * Kolom ini sebenarnya sudah tertulis di dalam Schema::create() milik
 * migration 000004 (create_absensi_siswa_table). Tapi barisnya ditambahkan
 * SESUDAH migration itu dijalankan di server produksi.
 *
 * Laravel mencatat migration yang sudah jalan berdasarkan NAMA BERKASNYA,
 * bukan isinya. Jadi begitu 000004 tercatat "sudah jalan", mengubah isinya
 * tidak berpengaruh sama sekali: `php artisan migrate` menjawab "Nothing to
 * migrate" dengan yakin, dan kolomnya tidak pernah ada.
 *
 * Akibatnya paling menyesatkan yang bisa dibayangkan:
 *   - di komputer pengembang SELALU benar, karena database di sana dibuat
 *     dari nol sesudah barisnya ditambahkan;
 *   - di server SELALU salah, dan perintah yang biasanya memperbaiki
 *     ("migrate") justru menyatakan tidak ada yang perlu dikerjakan.
 *
 * Lebih halus lagi: SQLite TIDAK melaporkan kesalahannya. Pada SQLite,
 * pengenal berkolom-ganda yang tidak dikenal diperlakukan sebagai teks
 * biasa, jadi `where "keterangan" = ?` hanya menghasilkan nol baris tanpa
 * satu pun galat. Hanya MySQL yang berteriak:
 *
 *   SQLSTATE[42S22]: Column not found: 1054 Unknown column 'keterangan'
 *
 * Itulah sebabnya seluruh uji otomatis di project ini hijau sementara
 * halaman Pantau Kehadiran Siswa mati 500 di simagas.online.
 * ===========================================================================
 *
 * ============ KENAPA DIJAGA hasColumn() ============
 * Database yang dibuat dari nol SUDAH punya kolom ini dari migration 000004.
 * Menambahkannya lagi akan menggagalkan seluruh `migrate` dengan "Duplicate
 * column name". Penjagaan ini membuat satu berkas yang sama benar untuk
 * dua keadaan: server lama yang kekurangan kolom, dan database baru yang
 * sudah lengkap.
 * ===================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('absensi_siswa', 'keterangan')) {
            return;
        }

        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->text('keterangan')->nullable()->after('status');
        });
    }

    /**
     * Sengaja TIDAK menghapus kolomnya.
     *
     * Pada database yang dibuat dari nol, kolom ini milik migration 000004 —
     * bukan milik migration ini. Menghapusnya saat rollback berarti merusak
     * tabel yang dibuat berkas lain, dan isinya (alasan izin, penanda alpa
     * otomatis) ikut hilang permanen.
     */
    public function down(): void
    {
        // Tidak ada yang dibatalkan: lihat penjelasan di atas.
    }
};
