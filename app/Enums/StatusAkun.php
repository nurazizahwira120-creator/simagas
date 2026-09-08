<?php

namespace App\Enums;

enum StatusAkun: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu Persetujuan',
            self::Active => 'Aktif',
            self::Rejected => 'Ditolak',
        };
    }

    /**
     * Hanya akun berstatus Active yang boleh login. Dipakai di
     * AuthController — tanpa penjagaan ini, kolom `status` cuma jadi
     * penanda kosmetik dan akun yang belum disetujui tetap bisa masuk.
     */
    public function bolehLogin(): bool
    {
        return $this === self::Active;
    }

    public function chip(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-50 text-amber-700 ring-amber-200',
            self::Active => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            self::Rejected => 'bg-rose-50 text-rose-700 ring-rose-200',
        };
    }
}
