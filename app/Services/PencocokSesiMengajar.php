<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\JadwalPelajaran;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Mencocokkan catatan Absen Mengajar (scan QR ruangan) dengan satu jadwal.
 *
 * ============ KENAPA INI DIANGKAT JADI SERVICE ============
 * Logika ini semula tinggal sebagai method PRIVAT di
 * App\Livewire\Guru\JurnalAbsenKelas, dan itu cukup selama hanya guru yang
 * memerlukannya.
 *
 * Begitu layar Live Monitoring ikut menampilkan "sudah diakhiri / belum",
 * jawabannya harus datang dari SUMBER YANG SAMA. Kalau logikanya disalin,
 * cepat atau lambat keduanya berbeda — dan bentuk perbedaannya sangat
 * menyesatkan: kepala sekolah melihat "belum diakhiri" sementara gurunya
 * melihat sesinya sudah terkunci. Tidak ada satu pun error yang muncul, dan
 * keduanya sama-sama yakin layarnya benar.
 * ==========================================================
 *
 * ============ KENAPA PENCOCOKANNYA TIDAK SESEDERHANA jadwal_id ============
 * `absensi_mengajar` TIDAK menyimpan jadwal_id. Yang tersimpan adalah
 * `kode_kelas` — teks yang terbaca dari stiker QR yang ditempel di ruangan.
 * Stiker itu menempel pada RUANGAN, bukan pada jadwal, karena satu ruangan
 * dipakai banyak jadwal sepanjang hari.
 *
 * Maka pencocokannya lewat teks, dan teks dari dunia nyata tidak pernah
 * rapi: "Ruang XI-RPL 1", "XI RPL 1", dan "xi_rpl_1" adalah ruangan yang
 * sama. normalkan() yang menyamakan ketiganya.
 * =========================================================================
 */
class PencocokSesiMengajar
{
    /**
     * Menyamakan bentuk penulisan kode ruangan.
     *
     * Empat langkahnya berurutan dan tidak boleh ditukar:
     *   1. buang awalan "Ruang"/"ruang-"/"RUANG_"  -> "XI-RPL 1"
     *   2. ubah "-" dan "_" jadi spasi              -> "XI RPL 1"
     *   3. rapikan spasi berlebih                   -> "XI RPL 1"
     *   4. besarkan semua huruf                     -> "XI RPL 1"
     *
     * Langkah 1 harus lebih dulu dari 2: kalau dibalik, "Ruang-XI" sudah jadi
     * "Ruang XI" dan polanya tetap memotongnya — kebetulan benar, tapi hanya
     * karena polanya ikut menerima spasi. Urutan ini yang membuatnya benar
     * karena sebab, bukan karena kebetulan.
     *
     * ============ JANGAN MENULIS POLA REGEX-NYA DI KOMENTAR INI ============
     * Versi pertama komentar ini mengutip polanya apa adanya. Di dalamnya ada
     * urutan bintang-garis-miring, dan urutan itu MENUTUP blok komentar lebih
     * awal — sisa kalimatnya lalu terbaca PHP sebagai kode.
     *
     * Galatnya sama sekali tidak menunjuk ke komentar:
     *   syntax error, unexpected identifier "i", expecting "function"
     *
     * Lebih buruk lagi, berkas ini hanya dimuat saat ada jam pelajaran yang
     * BENAR-BENAR berjalan — jadi seluruh uji tetap hijau saat dijalankan
     * malam hari, dan kerusakannya baru muncul di depan kelas pukul tujuh
     * pagi. Penjaganya sekarang: tests/Feature/SintaksPhpTest.
     * =======================================================================
     */
    public static function normalkan(?string $teks): ?string
    {
        if ($teks === null || trim($teks) === '') {
            return null;
        }

        return Str::of($teks)
            ->replaceMatches('/^ruang[-_ ]*/i', '')
            ->replace(['-', '_'], ' ')
            ->squish()
            ->upper()
            ->value();
    }

    /**
     * Catatan Absen Mengajar milik $userId pada $tanggal yang kode
     * ruangannya cocok dengan $jadwal — atau null kalau belum ada.
     *
     * @param  int|null  $userId  akun GURU-nya (users.id), bukan pegawai.id
     */
    public function untukJadwal(JadwalPelajaran $jadwal, ?int $userId, $tanggal = null): ?AbsensiMengajar
    {
        if (! $userId) {
            return null;
        }

        $sasaran = self::sasaran($jadwal);

        if (! $sasaran) {
            return null;
        }

        /*
         | Disaring di PHP, bukan di SQL, dan itu disengaja.
         |
         | Pencocokannya butuh normalkan() — regex + squish + upper — yang
         | tidak bisa ditulis sebagai WHERE yang berperilaku sama di MySQL
         | dan SQLite sekaligus. Bebannya tetap kecil: yang diambil hanya
         | sesi SATU guru pada SATU hari, jarang lebih dari sepuluh baris.
         */
        return AbsensiMengajar::query()
            ->where('user_id', $userId)
            ->whereDate('waktu_mulai', $tanggal ?: today())
            ->orderBy('waktu_mulai')
            ->get()
            ->first(fn (AbsensiMengajar $a) => in_array(self::normalkan($a->kode_kelas), $sasaran, true));
    }

    /**
     * Kode ruangan yang dianggap cocok untuk $jadwal, sudah dinormalkan.
     *
     * Dua sasaran, karena stiker QR bisa ditulis mengikuti NAMA KELAS
     * ("XI RPL 1") maupun NAMA RUANGAN ("Lab Komputer 2"). Sekolah ini
     * memakai keduanya bergantian, dan menuntut salah satu saja akan
     * membuat separuh sesi tidak pernah cocok.
     *
     * Dipisah jadi method sendiri supaya untukJadwal() dan pilihDari()
     * memakai aturan yang PERSIS sama — lihat catatan di atas kelas.
     *
     * @return array<int, string>
     */
    public static function sasaran(JadwalPelajaran $jadwal): array
    {
        return array_values(array_filter([
            self::normalkan($jadwal->kelas?->nama_kelas),
            self::normalkan($jadwal->ruangan),
        ]));
    }

    /**
     * Versi MASSAL untuk lembar cetak: memilih satu sesi dari kumpulan sesi
     * yang SUDAH diambil pemanggil (satu guru, satu hari).
     *
     * ============ BEDANYA DENGAN untukJadwal() ============
     * 1. Tanpa query. Lembar paraf memuat puluhan jadwal sekaligus; memanggil
     *    untukJadwal() per baris berarti satu query per baris (N+1). Di sini
     *    pemanggil mengambil semua sesi hari itu dalam SATU query, lalu
     *    method ini hanya menyaring di memori.
     *
     * 2. Memilih sesi yang jam mulainya PALING DEKAT dengan jadwal, dan
     *    melewati sesi yang sudah dipasangkan ke jadwal lain ($sudahDipakai).
     *    Tanpa ini, guru yang mengajar kelas yang sama dua kali sehari
     *    (jam 1-2 dan jam 5-6) akan tercetak dengan jam scan PAGI di kedua
     *    barisnya — dan lembar cadangan yang dibuat untuk membuktikan
     *    kejadian justru mencetak keterangan yang keliru.
     *
     * untukJadwal() sengaja TIDAK diubah: perilakunya dipakai layar jurnal
     * guru untuk mengunci sesi, dan mengubahnya bukan bagian dari fitur ini.
     * ======================================================
     *
     * @param  Collection<int, AbsensiMengajar>  $sesiGuru
     * @param  array<int, int>  $sudahDipakai  id sesi yang sudah dipasangkan
     */
    public function pilihDari(Collection $sesiGuru, JadwalPelajaran $jadwal, array $sudahDipakai = []): ?AbsensiMengajar
    {
        $sasaran = self::sasaran($jadwal);

        if (! $sasaran || $sesiGuru->isEmpty() || ! $jadwal->jam_mulai) {
            return null;
        }

        // Jam dibandingkan dalam "menit sejak tengah malam", bukan sebagai
        // tanggal-waktu: jam_mulai jadwal di-cast ke Carbon HARI INI,
        // sedangkan waktu_mulai sesi membawa tanggal aslinya. Membandingkan
        // keduanya langsung akan selalu berselisih sekian hari.
        $menitJadwal = $jadwal->jam_mulai->hour * 60 + $jadwal->jam_mulai->minute;

        return $sesiGuru
            ->reject(fn (AbsensiMengajar $a) => in_array($a->id, $sudahDipakai, true))
            ->filter(fn (AbsensiMengajar $a) => in_array(self::normalkan($a->kode_kelas), $sasaran, true))
            ->sortBy(fn (AbsensiMengajar $a) => abs(($a->waktu_mulai->hour * 60 + $a->waktu_mulai->minute) - $menitJadwal))
            ->first();
    }
}
