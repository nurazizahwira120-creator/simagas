<?php

namespace App\Services;

use App\Enums\Hari;
use App\Models\AgendaAkademik;
use App\Models\Pengaturan;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat yang menjawab "apakah tanggal ini hari KBM?".
 *
 * ============ KENAPA HARUS SATU TEMPAT ============
 * Jawabannya butuh DUA sumber yang sama sekali berbeda bentuknya:
 *
 *   1. Pola libur MINGGUAN — berulang selamanya, tersimpan sebagai satu
 *      string di Pengaturan Sistem ('hari_kbm'). Di sekolah ini: Jumat
 *      libur, Minggu justru masuk.
 *
 *   2. Agenda BERTANGGAL — kalender pendidikan Dinas & libur khusus
 *      sekolah, tersimpan sebagai rentang di tabel agenda_akademik.
 *
 * Kalau penggabungan keduanya ditulis ulang di setiap halaman, cepat atau
 * lambat dua halaman akan menjawab berbeda untuk tanggal yang sama — dan
 * yang paling mungkin berbeda justru penanda alpa otomatis dengan laporan
 * bulanan, dua hal yang paling mahal kalau tidak sinkron.
 * ==================================================
 *
 * ============ KENAPA ADA CACHE DI DALAM OBJEK ============
 * Menghitung hari efektif satu bulan memanggil adalahHariKbm() 30 kali.
 * Tanpa cache itu 30 query yang isinya nyaris sama. Cache-nya sengaja
 * hanya seumur objek (bukan Cache::remember), supaya perubahan pengaturan
 * langsung terasa di permintaan berikutnya tanpa perlu ada yang ingat
 * membersihkan cache.
 * =========================================================
 */
class KalenderAkademik
{
    /** Hari KBM bawaan kalau pengaturannya belum pernah diisi. */
    public const HARI_KBM_BAWAAN = 'senin,selasa,rabu,kamis,sabtu,minggu';

    public const JAM_PULANG_BAWAAN = '11:30';

    /** @var array<int, int>|null Carbon dayOfWeek (0=Minggu) yang masuk KBM */
    private ?array $hariKbm = null;

    /** @var array<string, Collection<int, AgendaAkademik>> cache per bulan */
    private array $agendaPerBulan = [];

    /* =================================================================
     * PENGATURAN
     * ================================================================= */

    /**
     * Hari apa saja yang berstatus hari KBM, sebagai nomor hari Carbon.
     *
     * @return array<int, int> 0=Minggu … 6=Sabtu
     */
    public function nomorHariKbm(): array
    {
        if ($this->hariKbm !== null) {
            return $this->hariKbm;
        }

        $tersimpan = (string) Pengaturan::ambil('hari_kbm', self::HARI_KBM_BAWAAN);

        $nomor = collect(explode(',', $tersimpan))
            ->map(fn (string $h) => trim(strtolower($h)))
            ->filter()
            ->map(fn (string $h) => Hari::tryFrom($h))
            ->filter()
            ->map(fn (Hari $h) => self::nomorCarbon($h))
            ->unique()
            ->values()
            ->all();

        /*
         | Jaring pengaman: pengaturan kosong atau berisi sampah TIDAK boleh
         | menghasilkan "tidak ada hari KBM sama sekali".
         |
         | Akibatnya kalau dibiarkan sangat buruk dan sangat senyap: penanda
         | alpa otomatis berhenti bekerja tanpa error apa pun, dan penyebut
         | persentase kehadiran jadi nol sehingga seluruh laporan bulanan
         | menampilkan tanda strip. Tidak ada satu pun pesan yang menjelaskan
         | kenapa. Lebih baik kembali ke bawaan.
         */
        if ($nomor === []) {
            $nomor = collect(explode(',', self::HARI_KBM_BAWAAN))
                ->map(fn (string $h) => self::nomorCarbon(Hari::from(trim($h))))
                ->all();
        }

        return $this->hariKbm = $nomor;
    }

    /** @return array<int, Hari> daftar hari KBM sebagai enum, urut Senin→Minggu */
    public function hariKbm(): array
    {
        $nomor = $this->nomorHariKbm();

        return collect(Hari::cases())
            ->filter(fn (Hari $h) => in_array(self::nomorCarbon($h), $nomor, true))
            ->sortBy(fn (Hari $h) => $h->urutan())
            ->values()
            ->all();
    }

    /** @return array<int, Hari> kebalikannya: hari yang libur setiap minggu */
    public function hariLiburMingguan(): array
    {
        $nomor = $this->nomorHariKbm();

        return collect(Hari::cases())
            ->reject(fn (Hari $h) => in_array(self::nomorCarbon($h), $nomor, true))
            ->sortBy(fn (Hari $h) => $h->urutan())
            ->values()
            ->all();
    }

    /** Jam pulang sekolah sebagai teks 'H:i'. */
    public function jamPulangTeks(): string
    {
        $nilai = (string) Pengaturan::ambil('jam_pulang_siswa', self::JAM_PULANG_BAWAAN);

        // Nilai rusak (pernah tersimpan sebelum validasinya ada, atau diubah
        // langsung lewat database) tidak boleh membuat penanda alpa melempar
        // exception di tengah cron yang tidak ada yang menontonnya.
        return preg_match('/^\d{1,2}:\d{2}$/', $nilai) ? $nilai : self::JAM_PULANG_BAWAAN;
    }

    /** Jam pulang pada tanggal tertentu, sebagai Carbon lengkap. */
    public function jamPulangPada(?CarbonInterface $tanggal = null): Carbon
    {
        $tanggal = $tanggal ? Carbon::parse($tanggal) : today();

        return $tanggal->copy()->setTimeFromTimeString($this->jamPulangTeks() . ':00');
    }

    /** Jam pulang hari ini sudah lewat? */
    public function sudahLewatJamPulang(?CarbonInterface $sekarang = null): bool
    {
        $sekarang = $sekarang ? Carbon::parse($sekarang) : now();

        return $sekarang->greaterThanOrEqualTo($this->jamPulangPada($sekarang));
    }

    /* =================================================================
     * PERTANYAAN INTI
     * ================================================================= */

    public function adalahHariKbm(CarbonInterface $tanggal): bool
    {
        return $this->alasanBukanKbm($tanggal) === null;
    }

    /**
     * Kenapa tanggal ini BUKAN hari KBM — atau null kalau memang hari KBM.
     *
     * Mengembalikan ALASAN, bukan sekadar true/false, dan itu disengaja.
     * Layar kepala sekolah pada hari Jumat harus bisa berkata "Hari ini
     * libur KBM (Jumat)" alih-alih menampilkan seluruh siswa sebagai tidak
     * hadir tanpa penjelasan — dan halaman yang hanya menerima boolean tidak
     * punya cara mengarang kalimat itu.
     */
    public function alasanBukanKbm(CarbonInterface $tanggal): ?string
    {
        $tanggal = Carbon::parse($tanggal);

        // 1. Pola mingguan diperiksa LEBIH DULU karena tidak butuh query.
        if (! in_array($tanggal->dayOfWeek, $this->nomorHariKbm(), true)) {
            return 'Libur mingguan (' . self::labelHari($tanggal) . ')';
        }

        // 2. Agenda bertanggal.
        $libur = $this->agendaBulan($tanggal)
            ->first(fn (AgendaAkademik $a) => $a->jenis->meliburkan()
                && $tanggal->betweenIncluded($a->tanggal_mulai, $a->tanggal_selesai));

        return $libur?->judul;
    }

    /**
     * Jumlah hari KBM dalam satu rentang, inklusif di kedua ujung.
     *
     * Inilah penyebut yang benar untuk persentase kehadiran. Sebelumnya
     * laporan bulanan memakai "semua hari kecuali Minggu", yang di sekolah
     * ini salah dua kali sekaligus: Minggu justru hari masuk, dan Jumat
     * justru libur.
     */
    public function jumlahHariKbm(CarbonInterface $awal, CarbonInterface $akhir): int
    {
        $jalan = Carbon::parse($awal)->startOfDay();
        $henti = Carbon::parse($akhir)->startOfDay();
        $jumlah = 0;

        // copy() di setiap langkah: Carbon bersifat mutable, dan addDay()
        // langsung pada objek pemanggil akan menggeser tanggal milik si
        // pemanggil — bug yang baru terlihat jauh dari tempat asalnya.
        while ($jalan->lessThanOrEqualTo($henti)) {
            if ($this->adalahHariKbm($jalan)) {
                $jumlah++;
            }

            $jalan = $jalan->copy()->addDay();
        }

        return $jumlah;
    }

    /**
     * Seluruh agenda yang menyentuh satu bulan — sekali query, lalu diingat.
     *
     * @return Collection<int, AgendaAkademik>
     */
    public function agendaBulan(CarbonInterface $tanggal): Collection
    {
        $bulan = Carbon::parse($tanggal)->startOfMonth();
        $kunci = $bulan->format('Y-m');

        if (isset($this->agendaPerBulan[$kunci])) {
            return $this->agendaPerBulan[$kunci];
        }

        return $this->agendaPerBulan[$kunci] = AgendaAkademik::query()
            ->menyentuh($bulan->toDateString(), $bulan->copy()->endOfMonth()->toDateString())
            ->orderBy('tanggal_mulai')
            ->get();
    }

    /**
     * Agenda yang berlaku pada satu tanggal (semua jenis).
     *
     * @return Collection<int, AgendaAkademik>
     */
    public function agendaPada(CarbonInterface $tanggal): Collection
    {
        $tanggal = Carbon::parse($tanggal);

        return $this->agendaBulan($tanggal)
            ->filter(fn (AgendaAkademik $a) => $tanggal->betweenIncluded($a->tanggal_mulai, $a->tanggal_selesai))
            ->values();
    }

    /* =================================================================
     * BANTU
     * ================================================================= */

    /**
     * Enum Hari -> nomor hari Carbon.
     *
     * Carbon memakai 0 untuk MINGGU, bukan Senin. Salah di titik ini
     * menggeser seluruh kalender satu hari, dan gejalanya sangat
     * membingungkan: liburnya benar jumlahnya, tapi jatuh di hari yang salah.
     */
    public static function nomorCarbon(Hari $hari): int
    {
        return match ($hari) {
            Hari::Minggu => CarbonInterface::SUNDAY,
            Hari::Senin => CarbonInterface::MONDAY,
            Hari::Selasa => CarbonInterface::TUESDAY,
            Hari::Rabu => CarbonInterface::WEDNESDAY,
            Hari::Kamis => CarbonInterface::THURSDAY,
            Hari::Jumat => CarbonInterface::FRIDAY,
            Hari::Sabtu => CarbonInterface::SATURDAY,
        };
    }

    /** Nomor hari Carbon -> enum Hari. */
    public static function hariDari(CarbonInterface $tanggal): Hari
    {
        return match ($tanggal->dayOfWeek) {
            CarbonInterface::SUNDAY => Hari::Minggu,
            CarbonInterface::MONDAY => Hari::Senin,
            CarbonInterface::TUESDAY => Hari::Selasa,
            CarbonInterface::WEDNESDAY => Hari::Rabu,
            CarbonInterface::THURSDAY => Hari::Kamis,
            CarbonInterface::FRIDAY => Hari::Jumat,
            default => Hari::Sabtu,
        };
    }

    private static function labelHari(CarbonInterface $tanggal): string
    {
        return self::hariDari($tanggal)->label();
    }
}
