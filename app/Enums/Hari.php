<?php

namespace App\Enums;

enum Hari: string
{
    case Senin = 'senin';
    case Selasa = 'selasa';
    case Rabu = 'rabu';
    case Kamis = 'kamis';
    case Jumat = 'jumat';
    case Sabtu = 'sabtu';
    case Minggu = 'minggu';

    public function label(): string
    {
        return match ($this) {
            self::Senin => 'Senin',
            self::Selasa => 'Selasa',
            self::Rabu => 'Rabu',
            self::Kamis => 'Kamis',
            self::Jumat => "Jum'at",
            self::Sabtu => 'Sabtu',
            self::Minggu => 'Minggu',
        };
    }

    /**
     * Urutan hari untuk sorting.
     *
     * Dipakai untuk mengurutkan jadwal di PHP (lewat Collection::sortBy),
     * BUKAN lewat ORDER BY di SQL. Alasannya: kolom `hari` disimpan sebagai
     * string ('senin', 'selasa', ...) sehingga ORDER BY di database akan
     * mengurutkannya secara alfabetis — jumat, kamis, minggu, rabu, sabtu,
     * selasa, senin — jelas salah. MySQL punya ORDER BY FIELD() untuk ini,
     * tapi SQLite (yang dipakai saat development) tidak, jadi sorting di
     * PHP adalah cara yang aman untuk kedua database.
     */
    public function urutan(): int
    {
        return match ($this) {
            self::Senin => 1,
            self::Selasa => 2,
            self::Rabu => 3,
            self::Kamis => 4,
            self::Jumat => 5,
            self::Sabtu => 6,
            self::Minggu => 7,
        };
    }

    /**
     * Hari efektif KBM: Senin–SABTU.
     *
     * Sabtu ikut masuk karena banyak SMK tetap menjalankan KBM, praktik, atau
     * ekstrakurikuler di hari itu. Sebelumnya daftar ini berhenti di Jumat,
     * sehingga jadwal yang sudah disimpan Super Admin untuk hari Sabtu tetap
     * ada di database tapi TIDAK PERNAH BISA DIPILIH di filter — datanya
     * seolah hilang. Minggu tetap dikecualikan.
     *
     * @return array<int, self>
     */
    public static function hariSekolah(): array
    {
        return [self::Senin, self::Selasa, self::Rabu, self::Kamis, self::Jumat, self::Sabtu];
    }

    /**
     * Hari untuk "hari ini" berdasarkan tanggal sistem. Carbon::dayOfWeek
     * mengembalikan 0 (Minggu) sampai 6 (Sabtu).
     */
    public static function hariIni(): self
    {
        return match (now()->dayOfWeek) {
            0 => self::Minggu,
            1 => self::Senin,
            2 => self::Selasa,
            3 => self::Rabu,
            4 => self::Kamis,
            5 => self::Jumat,
            default => self::Sabtu,
        };
    }
}
