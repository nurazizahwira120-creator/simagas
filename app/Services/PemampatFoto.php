<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Memampatkan foto sebelum disimpan ke storage.
 *
 * ============ KENAPA INI ADA ============
 * Foto bukti mengajar diambil langsung dari kamera HP. Satu jepretan HP
 * kelas menengah hari ini berukuran 3–5 MB pada resolusi 4000×3000. Di
 * sekolah ini polanya kira-kira:
 *
 *     11 guru x 4 sesi x 25 hari = 1.100 foto per bulan
 *
 * Pada 3 MB per foto itu berarti 3 GB per bulan, dan tidak ada satu pun
 * proses yang menghapusnya. Kuota hosting bersama biasanya habis dalam
 * hitungan bulan — dan gejalanya bukan "disk penuh" yang jelas, melainkan
 * upload yang tiba-tiba gagal dan halaman yang error tanpa sebab.
 *
 * Foto ini hanya dilihat sebagai bukti di layar, tidak pernah dicetak.
 * Lebar 1280 px sudah tajam untuk itu, dan JPEG mutu 70 memangkas ukuran
 * ke sekitar 150–250 KB — kurang lebih 1/15 dari aslinya.
 *
 * ============ SEMUA KEGAGALAN DI SINI TIDAK FATAL ============
 * Kalau ekstensi GD tidak ada, berkasnya bukan gambar, atau memori tidak
 * cukup, method ini mengembalikan false dan pemanggilnya menyimpan foto
 * ASLI apa adanya. Guru tidak boleh gagal mengunggah bukti mengajar hanya
 * karena pemampatannya bermasalah.
 */
class PemampatFoto
{
    /** Sisi terpanjang (px) setelah pemampatan. */
    public const MAKS_SISI = 1280;

    /** Mutu JPEG 0–100. 70 adalah titik biasa: mata sulit membedakannya. */
    public const MUTU = 70;

    /**
     * Menulis ulang berkas di $jalur menjadi JPEG yang sudah diperkecil.
     *
     * Berkasnya ditimpa DI TEMPAT. Pemanggilnya harus memakai ini pada
     * berkas sementara (temporary upload), bukan pada berkas yang sudah
     * tersimpan permanen.
     *
     * @return bool true kalau berhasil dipampatkan menjadi JPEG.
     */
    public static function keJpeg(string $jalur, int $maksSisi = self::MAKS_SISI, int $mutu = self::MUTU): bool
    {
        if (! extension_loaded('gd') || ! is_file($jalur) || ! is_readable($jalur)) {
            return false;
        }

        try {
            $info = @getimagesize($jalur);

            if ($info === false) {
                // Bukan gambar yang dikenali GD — biarkan apa adanya.
                return false;
            }

            [$lebar, $tinggi] = $info;
            $tipe = $info[2] ?? null;

            $sumber = match ($tipe) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($jalur),
                IMAGETYPE_PNG => @imagecreatefrompng($jalur),
                IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($jalur) : false,
                default => false,
            };

            if ($sumber === false) {
                return false;
            }

            // Foto dari HP hampir selalu membawa penanda orientasi EXIF, dan
            // GD MENGABAIKANNYA. Tanpa langkah ini foto potret tersimpan
            // miring 90 derajat — terlihat benar di galeri HP, terbalik di web.
            if ($tipe === IMAGETYPE_JPEG) {
                $sumber = self::luruskan($sumber, $jalur);
                $lebar = imagesx($sumber);
                $tinggi = imagesy($sumber);
            }

            $skala = min(1, $maksSisi / max($lebar, $tinggi));
            $lebarBaru = max(1, (int) round($lebar * $skala));
            $tinggiBaru = max(1, (int) round($tinggi * $skala));

            $tujuan = imagecreatetruecolor($lebarBaru, $tinggiBaru);

            // PNG/WEBP boleh transparan. JPEG tidak mengenal transparansi,
            // dan area transparan yang tidak diisi akan menjadi HITAM PEKAT.
            // Diisi putih dulu supaya hasilnya wajar.
            $putih = imagecolorallocate($tujuan, 255, 255, 255);
            imagefilledrectangle($tujuan, 0, 0, $lebarBaru, $tinggiBaru, $putih);

            imagecopyresampled($tujuan, $sumber, 0, 0, 0, 0, $lebarBaru, $tinggiBaru, $lebar, $tinggi);

            $berhasil = imagejpeg($tujuan, $jalur, $mutu);

            imagedestroy($sumber);
            imagedestroy($tujuan);

            return (bool) $berhasil;
        } catch (\Throwable $e) {
            // Termasuk "Allowed memory size exhausted" pada foto raksasa.
            Log::warning('Pemampatan foto dilewati.', [
                'jalur' => basename($jalur),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Memutar gambar sesuai penanda orientasi EXIF.
     *
     * @param  \GdImage  $gambar
     * @return \GdImage
     */
    private static function luruskan($gambar, string $jalur)
    {
        if (! function_exists('exif_read_data')) {
            return $gambar;
        }

        try {
            $exif = @exif_read_data($jalur);
            $orientasi = $exif['Orientation'] ?? null;

            $derajat = match ($orientasi) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($derajat === 0) {
                return $gambar;
            }

            $diputar = imagerotate($gambar, $derajat, 0);

            if ($diputar === false) {
                return $gambar;
            }

            imagedestroy($gambar);

            return $diputar;
        } catch (\Throwable $e) {
            return $gambar;
        }
    }
}
