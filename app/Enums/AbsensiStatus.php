<?php

namespace App\Enums;

enum AbsensiStatus: string
{
    case Hadir = 'hadir';
    case Izin = 'izin';
    case Sakit = 'sakit';
    case Alpha = 'alpha';

    /**
     * Label panjang — dipakai di kalimat/penjelasan, di mana tambahan
     * "(Tanpa Keterangan)" justru membantu memperjelas maksud "Alpa".
     */
    public function label(): string
    {
        return match ($this) {
            self::Hadir => 'Hadir',
            self::Izin => 'Izin',
            self::Sakit => 'Sakit',
            self::Alpha => 'Alpha (Tanpa Keterangan)',
        };
    }

    /**
     * Label pendek — khusus untuk chip/badge/tombol radio yang ruangnya
     * sempit (mis. baris tabel absensi wali kelas di layar HP), di mana
     * label panjang membuat pill-nya melebar dan tabel jadi susah dibaca.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Alpha => 'Alpa',
            default => $this->label(),
        };
    }
}
