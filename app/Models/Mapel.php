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

    /**
     * Pastikan setiap nama mata pelajaran punya baris di master `mapels`.
     *
     * ============ BUG YANG DIPERBAIKI ============
     * Master ini dulu HANYA diisi sekali, oleh migrasi 000031, dari jadwal
     * yang ada saat itu. Mata pelajaran yang ditambahkan ke jadwal SESUDAHNYA
     * (tahun ajaran baru, guru baru, mapel muatan lokal) tidak pernah masuk —
     * dan karena Input Nilai menyaring lewat master ini, mapel tersebut
     * diam-diam hilang dari pilihan guru. Tidak ada error; daftarnya cuma
     * "kurang satu".
     * =============================================
     *
     * Dipanggil dari tiga tempat:
     *   - JadwalPelajaran::booted()  -> setiap jadwal dibuat/diubah;
     *   - diajarOleh()               -> penyembuh diri untuk data lama;
     *   - migrasi 000042             -> mengisi yang terlewat sekali jalan.
     *
     * Pembandingnya TIDAK membedakan besar-kecil huruf (meniru MySQL), dan
     * yang disisipkan hanya nama yang benar-benar belum ada — jadi aman
     * dipanggil berulang kali. insertOrIgnore menjadi jaring terakhir kalau
     * dua permintaan kebetulan menyisipkan nama yang sama bersamaan.
     *
     * @param  iterable<int, string|null>  $nama
     * @return int jumlah mata pelajaran baru yang ditambahkan
     */
    public static function sinkron(iterable $nama): int
    {
        // 120 = panjang kolom mapels.nama. Nama lebih panjang dipotong
        // daripada membuat MySQL mode ketat menolak seluruh sisipan.
        $bersih = array_map(
            fn (string $n) => mb_substr($n, 0, 120),
            static::rapikanNama($nama),
        );

        if ($bersih === []) {
            return 0;
        }

        $sudahAda = static::query()
            ->pluck('nama')
            ->mapWithKeys(fn ($n) => [mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $n) ?? '')) => true]);

        $baru = array_values(array_filter(
            $bersih,
            fn (string $n) => ! $sudahAda->has(mb_strtolower($n)),
        ));

        if ($baru === []) {
            return 0;
        }

        $sekarang = now();

        return static::query()->insertOrIgnore(array_map(fn (string $n) => [
            'nama' => $n,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ], $baru));
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

        // Penyembuh diri: mapel di jadwal yang belum punya baris master
        // (data lama sebelum perbaikan ini) dibuatkan sekarang, supaya
        // langsung muncul di Input Nilai tanpa menunggu siapa pun.
        static::sinkron($nama);

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
