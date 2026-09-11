<?php

namespace App\Enums;

/**
 * Status penguncian rapor satu kelas pada satu semester.
 *
 * ============ APA YANG DIKUNCI OLEH STATUS INI ============
 *   draft     : guru masih boleh menyimpan & mengubah nilai.
 *   menunggu  : wali kelas sudah mengajukan. Nilai TERKUNCI — guru tidak bisa
 *               lagi mengubah apa pun, supaya angka yang dibaca kepala sekolah
 *               sama dengan angka yang nanti terbit.
 *   disetujui : rapor terbit. Nilai tetap terkunci, dan barulah wali murid
 *               bisa melihatnya.
 * =========================================================
 */
enum StatusRapor: string
{
    case Draft = 'draft';
    case Menunggu = 'menunggu';
    case Disetujui = 'disetujui';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Menunggu => 'Menunggu Persetujuan',
            self::Disetujui => 'Disetujui',
        };
    }

    /**
     * Apakah guru masih boleh menyimpan nilai pada status ini.
     *
     * Dipakai DI SERVER oleh NilaiController, bukan sekadar untuk
     * menyembunyikan tombol. Tombol yang disembunyikan bukan kontrol akses.
     */
    public function bolehUbahNilai(): bool
    {
        return $this === self::Draft;
    }

    /** Apakah rapornya sudah boleh dilihat wali murid. */
    public function terbitKeWaliMurid(): bool
    {
        return $this === self::Disetujui;
    }

    public function kelasBadge(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-500/10 text-gray-600 dark:text-gray-300',
            self::Menunggu => 'bg-warning-500/15 text-warning-700 dark:text-warning-400',
            self::Disetujui => 'bg-success-500/10 text-success-600',
        };
    }
}
