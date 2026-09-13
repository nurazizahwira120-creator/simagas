<?php

namespace App\Enums;

/**
 * Jenis izin yang dicatat petugas piket di gerbang.
 *
 * ============ KENAPA ENUM TERSENDIRI, BUKAN MEMAKAI AbsensiStatus ============
 * Sekilas keduanya mirip, dan menggabungkannya terasa lebih rapi. Tapi
 * keduanya menjawab pertanyaan yang berbeda:
 *
 *   AbsensiStatus  -> "hari ini anak ini dihitung apa" (hadir/izin/sakit/alpha)
 *   JenisIzin      -> "apa yang dilaporkan orang tuanya ke sekolah"
 *
 * Bedanya terlihat pada DISPENSASI. Sekolah membedakannya dari Izin biasa —
 * dispensasi adalah penugasan sekolah (lomba, rapat OSIS, upacara di
 * kecamatan), bukan keperluan pribadi. Kepala sekolah perlu bisa
 * membedakannya saat membaca rekap.
 *
 * Namun tabel `absensi_siswa` HANYA punya empat status, dan menambah satu
 * nilai ke enum kolom MySQL di tabel yang sudah berisi data produksi adalah
 * perubahan yang jauh lebih mahal daripada nilainya. Karena itu dispensasi
 * DISIMPAN utuh di tabel `pencatatan_izins` dan DIPETAKAN ke 'izin' saat
 * masuk ke absensi harian — lihat statusAbsensi() di bawah.
 *
 * Konsekuensinya harus diketahui: di laporan kehadiran, dispensasi terhitung
 * sebagai izin. Rinciannya tetap ada dan tidak hilang, tapi ada di halaman
 * Pencatatan Izin, bukan di rekap absensi.
 * ==============================================================================
 */
enum JenisIzin: string
{
    case Sakit = 'sakit';
    case Izin = 'izin';
    case Dispensasi = 'dispensasi';

    public function label(): string
    {
        return match ($this) {
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
            self::Dispensasi => 'Dispensasi',
        };
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Sakit => 'Tidak masuk karena sakit',
            self::Izin => 'Tidak masuk dengan izin keluarga',
            self::Dispensasi => 'Penugasan sekolah (lomba, kegiatan, delegasi)',
        };
    }

    /**
     * Status yang dicatat di `absensi_siswa` (kehadiran harian di gerbang).
     *
     * Dispensasi jatuh ke 'izin' karena tabel itu tidak mengenalnya — lihat
     * penjelasan panjang di kepala enum ini.
     */
    public function statusAbsensi(): AbsensiStatus
    {
        return match ($this) {
            self::Sakit => AbsensiStatus::Sakit,
            self::Izin, self::Dispensasi => AbsensiStatus::Izin,
        };
    }

    /**
     * Status yang dipakai sebagai isian awal di Jurnal & Absen Kelas.
     *
     * Ini yang membuat izin dari gerbang benar-benar SAMPAI ke guru mata
     * pelajaran — lihat JurnalAbsenKelas::izinDariGerbang().
     */
    public function statusKbm(): StatusKbm
    {
        return match ($this) {
            self::Sakit => StatusKbm::Sakit,
            self::Izin, self::Dispensasi => StatusKbm::Izin,
        };
    }

    /** Warna chip/badge Tailwind. */
    public function kelasBadge(): string
    {
        return match ($this) {
            self::Sakit => 'border-sky-300 bg-sky-500/10 text-sky-700 dark:text-sky-400',
            self::Izin => 'border-amber-300 bg-amber-500/10 text-amber-700 dark:text-amber-400',
            self::Dispensasi => 'border-violet-300 bg-violet-500/10 text-violet-700 dark:text-violet-400',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Sakit => 'exclamation-triangle',
            self::Izin => 'inbox',
            self::Dispensasi => 'academic-cap',
        };
    }

    /** @return array<int, self> */
    public static function semua(): array
    {
        return [self::Sakit, self::Izin, self::Dispensasi];
    }
}
