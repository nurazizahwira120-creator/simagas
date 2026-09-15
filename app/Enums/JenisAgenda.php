<?php

namespace App\Enums;

/**
 * Jenis agenda pada kalender pendidikan.
 *
 * ============ HANYA 'libur' YANG MENGUBAH PERHITUNGAN ============
 * Perbedaan ketiganya bukan sekadar warna di layar. Hanya Libur yang
 * membuat satu tanggal BERHENTI dihitung sebagai hari KBM — artinya hanya
 * Libur yang memengaruhi penandaan alpa otomatis dan penyebut persentase
 * kehadiran di laporan bulanan.
 *
 * Kegiatan dan Ujian tetap hari sekolah: anak tetap wajib datang, dan
 * ketidakhadirannya tetap tercatat. Menyamakan ketiganya berarti seluruh
 * pekan Asesmen Sumatif hilang dari rekap kehadiran.
 * ================================================================
 */
enum JenisAgenda: string
{
    case Libur = 'libur';
    case Kegiatan = 'kegiatan';
    case Ujian = 'ujian';

    public function label(): string
    {
        return match ($this) {
            self::Libur => 'Libur',
            self::Kegiatan => 'Kegiatan',
            self::Ujian => 'Ujian / Asesmen',
        };
    }

    /** Jenis ini membuat tanggalnya BUKAN hari KBM? */
    public function meliburkan(): bool
    {
        return $this === self::Libur;
    }

    public function kelasBadge(): string
    {
        return match ($this) {
            self::Libur => 'border-red-300 bg-red-500/10 text-brand-danger-text',
            self::Kegiatan => 'border-sky-300 bg-sky-500/10 text-sky-700 dark:text-sky-400',
            self::Ujian => 'border-amber-300 bg-amber-500/10 text-amber-700 dark:text-amber-400',
        };
    }

    /** Warna titik penanda di dalam kotak tanggal pada grid kalender. */
    public function kelasTitik(): string
    {
        return match ($this) {
            self::Libur => 'bg-brand-danger',
            self::Kegiatan => 'bg-sky-500',
            self::Ujian => 'bg-amber-500',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Libur => 'x-circle',
            self::Kegiatan => 'calendar',
            self::Ujian => 'clipboard-check',
        };
    }

    /** @return array<int, self> */
    public static function semua(): array
    {
        return [self::Libur, self::Kegiatan, self::Ujian];
    }
}
