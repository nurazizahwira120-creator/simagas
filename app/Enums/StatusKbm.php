<?php

namespace App\Enums;

/**
 * Status kehadiran siswa PER JAM PELAJARAN (absensi KBM).
 *
 * ================== BACA INI SEBELUM MENULIS QUERY ==================
 * Enum ini BUKAN App\Enums\AbsensiStatus, dan nilainya TIDAK sama persis:
 *
 *   AbsensiStatus (absensi_siswa, absensi_pegawai) : hadir, izin, sakit, alpha
 *   StatusKbm     (absensi_kbm_siswa)              : hadir, sakit, izin, alpa, bolos
 *                                                                        ^^^^
 * Perhatikan "alpha" vs "alpa" — beda satu huruf. Membandingkan langsung
 * antara dua tabel (mis. `where('status', $absensiSiswa->status)`) akan
 * SELALU meleset untuk kasus itu. Kalau perlu menjembatani keduanya, pakai
 * StatusKbm::dariAbsensiStatus() di bawah, jangan menyamakan string mentah.
 *
 * Nilainya mengikuti brief apa adanya, karena itu yang tertulis di kontrak
 * kolom enum di migrasi 000017 dan sudah menjadi data.
 * ====================================================================
 *
 * "Bolos" sengaja dipisah dari "Alpa": alpa berarti tidak masuk sekolah tanpa
 * keterangan, sedangkan bolos berarti anaknya TERCATAT masuk gerbang pagi ini
 * tapi tidak ada di kelas — dua kejadian yang penanganannya berbeda bagi wali
 * kelas maupun orang tua.
 */
enum StatusKbm: string
{
    case Hadir = 'hadir';
    case Sakit = 'sakit';
    case Izin = 'izin';
    case Alpa = 'alpa';
    case Bolos = 'bolos';

    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
            self::Alpa => 'Alpa',
            self::Bolos => 'Bolos',
        };
    }

    /** Kalimat yang dilihat orang tua di halaman Pantauan KBM. */
    public function labelWali(): string
    {
        return match ($this) {
            self::Hadir => 'Mengikuti Pelajaran',
            self::Sakit => 'Sakit',
            self::Izin => 'Izin',
            self::Alpa => 'Tidak Hadir di Kelas',
            self::Bolos => 'Bolos — masuk gerbang, tidak di kelas',
        };
    }

    /**
     * Kelas warna badge TailAdmin. Dikumpulkan di sini, bukan disebar di
     * beberapa view, supaya "hijau untuk hadir, merah untuk alpa" tidak
     * pelan-pelan berbeda antara halaman guru dan halaman orang tua.
     */
    public function kelasBadge(): string
    {
        return match ($this) {
            self::Hadir => 'bg-success-500/10 text-success-600',
            self::Sakit => 'bg-warning-500/15 text-warning-700',
            self::Izin => 'bg-brand-500/10 text-brand-600',
            self::Alpa, self::Bolos => 'bg-error-500/10 text-error-600',
        };
    }

    /** Warna titik/radio di form guru. */
    public function kelasTitik(): string
    {
        return match ($this) {
            self::Hadir => 'border-success-500 text-success-500',
            self::Sakit => 'border-warning-500 text-warning-500',
            self::Izin => 'border-brand-500 text-brand-500',
            self::Alpa, self::Bolos => 'border-error-500 text-error-500',
        };
    }

    /**
     * Status yang dipilihkan guru di form Jurnal & Absen Kelas.
     *
     * "Bolos" TIDAK ikut ditawarkan sebagai tombol manual: statusnya diisi
     * otomatis saat menyimpan, ketika guru menandai siswa Alpa PADAHAL siswa
     * itu tercatat hadir di gerbang pagi ini. Membedakannya adalah pekerjaan
     * yang bisa dilakukan sistem dari data yang sudah ada — meminta guru
     * mengingat siapa yang tadi pagi masuk gerbang justru sumber kesalahan.
     *
     * @return array<int, self>
     */
    public static function pilihanGuru(): array
    {
        return [self::Hadir, self::Sakit, self::Izin, self::Alpa];
    }

    /** Jembatan dari status absensi harian ke status KBM. */
    public static function dariAbsensiStatus(AbsensiStatus $status): self
    {
        return match ($status) {
            AbsensiStatus::Hadir => self::Hadir,
            AbsensiStatus::Izin => self::Izin,
            AbsensiStatus::Sakit => self::Sakit,
            AbsensiStatus::Alpha => self::Alpa,
        };
    }
}
