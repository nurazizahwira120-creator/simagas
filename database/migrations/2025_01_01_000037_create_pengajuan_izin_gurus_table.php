<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengajuan Izin Khusus Guru — ITT & IDT.
     *
     * ============ KENAPA TABEL SENDIRI, BUKAN MENUMPANG absensi_pegawai ============
     * Sekilas mubazir: `absensi_pegawai` sudah punya status 'izin' beserta
     * kolom `alasan` dan `bukti_foto`. Tapi dua tabel ini menjawab pertanyaan
     * yang berbeda, dan bentuknya pun berbeda:
     *
     *   absensi_pegawai       -> SATU BARIS PER HARI. unique(pegawai_id,
     *                            tanggal). Isinya "hari ini orang ini dihitung
     *                            apa".
     *
     *   pengajuan_izin_gurus  -> SATU BARIS PER PENGAJUAN, dan satu pengajuan
     *                            bisa mencakup BEBERAPA HARI (tanggal_mulai
     *                            s.d. tanggal_selesai). Isinya "apa yang
     *                            diminta guru ini, dan apa jawabannya".
     *
     * Memaksakan rentang tiga hari ke dalam tabel satu-baris-per-hari berarti
     * menyalin alasan dan detail tugas yang sama ke tiga baris — dan sejak
     * saat itu, mengoreksi satu pengajuan berarti mengingat untuk mengoreksi
     * ketiganya. Persetujuan yang ditolak pun tidak punya tempat: baris
     * absensi tidak bisa menyimpan "pernah diminta, tapi ditolak".
     *
     * Hubungan keduanya SATU ARAH: pengajuan yang DISETUJUI menuliskan
     * statusnya ke absensi_pegawai (lihat App\Services\PenerapIzinGuru).
     * =============================================================================
     */
    public function up(): void
    {
        Schema::create('pengajuan_izin_gurus', function (Blueprint $table) {
            $table->id();

            /*
             | guru_id menunjuk ke `users`, dan di sini itu MEMANG BENAR —
             | berbeda dengan pencatatan izin siswa yang harus menunjuk tabel
             | `siswa` karena siswa tidak punya akun.
             |
             | Guru punya akun login, dan yang mengajukan izin adalah orang
             | yang sedang login. restrictOnDelete: pengajuan izin adalah
             | dokumen administratif yang bisa dipertanyakan berbulan-bulan
             | kemudian ("siapa yang meninggalkan tugas ini?"). Menghapus
             | akunnya tidak boleh diam-diam menghapus jejaknya.
             */
            $table->foreignId('guru_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');

            // Kapital, karena keduanya akronim — lihat App\Enums\JenisIzinGuru.
            $table->enum('jenis_izin', ['ITT', 'IDT']);

            $table->text('alasan');

            // Hanya terisi untuk IDT. Kewajibannya ditegakkan di level
            // aplikasi (IzinGuruController), BUKAN di database: enum
            // 'jenis_izin' dan nullability 'detail_tugas' adalah dua kolom
            // terpisah, dan tidak ada constraint portabel yang bisa
            // mengaitkan keduanya di MySQL maupun SQLite sekaligus.
            $table->text('detail_tugas')->nullable();

            // Jalur berkas lampiran tugas di disk PRIVAT ('local').
            $table->string('file_tugas')->nullable();

            $table->enum('status_approval', ['Pending', 'Disetujui', 'Ditolak'])
                ->default('Pending');

            // Siapa yang memutuskan, kapan, dan kenapa. Kolom-kolom ini TIDAK
            // ada di spesifikasi, tapi tanpa ketiganya status 'Ditolak' jadi
            // buntu: gurunya melihat pengajuannya ditolak dan tidak punya cara
            // mengetahui alasannya selain bertanya langsung.
            $table->foreignId('penyetuju_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diputuskan_pada')->nullable();
            $table->text('catatan_penyetuju')->nullable();

            $table->timestamps();

            /*
             | Dua indeks untuk dua pertanyaan yang paling sering:
             |   - "pengajuan saya" (halaman guru)
             |   - "yang masih menunggu" (halaman kepala sekolah)
             |
             | Rentang tanggalnya ikut diindeks karena Live Monitoring nanti
             | akan bertanya "siapa yang izin HARI INI" — mencocokkan hari ini
             | di antara tanggal_mulai dan tanggal_selesai.
             */
            $table->index(['guru_id', 'status_approval']);
            $table->index(['status_approval', 'tanggal_mulai']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengajuan_izin_gurus');
    }
};
