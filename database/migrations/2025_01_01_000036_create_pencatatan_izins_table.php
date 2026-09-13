<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pencatatan Izin Satu Pintu.
     *
     * Siswa di sekolah ini tidak membawa HP, jadi tidak ada jalur "siswa
     * mengajukan izin sendiri". Semua izin masuk lewat SATU pintu: petugas
     * piket atau admin di gerbang, yang menerima surat/telepon dari orang tua
     * lalu mencatatkannya di sini.
     *
     * ============ DUA PENYIMPANGAN DARI SPESIFIKASI, DAN ALASANNYA ============
     *
     * 1. `siswa_id` menunjuk ke tabel `siswa`, BUKAN `users`.
     *
     *    Spesifikasi menyebut "foreign key ke users". Di project ini itu tidak
     *    mungkin: SISWA TIDAK PUNYA AKUN LOGIN sama sekali. Yang punya akun
     *    adalah wali muridnya (siswa.wali_murid_id -> users.id). Memaksakan
     *    FK ke `users` akan gagal saat migrasi dijalankan, dan kalaupun
     *    berhasil ia akan menunjuk orang yang salah.
     *
     * 2. `petugas_id` memang menunjuk ke `users` — dan itu benar.
     *
     *    Petugas piket/admin PUNYA akun login, dan yang ingin diketahui
     *    kemudian hari adalah "siapa yang mencatat ini", yaitu akunnya.
     *
     * ============ KENAPA restrictOnDelete PADA PETUGAS ============
     * Catatan izin adalah dokumen administratif yang bisa dipertanyakan
     * berbulan-bulan kemudian ("siapa yang meloloskan anak ini?"). Kalau
     * akun petugasnya dihapus dan barisnya ikut hilang (cascade) atau
     * petugasnya jadi null, jejak pertanggungjawabannya lenyap justru pada
     * saat paling dibutuhkan. restrictOnDelete memaksa admin menangani
     * catatannya lebih dulu sebelum menghapus akun — sengaja merepotkan.
     * ==========================================================================
     */
    public function up(): void
    {
        Schema::create('pencatatan_izins', function (Blueprint $table) {
            $table->id();

            // Siswa dihapus -> catatan izinnya ikut terhapus; tanpa siswanya
            // baris ini tidak punya arti apa pun.
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnDelete();

            // Petugas yang mencatat. Lihat catatan restrictOnDelete di atas.
            $table->foreignId('petugas_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('tanggal');

            // Nilainya huruf kecil supaya cocok dengan App\Enums\JenisIzin
            // dan seragam dengan enum lain di project ini (absensi_siswa
            // memakai 'hadir'/'izin'/'sakit'/'alpha', bukan kapital).
            $table->enum('status', ['sakit', 'izin', 'dispensasi']);

            $table->text('keterangan')->nullable();

            // Jalur berkas surat di disk PRIVAT — bukan disk public.
            // Alasannya dijelaskan di AbsensiGerbangController::simpanSurat().
            $table->string('foto_surat')->nullable();

            $table->timestamps();

            /*
             | Satu siswa hanya boleh punya SATU catatan izin per tanggal.
             |
             | Dijaga di DATABASE, bukan hanya di validasi controller. Dua
             | petugas yang menekan Simpan nyaris bersamaan untuk siswa yang
             | sama akan lolos pemeriksaan "sudah ada?" di PHP — keduanya
             | membaca sebelum salah satunya menulis. Hanya database yang bisa
             | menolak yang kedua dengan pasti.
             |
             | Petugas yang ingin mengoreksi memakai jalur ubah, bukan membuat
             | baris kedua yang saling bertentangan.
             */
            $table->unique(['siswa_id', 'tanggal']);

            // Mempercepat pertanyaan yang paling sering: "izin apa saja hari
            // ini" (halaman gerbang) dan "rekap izin bulan ini".
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencatatan_izins');
    }
};
