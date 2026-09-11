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

    /**
     * Merapikan daftar nama mata pelajaran menjadi daftar yang layak masuk
     * kolom `nama` yang UNIK.
     *
     * ============ KENAPA INI HARUS ADA ============
     * Kolom `mapels.nama` unik. Di MySQL keunikan itu dinilai memakai
     * collation bawaan (utf8mb4_..._ci) yang TIDAK MEMBEDAKAN BESAR-KECIL
     * HURUF: "Matematika" dan "matematika" dianggap nama yang sama.
     *
     * PHP membedakannya. Jadi menyaring dengan unique() biasa meloloskan dua
     * ejaan, lalu MySQL menolak baris kedua dengan "Duplicate entry" — dan
     * kalau itu terjadi DI DALAM MIGRASI, migrasinya berhenti di tengah jalan:
     * tabel `nilais`, `validasi_rapors`, dan `penghargaans` tidak pernah
     * dibuat. Yang terlihat pengguna cuma "500 Server Error" di setiap
     * halaman SIAKAD, tanpa petunjuk apa pun soal nama mata pelajaran.
     *
     * Ini TIDAK akan pernah ketahuan di SQLite (dipakai saat pengujian),
     * karena di sana UNIQUE pada teks justru membedakan besar-kecil huruf.
     * Karena itu logikanya dipisah ke sini: supaya bisa diuji tanpa database
     * sama sekali.
     *
     * Yang dikerjakan:
     *   - memangkas spasi di ujung;
     *   - meratakan spasi ganda di tengah ("Bahasa  Inggris" -> "Bahasa Inggris");
     *   - membuang nama kosong;
     *   - menyatukan ejaan yang hanya beda besar-kecil huruf. Yang DIPAKAI
     *     adalah ejaan pertama yang ditemui, bukan versi huruf kecil semua,
     *     supaya nama yang dilihat guru tetap wajar.
     *
     * @param  iterable<int, string|null>  $nama
     * @return array<int, string>
     */
    public static function rapikanNama(iterable $nama): array
    {
        $hasil = [];

        foreach ($nama as $satu) {
            $bersih = trim(preg_replace('/\s+/u', ' ', (string) $satu) ?? '');

            if ($bersih === '') {
                continue;
            }

            // Kunci pembanding disamakan huruf kecil — meniru cara MySQL
            // menilai keunikan, bukan cara PHP.
            $kunci = mb_strtolower($bersih);

            if (! array_key_exists($kunci, $hasil)) {
                $hasil[$kunci] = $bersih;
            }
        }

        return array_values($hasil);
    }

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
        $nama = static::rapikanNama(
            JadwalPelajaran::query()
                ->where('guru_id', $pegawaiId)
                ->distinct()
                ->pluck('mata_pelajaran')
        );

        if ($nama === []) {
            return collect();
        }

        /*
         | whereIn pada nama yang sudah dirapikan.
         |
         | Di MySQL perbandingannya tidak membedakan besar-kecil huruf, jadi
         | "matematika" di jadwal tetap cocok dengan "Matematika" di master —
         | dan itu memang yang diinginkan. Di SQLite perbandingannya ketat,
         | tetapi keduanya berasal dari sumber yang sama (nama di jadwal juga
         | yang menyemai tabel ini), jadi hasilnya tetap cocok.
         */
        return static::query()
            ->whereIn('nama', $nama)
            ->orderBy('nama')
            ->get();
    }
}
