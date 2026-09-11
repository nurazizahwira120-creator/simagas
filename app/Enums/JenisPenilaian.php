<?php

namespace App\Enums;

/**
 * Jenis penilaian yang masuk rapor.
 *
 * Nilainya huruf kecil (dipakai di database, URL, dan atribut HTML); yang
 * dilihat guru dan orang tua diambil dari label().
 */
enum JenisPenilaian: string
{
    case Formatif = 'formatif';
    case Sumatif = 'sumatif';
    case Praktik = 'praktik';

    public function label(): string
    {
        return match ($this) {
            self::Formatif => 'Formatif',
            self::Sumatif => 'Sumatif',
            self::Praktik => 'Praktik',
        };
    }

    /** Penjelasan singkat, ditampilkan sebagai keterangan kolom di form guru. */
    public function penjelasan(): string
    {
        return match ($this) {
            self::Formatif => 'Penilaian harian selama proses belajar',
            self::Sumatif => 'Penilaian akhir bab / akhir semester',
            self::Praktik => 'Penilaian keterampilan atau unjuk kerja',
        };
    }

    public function kelasBadge(): string
    {
        return match ($this) {
            self::Formatif => 'bg-brand-500/10 text-brand-600',
            self::Sumatif => 'bg-success-500/10 text-success-600',
            self::Praktik => 'bg-warning-500/15 text-warning-700',
        };
    }

    /** @return array<int, self> */
    public static function urut(): array
    {
        return [self::Formatif, self::Sumatif, self::Praktik];
    }
}
