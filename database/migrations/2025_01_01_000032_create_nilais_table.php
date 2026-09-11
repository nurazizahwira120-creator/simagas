<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai siswa per mata pelajaran, per semester, per jenis penilaian.
     *
     * ============ SATU BARIS = SATU ANGKA YANG MASUK RAPOR ============
     * Kunci uniknya (siswa + mapel + tahun ajaran + semester + jenis) membuat
     * satu kombinasi hanya boleh punya SATU angka. Itu keputusan yang
     * menentukan bentuk seluruh fitur ini:
     *
     *   - Form guru menjadi GRID yang bisa disimpan berulang kali dengan aman
     *     (upsert), bukan daftar yang menumpuk baris baru tiap klik Simpan.
     *   - Rata-rata rapor punya arti tunggal. Tanpa kunci ini, guru yang
     *     menekan Simpan dua kali membuat nilai anak terhitung dua kali dan
     *     peringkat kelas berubah tanpa ada yang tahu sebabnya.
     *
     * Kalau suatu saat sekolah butuh banyak nilai formatif per mata pelajaran
     * (mis. ulangan harian 1..5), yang ditambah adalah kolom `urutan` pada
     * kunci unik ini — bukan menghapus kuncinya.
     * ==================================================================
     */
    public function up(): void
    {
        Schema::create('nilais', function (Blueprint $table) {
            $table->id();

            // Siswa keluar dari sekolah -> nilainya ikut terhapus. Rapor tanpa
            // siswanya tidak punya arti dan tidak pernah dibuka siapa pun.
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnDelete();

            // restrictOnDelete: mata pelajaran yang sudah dipakai menilai TIDAK
            // boleh dihapus begitu saja — menghapusnya akan melenyapkan nilai
            // yang sudah sah masuk rapor.
            $table->foreignId('mapel_id')
                ->constrained('mapels')
                ->restrictOnDelete();

            /*
             | guru_id menunjuk ke `pegawai`, BUKAN `users`.
             |
             | Mengikuti aturan yang sudah berlaku di jadwal_pelajaran: yang
             | mengajar adalah ORANGNYA, dan tidak semua pegawai punya akun
             | login. Menunjuk ke users akan membuat nilai tidak bisa dicatat
             | atas nama guru honorer yang belum dibuatkan akun.
             |
             | nullOnDelete: kalau data pegawainya dihapus, nilainya TETAP ada
             | (hanya kehilangan penanda siapa yang menginput). Rapor siswa
             | tidak boleh hilang karena gurunya pindah sekolah.
             */
            $table->foreignId('guru_id')
                ->nullable()
                ->constrained('pegawai')
                ->nullOnDelete();

            $table->string('tahun_ajaran', 20);   // mis. "2026/2027"

            // Huruf kecil, MENGIKUTI tabel tahun_ajaran yang sudah ada.
            // Menulis 'Ganjil' di sini sementara tabel sebelah menulis
            // 'ganjil' berarti setiap perbandingan antar-tabel meleset diam-
            // diam dan rapornya selalu tampak kosong.
            $table->enum('semester', ['ganjil', 'genap']);

            $table->enum('jenis_penilaian', ['formatif', 'sumatif', 'praktik']);

            // 5,2 -> sampai 999.99. Batas 0–100 ditegakkan di validasi
            // aplikasi; kolomnya sengaja lebih longgar supaya sekolah yang
            // memakai skala lain tidak terbentur struktur database.
            $table->decimal('skor', 5, 2);

            $table->timestamps();

            $table->unique(
                ['siswa_id', 'mapel_id', 'tahun_ajaran', 'semester', 'jenis_penilaian'],
                'nilai_unik_per_jenis'
            );

            // Dipakai halaman rapor wali murid ("semua nilai anak ini pada
            // semester sekian") dan perhitungan Bintang Kelas.
            $table->index(['tahun_ajaran', 'semester'], 'nilai_periode_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilais');
    }
};
