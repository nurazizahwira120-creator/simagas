<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Enums\StatusKbm;
use App\Models\AbsensiEkskul;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiMengajar;
use App\Models\JadwalEkskul;
use App\Models\JadwalPelajaran;
use App\Models\Pegawai;
use App\Models\SesiEkskul;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * MONITORING KBM HARI INI — seluruh jadwal kelas & ekskul HARI INI, dari
 * jam pertama sampai terakhir, beserta keadaan sesinya.
 *
 * ============ DULU vs SEKARANG ============
 * Dulu hanya jadwal yang jamnya SEDANG berlangsung, dan kartunya hilang
 * begitu jamnya habis — sesi yang lupa diakhiri guru ikut hilang dari layar,
 * padahal justru itu yang perlu ditindak (honornya tidak tercatat).
 * Sekarang seluruh hari tampil, dan sesi yang menggantung bisa ditutup
 * Kepsek/Admin lewat "Bantu Akhiri Sesi" (App\Services\PenutupSesi).
 * ==========================================
 *
 * ============ STATUS SETIAP JADWAL ============
 *  belum_mulai    : jamnya belum tiba
 *  menunggu       : sudah mulai < 10 menit, belum ada scan QR (masih wajar)
 *  berjalan       : sudah scan QR, jadwal belum selesai
 *  belum_diakhiri : sudah scan QR, jadwal SUDAH selesai, Akhiri Sesi belum
 *                   ditekan  -> tombol "Bantu Akhiri Sesi"
 *  selesai        : sesi sudah diakhiri (guru sendiri atau dibantu)
 *  kosong         : jam berjalan > 10 menit, belum ada scan QR
 *  tidak_ada_sesi : jam sudah lewat tanpa scan QR sama sekali
 *  berhalangan    : guru izin/sakit/alpa dan belum ada sesi
 * ==============================================
 *
 * Pasangan sesi <-> jadwal memakai PencocokSesiMengajar::pasangkan() —
 * aturan yang SAMA dengan Jurnal guru, Rekap Jam Mengajar & Lembar Paraf:
 * selisih jam scan paling kecil dipasang dulu, satu sesi untuk satu jadwal.
 *
 * Jumlah query tetap berapa pun jumlah kelasnya (tanpa N+1).
 */
class MonitoringKbmHarian
{
    /** Menit pertama sebuah jam yang belum dianggap "kosong". */
    public const TOLERANSI_MENIT = 10;

    /** Sesi menggantung dari berapa hari ke belakang yang masih ditampilkan. */
    public const HARI_MENGGANTUNG = 7;

    public function __construct(
        private readonly PencocokSesiMengajar $pencocok,
        private readonly StatusBerhalanganGuru $berhalangan,
        private readonly AturanSesiEkskul $aturanEkskul,
    ) {
    }

    /**
     * @return array{baris: array<int, array<string, mixed>>, ringkas: array<string, int>}
     */
    public function hariIni(?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();

        $baris = collect($this->barisKbm($sekarang))
            ->concat($this->barisEkskul($sekarang))
            ->sortBy(fn (array $b) => $b['urut'])
            ->values()
            ->all();

        $hitung = collect($baris)->countBy('kelompok');

        return [
            'baris' => $baris,
            'ringkas' => [
                'total' => count($baris),
                'berjalan' => (int) $hitung->get('berjalan', 0),
                'belum_diakhiri' => (int) $hitung->get('belum_diakhiri', 0),
                'selesai' => (int) $hitung->get('selesai', 0),
                'belum_mulai' => (int) $hitung->get('belum_mulai', 0),
                'bermasalah' => (int) $hitung->get('bermasalah', 0),
            ],
        ];
    }

    /**
     * Sesi kelas & ekskul HARI-HARI SEBELUMNYA (s.d. 7 hari) yang belum
     * pernah diakhiri. Tanpa daftar ini sesi kemarin menggantung selamanya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function menggantung(?Carbon $sekarang = null): Collection
    {
        $sekarang = ($sekarang ?? now())->copy();
        $awal = $sekarang->copy()->subDays(self::HARI_MENGGANTUNG)->startOfDay();
        $kemarin = $sekarang->copy()->subDay()->endOfDay();

        $kbm = AbsensiMengajar::query()
            ->with('user:id,name')
            ->whereNull('waktu_selesai')
            ->whereBetween('waktu_mulai', [$awal, $kemarin])
            ->orderBy('waktu_mulai')
            ->get();

        $jadwalGuru = $this->jadwalPerAkun($kbm->pluck('user_id')->unique()->all());

        $hasil = $kbm->map(function (AbsensiMengajar $s) use ($jadwalGuru) {
            $j = $this->jadwalUntukSesi($s, $jadwalGuru->get($s->user_id, collect()));

            return [
                'jenis' => 'kbm',
                'id' => $s->id,
                'tanggal' => $s->waktu_mulai,
                'judul' => $j ? $j->mata_pelajaran . ' · ' . ($j->kelas?->nama_kelas ?? '-') : 'Ruangan ' . $s->kode_kelas . ' (di luar jadwal)',
                'jam' => $j ? $j->rentangJam() : $s->waktu_mulai->format('H:i'),
                'orang' => $s->user?->name ?? '-',
                'scan' => $s->waktu_mulai->format('H:i'),
            ];
        });

        $ekskul = SesiEkskul::query()
            ->with(['jadwal', 'user:id,name'])
            ->whereNull('waktu_selesai')
            ->whereAntaraTanggal('tanggal', $awal, $kemarin)
            ->get()
            ->filter(fn (SesiEkskul $s) => $s->jadwal !== null)
            ->map(fn (SesiEkskul $s) => [
                'jenis' => 'ekskul',
                'id' => $s->id,
                'tanggal' => $s->waktu_mulai,
                'judul' => 'Ekskul ' . $s->jadwal->nama_ekskul,
                'jam' => $s->jadwal->rentangJam(),
                'orang' => $s->user?->name ?? '-',
                'scan' => $s->waktu_mulai->format('H:i'),
            ]);

        return $hasil->concat($ekskul)->sortBy(fn ($b) => $b['tanggal']->getTimestamp())->values();
    }

    /**
     * Jadwal yang menjadi pasangan sebuah sesi KBM: hari yang sama, kode
     * ruangan cocok, jam mulai paling dekat dengan jam scan. null = sesi di
     * luar jadwal (tidak ada dasar honor).
     *
     * @param  Collection<int, JadwalPelajaran>|null  $jadwalGuru  null = diambil sendiri
     */
    public function jadwalUntukSesi(AbsensiMengajar $sesi, ?Collection $jadwalGuru = null): ?JadwalPelajaran
    {
        $jadwalGuru ??= $this->jadwalPerAkun([$sesi->user_id])->get($sesi->user_id, collect());
        $hari = AturanSesiEkskul::hariDari($sesi->waktu_mulai)->value;
        $kode = PencocokSesiMengajar::normalkan($sesi->kode_kelas);
        $menitScan = $sesi->waktu_mulai->hour * 60 + $sesi->waktu_mulai->minute;

        return $jadwalGuru
            ->where('hari', $hari)
            ->filter(fn (JadwalPelajaran $j) => in_array($kode, PencocokSesiMengajar::sasaran($j), true))
            ->sortBy(fn (JadwalPelajaran $j) => abs($j->jam_mulai->hour * 60 + $j->jam_mulai->minute - $menitScan))
            ->first();
    }

    /* ===================== KELAS ===================== */

    /** @return array<int, array<string, mixed>> */
    private function barisKbm(Carbon $sekarang): array
    {
        $jadwal = JadwalPelajaran::query()
            ->with(['guru:id,nama,user_id', 'kelas:id,nama_kelas'])
            ->where('hari', AturanSesiEkskul::hariDari($sekarang)->value)
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get(['id', 'hari', 'jam_mulai', 'jam_selesai', 'mata_pelajaran', 'ruangan', 'kelas_id', 'guru_id']);

        if ($jadwal->isEmpty()) {
            return [];
        }

        $tanggal = $sekarang->toDateString();

        $sesiPerAkun = AbsensiMengajar::query()
            ->with('pengakhir:id,name')
            ->whereIn('user_id', $jadwal->pluck('guru.user_id')->filter()->unique()->values())
            ->wherePadaTanggal('waktu_mulai', $tanggal)
            ->orderBy('waktu_mulai')
            ->get()
            ->groupBy('user_id');

        $jurnal = AbsensiKbmSiswa::query()
            ->whereIn('jadwal_id', $jadwal->pluck('id'))
            ->wherePadaTanggal('tanggal', $tanggal)
            ->groupBy('jadwal_id')
            ->select('jadwal_id')
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as bolos', [StatusKbm::Alpa->value, StatusKbm::Bolos->value])
            ->toBase()->get()->keyBy('jadwal_id');

        $berhalangan = $this->berhalangan->pada($jadwal->pluck('guru')->filter(), $sekarang);

        // Sesi ↔ jadwal dipasangkan per guru untuk SEHARI PENUH — aturan
        // yang sama dengan layar Jurnal guru (PencocokSesiMengajar::pasangkan).
        $pasangan = [];

        foreach ($jadwal->filter(fn (JadwalPelajaran $j) => $j->guru?->user_id)->groupBy('guru.user_id') as $akun => $jadwalGuru) {
            $pasangan += $this->pencocok->pasangkan($jadwalGuru, $sesiPerAkun->get($akun, collect()));
        }

        return $jadwal->map(function (JadwalPelajaran $j) use ($sekarang, $pasangan, $jurnal, $berhalangan) {
            $sesi = $pasangan[$j->id] ?? null;

            $mulai = $sekarang->copy()->setTime($j->jam_mulai->hour, $j->jam_mulai->minute);
            $selesai = $sekarang->copy()->setTime($j->jam_selesai->hour, $j->jam_selesai->minute);
            $halangan = $j->guru_id ? $berhalangan->get($j->guru_id) : null;
            $status = $this->status($sekarang, $mulai, $selesai, $sesi, $halangan !== null);
            $r = $jurnal->get($j->id);

            return [
                'jenis' => 'kbm',
                'urut' => $j->jam_mulai->format('H:i') . '|0|' . ($j->kelas?->nama_kelas ?? ''),
                'jadwal' => $j,
                'judul' => $j->kelas?->nama_kelas ?? 'Kelas —',
                'subjudul' => $j->mata_pelajaran,
                'orang' => $j->guru?->nama ?? '— belum ditetapkan —',
                'jam' => $j->rentangJam(),
                'ruangan' => $j->ruangan,
                'sesi' => $sesi,
                'status' => $status,
                'kelompok' => self::kelompok($status),
                'gaya' => self::gaya($status),
                'menit_berjalan' => $sekarang->gt($mulai) ? (int) $mulai->diffInMinutes($sekarang->copy()->min($selesai)) : 0,
                'jurnal_terisi' => $r !== null,
                'jumlah_bolos' => (int) ($r->bolos ?? 0),
                'berhalangan' => $halangan,
                'bisa_diakhiri' => $sesi !== null && ! $sesi->sudahSelesai(),
            ];
        })->all();
    }

    /* ===================== EKSKUL ===================== */

    /** @return array<int, array<string, mixed>> */
    private function barisEkskul(Carbon $sekarang): array
    {
        if ($this->aturanEkskul->libur($sekarang)) {
            return [];
        }

        $ekskul = JadwalEkskul::query()
            ->with('pembina:id,nama,user_id')
            ->whereRaw('LOWER(TRIM(hari)) = ?', [AturanSesiEkskul::hariDari($sekarang)->value])
            ->orderBy('jam_mulai')
            ->get();

        if ($ekskul->isEmpty()) {
            return [];
        }

        $tanggal = $sekarang->toDateString();

        $sesi = SesiEkskul::query()
            ->with('pengakhir:id,name')
            ->whereIn('jadwal_ekskul_id', $ekskul->pluck('id'))
            ->wherePadaTanggal('tanggal', $tanggal)
            ->get()
            ->keyBy('jadwal_ekskul_id');

        $absen = AbsensiEkskul::query()
            ->whereIn('jadwal_ekskul_id', $ekskul->pluck('id'))
            ->wherePadaTanggal('tanggal', $tanggal)
            ->groupBy('jadwal_ekskul_id')
            ->select('jadwal_ekskul_id')
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('SUM(CASE WHEN status_kehadiran = ? THEN 1 ELSE 0 END) as hadir', [AbsensiStatus::Hadir->value])
            ->selectRaw('SUM(CASE WHEN status_kehadiran = ? THEN 1 ELSE 0 END) as alpa', [AbsensiStatus::Alpha->value])
            ->toBase()->get()->keyBy('jadwal_ekskul_id');

        return $ekskul->map(function (JadwalEkskul $e) use ($sekarang, $sesi, $absen) {
            $w = $this->aturanEkskul->waktu($e, $sekarang);
            $s = $sesi->get($e->id);
            $a = $absen->get($e->id);
            $tertaut = (bool) $e->pembina?->user_id;
            $status = $this->status($sekarang, $w['mulai'], $w['selesai'], $s, false);

            return [
                'jenis' => 'ekskul',
                'urut' => JadwalEkskul::jam($e->jam_mulai) . '|1|' . $e->nama_ekskul,
                'ekskul' => $e,
                'judul' => $e->nama_ekskul,
                'subjudul' => 'Ekstrakurikuler',
                'orang' => $e->namaPembina(),
                'jam' => $e->rentangJam(),
                'ruangan' => null,
                'sesi' => $s,
                'status' => $status,
                'kelompok' => self::kelompok($status),
                'gaya' => self::gaya($status),
                'menit_berjalan' => $sekarang->gt($w['mulai']) ? (int) $w['mulai']->diffInMinutes($sekarang->copy()->min($w['selesai'])) : 0,
                'pembina_tertaut' => $tertaut,
                'absen_jumlah' => (int) ($a->jumlah ?? 0),
                'absen_hadir' => (int) ($a->hadir ?? 0),
                'jumlah_bolos' => (int) ($a->alpa ?? 0),
                'jurnal_terisi' => $a !== null,
                'berhalangan' => null,
                'bisa_diakhiri' => $s !== null && ! $s->sudahSelesai(),
            ];
        })->all();
    }

    /* ===================== ATURAN ===================== */

    private function status(Carbon $sekarang, Carbon $mulai, Carbon $selesai, $sesi, bool $berhalangan): string
    {
        if ($sesi) {
            return match (true) {
                $sesi->sudahSelesai() => 'selesai',
                $sekarang->lte($selesai) => 'berjalan',
                default => 'belum_diakhiri',
            };
        }

        return match (true) {
            $sekarang->lt($mulai) => 'belum_mulai',
            $berhalangan => 'berhalangan',
            $sekarang->lte($selesai) => $mulai->diffInMinutes($sekarang) < self::TOLERANSI_MENIT ? 'menunggu' : 'kosong',
            default => 'tidak_ada_sesi',
        };
    }

    /** Kelompok untuk saringan & ringkasan di atas halaman. */
    public static function kelompok(string $status): string
    {
        return match ($status) {
            'berjalan', 'menunggu' => 'berjalan',
            'belum_diakhiri' => 'belum_diakhiri',
            'selesai' => 'selesai',
            'belum_mulai' => 'belum_mulai',
            default => 'bermasalah',
        };
    }

    /**
     * Warna & label per status — satu keputusan di satu tempat.
     *
     * @return array<string, string>
     */
    public static function gaya(string $status): array
    {
        $abu = ['kartu' => 'border-gray-200 dark:border-gray-800', 'pita' => 'bg-gray-300 dark:bg-gray-700', 'chip' => 'border-gray-300 bg-gray-500/10 text-brand-muted'];
        $hijau = ['kartu' => 'border-emerald-300 dark:border-emerald-500/40', 'pita' => 'bg-emerald-500', 'chip' => 'border-emerald-300 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'];
        $biru = ['kartu' => 'border-brand-300 dark:border-brand-500/40', 'pita' => 'bg-brand-500', 'chip' => 'border-brand-300 bg-brand-500/10 text-brand-accent-text'];
        $oranye = ['kartu' => 'border-orange-300 dark:border-orange-500/50', 'pita' => 'bg-orange-500', 'chip' => 'border-orange-300 bg-orange-500/10 text-orange-700 dark:text-orange-400'];
        $kuning = ['kartu' => 'border-amber-300 dark:border-amber-500/40', 'pita' => 'bg-amber-500', 'chip' => 'border-amber-300 bg-amber-500/10 text-amber-700 dark:text-amber-400'];
        $merah = ['kartu' => 'border-red-400 dark:border-red-500/50', 'pita' => 'bg-red-500', 'chip' => 'border-red-300 bg-red-500/10 text-brand-danger-text'];

        return match ($status) {
            'belum_mulai' => $abu + ['ikon' => 'clock', 'label' => 'Belum mulai'],
            'menunggu' => $abu + ['ikon' => 'clock', 'label' => 'Baru dimulai'],
            'berjalan' => $biru + ['ikon' => 'eye', 'label' => 'Sedang berjalan'],
            'belum_diakhiri' => $oranye + ['ikon' => 'exclamation-triangle', 'label' => 'Belum diakhiri'],
            'selesai' => $hijau + ['ikon' => 'check-circle', 'label' => 'Selesai'],
            'berhalangan' => $kuning + ['ikon' => 'swap', 'label' => 'Guru berhalangan'],
            'kosong' => $merah + ['ikon' => 'x-circle', 'label' => 'Belum scan QR'],
            default => $merah + ['ikon' => 'x-circle', 'label' => 'Tidak ada sesi'],
        };
    }

    /**
     * Seluruh jadwal mingguan milik akun-akun guru, dikelompokkan per akun.
     *
     * @param  array<int, int>  $userIds
     * @return Collection<int, Collection<int, JadwalPelajaran>>
     */
    private function jadwalPerAkun(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $pegawai = Pegawai::query()->whereIn('user_id', $userIds)->pluck('user_id', 'id');

        return JadwalPelajaran::query()
            ->with('kelas:id,nama_kelas')
            ->whereIn('guru_id', $pegawai->keys())
            ->get()
            ->groupBy(fn (JadwalPelajaran $j) => (int) $pegawai[$j->guru_id]);
    }
}
