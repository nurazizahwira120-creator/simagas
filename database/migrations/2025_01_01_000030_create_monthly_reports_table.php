<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog laporan bulanan yang dihasilkan Cron Job.
 *
 * Tabel ini TIDAK menyimpan isi laporannya — hanya jalur berkas PDF-nya di
 * disk plus beberapa angka ringkasan supaya daftar di dasbor bisa
 * menampilkan sesuatu yang berarti tanpa harus membuka PDF-nya satu per
 * satu.
 *
 * ============ KENAPA `periode` UNIQUE ============
 * Cron bisa berjalan dua kali untuk bulan yang sama: server di-restart tepat
 * jam 01:00, admin menjalankan perintahnya manual untuk memeriksa, atau
 * hosting mengulang cron yang timeout. Tanpa unique, dasbor kepala sekolah
 * menampilkan "Agustus 2026" tiga kali dan tidak ada yang tahu mana yang
 * benar.
 *
 * Dengan unique, perintahnya cukup memakai updateOrCreate: menjalankan ulang
 * MEMPERBARUI laporan bulan itu, bukan menggandakannya.
 * =================================================
 *
 * Tanggalnya selalu HARI PERTAMA bulan yang dilaporkan (mis. 2026-08-01
 * untuk laporan Agustus 2026), bukan tanggal pembuatannya. Itu yang membuat
 * pengurutan dan pencarian per bulan sederhana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();

            // Hari pertama bulan yang dilaporkan.
            $table->date('periode')->unique();

            $table->string('judul');

            // Jalur relatif di disk 'public', mis.
            // "laporan-bulanan/laporan-2026-08.pdf".
            //
            // Disimpan sebagai jalur, BUKAN URL penuh: URL dirakit dari
            // APP_URL yang bisa berubah (http -> https, ganti domain), dan
            // URL lama yang tersimpan di database akan menunjuk ke tempat
            // yang salah selamanya.
            $table->string('file_path');

            // Angka ringkasan untuk ditampilkan di daftar.
            $table->unsignedInteger('jumlah_hari_kerja')->default(0);
            $table->unsignedInteger('jumlah_pegawai')->default(0);
            $table->unsignedInteger('jumlah_siswa')->default(0);
            $table->unsignedInteger('kehadiran_pegawai')->default(0);
            $table->unsignedInteger('kehadiran_siswa')->default(0);

            // Kapan berkasnya benar-benar dibuat. Berbeda dari created_at
            // kalau laporannya pernah dibuat ulang.
            $table->timestamp('dibuat_pada')->nullable();

            $table->timestamps();

            // Dasbor selalu menampilkan yang terbaru lebih dulu.
            $table->index('periode', 'monthly_reports_periode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
