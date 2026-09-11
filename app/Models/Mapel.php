<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Master mata pelajaran. Lihat migrasi 000031 untuk alasan tabel ini terpisah
 * dari kolom teks `jadwal_pelajaran.mata_pelajaran` yang lama.
 */
class Mapel extends Model
{
    protected $fillable = ['nama', 'kode'];

    public function nilai(): HasMany
    {
        return $this->hasMany(Nilai::class, 'mapel_id');
    }

    /**
     * Mata pelajaran yang diajar seorang guru, dicocokkan lewat NAMA di
     * jadwal pelajaran.
     *
     * ============ KENAPA LEWAT NAMA ============
     * jadwal_pelajaran menyimpan mata pelajaran sebagai teks, bukan foreign
     * key (lihat migrasi 000031). Jembatannya karena itu nama — dan nama itu
     * juga yang dipakai menyemai tabel ini, sehingga keduanya pasti cocok.
     *
     * Dua query, bukan satu join: daftar nama diambil lebih dulu supaya
     * pencocokannya bisa dinormalkan di PHP (spasi berlebih di data lama).
     * Jumlah barisnya puluhan, jadi ini bukan soal performa.
     * ===========================================
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function diajarOleh(int $pegawaiId)
    {
        $nama = JadwalPelajaran::query()
            ->where('guru_id', $pegawaiId)
            ->distinct()
            ->pluck('mata_pelajaran')
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($nama->isEmpty()) {
            return collect();
        }

        return static::query()
            ->whereIn('nama', $nama)
            ->orderBy('nama')
            ->get();
    }
}
