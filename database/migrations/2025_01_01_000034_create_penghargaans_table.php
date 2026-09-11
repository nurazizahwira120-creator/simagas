<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat apresiasi — dipakai dua fitur sekaligus:
     *   - "Bintang Kelas (Peringkat 1)" untuk siswa, dibuat saat kepala
     *     sekolah menyetujui rapor satu kelas.
     *   - "Guru Teladan & Tertib Administrasi", dibuat otomatis tiap tanggal 1
     *     oleh App\Console\Commands\KalkulasiPenghargaanGuru.
     */
    public function up(): void
    {
        Schema::create('penghargaans', function (Blueprint $table) {
            $table->id();

            // Akun PENERIMA pemberitahuan. Untuk penghargaan siswa, ini akun
            // WALI MURID-nya — siswa tidak punya akun login di sistem ini.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             | Siswa yang sebenarnya berprestasi.
             |
             | Tidak ada di brief, tapi tanpa kolom ini penghargaan siswa
             | menjadi rancu pada kasus yang sangat biasa: satu wali murid
             | dengan dua anak. Bannernya akan berbunyi "Selamat, anak Anda
             | jadi Bintang Kelas" tanpa bisa menyebut anak yang mana.
             */
            $table->foreignId('siswa_id')
                ->nullable()
                ->constrained('siswa')
                ->cascadeOnDelete();

            $table->foreignId('kelas_id')
                ->nullable()
                ->constrained('kelas')
                ->nullOnDelete();

            // 'siswa' atau 'guru'.
            $table->string('peran', 20);

            $table->string('kategori', 120);

            /*
             | Bentuk periode BERBEDA menurut perannya, dan itu disengaja:
             |   siswa -> "2026/2027-ganjil"  (satu semester)
             |   guru  -> "2026-08"           (satu bulan)
             | Keduanya sama-sama string yang bisa diurutkan dan dibandingkan
             | persis, sehingga satu kunci unik di bawah cukup untuk keduanya.
             */
            $table->string('periode', 30);

            $table->text('pesan_apresiasi');

            // Angka yang mendasari penghargaan (rata-rata nilai / skor
            // kedisiplinan). Disimpan supaya keputusannya bisa ditelusuri
            // ulang berbulan-bulan kemudian, bukan sekadar dipercaya.
            $table->decimal('nilai_acuan', 6, 2)->nullable();

            $table->timestamps();

            /*
             | Kunci unik inilah yang membuat seluruh fitur ini aman diulang.
             |
             | Kepala sekolah bisa menyetujui rapor dua kali (mis. sesudah
             | dikembalikan lalu disetujui lagi), dan cron tanggal 1 bisa
             | berjalan dua kali kalau server sempat restart. Tanpa kunci ini,
             | riwayat apresiasi penuh baris kembar dan HP penerimanya berbunyi
             | berkali-kali untuk penghargaan yang sama.
             */
            $table->unique(['user_id', 'kategori', 'periode'], 'penghargaan_unik');

            $table->index(['peran', 'periode'], 'penghargaan_peran_periode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penghargaans');
    }
};
