<?php

namespace App\Enums;

/**
 * Status persetujuan pengajuan izin guru.
 *
 * ============ KENAPA ADA PERSETUJUAN DI SINI ============
 * Jalur izin pegawai yang lama TIDAK punya persetujuan sama sekali: form-nya
 * menulis langsung ke `absensi_pegawai`, dan halaman kepala sekolah hanya
 * menampilkan hasilnya. Untuk izin biasa itu memadai.
 *
 * Untuk IDT tidak. Izin dengan tugas berarti ada KELAS YANG BERJALAN tanpa
 * gurunya, dan piket akan bertindak berdasarkan tugas yang tercatat di sini.
 * Kalau pengajuan langsung berlaku tanpa dilihat siapa pun, seorang guru bisa
 * menuliskan "kerjakan LKS halaman 40" pagi itu juga dan seluruh sekolah
 * mengikutinya tanpa satu pun atasan tahu.
 *
 * Karena itu: tercatat sejak diajukan, tapi BARU BERLAKU setelah disetujui.
 * =======================================================
 */
enum StatusApproval: string
{
    case Pending = 'Pending';
    case Disetujui = 'Disetujui';
    case Ditolak = 'Ditolak';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
        };
    }

    /**
     * Status ini sudah boleh memengaruhi absensi harian guru?
     *
     * Hanya 'Disetujui'. Selama Pending, pengajuannya tercatat tapi kehadiran
     * gurunya belum berubah sama sekali — dan itu memang keadaan yang benar:
     * belum ada yang mengizinkan.
     */
    public function berlaku(): bool
    {
        return $this === self::Disetujui;
    }

    /** Masih bisa disetujui/ditolak? */
    public function bisaDiputuskan(): bool
    {
        return $this === self::Pending;
    }

    public function kelasBadge(): string
    {
        return match ($this) {
            self::Pending => 'border-amber-300 bg-amber-500/10 text-amber-700 dark:text-amber-400',
            self::Disetujui => 'border-emerald-300 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            self::Ditolak => 'border-red-300 bg-red-500/10 text-brand-danger-text',
        };
    }

    public function ikon(): string
    {
        return match ($this) {
            self::Pending => 'clock',
            self::Disetujui => 'check-circle',
            self::Ditolak => 'x-circle',
        };
    }
}
