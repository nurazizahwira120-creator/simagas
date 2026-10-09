<?php

namespace App\Services;

use App\Enums\Hari;
use App\Livewire\Guru\JurnalAbsenKelas;
use App\Models\AbsensiEkskul;
use App\Models\JadwalEkskul;
use App\Models\Pegawai;
use App\Models\SesiEkskul;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * ATURAN SESI EKSKUL — Mulai/Akhiri Sesi pembina ekstrakurikuler, dibuat
 * SAMA dengan sesi KBM supaya guru tidak perlu mempelajari aturan baru.
 *
 * ============ ATURAN WAKTUNYA (sama dengan KBM) ============
 *   jam_mulai − 15 menit   -> scan QR ekskul sudah diterima (Mulai Sesi)
 *   jam_selesai            -> kegiatan selesai
 *   jam_selesai + 5 menit  -> pembina diingatkan (bunyi + getar + push)
 *   jam_selesai + 15 menit -> BATAS: "Akhiri Sesi" tidak bisa ditekan lagi
 * Angka 15 dan 5 dipinjam dari JurnalAbsenKelas & PengingatAkhiriSesi,
 * tidak ditulis ulang — kalau aturan KBM diubah, ekskul ikut bergeser.
 * ============================================================
 *
 * ============ SYARAT MULAI ============
 *  - yang men-scan adalah PEMBINA ekskul itu (pegawai yang tertaut akun)
 *  - hari ini memang hari ekskulnya
 *  - bukan hari libur di Kalender Pendidikan (agenda jenis Libur)
 *  - masih di dalam jendela waktu di atas
 *  - kode yang di-scan = kode QR ekskul itu (bukan ekskul lain)
 * Tidak mensyaratkan Absen Kehadiran pagi: ekskul sering berjalan sore
 * atau di hari tanpa KBM. Bukti hadirnya adalah scan QR + foto.
 * ======================================
 *
 * ============ SYARAT AKHIRI ============
 *  - sesi belum diakhiri dan batas belum lewat
 *  - absensi anggota HARI INI sudah disimpan
 *  - foto bukti sudah diunggah
 * =======================================
 */
class AturanSesiEkskul
{
    public function __construct(private readonly KalenderAkademik $kalender)
    {
    }

    public static function toleransiMenit(): int
    {
        return JurnalAbsenKelas::TOLERANSI_MENIT;
    }

    /* ===================== WAKTU & HARI ===================== */

    public function hariCocok(JadwalEkskul $jadwal, Carbon $tanggal): bool
    {
        return Str::of((string) $jadwal->hari)->trim()->lower()->value() === self::hariDari($tanggal)->value;
    }

    /**
     * Semua titik waktu sesi pada tanggal $tanggal.
     *
     * @return array{buka: Carbon, mulai: Carbon, selesai: Carbon, pengingat: Carbon, batas: Carbon}
     */
    public function waktu(JadwalEkskul $jadwal, Carbon $tanggal): array
    {
        $mulai = $this->jamPada((string) $jadwal->jam_mulai, $tanggal);
        $selesai = $this->jamPada((string) $jadwal->jam_selesai, $tanggal);

        return [
            'buka' => $mulai->copy()->subMinutes(self::toleransiMenit()),
            'mulai' => $mulai,
            'selesai' => $selesai,
            'pengingat' => $selesai->copy()->addMinutes(PengingatAkhiriSesi::MENIT_PENGINGAT),
            'batas' => $selesai->copy()->addMinutes(self::toleransiMenit()),
        ];
    }

    /** Judul agenda libur pada tanggal itu, atau null. Pola libur MINGGUAN sengaja diabaikan. */
    public function libur(Carbon $tanggal): ?string
    {
        // startOfDay WAJIB: agendaPada() membandingkan dengan tanggal_mulai/
        // selesai pukul 00.00, jadi "hari ini pukul 14.00" dianggap sudah
        // melewati agenda satu hari dan liburnya tidak terbaca.
        return $this->kalender->agendaPada($tanggal->copy()->startOfDay())
            ->first(fn ($a) => $a->jenis->meliburkan())?->judul;
    }

    /**
     * Akun pembina. Dibaca langsung dari tabel pegawai (bukan relasi yang
     * mungkin sudah dimuat TANPA kolom user_id oleh pemanggil).
     */
    public function pembinaUserId(JadwalEkskul $jadwal): ?int
    {
        if (! $jadwal->pembina_id) {
            return null;
        }

        $id = Pegawai::query()->whereKey($jadwal->pembina_id)->value('user_id');

        return $id ? (int) $id : null;
    }

    /* ===================== MULAI ===================== */

    /** null = boleh mulai; selain itu kalimat alasan penolakannya. */
    public function alasanTidakBisaMulai(JadwalEkskul $jadwal, ?User $user, Carbon $sekarang): ?string
    {
        $pembina = $this->pembinaUserId($jadwal);

        if (! $pembina) {
            return 'Ekskul ini belum punya pembina yang tertaut ke akun. Minta admin memilih pembinanya di Jadwal Ekskul.';
        }

        if (! $user || $user->id !== $pembina) {
            return 'Hanya pembina ekskul ini yang bisa memulai sesinya.';
        }

        if (! $this->hariCocok($jadwal, $sekarang)) {
            return "{$jadwal->nama_ekskul} dijadwalkan setiap {$jadwal->hari}, bukan hari ini.";
        }

        if ($libur = $this->libur($sekarang)) {
            return "Hari ini libur ({$libur}). Sesi ekskul tidak bisa dimulai.";
        }

        $w = $this->waktu($jadwal, $sekarang);

        if ($sekarang->lt($w['buka'])) {
            return 'Sesi baru bisa dimulai pukul ' . $w['buka']->format('H:i') . ' (15 menit sebelum jadwal).';
        }

        if ($sekarang->gt($w['batas'])) {
            return 'Jadwal ekskul hari ini sudah lewat (batas pukul ' . $w['batas']->format('H:i') . ').';
        }

        return null;
    }

    /**
     * Pembina men-scan QR ekskul.
     *
     * @return array{tipe: string, judul: string, pesan: string, sesi?: SesiEkskul}
     */
    public function mulai(JadwalEkskul $jadwal, User $user, string $kode, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();
        $kode = trim($kode);

        if ($kode === '' || strcasecmp($kode, (string) $jadwal->kode_qr) !== 0) {
            $lain = JadwalEkskul::tampakKodeEkskul($kode)
                ? JadwalEkskul::query()->where('kode_qr', $kode)->value('nama_ekskul')
                : null;

            return ['tipe' => 'error', 'judul' => 'QR tidak cocok',
                'pesan' => $lain
                    ? "Yang di-scan adalah QR ekskul {$lain}, bukan {$jadwal->nama_ekskul}."
                    : "Kode yang terbaca bukan QR ekskul {$jadwal->nama_ekskul}. Scan stiker QR resmi ekskul ini."];
        }

        if ($alasan = $this->alasanTidakBisaMulai($jadwal, $user, $sekarang)) {
            return ['tipe' => 'error', 'judul' => 'Sesi belum bisa dimulai', 'pesan' => $alasan];
        }

        if ($ada = $this->sesiPada($jadwal, $sekarang)) {
            return $ada->sudahSelesai()
                ? ['tipe' => 'warn', 'judul' => 'Sesi sudah diakhiri', 'pesan' => 'Sesi ekskul hari ini sudah ditutup pukul ' . $ada->waktu_selesai->format('H:i') . '.', 'sesi' => $ada]
                : ['tipe' => 'warn', 'judul' => 'Sesi sudah berjalan', 'pesan' => 'Sesi ini sudah dimulai pukul ' . $ada->waktu_mulai->format('H:i') . '. Lanjutkan mengisi absensi anggota.', 'sesi' => $ada];
        }

        try {
            $sesi = SesiEkskul::create([
                'jadwal_ekskul_id' => $jadwal->id,
                'user_id' => $user->id,
                'tanggal' => $sekarang->toDateString(),
                'waktu_mulai' => $sekarang,
            ]);
        } catch (QueryException $e) {
            // Dua scan nyaris bersamaan: indeks unik menolak yang kedua.
            $sesi = $this->sesiPada($jadwal, $sekarang);

            if (! $sesi) {
                throw $e;
            }
        }

        return ['tipe' => 'ok', 'judul' => 'Sesi ekskul dimulai',
            'pesan' => "{$jadwal->nama_ekskul} dimulai pukul " . $sesi->waktu_mulai->format('H:i') . '. Isi absensi anggota, unggah foto bukti, lalu Akhiri Sesi.',
            'sesi' => $sesi];
    }

    public function sesiPada(JadwalEkskul $jadwal, Carbon $tanggal): ?SesiEkskul
    {
        return SesiEkskul::query()
            ->where('jadwal_ekskul_id', $jadwal->id)
            ->wherePadaTanggal('tanggal', $tanggal->toDateString())
            ->first();
    }

    /* ===================== AKHIRI ===================== */

    public function absensiTerisi(JadwalEkskul $jadwal, Carbon $tanggal): bool
    {
        return AbsensiEkskul::query()
            ->where('jadwal_ekskul_id', $jadwal->id)
            ->wherePadaTanggal('tanggal', $tanggal->toDateString())
            ->exists();
    }

    /** null = boleh diakhiri. */
    public function alasanTidakBisaAkhiri(SesiEkskul $sesi, JadwalEkskul $jadwal, Carbon $sekarang): ?string
    {
        if ($sesi->sudahSelesai()) {
            return 'Sesi ini sudah diakhiri sebelumnya.';
        }

        if (! $sesi->tanggal->isSameDay($sekarang) || $sekarang->gt($this->waktu($jadwal, $sekarang)['batas'])) {
            return 'Batas menekan "Akhiri Sesi" (15 menit setelah jadwal selesai) sudah lewat.';
        }

        if (! $this->absensiTerisi($jadwal, $sekarang)) {
            return 'Simpan dulu absensi anggota hari ini.';
        }

        if (! $sesi->adaBukti()) {
            return 'Unggah dulu foto bukti kegiatan ekskul.';
        }

        return null;
    }

    /* ===================== PENGINGAT ===================== */

    /**
     * Sesi ekskul HARI INI yang masih terbuka dan batasnya belum lewat.
     * Satu query (sesi + jadwal di-eager-load), berapa pun jumlah ekskulnya.
     *
     * @return Collection<int, array{sesi: SesiEkskul, jadwal: JadwalEkskul, user_id: int, selesai: Carbon, pengingat: Carbon, batas: Carbon}>
     */
    public function sesiTerbuka(?int $userId = null, ?Carbon $sekarang = null): Collection
    {
        $sekarang = ($sekarang ?? now())->copy();

        return SesiEkskul::query()
            ->with('jadwal')
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->wherePadaTanggal('tanggal', $sekarang->toDateString())
            ->whereNull('waktu_selesai')
            ->get()
            ->filter(fn (SesiEkskul $s) => $s->jadwal !== null)
            ->map(function (SesiEkskul $s) use ($sekarang) {
                $w = $this->waktu($s->jadwal, $sekarang);

                return ['sesi' => $s, 'jadwal' => $s->jadwal, 'user_id' => (int) $s->user_id,
                    'selesai' => $w['selesai'], 'pengingat' => $w['pengingat'], 'batas' => $w['batas']];
            })
            ->filter(fn (array $b) => $sekarang->lt($b['batas']))
            ->sortBy(fn (array $b) => $b['batas']->getTimestamp())
            ->values();
    }

    /** Sesi yang sudah masuk jendela pengingat dan belum pernah diingatkan. */
    public function jatuhTempo(?Carbon $sekarang = null): Collection
    {
        $sekarang = ($sekarang ?? now())->copy();

        return $this->sesiTerbuka(null, $sekarang)
            ->filter(fn (array $b) => $sekarang->gte($b['pengingat']) && $b['sesi']->pengingat_akhiri_pada === null)
            ->values();
    }

    public static function kalimat(JadwalEkskul $jadwal, Carbon $batas): string
    {
        return "Ekskul {$jadwal->nama_ekskul} sudah selesai. Tekan \"Akhiri Sesi\" sebelum pukul " . $batas->format('H:i') . '.';
    }

    /* ===================== PEMBANTU ===================== */

    /** Jam teks ("13:30" / "13:30:00") ditempelkan ke TANGGAL $tanggal. */
    private function jamPada(string $jam, Carbon $tanggal): Carbon
    {
        [$h, $m] = array_pad(explode(':', $jam), 2, 0);

        return $tanggal->copy()->setTime((int) $h, (int) $m, 0);
    }

    public static function hariDari(Carbon $tanggal): Hari
    {
        return match ($tanggal->dayOfWeek) {
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
