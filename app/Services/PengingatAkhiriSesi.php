<?php

namespace App\Services;

use App\Enums\Hari;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Models\AbsensiMengajar;
use App\Models\JadwalPelajaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Menentukan sesi mengajar mana yang BELUM diakhiri guru dan kapan guru
 * harus diingatkan.
 *
 * ============ ATURAN WAKTUNYA ============
 *   jam_selesai jadwal                -> KBM selesai
 *   jam_selesai + 5 menit  (PENGINGAT) -> guru diingatkan: bunyi + getar
 *   jam_selesai + 15 menit (BATAS)     -> layar Jurnal menutup; tombol
 *                                         "Akhiri Sesi" tidak bisa ditekan
 *
 * BATAS tidak ditulis ulang di sini: angkanya dipinjam dari
 * JurnalAbsenKelas::TOLERANSI_MENIT, karena itulah yang SEBENARNYA mengunci
 * layar guru. Kalau suatu saat toleransinya diubah, pengingat ini ikut
 * bergeser dengan sendirinya — tidak mungkin terjadi "diingatkan sisa 10
 * menit" padahal layarnya sudah terkunci.
 * =========================================
 *
 * Dipakai DUA tempat, dengan aturan yang sama:
 *   1. Perintah terjadwal `simagas:pengingat-akhiri-sesi` (tiap menit)
 *      -> push ke HP + lonceng, berbunyi walau aplikasi sedang ditutup.
 *   2. Partial partials/pengingat-akhiri-sesi di layout
 *      -> alarm di layar yang SEDANG dibuka guru (bunyi, getar, hitung mundur).
 */
class PengingatAkhiriSesi
{
    /** Menit sesudah jam selesai KBM ketika guru mulai diingatkan. */
    public const MENIT_PENGINGAT = 5;

    public function __construct(private readonly PencocokSesiMengajar $pencocok)
    {
    }

    /** Menit sesudah jam selesai ketika tombol "Akhiri Sesi" terkunci. */
    public static function menitBatas(): int
    {
        return JurnalAbsenKelas::TOLERANSI_MENIT;
    }

    /**
     * Seluruh sesi HARI INI yang masih terbuka dan batasnya belum lewat.
     *
     * Satu query jadwal + satu query sesi, berapa pun jumlah gurunya — tanpa
     * N+1. Pencocokan sesi <-> jadwal dikerjakan di memori lewat
     * PencocokSesiMengajar::pertamaDari(), aturan yang sama persis dengan
     * layar Jurnal guru.
     *
     * @param  int|null  $pegawaiId  batasi ke satu guru (dipakai layout);
     *                               null = semua guru (dipakai perintah).
     * @return Collection<int, array{sesi: AbsensiMengajar, jadwal: JadwalPelajaran, user_id: int, selesai: Carbon, pengingat: Carbon, batas: Carbon}>
     */
    public function sesiTerbuka(?int $pegawaiId = null, ?Carbon $sekarang = null): Collection
    {
        $sekarang = ($sekarang ?? now())->copy();

        $jadwalHariIni = JadwalPelajaran::query()
            ->with(['kelas:id,nama_kelas', 'guru:id,user_id'])
            ->where('hari', $this->hariDari($sekarang)->value)
            ->when($pegawaiId, fn ($q) => $q->where('guru_id', $pegawaiId))
            ->orderBy('jam_selesai')
            ->get()
            // Jadwal yang batasnya sudah lewat tidak perlu diproses sama sekali.
            ->filter(fn (JadwalPelajaran $j) => $j->jam_selesai !== null
                && $sekarang->lt($this->batasUntuk($j, $sekarang)))
            // Tanpa akun login, tidak ada siapa pun yang bisa diingatkan.
            ->filter(fn (JadwalPelajaran $j) => $j->guru?->user_id !== null)
            ->values();

        if ($jadwalHariIni->isEmpty()) {
            return collect();
        }

        // SATU query untuk semua sesi semua guru yang terlibat hari ini.
        $sesiPerUser = AbsensiMengajar::query()
            ->whereIn('user_id', $jadwalHariIni->pluck('guru.user_id')->unique()->values())
            ->whereDate('waktu_mulai', $sekarang->toDateString())
            ->orderBy('waktu_mulai')
            ->get()
            ->groupBy('user_id');

        $hasil = collect();
        $sudahDipakai = [];

        foreach ($jadwalHariIni as $jadwal) {
            $userId = (int) $jadwal->guru->user_id;
            $sesi = $this->pencocok->pertamaDari($sesiPerUser->get($userId, collect()), $jadwal);

            // Belum scan QR (tidak ada sesi) atau sudah diakhiri: tidak ada
            // yang perlu diingatkan. Satu sesi juga hanya diingatkan SEKALI
            // walau cocok untuk dua jadwal berurutan di ruangan yang sama.
            if (! $sesi || $sesi->sudahSelesai() || isset($sudahDipakai[$sesi->id])) {
                continue;
            }

            $sudahDipakai[$sesi->id] = true;
            $selesai = $this->jamPadaTanggal($jadwal->jam_selesai, $sekarang);

            $hasil->push([
                'sesi' => $sesi,
                'jadwal' => $jadwal,
                'user_id' => $userId,
                'selesai' => $selesai,
                'pengingat' => $selesai->copy()->addMinutes(self::MENIT_PENGINGAT),
                'batas' => $selesai->copy()->addMinutes(self::menitBatas()),
            ]);
        }

        return $hasil;
    }

    /**
     * Sesi yang SEKARANG sudah masuk jendela pengingat (+5 s.d. +15 menit)
     * dan belum pernah diingatkan lewat push/lonceng.
     *
     * Jendelanya berupa RENTANG, bukan satu menit tepat: kalau cron hosting
     * terlambat satu-dua menit, pengingatnya tetap terkirim selama tombol
     * "Akhiri Sesi" masih bisa ditekan.
     *
     * @return Collection<int, array{sesi: AbsensiMengajar, jadwal: JadwalPelajaran, user_id: int, selesai: Carbon, pengingat: Carbon, batas: Carbon}>
     */
    public function jatuhTempo(?Carbon $sekarang = null): Collection
    {
        $sekarang = ($sekarang ?? now())->copy();

        return $this->sesiTerbuka(null, $sekarang)
            ->filter(fn (array $b) => $sekarang->gte($b['pengingat'])
                && $b['sesi']->pengingat_akhiri_pada === null)
            ->values();
    }

    /** Kalimat pengingat — satu sumber untuk push, lonceng, dan layar. */
    public static function kalimat(JadwalPelajaran $jadwal, Carbon $batas): string
    {
        $kelas = $jadwal->kelas?->nama_kelas ?? '-';

        return "{$jadwal->mata_pelajaran} ({$kelas}) sudah selesai. Tekan \"Akhiri Sesi\" sebelum pukul "
            . $batas->format('H:i') . '.';
    }

    private function batasUntuk(JadwalPelajaran $jadwal, Carbon $sekarang): Carbon
    {
        return $this->jamPadaTanggal($jadwal->jam_selesai, $sekarang)->addMinutes(self::menitBatas());
    }

    /**
     * Tempelkan jam jadwal ke TANGGAL $sekarang.
     *
     * jam_selesai di-cast ke Carbon dengan tanggal hari ketika model dibaca;
     * memakai tanggalnya langsung akan salah begitu $sekarang dipalsukan di
     * pengujian atau perintahnya berjalan melewati tengah malam.
     */
    private function jamPadaTanggal(Carbon $jam, Carbon $sekarang): Carbon
    {
        return $sekarang->copy()->setTime($jam->hour, $jam->minute, 0);
    }

    /** Hari dari $sekarang — bukan dari now(), supaya bisa diuji. */
    private function hariDari(Carbon $sekarang): Hari
    {
        return match ($sekarang->dayOfWeek) {
            0 => Hari::Minggu,
            1 => Hari::Senin,
            2 => Hari::Selasa,
            3 => Hari::Rabu,
            4 => Hari::Kamis,
            5 => Hari::Jumat,
            default => Hari::Sabtu,
        };
    }
}
