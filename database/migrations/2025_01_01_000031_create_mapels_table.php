<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master mata pelajaran.
     *
     * ============ KENAPA TABEL INI BARU ADA SEKARANG ============
     * Sampai hari ini SIMAGAS menyimpan mata pelajaran sebagai TEKS BEBAS di
     * kolom `jadwal_pelajaran.mata_pelajaran`. Itu cukup selama mata pelajaran
     * hanya dipakai sebagai label di jadwal.
     *
     * Nilai rapor berbeda: nilainya harus bisa dijumlahkan dan dibandingkan
     * antar kelas. Dengan teks bebas, "Matematika" dan "matematika " (spasi di
     * ujung) menjadi dua mata pelajaran berbeda — dan rata-rata rapor seorang
     * siswa diam-diam salah tanpa satu pun pesan error.
     *
     * ============ YANG SENGAJA TIDAK DIUBAH ============
     * Tabel `jadwal_pelajaran` TIDAK disentuh. Mengubah kolom teksnya menjadi
     * foreign key berarti menyentuh jadwal, jurnal KBM, Rekap KBM per Jadwal,
     * dan laporan bulanan sekaligus — empat fitur yang sedang dipakai, demi
     * kerapian yang tidak menambah satu pun kemampuan baru hari ini.
     *
     * Jembatannya cukup lewat NAMA: isian tabel ini disemai dari nama mata
     * pelajaran yang SUDAH ADA di jadwal, sehingga guru melihat daftar yang
     * sama persis dengan yang ia ajarkan.
     * ===================================================
     */
    public function up(): void
    {
        Schema::create('mapels', function (Blueprint $table) {
            $table->id();

            // Unik supaya satu mata pelajaran tidak pernah punya dua baris.
            $table->string('nama', 120)->unique();

            // Kode opsional (mis. "MTK") untuk keperluan cetak rapor nanti.
            $table->string('kode', 20)->nullable();

            $table->timestamps();
        });

        /*
         | Semai dari data yang sudah ada.
         |
         | Tanpa langkah ini, guru membuka halaman input nilai dan menemukan
         | daftar mata pelajaran KOSONG — fitur barunya terlihat rusak padahal
         | datanya memang belum pernah dibuat. Diambil dari jadwal karena di
         | situlah satu-satunya tempat nama mata pelajaran pernah ditulis.
         */
        if (! Schema::hasTable('jadwal_pelajaran')) {
            return;
        }

        $nama = DB::table('jadwal_pelajaran')
            ->select('mata_pelajaran')
            ->distinct()
            ->pluck('mata_pelajaran')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($nama->isEmpty()) {
            return;
        }

        DB::table('mapels')->insert(
            $nama->map(fn ($n) => [
                'nama' => $n,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all()
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('mapels');
    }
};
