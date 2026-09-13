<?php

namespace App\Services;

use App\Enums\JenisIzin;
use App\Models\AbsensiSiswa;
use App\Models\PencatatanIzin;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat izin siswa dicatat.
 *
 * ============ KENAPA DIEKSTRAK JADI SERVICE ============
 * Halaman Gerbang sekarang punya DUA pintu masuk yang menulis hal yang sama:
 *
 *   - App\Livewire\Gerbang\FormIzin        -> jalur real-time (tanpa reload)
 *   - App\Http\Controllers\AbsensiGerbangController::storeIzin()
 *                                          -> jalur form biasa (POST + redirect)
 *
 * Kalau logikanya disalin ke dua tempat, cepat atau lambat keduanya
 * menyimpang — dan penyimpangan yang paling mungkin justru pada bagian
 * tersulitnya: penyisipan ke absensi harian. Hasilnya: izin yang dicatat
 * lewat satu pintu sampai ke guru, lewat pintu lain tidak, tanpa error apa
 * pun yang menjelaskan bedanya.
 *
 * Karena itu keduanya memanggil catat() di bawah, dan tidak ada satu pun
 * baris penyimpanan di luar berkas ini.
 * =======================================================
 */
class PencatatIzin
{
    /**
     * Mencatat satu izin sekaligus menyisipkan status hariannya.
     *
     * @param  string|null  $jalurSurat  jalur berkas yang SUDAH tersimpan di disk
     *
     * @throws \Throwable  dilempar kembali kalau transaksinya gagal
     */
    public function catat(
        Siswa $siswa,
        User $petugas,
        JenisIzin $jenis,
        ?string $keterangan = null,
        ?string $jalurSurat = null,
        $tanggal = null,
    ): PencatatanIzin {
        $tanggal = $tanggal ?: today();

        /*
         | ============ SATU TRANSAKSI UNTUK DUA TABEL ============
         | Pencatatan izin dan status absensi harian HARUS jadi bersama atau
         | tidak sama sekali.
         |
         | Kalau yang pertama tersimpan lalu yang kedua gagal, hasilnya
         | keadaan yang paling menyesatkan: petugas melihat izinnya tercatat
         | rapi di layar gerbang, sementara guru di jam ketiga melihat anak
         | itu berstatus Alpa dan menghubungi orang tuanya menanyakan anak
         | yang justru sudah diizinkan sekolah.
         */
        return DB::transaction(function () use ($siswa, $petugas, $jenis, $keterangan, $jalurSurat, $tanggal) {
            $izin = PencatatanIzin::create([
                'siswa_id' => $siswa->id,
                'petugas_id' => $petugas->id,
                'tanggal' => $tanggal,
                'status' => $jenis->value,
                'keterangan' => $keterangan,
                'foto_surat' => $jalurSurat,
            ]);

            $this->sisipkanKeAbsensiHarian($siswa, $jenis, $keterangan, $tanggal);

            return $izin;
        });
    }

    /** Siswa ini sudah punya catatan izin pada tanggal tersebut? */
    public function sudahAda(Siswa $siswa, $tanggal = null): bool
    {
        return PencatatanIzin::where('siswa_id', $siswa->id)
            ->whereDate('tanggal', $tanggal ?: today())
            ->exists();
    }

    /**
     * ============================================================
     * INTEGRASI IZIN -> ABSENSI HARIAN
     * ============================================================
     * Inilah bagian yang membuat "satu pintu" benar-benar berarti. Tanpa
     * method ini, pencatatan izin hanyalah catatan terpisah yang harus
     * dibacakan ulang ke setiap guru secara lisan.
     *
     * ---- Kenapa updateOrCreate, bukan create ----
     * Tabel `absensi_siswa` punya unique(siswa_id, tanggal) — satu siswa satu
     * baris per hari. create() akan melempar QueryException begitu anaknya
     * SUDAH sempat men-scan kartu di gerbang pagi tadi lalu siang harinya
     * dijemput orang tua karena sakit. Kejadian itu bukan kasus langka; itu
     * justru salah satu alasan utama fitur ini dibuat.
     *
     * ---- Kenapa jam_masuk TIDAK ikut ditulis ----
     * Kalau anaknya sempat hadir pagi lalu pulang karena sakit, jam
     * kedatangannya adalah fakta yang benar-benar terjadi dan tidak boleh
     * dihapus hanya karena statusnya berubah. Dengan tidak menyertakan
     * jam_masuk di daftar nilai yang ditulis:
     *   - baris yang SUDAH ada  -> jam masuknya dipertahankan apa adanya
     *   - baris BARU            -> jam masuknya null, dan itu memang benar
     *                              (anaknya tidak pernah sampai di gerbang)
     *
     * ---- Kenapa statusnya DITIMPA ----
     * Status yang berlaku adalah yang terakhir diketahui sekolah. Anak yang
     * hadir lalu dipulangkan sakit, hari itu berstatus Sakit — dan guru di
     * jam-jam berikutnya perlu melihat itu, bukan "Hadir" dari pagi tadi.
     */
    private function sisipkanKeAbsensiHarian(Siswa $siswa, JenisIzin $jenis, ?string $keterangan, $tanggal): void
    {
        AbsensiSiswa::updateOrCreate(
            [
                'siswa_id' => $siswa->id,
                'tanggal' => $tanggal,
            ],
            [
                'status' => $jenis->statusAbsensi(),

                // Keterangan bawaan per jenis dipakai kalau petugas tidak
                // mengetik apa pun, supaya baris absensinya tetap bisa
                // dijelaskan saat dibaca kembali berbulan-bulan kemudian.
                'keterangan' => $keterangan ?: $jenis->keterangan(),
            ],
        );
    }
}
