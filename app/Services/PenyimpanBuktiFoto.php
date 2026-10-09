<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Menyimpan SATU foto bukti ke disk 'public' — dipampatkan dulu, diberi
 * nama acak. Aturannya sama dengan foto bukti mengajar di
 * JurnalAbsenKelas::unggahBukti() (lihat catatan panjang di sana):
 *
 *   1. dipampatkan selagi masih berkas sementara (PemampatFoto)
 *   2. nama berkas UUID, bukan nama asli dari HP — tidak bisa ditebak
 *   3. ekstensi ikut berubah ke .jpg bila isinya sudah jadi JPEG
 */
class PenyimpanBuktiFoto
{
    /** @return string jalur relatif di disk 'public' */
    public static function simpan(UploadedFile $foto, string $folder): string
    {
        $ekstensi = $foto->extension();

        try {
            $jalurSementara = $foto->getRealPath();
        } catch (\Throwable $e) {
            $jalurSementara = null;
        }

        if (is_string($jalurSementara) && PemampatFoto::keJpeg($jalurSementara)) {
            $ekstensi = 'jpg';
        }

        $jalur = $foto->storeAs($folder, (string) Str::uuid() . '.' . $ekstensi, 'public');

        if ($jalur === false || blank($jalur)) {
            throw new RuntimeException('Storage menolak menyimpan berkas.');
        }

        return $jalur;
    }
}
