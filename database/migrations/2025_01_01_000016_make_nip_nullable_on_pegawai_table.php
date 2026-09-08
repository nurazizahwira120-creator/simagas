<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolom `nip` pada tabel `pegawai` jadi NULLABLE.
     *
     * Alasannya: guru honorer dan staf baru sering belum punya NIP. Selama
     * kolomnya NOT NULL, mereka tidak bisa didaftarkan sama sekali — dan
     * jalan pintas yang biasa dipakai (mengisi "-" atau "0") justru merusak
     * data, karena pegawai KEDUA tanpa NIP akan ditolak oleh index unique
     * yang menganggap "-" itu kembar.
     *
     * Index unique-nya SENGAJA dipertahankan: NIP yang benar-benar diisi
     * tetap tidak boleh dobel. MySQL dan SQLite sama-sama mengizinkan banyak
     * baris NULL pada kolom unique, jadi berapa pun pegawai tanpa NIP tidak
     * akan saling menabrak. Yang penting di sisi aplikasi: nilai kosong harus
     * disimpan sebagai NULL, bukan string kosong — itu sudah ditangani di
     * Register, EditDataLengkap, PegawaiController, dan PegawaiImport.
     *
     * ->change() di Laravel 11+ tidak lagi membutuhkan doctrine/dbal; paket
     * itu TIDAK perlu dipasang.
     */
    public function up(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nip')->nullable()->change();
        });
    }

    /**
     * Dikembalikan ke NOT NULL.
     *
     * Baris yang NIP-nya NULL diisi penanda sementara lebih dulu, karena
     * database menolak mengubah kolom jadi NOT NULL selama masih ada NULL di
     * dalamnya — tanpa langkah ini, `migrate:rollback` akan gagal di tengah
     * jalan dan meninggalkan skema setengah jadi. Penandanya dibuat unik per
     * baris supaya tidak melanggar index unique.
     */
    public function down(): void
    {
        DB::table('pegawai')->whereNull('nip')->orderBy('id')->each(function ($baris) {
            DB::table('pegawai')->where('id', $baris->id)->update([
                'nip' => 'TANPA-NIP-' . $baris->id,
            ]);
        });

        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('nip')->nullable(false)->change();
        });
    }
};
