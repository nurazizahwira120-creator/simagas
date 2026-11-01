<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Mengubah teks polos yang diketik manusia (isi pengumuman) menjadi HTML
 * aman dengan alamat web yang bisa diklik.
 *
 * ============ URUTANNYA MENENTUKAN KEAMANAN ============
 *   1. Seluruh teks DI-ESCAPE lebih dulu dengan e().
 *   2. Baru sesudahnya alamat http(s):// dibungkus <a>.
 * Dengan urutan ini, tag apa pun yang diketik pengirim (<script>, <img
 * onerror>) sudah menjadi teks biasa sebelum ada satu tag pun yang kita
 * sisipkan. Kebalikannya — membungkus tautan dulu lalu meloloskan sisanya —
 * membuka celah XSS.
 * =======================================================
 *
 * Hanya skema http/https yang dikenali, jadi "javascript:..." tidak pernah
 * menjadi tautan. Baris baru dibiarkan apa adanya; tampilkan hasilnya di
 * elemen ber-class whitespace-pre-line supaya paragrafnya tetap terpisah.
 */
final class TeksBertaut
{
    /** Tanda baca di ujung yang biasanya bagian kalimat, bukan bagian alamat. */
    private const EKOR = '.,;:!?)]}\'"';

    public static function html(?string $teks, string $kelasTautan = ''): HtmlString
    {
        $aman = e((string) $teks);

        // Setelah e(), tanda & di dalam alamat menjadi &amp; — dan memang
        // begitulah & ditulis dengan benar di dalam atribut href.
        $hasil = preg_replace_callback(
            '~\bhttps?://[^\s<>"]+~iu',
            function (array $m) use ($kelasTautan) {
                $alamat = $m[0];
                $ekor = '';

                // "Lihat https://contoh.id/form." -> titiknya bukan bagian
                // alamat. Entitas &quot; / &#039; hasil e() juga dikupas.
                while ($alamat !== '') {
                    if (preg_match('/(&quot;|&#039;|&gt;)$/', $alamat, $x)) {
                        $ekor = $x[1] . $ekor;
                        $alamat = substr($alamat, 0, -strlen($x[1]));

                        continue;
                    }

                    if (str_contains(self::EKOR, substr($alamat, -1))) {
                        $ekor = substr($alamat, -1) . $ekor;
                        $alamat = substr($alamat, 0, -1);

                        continue;
                    }

                    break;
                }

                if ($alamat === '' || preg_match('~^https?://$~i', $alamat)) {
                    return $m[0];
                }

                $kelas = $kelasTautan !== '' ? ' class="' . e($kelasTautan) . '"' : '';

                return '<a href="' . $alamat . '" target="_blank" rel="noopener noreferrer nofollow"' . $kelas . '>'
                    . $alamat . '</a>' . $ekor;
            },
            $aman,
        );

        return new HtmlString($hasil ?? $aman);
    }
}
