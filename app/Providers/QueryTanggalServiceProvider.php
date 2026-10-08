<?php

namespace App\Providers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\ServiceProvider;

/**
 * Filter tanggal yang BISA MEMAKAI INDEKS database.
 *
 * ============ MASALAH YANG DIPERBAIKI ============
 * whereDate('tanggal', $hari) diterjemahkan MySQL menjadi
 *   WHERE date(tanggal) = '2026-10-08'
 * Karena kolomnya dibungkus fungsi date(), MySQL TIDAK BISA memakai indeks
 * apa pun dan terpaksa membaca SELURUH tabel. Diuji pada 450 ribu baris
 * absensi (600 siswa x 2 tahun): 53 ms per query. Dengan rentang di bawah
 * ditambah indeks pada kolom tanggal (migrasi 000043): 0,2–0,4 ms.
 * Tabel absensi KBM tumbuh ±800 ribu baris per tahun, jadi selisih ini
 * makin terasa setiap semester.
 * =================================================
 *
 * ============ KENAPA BATAS ATASNYA "23:59:59", BAWAHNYA TANPA JAM ============
 * Kolom tanggal tersimpan dalam DUA bentuk tergantung database dan cara
 * menyisipkannya:
 *   - MySQL (kolom DATE)                 -> '2026-10-08'
 *   - SQLite lewat Eloquent (dipakai tes) -> '2026-10-08 00:00:00'
 *   - SQLite lewat DB::table()->insert   -> '2026-10-08'
 * Rentang ['2026-10-08', '2026-10-08 23:59:59'] mencakup KETIGANYA, juga
 * kolom DATETIME (waktu_mulai) di jam berapa pun. Itulah sebabnya dulu
 * dipakai whereDate(): whereBetween dengan batas tanggal polos melewatkan
 * baris bertanggal sama yang menyimpan jam. Batas atas berjam menutup
 * celah itu tanpa membungkus kolom dengan fungsi.
 * ============================================================================
 *
 * Dipasang pada Query\Builder, jadi bisa dipanggil dari model mana pun:
 *   AbsensiSiswa::wherePadaTanggal('tanggal', today())
 *   $q->whereAntaraTanggal('absensi_kbm_siswa.tanggal', $dari, $sampai)
 */
class QueryTanggalServiceProvider extends ServiceProvider
{
    /** 'Y-m-d' — batas BAWAH satu hari. */
    public static function awal(mixed $tanggal): string
    {
        return Carbon::parse($tanggal)->format('Y-m-d');
    }

    /** 'Y-m-d 23:59:59' — batas ATAS satu hari. */
    public static function akhir(mixed $tanggal): string
    {
        return Carbon::parse($tanggal)->format('Y-m-d') . ' 23:59:59';
    }

    public function boot(): void
    {
        // Tepat pada satu hari.
        Builder::macro('wherePadaTanggal', function (string $kolom, mixed $tanggal) {
            /** @var Builder $this */
            return $this->whereBetween($kolom, [
                QueryTanggalServiceProvider::awal($tanggal),
                QueryTanggalServiceProvider::akhir($tanggal),
            ]);
        });

        // Dari hari $dari sampai hari $sampai, keduanya ikut.
        Builder::macro('whereAntaraTanggal', function (string $kolom, mixed $dari, mixed $sampai) {
            /** @var Builder $this */
            return $this->whereBetween($kolom, [
                QueryTanggalServiceProvider::awal($dari),
                QueryTanggalServiceProvider::akhir($sampai),
            ]);
        });

        // Satu bulan kalender penuh — pengganti whereYear() + whereMonth().
        Builder::macro('wherePadaBulan', function (string $kolom, int $tahun, int $bulan) {
            $awal = Carbon::create($tahun, $bulan, 1);

            /** @var Builder $this */
            return $this->whereBetween($kolom, [
                QueryTanggalServiceProvider::awal($awal),
                QueryTanggalServiceProvider::akhir($awal->copy()->endOfMonth()),
            ]);
        });

        // Sebelum hari $tanggal (hari itu sendiri TIDAK ikut).
        Builder::macro('whereSebelumTanggal', function (string $kolom, mixed $tanggal) {
            /** @var Builder $this */
            return $this->where($kolom, '<', QueryTanggalServiceProvider::awal($tanggal));
        });
    }
}
