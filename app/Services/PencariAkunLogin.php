<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Menerjemahkan apa pun yang diketik di kolom "Masuk" menjadi SATU akun.
 *
 * Pengguna boleh mengetik salah satu dari tiga hal:
 *   1. Email          -> dicocokkan ke users.email
 *   2. NIS anak       -> dicocokkan ke siswa.nis, lalu diambil akun WALI MURID-nya
 *   3. Nomor HP       -> dicocokkan ke users.no_hp
 *
 * ============ SOAL NIS: TIDAK ADA AKUN MILIK SISWA ============
 * Di sistem ini SISWA TIDAK PUNYA AKUN LOGIN — tidak ada UserRole::Siswa,
 * dan tabel `siswa` tidak punya kolom user_id. Yang punya akun adalah orang
 * tuanya, terhubung lewat siswa.wali_murid_id.
 *
 * Jadi "login dengan NIS" artinya: mengetik NIS anak akan masuk ke akun WALI
 * MURID anak tersebut. Itu memang yang dibutuhkan di lapangan — orang tua
 * hafal NIS anaknya, sering tidak hafal email yang didaftarkan pihak sekolah.
 *
 * Kalau siswa itu belum ditautkan ke akun wali murid mana pun
 * (wali_murid_id kosong), NIS-nya tidak bisa dipakai masuk. Yang muncul
 * pesan kegagalan biasa, BUKAN "NIS ini belum punya akun" — lihat alasannya
 * di bagian berikutnya.
 * ==============================================================
 *
 * ============ KENAPA HASILNYA BISA "GANDA" ============
 * Kolom users.no_hp TIDAK unik di database, dan memang tidak bisa dibuat
 * unik begitu saja: data lama sudah terlanjur berisi nomor kembar (satu
 * orang tua dengan dua anak yang didaftarkan dua kali, atau nomor sekolah
 * yang dipakai beberapa staf).
 *
 * Kalau satu nomor cocok dengan dua akun, sistem TIDAK BOLEH memilih salah
 * satunya diam-diam — itu berarti seseorang bisa masuk ke akun orang lain
 * hanya karena nomornya sama. Karena itu keadaan ini dikembalikan apa adanya
 * sebagai "ganda", dan pemanggilnya wajib menolak sambil meminta pengguna
 * memakai email.
 * ======================================================
 */
class PencariAkunLogin
{
    public const JENIS_EMAIL = 'email';
    public const JENIS_NIS = 'nis';
    public const JENIS_HP = 'no_hp';

    /**
     * Panjang minimal digit sebelum sebuah masukan dianggap nomor HP.
     *
     * Tanpa batas ini, mengetik "0" akan cocok dengan akun mana pun yang
     * kolom no_hp-nya kebetulan berisi "0" atau "-".
     */
    private const MIN_DIGIT_HP = 8;

    /**
     * @return array{user: ?User, jenis: ?string, ganda: bool}
     */
    public function cari(string $identitas): array
    {
        $identitas = trim($identitas);

        if ($identitas === '') {
            return $this->hasil(null, null);
        }

        // Ada '@' -> pasti dimaksudkan sebagai email. Tidak diteruskan ke
        // pencarian NIS/HP, supaya salah ketik email tidak berakhir cocok
        // dengan nomor HP seseorang secara kebetulan.
        if (str_contains($identitas, '@')) {
            return $this->hasil($this->lewatEmail($identitas), self::JENIS_EMAIL);
        }

        $lewatNis = $this->lewatNis($identitas);
        $lewatHp = $this->lewatHp($identitas);

        /*
         | Semua kandidat dikumpulkan DULU, baru diputuskan.
         |
         | Versi yang "cek NIS, kalau ketemu langsung pakai" terlihat lebih
         | sederhana dan menyembunyikan satu kasus berbahaya: sebuah angka
         | bisa sekaligus merupakan NIS seorang anak DAN nomor HP orang lain.
         | Kalau NIS langsung menang, orang itu masuk ke akun yang bukan
         | miliknya tanpa ada yang tahu.
         */
        $kandidat = collect([$lewatNis])
            ->merge($lewatHp)
            ->filter()
            ->unique('id')
            ->values();

        if ($kandidat->count() > 1) {
            return ['user' => null, 'jenis' => null, 'ganda' => true];
        }

        $user = $kandidat->first();

        if (! $user) {
            return $this->hasil(null, null);
        }

        return $this->hasil($user, $lewatNis && $lewatNis->id === $user->id ? self::JENIS_NIS : self::JENIS_HP);
    }

    /**
     * Bentuk baku sebuah nomor HP Indonesia: selalu diawali "0", tanpa
     * pemisah apa pun. Dipakai juga oleh pemanggil untuk kunci pembatas
     * percobaan login, supaya "0812-3456" dan "+62812 3456" tidak dihitung
     * sebagai dua identitas berbeda oleh RateLimiter.
     */
    public function bakukanHp(string $nilai): string
    {
        $digit = preg_replace('/\D+/', '', $nilai) ?? '';

        if ($digit === '') {
            return '';
        }

        // "628xxx" (kode negara) -> "08xxx".
        if (str_starts_with($digit, '62')) {
            return '0' . substr($digit, 2);
        }

        // "8xxx" (nol-nya hilang saat disalin dari kontak) -> "08xxx".
        if (! str_starts_with($digit, '0')) {
            return '0' . $digit;
        }

        return $digit;
    }

    /* ===================== PENCARIAN ===================== */

    private function lewatEmail(string $email): ?User
    {
        /*
         | LOWER() dipakai di kedua sisi, bukan `where('email', $email)`.
         |
         | MySQL dengan collation bawaan memang tidak peduli besar-kecil
         | huruf, tapi SQLite (dipakai saat pengujian) peduli. Tanpa baris
         | ini, "Budi@Sekolah.id" gagal masuk di satu tempat dan berhasil di
         | tempat lain — perbedaan yang baru ketahuan setelah dipakai orang.
         */
        return User::whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
    }

    private function lewatNis(string $nis): ?User
    {
        // NIS TIDAK dinormalkan (angka nol di depan tidak dibuang):
        // "0012345" dan "12345" adalah dua siswa yang berbeda.
        if (! preg_match('/^[0-9]{4,30}$/', $nis)) {
            return null;
        }

        $siswa = Siswa::with('waliMurid')->where('nis', $nis)->first();

        return $siswa?->waliMurid;
    }

    /** @return Collection<int, User> */
    private function lewatHp(string $nomor): Collection
    {
        $baku = $this->bakukanHp($nomor);

        if (strlen($baku) < self::MIN_DIGIT_HP) {
            return collect();
        }

        /*
         | Nomor di database ditulis bebas: "0812-3456-7890", "+62 812 3456",
         | "(0812) 34567". Karena itu pemisahnya dibuang DI SISI SQL lebih
         | dulu, lalu hasilnya dibandingkan dengan beberapa bentuk penulisan
         | yang mungkin dari masukan pengguna.
         |
         | REPLACE dipilih karena ada di MySQL maupun SQLite. Fungsi regex
         | seperti REGEXP_REPLACE tidak ada di SQLite bawaan, dan memakainya
         | berarti halaman login mati saat diuji.
         |
         | Konsekuensi yang diterima: ekspresi ini membuat indeks tidak
         | terpakai, jadi query-nya memindai seluruh tabel users. Untuk
         | sekolah dengan ratusan akun itu tidak terasa; kalau suatu saat
         | jumlahnya puluhan ribu, jalan keluarnya adalah kolom no_hp yang
         | sudah dibakukan saat disimpan, bukan mempercepat query ini.
         */
        $bersih = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(no_hp, '-', ''), ' ', ''), '+', ''), '(', ''), ')', '')";

        $tanpaNol = substr($baku, 1);          // 08123... -> 8123...
        $kodeNegara = '62' . $tanpaNol;        // -> 628123...

        return User::query()
            ->whereNotNull('no_hp')
            ->where('no_hp', '!=', '')
            ->whereRaw($bersih . ' IN (?, ?, ?)', [$baku, $kodeNegara, $tanpaNol])
            ->get();
    }

    /** @return array{user: ?User, jenis: ?string, ganda: bool} */
    private function hasil(?User $user, ?string $jenis): array
    {
        return ['user' => $user, 'jenis' => $user ? $jenis : null, 'ganda' => false];
    }
}
