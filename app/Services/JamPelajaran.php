<?php

namespace App\Services;

use App\Models\JadwalPelajaran;
use App\Models\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Satuan JAM PELAJARAN (JP) — dasar seluruh hitungan beban mengajar guru.
 *
 * ============ APA ITU 1 JP DI SINI ============
 * Satu JP = sekian menit tatap muka, dan "sekian" itu DIATUR SEKOLAH, bukan
 * ditanam di kode. Bawaannya 35 menit; Super Admin bisa mengubahnya di
 * Pengaturan Sistem -> tab Aturan Absensi bila jadwal berubah (mis. jam
 * pelajaran dipersingkat saat Ramadan).
 *
 * Jumlah JP sebuah jadwal TIDAK disimpan di tabel jadwal_pelajaran. Ia
 * DIHITUNG dari panjang jadwalnya dibagi durasi 1 JP. Menyimpannya sebagai
 * kolom berarti dua sumber kebenaran: begitu jam selesai jadwal diubah atau
 * durasi JP diganti, angka yang tersimpan diam-diam salah dan tidak ada
 * satu pun yang memberi tahu.
 * =============================================
 *
 * ============ ATURAN PEMBULATAN ============
 * JP = menit jadwal ÷ durasi 1 JP, dibulatkan ke bilangan bulat TERDEKAT,
 * minimal 1 untuk jadwal yang panjangnya lebih dari nol.
 *
 *   70 menit  ÷ 35 = 2,00 -> 2 JP
 *   105 menit ÷ 35 = 3,00 -> 3 JP
 *   75 menit  ÷ 35 = 2,14 -> 2 JP   (lebih 5 menit: pergantian kelas)
 *   90 menit  ÷ 35 = 2,57 -> 3 JP   (jadwal lama yang belum disesuaikan)
 *   20 menit  ÷ 35 = 0,57 -> 1 JP   (jadwal pendek tetap dihitung)
 *
 * Dibulatkan ke terdekat, BUKAN ke bawah: jadwal yang kelebihan beberapa
 * menit untuk perpindahan kelas tidak boleh kehilangan satu JP utuh.
 * ===========================================
 *
 * Nilai durasi dibaca SEKALI per objek, jadi satu laporan yang menghitung
 * ratusan jadwal hanya menjalankan satu query pengaturan. Objeknya sengaja
 * TIDAK dijadikan singleton: nilai yang baru disimpan admin harus langsung
 * terbaca pada permintaan berikutnya, bukan tertahan di memori.
 */
class JamPelajaran
{
    /** Kunci di tabel `pengaturan`. */
    public const KUNCI = 'durasi_jp';

    /** Durasi bawaan 1 JP (menit), dipakai bila pengaturannya belum diisi. */
    public const BAWAAN = 35;

    /**
     * Rentang yang diterima. Di luar rentang ini hampir pasti salah ketik
     * (mis. "350" atau "3,5"), dan menerima angka seperti itu akan membuat
     * seluruh laporan beban mengajar menyimpang tanpa tanda apa pun.
     */
    public const MIN = 20;

    public const MAKS = 90;

    private ?int $durasi = null;

    /** Durasi 1 JP dalam menit, selalu di dalam rentang MIN..MAKS. */
    public function durasiMenit(): int
    {
        if ($this->durasi !== null) {
            return $this->durasi;
        }

        // Tabel yang belum ada (server baru sebelum migrate) tidak boleh
        // menjatuhkan halaman yang hanya ingin menampilkan jumlah JP.
        try {
            $tersimpan = Pengaturan::ambil(self::KUNCI);
        } catch (\Throwable) {
            $tersimpan = null;
        }

        $nilai = filter_var($tersimpan, FILTER_VALIDATE_INT);

        return $this->durasi = ($nilai !== false && $nilai >= self::MIN && $nilai <= self::MAKS)
            ? $nilai
            : self::BAWAAN;
    }

    /** Jumlah JP untuk rentang menit tertentu. */
    public function dariMenit(int $menit): int
    {
        if ($menit <= 0) {
            return 0;
        }

        return max(1, (int) round($menit / $this->durasiMenit()));
    }

    /**
     * Jumlah JP antara dua jam ("07:00" & "08:10", atau Carbon).
     * Hanya jam & menitnya yang dipakai; tanggalnya diabaikan.
     */
    public function antara(CarbonInterface|string|null $mulai, CarbonInterface|string|null $selesai): int
    {
        if (blank($mulai) || blank($selesai)) {
            return 0;
        }

        return $this->dariMenit($this->menitDalamHari($selesai) - $this->menitDalamHari($mulai));
    }

    public function untukJadwal(JadwalPelajaran $jadwal): int
    {
        return $this->antara($jadwal->jam_mulai, $jadwal->jam_selesai);
    }

    /** Panjang jadwal dalam menit. */
    public function menitJadwal(JadwalPelajaran $jadwal): int
    {
        if (! $jadwal->jam_mulai || ! $jadwal->jam_selesai) {
            return 0;
        }

        return max(0, $this->menitDalamHari($jadwal->jam_selesai) - $this->menitDalamHari($jadwal->jam_mulai));
    }

    /**
     * Apakah panjang jadwal pas kelipatan durasi JP.
     *
     * Dipakai untuk memberi tanda di layar jadwal, BUKAN untuk menolak:
     * jadwal yang belum disesuaikan tetap sah dan tetap dihitung.
     */
    public function pas(JadwalPelajaran $jadwal): bool
    {
        $menit = $this->menitJadwal($jadwal);

        return $menit > 0 && $menit % $this->durasiMenit() === 0;
    }

    /**
     * Menit sejak tengah malam.
     *
     * Kolom jam di-cast ke Carbon HARI INI, sedangkan pembanding lain bisa
     * membawa tanggal berbeda — membandingkan objek Carbon-nya langsung akan
     * berselisih sekian hari. Mengambil jam & menitnya saja menghindari itu.
     */
    private function menitDalamHari(CarbonInterface|string $jam): int
    {
        $c = $jam instanceof CarbonInterface ? $jam : Carbon::parse($jam);

        return $c->hour * 60 + $c->minute;
    }
}
