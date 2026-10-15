<?php

namespace App\Services;

use App\Models\AbsensiMengajar;
use App\Models\HonorMengajar;
use App\Models\JadwalEkskul;
use App\Models\JadwalPelajaran;
use App\Models\SesiEkskul;
use App\Models\User;
use App\Notifications\SesiDiakhiriAdmin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * PENUTUP SESI — SATU-SATUNYA tempat sesi mengajar & sesi ekskul diakhiri.
 *
 * Dipakai tiga jalur, dengan langkah yang SAMA PERSIS:
 *   1. Guru menekan "Akhiri Sesi Kelas"   (JurnalAbsenKelas)
 *   2. Pembina menekan "Akhiri Sesi Ekskul" (AbsensiEkskul)
 *   3. Kepsek/Super Admin menekan "Bantu Akhiri Sesi" (Live Monitoring)
 *
 * ============ LANGKAHNYA ============
 *  a. UPDATE bersyarat "... WHERE waktu_selesai IS NULL" — atomik: dua
 *     orang yang menekan bersamaan tidak mungkin sama-sama menutup.
 *  b. Honor dicatat lewat PencatatHonor dengan rumus yang SAMA untuk semua
 *     jalur: JP = panjang JADWAL ÷ durasi_jp (Pengaturan), bukan lamanya
 *     sesi nyata — sama dengan Rekap Jam Mengajar.
 *  c. Khusus jalur 3: siapa & alasannya disimpan, rincian honor diberi
 *     catatan, dan gurunya menerima lonceng.
 * ====================================
 *
 * ============ WAKTU SELESAI JALUR 3 ============
 * Bukan jam tombol ditekan. Kepsek bisa saja baru sempat menutupnya sore
 * atau keesokan harinya; mencatat jam itu berarti mengarang durasi. Yang
 * dipakai: JAM SELESAI JADWAL pada tanggal sesinya — atau jam sekarang bila
 * tombol ditekan sebelum jadwal selesai. Tidak pernah lebih awal dari jam
 * scan. Sesi yang tidak cocok jadwal mana pun ditutup tepat di jam scannya
 * (durasi nol, tanpa honor): tidak ada dasar untuk mengarang lamanya.
 * ===============================================
 */
class PenutupSesi
{
    public function __construct(private readonly PencatatHonor $honor)
    {
    }

    /**
     * @return array{berhasil: bool, honor: ?HonorMengajar, waktu_selesai: ?Carbon}
     */
    public function akhiriKbm(AbsensiMengajar $sesi, ?JadwalPelajaran $jadwal, ?User $oleh = null, ?string $alasan = null, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();
        $selesai = $oleh
            ? $this->waktuSelesaiAdil($sesi->waktu_mulai, $jadwal ? $this->jamPada($jadwal->jam_selesai->format('H:i'), $sesi->waktu_mulai) : null, $sekarang)
            : $sekarang;

        if (! $this->tutup($sesi, $selesai, $oleh, $alasan)) {
            return ['berhasil' => false, 'honor' => null, 'waktu_selesai' => null];
        }

        $honor = $jadwal ? $this->catat(fn () => $this->honor->catatMengajar($sesi, $jadwal, $this->catatan($oleh)), $sesi) : null;

        if ($oleh) {
            $this->kabari($sesi->user_id, ($jadwal ? "{$jadwal->mata_pelajaran} (" . ($jadwal->kelas?->nama_kelas ?? '-') . ')' : 'Sesi mengajar ' . $sesi->kode_kelas)
                . ', ' . $sesi->waktu_mulai->translatedFormat('d M') . ", diakhiri oleh {$oleh->name}. Alasan: {$alasan}");
        }

        return ['berhasil' => true, 'honor' => $honor, 'waktu_selesai' => $selesai];
    }

    /**
     * @return array{berhasil: bool, honor: ?HonorMengajar, waktu_selesai: ?Carbon}
     */
    public function akhiriEkskul(SesiEkskul $sesi, JadwalEkskul $jadwal, ?User $oleh = null, ?string $alasan = null, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();
        $selesai = $oleh
            ? $this->waktuSelesaiAdil($sesi->waktu_mulai, $this->jamPada((string) $jadwal->jam_selesai, $sesi->tanggal), $sekarang)
            : $sekarang;

        if (! $this->tutup($sesi, $selesai, $oleh, $alasan)) {
            return ['berhasil' => false, 'honor' => null, 'waktu_selesai' => null];
        }

        $honor = $this->catat(fn () => $this->honor->catatEkskul($sesi, $jadwal, $this->catatan($oleh)), $sesi);

        if ($oleh) {
            $this->kabari($sesi->user_id, "Ekskul {$jadwal->nama_ekskul}, " . $sesi->tanggal->translatedFormat('d M')
                . ", diakhiri oleh {$oleh->name}. Alasan: {$alasan}");
        }

        return ['berhasil' => true, 'honor' => $honor, 'waktu_selesai' => $selesai];
    }

    /* ===================== PEMBANTU ===================== */

    /** min(jam selesai jadwal, sekarang), tidak lebih awal dari jam scan. */
    private function waktuSelesaiAdil(Carbon $mulai, ?Carbon $jadwalSelesai, Carbon $sekarang): Carbon
    {
        $selesai = $jadwalSelesai ? $jadwalSelesai->copy()->min($sekarang) : $mulai->copy();

        return $selesai->lt($mulai) ? $mulai->copy() : $selesai;
    }

    /** UPDATE bersyarat — true hanya bila proses INI yang menutup sesinya. */
    private function tutup(Model $sesi, Carbon $selesai, ?User $oleh, ?string $alasan): bool
    {
        $isi = ['waktu_selesai' => $selesai];

        if ($oleh) {
            $isi += ['diakhiri_oleh' => $oleh->id, 'alasan_diakhiri' => mb_substr((string) $alasan, 0, 255)];
        }

        $berhasil = $sesi->newQuery()->whereKey($sesi->getKey())->whereNull('waktu_selesai')->update($isi) === 1;

        if ($berhasil) {
            $sesi->forceFill($isi)->syncOriginal();
        }

        return $berhasil;
    }

    private function catatan(?User $oleh): ?string
    {
        return $oleh ? 'diakhiri oleh ' . $oleh->name : null;
    }

    /** Gagal mencatat honor TIDAK membatalkan penutupan sesi. */
    private function catat(callable $langkah, Model $sesi): ?HonorMengajar
    {
        try {
            return $langkah();
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat honor saat menutup sesi.', ['sesi' => $sesi::class . '#' . $sesi->getKey(), 'error' => $e->getMessage()]);

            return null;
        }
    }

    private function kabari(int $userId, string $pesan): void
    {
        try {
            User::find($userId)?->notify(new SesiDiakhiriAdmin($pesan));
        } catch (\Throwable $e) {
            Log::warning('Gagal mengabari guru soal sesi yang diakhiri admin.', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }

    /** Jam teks/jam jadwal ditempelkan ke TANGGAL sesinya. */
    private function jamPada(string $jam, Carbon $tanggal): Carbon
    {
        [$h, $m] = array_pad(explode(':', $jam), 2, 0);

        return $tanggal->copy()->setTime((int) $h, (int) $m, 0);
    }
}
