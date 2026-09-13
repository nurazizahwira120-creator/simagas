<?php

namespace App\Enums;

/**
 * Jenis izin khusus guru: ITT dan IDT.
 *
 * ============ APA BEDANYA, DAN KENAPA PERLU DIBEDAKAN ============
 * Keduanya sama-sama berarti gurunya tidak hadir. Yang membedakan bukan
 * alasannya, melainkan NASIB KELASNYA:
 *
 *   ITT (Izin Tanpa Tugas) -> kelasnya kosong. Tidak ada yang harus
 *                             dikerjakan siswa, dan piket perlu tahu supaya
 *                             bisa mengisi atau memulangkan lebih awal.
 *
 *   IDT (Izin Dengan Tugas) -> gurunya meninggalkan tugas. Kelasnya "jalan"
 *                              walau gurunya tidak ada, dan piket perlu tahu
 *                              TUGAS APA yang harus disampaikan.
 *
 * Perbedaan itulah yang membuat `detail_tugas` wajib untuk IDT: izin
 * berlabel "dengan tugas" yang tidak menyebutkan tugasnya sama sekali adalah
 * ITT yang menyamar, dan piket yang mengandalkannya akan berdiri di depan
 * kelas tanpa tahu harus menyampaikan apa.
 * ================================================================
 *
 * CATATAN nilai enum: ITT/IDT ditulis KAPITAL, berbeda dari enum lain di
 * project ini yang memakai huruf kecil. Disengaja — keduanya akronim, dan
 * "itt"/"idt" tidak pernah ditulis begitu oleh siapa pun di sekolah.
 */
enum JenisIzinGuru: string
{
    case Itt = 'ITT';
    case Idt = 'IDT';

    public function label(): string
    {
        return match ($this) {
            self::Itt => 'ITT — Izin Tanpa Tugas',
            self::Idt => 'IDT — Izin Dengan Tugas',
        };
    }

    /** Label pendek untuk chip/badge yang ruangnya sempit. */
    public function kode(): string
    {
        return $this->value;
    }

    public function keterangan(): string
    {
        return match ($this) {
            self::Itt => 'Kelas kosong — tidak ada tugas yang ditinggalkan.',
            self::Idt => 'Kelas tetap berjalan dengan tugas yang Anda tinggalkan.',
        };
    }

    /** Jenis ini WAJIB menyertakan detail tugas? */
    public function butuhTugas(): bool
    {
        return $this === self::Idt;
    }

    public function kelasBadge(): string
    {
        return match ($this) {
            self::Itt => 'border-red-300 bg-red-500/10 text-brand-danger-text',
            self::Idt => 'border-sky-300 bg-sky-500/10 text-sky-700 dark:text-sky-400',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Itt => 'x-circle',
            self::Idt => 'clipboard-check',
        };
    }

    /** @return array<int, self> */
    public static function semua(): array
    {
        return [self::Itt, self::Idt];
    }
}
