<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\JadwalPelajaran;
use App\Models\Pegawai;
use Illuminate\Support\Carbon;
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

        $tanggal = $tanggal ? Carbon::parse($tanggal)->startOfDay() : today();

        /*
         | Disaring di PHP, bukan di SQL, dan itu disengaja.
         |
         | Pencocokannya butuh normalkan() — regex + squish + upper — yang
         | tidak bisa ditulis sebagai WHERE yang berperilaku sama di MySQL
         | dan SQLite sekaligus. Bebannya tetap kecil: yang diambil hanya
         | sesi SATU guru pada SATU hari, jarang lebih dari sepuluh baris.
         */
        $sesiGuru = AbsensiMengajar::query()
            ->where('user_id', $userId)
            ->wherePadaTanggal('waktu_mulai', $tanggal)
            ->orderBy('waktu_mulai')
            ->get();

        if ($sesiGuru->isEmpty()) {
            return null;
        }

        /*
         | Sesi dipasangkan dengan SELURUH jadwal guru itu pada hari yang
         | sama — bukan hanya jadwal ini — lewat pasangkan(). Tanpa itu, guru
         | yang mengajar ruangan yang sama dua kali sehari (jam 1-2 dan jam
         | 5-6) akan melihat sesi PAGI-nya (sudah diakhiri) di jadwal siang,
         | sehingga jurnal siangnya terkunci padahal ia baru saja scan.
         |
         | Jadwal yang sedang dibuka ikut dimasukkan walau bukan miliknya
         | (guru inval mengisi kelas guru lain).
         */
        $jadwalGuru = JadwalPelajaran::query()
            ->with('kelas:id,nama_kelas')
            ->where('hari', AturanSesiEkskul::hariDari($tanggal)->value)
            ->whereIn('guru_id', Pegawai::query()->where('user_id', $userId)->select('id'))
            ->get(['id', 'hari', 'jam_mulai', 'jam_selesai', 'ruangan', 'kelas_id', 'guru_id']);

        if (! $jadwalGuru->contains('id', $jadwal->id)) {
            $jadwalGuru->push($jadwal);
        }

        return $this->pasangkan($jadwalGuru, $sesiGuru)[$jadwal->id] ?? null;
    }

    /**
     * Memasangkan sesi-sesi SATU guru pada SATU hari dengan jadwal-jadwalnya.
     *
     * Aturannya: dari semua pasangan (jadwal, sesi) yang ruangannya cocok,
     * pasangan dengan selisih jam scan ↔ jam mulai jadwal TERKECIL dipasang
     * lebih dulu; satu sesi hanya untuk satu jadwal dan sebaliknya.
     *
     * Contoh — Budi mengajar XI RPL 1 pukul 07.00 dan 12.00, tetapi hanya
     * scan pukul 12.01: sesi itu milik jadwal 12.00 (selisih 1 menit), bukan
     * jadwal 07.00 (selisih 5 jam). Memilih per jadwal secara berurutan akan
     * memberikannya ke jadwal 07.00 hanya karena jadwal itu diproses duluan.
     *
     * Dipakai layar Jurnal guru (lewat untukJadwal), panel detail dan layar
     * Monitoring KBM, serta pengingat Akhiri Sesi — sehingga semuanya selalu
     * membicarakan sesi yang SAMA untuk jadwal yang sama.
     *
     * @param  Collection<int, JadwalPelajaran>  $jadwalGuru
     * @param  Collection<int, AbsensiMengajar>  $sesiGuru
     * @return array<int, AbsensiMengajar> kunci = id jadwal (yang tak berpasangan tidak ada)
     */
    public function pasangkan(Collection $jadwalGuru, Collection $sesiGuru): array
    {
        $menit = fn (\DateTimeInterface $w): int => (int) $w->format('G') * 60 + (int) $w->format('i');

        $kodeSesi = $sesiGuru->mapWithKeys(fn (AbsensiMengajar $s) => [$s->id => self::normalkan($s->kode_kelas)]);
        $kandidat = [];

        foreach ($jadwalGuru as $j) {
            $sasaran = self::sasaran($j);

            if (! $sasaran || ! $j->jam_mulai) {
                continue;
            }

            foreach ($sesiGuru as $s) {
                if ($s->waktu_mulai && in_array($kodeSesi[$s->id], $sasaran, true)) {
                    $kandidat[] = [
                        abs($menit($s->waktu_mulai) - $menit($j->jam_mulai)),
                        $menit($j->jam_mulai),
                        $s->waktu_mulai->getTimestamp(),
                        $j->id,
                        $s,
                    ];
                }
            }
        }

        // Urutan tetap (deterministik): selisih, lalu jam jadwal, jam scan, id.
        usort($kandidat, fn ($a, $b) => [$a[0], $a[1], $a[2], $a[3]] <=> [$b[0], $b[1], $b[2], $b[3]]);

        $hasil = [];
        $dipakai = [];

        foreach ($kandidat as [, , , $jadwalId, $sesi]) {
            if (isset($hasil[$jadwalId]) || isset($dipakai[$sesi->id])) {
                continue;
            }

            $hasil[$jadwalId] = $sesi;
            $dipakai[$sesi->id] = true;
        }

        return $hasil;
    }

    /**
     * Kode ruangan yang dianggap cocok untuk $jadwal, sudah dinormalkan.
     *
     * Dua sasaran, karena stiker QR bisa ditulis mengikuti NAMA KELAS
     * ("XI RPL 1") maupun NAMA RUANGAN ("Lab Komputer 2"). Sekolah ini
     * memakai keduanya bergantian, dan menuntut salah satu saja akan
     * membuat separuh sesi tidak pernah cocok.
     *
     * Dipisah jadi method sendiri supaya untukJadwal() dan pasangkan()
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
}
