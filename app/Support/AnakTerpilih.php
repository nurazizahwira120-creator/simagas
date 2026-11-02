<?php

namespace App\Support;

use App\Models\Siswa;
use Illuminate\Support\Collection;

/**
 * Anak yang sedang dilihat wali murid — DIINGAT antarhalaman.
 *
 * ============ MASALAH YANG DIPERBAIKI ============
 * Setiap halaman wali murid punya pemilih anaknya sendiri dan selalu
 * kembali ke anak PERTAMA. Orang tua yang memilih "Giovani" di Beranda lalu
 * mengetuk menu Kehadiran di bilah bawah tiba-tiba melihat data "Alexandro"
 * — tanpa sadar bahwa yang tampil adalah anak yang lain.
 * =================================================
 *
 * Pilihan disimpan di SESSION, bukan di URL saja: bilah menu bawah dan
 * sidebar menautkan alamat polos (/wali-murid/rapor), jadi satu-satunya
 * tempat yang dibawa dari halaman ke halaman adalah session.
 *
 * ============ KEAMANAN ============
 * Id yang diminta (dari ?anak=, dari properti Livewire, atau dari session)
 * SELALU dicocokkan ke koleksi anak milik akun yang login. Id siswa keluarga
 * lain tidak pernah menemukan apa pun — pemanggil jatuh ke anak pertama.
 * ==================================
 */
final class AnakTerpilih
{
    public const KUNCI_SESSION = 'wali_murid.anak_id';

    /**
     * @param  Collection<int, Siswa>  $anakAnak  anak milik akun yang login
     * @param  mixed  $diminta  id dari ?anak= / Livewire; null = pakai yang diingat
     */
    public static function pilih(Collection $anakAnak, mixed $diminta = null): ?Siswa
    {
        if ($anakAnak->isEmpty()) {
            return null;
        }

        if (is_numeric($diminta) && ($anak = $anakAnak->firstWhere('id', (int) $diminta))) {
            self::ingat($anak->id);

            return $anak;
        }

        $diingat = session(self::KUNCI_SESSION);

        return (is_numeric($diingat) ? $anakAnak->firstWhere('id', (int) $diingat) : null)
            ?? $anakAnak->first();
    }

    /** Id anak yang diingat, hanya kalau memang termasuk $anakAnak. */
    public static function idDiingat(Collection $anakAnak): ?int
    {
        return self::pilih($anakAnak)?->id;
    }

    public static function ingat(?int $id): void
    {
        if ($id !== null) {
            session()->put(self::KUNCI_SESSION, $id);
        }
    }
}
