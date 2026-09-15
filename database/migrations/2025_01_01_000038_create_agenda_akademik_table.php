<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kalender Pendidikan — agenda & hari libur satu tahun ajaran.
     *
     * ============ KENAPA RENTANG, BUKAN SATU BARIS PER TANGGAL ============
     * Godaannya besar untuk menyimpan satu baris per tanggal libur: query
     * "apakah hari ini libur" jadi sesederhana where('tanggal', today()).
     *
     * Tapi kalender pendidikan aslinya TIDAK berbentuk begitu. Sumbernya
     * berbunyi "21 Desember 2026 s.d. 4 Januari 2027 — Libur Akhir Semester
     * Gasal": SATU keputusan, satu judul, satu alasan. Memecahnya jadi 15
     * baris berarti mengoreksi tanggalnya (hal yang lumrah terjadi lewat
     * surat edaran susulan) harus menghapus 15 baris dan membuat 15 baris
     * baru — dan yang lupa menghapus satu saja meninggalkan hari libur
     * hantu yang tidak bisa dijelaskan siapa pun.
     *
     * Bentuk rentang juga yang membuat halaman kalender bisa menampilkan
     * "Libur Akhir Semester Gasal (15 hari)" alih-alih 15 kotak identik.
     * ======================================================================
     *
     * ============ APA YANG TIDAK DISIMPAN DI SINI ============
     * Hari libur MINGGUAN (di sekolah ini: Jumat) TIDAK disimpan sebagai
     * baris di tabel ini. Ia pola yang berulang selamanya, bukan agenda
     * bertanggal — tempatnya di Pengaturan Sistem sebagai kunci 'hari_kbm'.
     *
     * Menyimpannya di sini berarti setiap Jumat sepanjang tahun jadi satu
     * baris, dan mengubah hari liburnya berarti menulis ulang 52 baris.
     * =========================================================
     */
    public function up(): void
    {
        Schema::create('agenda_akademik', function (Blueprint $table) {
            $table->id();

            $table->string('judul');

            $table->date('tanggal_mulai');

            /*
             | Selalu TERISI, bahkan untuk agenda satu hari (diisi sama dengan
             | tanggal_mulai). Nullable akan memaksa setiap query menulis
             | COALESCE(tanggal_selesai, tanggal_mulai) — satu cabang tambahan
             | di setiap tempat, dan satu tempat yang lupa sudah cukup untuk
             | membuat agenda satu hari tidak pernah cocok.
             */
            $table->date('tanggal_selesai');

            $table->enum('jenis', ['libur', 'kegiatan', 'ujian'])->default('kegiatan');

            $table->text('keterangan')->nullable();

            /*
             | Asal datanya: 'kaldik' = dari Kalender Pendidikan Dinas
             | Provinsi, 'sekolah' = ditambahkan sendiri oleh sekolah.
             |
             | Dibedakan supaya seeder kalender tahun ajaran berikutnya bisa
             | mengganti baris 'kaldik' tanpa ikut menghapus libur khusus yang
             | sudah susah payah diisi sekolah (mis. haul pendiri, libur
             | karena banjir).
             */
            $table->enum('sumber', ['kaldik', 'sekolah'])->default('sekolah');

            $table->string('tahun_ajaran', 9)->nullable();

            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            /*
             | Pertanyaan yang paling sering: "apakah TANGGAL INI libur?",
             | yang dijalankan berkali-kali — sekali untuk setiap hari saat
             | menghitung hari efektif sebulan.
             |
             | Indeks gabungan (tanggal_mulai, tanggal_selesai) melayani
             | pencarian rentang yang mengapit satu tanggal; jenis diindeks
             | terpisah karena penyaringan "hanya yang libur" hampir selalu
             | ikut menyertainya.
             */
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
            $table->index(['jenis', 'tanggal_mulai']);
            $table->index('sumber');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_akademik');
    }
};
