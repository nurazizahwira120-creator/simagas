<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan aplikasi kunci-nilai.
 *
 * Kunci yang dipakai saat ini (lihat SettingController::KUNCI_WAKTU):
 *   jam_masuk_siswa, batas_terlambat_siswa,
 *   jam_masuk_pegawai, batas_terlambat_pegawai
 */
class Pengaturan extends Model
{
    protected $table = 'pengaturan';

    protected $primaryKey = 'kunci';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['kunci', 'nilai'];

    /**
     * Baca satu pengaturan; kembalikan $default kalau belum pernah diisi.
     */
    public static function ambil(string $kunci, ?string $default = null): ?string
    {
        return static::query()->whereKey($kunci)->value('nilai') ?? $default;
    }

    /**
     * Tulis satu pengaturan (buat kalau belum ada, timpa kalau sudah).
     */
    public static function simpan(string $kunci, ?string $nilai): void
    {
        static::updateOrCreate(['kunci' => $kunci], ['nilai' => $nilai]);
    }

    /**
     * Baca banyak sekaligus dalam satu query.
     *
     * @param  array<string, string|null>  $kunciDanDefault  [kunci => default]
     * @return array<string, string|null>
     */
    public static function ambilBanyak(array $kunciDanDefault): array
    {
        $tersimpan = static::query()
            ->whereIn('kunci', array_keys($kunciDanDefault))
            ->pluck('nilai', 'kunci')
            ->all();

        $hasil = [];
        foreach ($kunciDanDefault as $kunci => $default) {
            $hasil[$kunci] = $tersimpan[$kunci] ?? $default;
        }

        return $hasil;
    }
}
