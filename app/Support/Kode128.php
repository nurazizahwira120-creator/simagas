<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Penghasil barcode Code 128 (subset B) dalam PHP murni.
 *
 * KENAPA TIDAK PAKAI PAKET (mis. milon/barcode "DNS1D"):
 * Aplikasi ini sudah punya dua dependensi yang harus dipasang manual
 * (livewire & maatwebsite/excel). Menambah satu lagi hanya untuk menggambar
 * garis hitam-putih berarti satu hal lagi yang bisa lupa dipasang dan bikin
 * halaman error. Kelas ini tidak butuh apa pun: tanpa composer, tanpa CDN,
 * tanpa ekstensi GD — jadi kartu tetap tercetak walau internet sekolah mati.
 *
 * ASAL TABEL POLA DI BAWAH:
 * 103 pola ini TIDAK diketik dari ingatan. Semuanya diturunkan secara
 * otomatis dengan membongkar keluaran encoder rujukan (zxing-cpp), lalu
 * hasil kelas ini diuji balik: barcode-nya dirender jadi gambar dan dibaca
 * ulang oleh pembaca barcode sungguhan sampai teksnya cocok persis. Barcode
 * yang salah tidak kelihatan salah oleh mata — ia hanya gagal di gerbang
 * sekolah — jadi ia harus dibuktikan, bukan diperkirakan.
 *
 * Subset B dipilih (bukan C yang lebih rapat) karena ia menerima angka
 * maupun huruf apa adanya; NIS sekolah tidak selalu murni angka.
 */
class Kode128
{
    /** Lebar tiap elemen (batang & spasi) per nilai simbol. */
    private const POLA = [
        0 => '212222', 1 => '222122', 2 => '222221', 3 => '121223', 4 => '121322', 5 => '131222', 6 => '122213', 7 => '122312',
        8 => '132212', 9 => '221213', 10 => '221312', 11 => '231212', 12 => '112232', 13 => '122132', 14 => '122231', 15 => '113222',
        16 => '123122', 17 => '123221', 18 => '223211', 19 => '221132', 20 => '221231', 21 => '213212', 22 => '223112', 23 => '312131',
        24 => '311222', 25 => '321122', 26 => '321221', 27 => '312212', 28 => '322112', 29 => '322211', 30 => '212123', 31 => '212321',
        32 => '232121', 33 => '111323', 34 => '131123', 35 => '131321', 36 => '112313', 37 => '132113', 38 => '132311', 39 => '211313',
        40 => '231113', 41 => '231311', 42 => '112133', 43 => '112331', 44 => '132131', 45 => '113123', 46 => '113321', 47 => '133121',
        48 => '313121', 49 => '211331', 50 => '231131', 51 => '213113', 52 => '213311', 53 => '213131', 54 => '311123', 55 => '311321',
        56 => '331121', 57 => '312113', 58 => '312311', 59 => '332111', 60 => '314111', 61 => '221411', 62 => '431111', 63 => '111224',
        64 => '111422', 65 => '121124', 66 => '121421', 67 => '141122', 68 => '141221', 69 => '112214', 70 => '112412', 71 => '122114',
        72 => '122411', 73 => '142112', 74 => '142211', 75 => '241211', 76 => '221114', 77 => '413111', 78 => '241112', 79 => '134111',
        80 => '111242', 81 => '121142', 82 => '121241', 83 => '114212', 84 => '124112', 85 => '124211', 86 => '411212', 87 => '421112',
        88 => '421211', 89 => '212141', 90 => '214121', 91 => '412121', 92 => '111143', 93 => '111341', 94 => '131141', 95 => '114113',
        96 => '114311', 97 => '411113', 98 => '411311', 99 => '113141', 100 => '114131', 101 => '311141', 102 => '411131',
    ];

    private const START_B = 104;
    private const POLA_START_B = '211214';
    private const POLA_STOP = '2331112';

    /**
     * Ubah teks jadi deretan lebar elemen, bergantian batang-spasi dimulai
     * dari batang. Contoh [2,1,1,2,...] = batang 2 modul, spasi 1 modul, dst.
     *
     * @return array<int, int>
     */
    public static function elemen(string $teks): array
    {
        if ($teks === '') {
            throw new InvalidArgumentException('Teks barcode tidak boleh kosong.');
        }

        $nilai = [];

        foreach (str_split($teks) as $huruf) {
            $kode = ord($huruf);

            // Subset B memuat ASCII 32..126. Di luar itu tidak bisa
            // dikodekan — lebih baik ditolak terang-terangan daripada
            // menghasilkan barcode yang diam-diam salah baca.
            if ($kode < 32 || $kode > 126) {
                throw new InvalidArgumentException(
                    "Karakter tidak didukung Code 128 subset B: " . var_export($huruf, true)
                );
            }

            $nilai[] = $kode - 32;
        }

        // Checksum: (nilai start + sum(posisi * nilai)) mod 103.
        $jumlah = self::START_B;
        foreach ($nilai as $i => $v) {
            $jumlah += ($i + 1) * $v;
        }
        $check = $jumlah % 103;

        $rangkaian = self::POLA_START_B;
        foreach ($nilai as $v) {
            $rangkaian .= self::POLA[$v];
        }
        $rangkaian .= self::POLA[$check];
        $rangkaian .= self::POLA_STOP;

        return array_map('intval', str_split($rangkaian));
    }

    /**
     * Total lebar barcode dalam modul — dipakai view untuk menghitung lebar
     * satu modul agar barcode pas di ruang yang tersedia.
     */
    public static function lebarModul(string $teks): int
    {
        return array_sum(self::elemen($teks));
    }
}
