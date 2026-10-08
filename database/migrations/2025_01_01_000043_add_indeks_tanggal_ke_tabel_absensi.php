<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks pada kolom tanggal di tabel-tabel absensi yang terus membesar.
 *
 * ============ KENAPA SEKARANG ============
 * Banyak layar menyaring absensi HANYA berdasarkan tanggal ("siapa yang hadir
 * hari ini", "rekap bulan ini") — tanpa siswa_id/pegawai_id di depannya.
 * Indeks unik yang sudah ada diawali siswa_id/pegawai_id/jadwal_id, jadi tidak
 * menolong query semacam itu; MySQL membaca seluruh tabel.
 *
 * Perkiraan pertumbuhan (600 siswa, ±200 hari efektif):
 *   absensi_siswa      ±120 ribu baris / tahun
 *   absensi_kbm_siswa  ±800 ribu baris / tahun
 * Diuji pada 450 ribu baris: 53 ms -> 0,2–0,4 ms per query, BERSAMA perubahan
 * cara memfilter tanggal (lihat App\Providers\QueryTanggalServiceProvider).
 * Indeks saja tidak cukup: whereDate() membungkus kolom dengan date() dan
 * membuat indeks apa pun tidak terpakai.
 * =========================================
 *
 * Dijaga Schema::hasIndex() supaya aman dijalankan ulang.
 */
return new class extends Migration
{
    /** tabel => [kolom, nama indeks] */
    private const INDEKS = [
        'absensi_siswa' => ['tanggal', 'absensi_siswa_tanggal_index'],
        'absensi_pegawai' => ['tanggal', 'absensi_pegawai_tanggal_index'],
        'absensi_kbm_siswa' => ['tanggal', 'absensi_kbm_siswa_tanggal_index'],
        'absensi_mengajar' => ['waktu_mulai', 'absensi_mengajar_waktu_mulai_index'],
    ];

    public function up(): void
    {
        foreach (self::INDEKS as $tabel => [$kolom, $nama]) {
            if (! Schema::hasTable($tabel) || Schema::hasIndex($tabel, $nama)) {
                continue;
            }

            Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom, $nama));
        }
    }

    public function down(): void
    {
        foreach (self::INDEKS as $tabel => [, $nama]) {
            if (Schema::hasTable($tabel) && Schema::hasIndex($tabel, $nama)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($nama));
            }
        }
    }
};
