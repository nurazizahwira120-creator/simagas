<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengunci status rapor per kelas per semester.
     *
     * Alurnya: Draft (guru masih boleh mengubah nilai) -> Menunggu Persetujuan
     * (wali kelas sudah mengajukan, nilai TERKUNCI) -> Disetujui (kepala
     * sekolah menerbitkan, rapor tampil di akun wali murid).
     *
     * ============ KENAPA NILAI ENUM-NYA SATU KATA ============
     * Brief menyebut 'Draft', 'Menunggu Persetujuan', 'Disetujui'. Yang
     * disimpan di sini 'draft', 'menunggu', 'disetujui' — tanpa spasi dan
     * tanpa huruf besar.
     *
     * Alasannya praktis: nilai enum dipakai di URL, di atribut HTML, di
     * perbandingan string, dan di query. Spasi di dalamnya menuntut pengutipan
     * yang benar di setiap tempat itu, dan satu tempat yang lupa menghasilkan
     * status yang tidak pernah cocok — rapor tersangkut di "Menunggu" selamanya
     * tanpa pesan error.
     *
     * Tulisan yang dilihat pengguna tetap persis seperti brief; lihat
     * App\Enums\StatusRapor::label().
     * =========================================================
     */
    public function up(): void
    {
        Schema::create('validasi_rapors', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->cascadeOnDelete();

            $table->string('tahun_ajaran', 20);
            $table->enum('semester', ['ganjil', 'genap']);

            $table->enum('status', ['draft', 'menunggu', 'disetujui'])
                ->default('draft');

            // Jejak pengajuan (wali kelas) — bukan bagian brief, tapi tanpa
            // ini tidak ada yang bisa menjawab "siapa yang mengajukan ini".
            $table->foreignId('diajukan_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('diajukan_pada')->nullable();

            // Jejak persetujuan (kepala sekolah).
            $table->foreignId('disetujui_oleh')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('tanggal_persetujuan')->nullable();

            // Alasan kalau kepala sekolah mengembalikan rapor ke Draft.
            $table->string('catatan')->nullable();

            $table->timestamps();

            // Satu kelas hanya punya SATU status per semester. Ini yang
            // membuat tombol "Ajukan" aman ditekan dua kali.
            $table->unique(['kelas_id', 'tahun_ajaran', 'semester'], 'rapor_unik_per_kelas');

            $table->index('status', 'rapor_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('validasi_rapors');
    }
};
