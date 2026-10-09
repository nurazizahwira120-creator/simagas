<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SESI EKSKUL — alur Mulai/Akhiri Sesi untuk pembina ekstrakurikuler,
 * sama dengan KBM: scan QR -> absen anggota -> foto bukti -> Akhiri Sesi.
 *
 *   jadwal_ekskuls.kode_qr  : isi stiker QR ekskul ("EKSKUL-12-K7Q2XW").
 *                             Ada potongan acak di ujungnya supaya kodenya
 *                             tidak bisa ditebak dari nomor urut saja.
 *   jadwal_ekskuls.jp_honor : JP yang dihitung untuk honor. Kosong =
 *                             dihitung otomatis dari panjang jadwal.
 *   sesi_ekskul             : satu baris = satu pertemuan ekskul yang
 *                             dimulai pembina lewat scan QR.
 *   honor_mengajar.jadwal_ekskul_id : honor yang berasal dari ekskul.
 *
 * unique(jadwal_ekskul_id, tanggal) pada sesi_ekskul: satu ekskul hanya
 * punya SATU sesi per hari. Scan ulang di hari yang sama melanjutkan sesi
 * yang ada, bukan membuat sesi baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_ekskuls', function (Blueprint $table) {
            if (! Schema::hasColumn('jadwal_ekskuls', 'kode_qr')) {
                $table->string('kode_qr', 40)->nullable()->unique();
            }
            if (! Schema::hasColumn('jadwal_ekskuls', 'jp_honor')) {
                $table->unsignedTinyInteger('jp_honor')->nullable();
            }
        });

        // Ekskul yang sudah ada langsung mendapat kode QR.
        DB::table('jadwal_ekskuls')->whereNull('kode_qr')->orderBy('id')->get(['id'])
            ->each(fn ($j) => DB::table('jadwal_ekskuls')->where('id', $j->id)
                ->update(['kode_qr' => 'EKSKUL-' . $j->id . '-' . Str::upper(Str::random(6))]));

        if (! Schema::hasTable('sesi_ekskul')) {
            Schema::create('sesi_ekskul', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jadwal_ekskul_id')->constrained('jadwal_ekskuls')->cascadeOnDelete();
                // Pembina yang men-scan QR.
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->date('tanggal');
                $table->dateTime('waktu_mulai');
                $table->dateTime('waktu_selesai')->nullable();
                $table->string('foto_bukti')->nullable();
                $table->timestamp('bukti_dihapus_pada')->nullable();
                $table->timestamp('pengingat_akhiri_pada')->nullable();
                $table->timestamps();

                $table->unique(['jadwal_ekskul_id', 'tanggal'], 'sesi_ekskul_jadwal_tanggal_unik');
                $table->index(['user_id', 'tanggal']);
                $table->index('tanggal');
            });
        }

        if (Schema::hasTable('honor_mengajar') && ! Schema::hasColumn('honor_mengajar', 'jadwal_ekskul_id')) {
            Schema::table('honor_mengajar', function (Blueprint $table) {
                $table->foreignId('jadwal_ekskul_id')->nullable()->constrained('jadwal_ekskuls')->nullOnDelete();
                $table->unique(['jadwal_ekskul_id', 'tanggal', 'peran'], 'honor_mengajar_ekskul_tanggal_peran_unik');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('honor_mengajar', 'jadwal_ekskul_id')) {
            Schema::table('honor_mengajar', function (Blueprint $table) {
                $table->dropUnique('honor_mengajar_ekskul_tanggal_peran_unik');
                $table->dropConstrainedForeignId('jadwal_ekskul_id');
            });
        }

        Schema::dropIfExists('sesi_ekskul');

        Schema::table('jadwal_ekskuls', function (Blueprint $table) {
            if (Schema::hasColumn('jadwal_ekskuls', 'kode_qr')) {
                $table->dropUnique(['kode_qr']);
                $table->dropColumn('kode_qr');
            }
            if (Schema::hasColumn('jadwal_ekskuls', 'jp_honor')) {
                $table->dropColumn('jp_honor');
            }
        });
    }
};
