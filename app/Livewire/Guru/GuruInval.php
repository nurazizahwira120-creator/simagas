<?php

namespace App\Livewire\Guru;

use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\HonorMengajar;
use App\Models\JadwalPelajaran;
use App\Models\PenugasanInval;
use App\Models\User;
use App\Services\AturanHonor;
use App\Services\GuruInval as AturanInval;
use App\Services\KalenderAkademik;
use App\Services\PencatatAbsensiKbm;
use App\Services\PencatatHonor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * GURU INVAL — pengganti "Kelas Pengganti".
 *
 * ============ DUA WAJAH SATU HALAMAN ============
 *  Kepsek / Super Admin (PENUNJUK)
 *    - melihat semua jam pelajaran yang gurunya berhalangan, untuk hari ini
 *      atau tanggal lain s.d. 14 hari ke depan (izin yang sudah disetujui
 *      bisa direncanakan penggantinya sejak jauh hari);
 *    - menunjuk satu guru/staf per jam, atau satu orang untuk SEMUA jam
 *      seorang guru yang berhalangan hari itu;
 *    - tetap bisa mengisi/mengoreksi absensi kelas mana pun (hari ini).
 *
 *  Guru / wali kelas / piket / staf / TU (INVAL)
 *    - hanya melihat jam yang DITUGASKAN kepadanya;
 *    - mengisi absensi kelas itu tanpa scan QR, mulai 15 menit sebelum jam
 *      pelajarannya sampai akhir hari.
 * ================================================
 *
 * Kelas yang belum ditunjuk invalnya TIDAK bisa diisi guru lain (keputusan
 * sekolah) — hanya oleh Kepsek / Super Admin. Penyimpanan tetap lewat
 * PencatatAbsensiKbm, jadi aturan bolos, izin gerbang, dan peringatan
 * WhatsApp sama persis dengan jurnal guru; akun pengisinya tercatat di
 * kolom diisi_oleh dan JP-nya dikreditkan ke inval di Rekap Jam Mengajar.
 *
 * Seluruh hak akses diperiksa ULANG di setiap method publik. Properti publik
 * (jadwalId, tanggal, pilihan) ikut dikirim browser dan tidak dipercaya.
 */
class GuruInval extends Component
{
    /** Kelas terbuka untuk diisi sekian menit sebelum jam pelajarannya. */
    public const TOLERANSI_MENIT = 15;

    /** Tanggal yang sedang dilihat penunjuk (Y-m-d). Inval selalu hari ini. */
    public string $tanggal = '';

    public ?int $jadwalId = null;

    /** @var array<int|string, int|string|null> jadwal_id => user_id calon (form tunjuk per jam) */
    public array $pilihan = [];

    /** @var array<int|string, int|string|null> guru_id (pegawai) => user_id calon (form "semua jam") */
    public array $pilihanSemua = [];

    /** @var array<int|string, string> siswa_id => nilai StatusKbm */
    public array $status = [];

    /** @var array<int|string, string> */
    public array $keterangan = [];

    public ?array $notif = null;

    public function mount(): void
    {
        abort_unless($this->boleh(), 403);
        $this->tanggal = today()->toDateString();
        $this->isiPilihan();
    }

    public function updatedTanggal(): void
    {
        $this->reset('jadwalId', 'status', 'keterangan', 'pilihanSemua', 'notif');
        $this->segarkan();
        $this->isiPilihan();
    }

    /* ===================== DATA ===================== */

    #[Computed]
    public function penunjuk(): bool
    {
        return AturanInval::bolehMenunjuk(auth()->user());
    }

    /** Tanggal yang dipakai — dijepit ke rentang yang sah, apa pun kiriman browser. */
    #[Computed]
    public function tanggalDipakai(): Carbon
    {
        if (! $this->penunjuk) {
            return today();
        }

        try {
            $t = Carbon::createFromFormat('Y-m-d', $this->tanggal)->startOfDay();
        } catch (\Throwable) {
            return today();
        }

        return $t->lt(today()) || $t->gt(today()->addDays(AturanInval::MAKS_HARI_KE_DEPAN)) ? today() : $t;
    }

    #[Computed]
    public function hariIni(): bool
    {
        return $this->tanggalDipakai->isSameDay(today());
    }

    #[Computed]
    public function libur(): ?string
    {
        try {
            return app(KalenderAkademik::class)->alasanBukanKbm($this->tanggalDipakai);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Jam pelajaran yang tampil, dikunci jadwal_id.
     *   Penunjuk : semua jam yang gurunya berhalangan pada tanggal itu.
     *   Inval    : hanya jam yang ditugaskan kepadanya hari ini.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function daftarKelas(): Collection
    {
        if ($this->libur) {
            return collect();
        }

        $tanggal = $this->tanggalDipakai;
        $berhalangan = $this->aturan()->jadwalBerhalangan($tanggal);
        $penugasan = $this->aturan()->penugasanPada($tanggal, $this->penunjuk ? null : auth()->id());

        // Inval hanya melihat jam miliknya, dan hanya selama guru aslinya
        // MASIH berhalangan: kalau izinnya dibatalkan, kelas kembali ke
        // gurunya dan tugas inval otomatis tidak berlaku.
        if (! $this->penunjuk) {
            $berhalangan = $berhalangan->only($penugasan->keys()->all());
        }

        if ($berhalangan->isEmpty()) {
            return collect();
        }

        $rekap = $this->hariIni
            ? AbsensiKbmSiswa::query()
                ->whereIn('jadwal_id', $berhalangan->keys())
                ->wherePadaTanggal('tanggal', $tanggal)
                ->groupBy('jadwal_id')
                ->select('jadwal_id')
                ->selectRaw('COUNT(*) as jumlah')
                ->selectRaw('MAX(diisi_oleh) as pengisi')
                ->selectRaw('MAX(updated_at) as terakhir')
                ->get()
                ->keyBy('jadwal_id')
            : collect();

        $namaPengisi = User::query()
            ->whereIn('id', $rekap->pluck('pengisi')->filter()->unique())
            ->pluck('name', 'id');

        $sekarang = now();

        return $berhalangan->map(function (array $b) use ($penugasan, $rekap, $namaPengisi, $sekarang, $tanggal) {
            /** @var JadwalPelajaran $j */
            $j = $b['jadwal'];
            $r = $rekap->get($j->id);
            /** @var PenugasanInval|null $tugas */
            $tugas = $penugasan->get($j->id);
            $buka = $tanggal->copy()->setTime($j->jam_mulai->hour, $j->jam_mulai->minute)->subMinutes(self::TOLERANSI_MENIT);

            return $b + [
                'inval' => $tugas?->inval,
                'terisi' => $r !== null && (int) $r->jumlah > 0,
                'jumlah' => (int) ($r->jumlah ?? 0),
                'pengisi' => $r?->pengisi ? ($namaPengisi[$r->pengisi] ?? 'Akun terhapus') : ($r ? $j->guru?->nama : null),
                'diisi_pada' => $r?->terakhir ? Carbon::parse($r->terakhir)->format('H:i') : null,
                'buka_pukul' => $buka->format('H:i'),
                // Absensi hanya bisa diisi HARI INI; tanggal mendatang hanya untuk menunjuk.
                'terbuka' => $this->hariIni && $sekarang->gte($buka),
            ];
        });
    }

    /**
     * Jam berhalangan dikelompokkan per guru — untuk tampilan penunjuk dan
     * tombol "satu orang untuk semua jam".
     *
     * @return Collection<int, array{guru: mixed, alasan: array, kelas: Collection}>
     */
    #[Computed]
    public function perGuru(): Collection
    {
        return $this->daftarKelas
            ->groupBy(fn (array $i) => $i['jadwal']->guru_id)
            ->map(fn (Collection $kelas) => [
                'guru' => $kelas->first()['jadwal']->guru,
                'alasan' => $kelas->first()['alasan'],
                'kelas' => $kelas,
            ]);
    }

    /** @return Collection<int, array{user: User, sibuk: array}> */
    #[Computed]
    public function calon(): Collection
    {
        return $this->penunjuk && ! $this->libur ? $this->aturan()->calon($this->tanggalDipakai) : collect();
    }

    /**
     * Tugas inval saya pada hari-hari BERIKUTNYA (informasi saja).
     *
     * @return Collection<int, PenugasanInval>
     */
    #[Computed]
    public function tugasMendatang(): Collection
    {
        if ($this->penunjuk) {
            return collect();
        }

        return PenugasanInval::query()
            ->with(['jadwal.kelas:id,nama_kelas', 'jadwal.guru:id,nama'])
            ->where('inval_user_id', auth()->id())
            ->whereAntaraTanggal('tanggal', today()->addDay(), today()->addDays(AturanInval::MAKS_HARI_KE_DEPAN))
            ->orderBy('tanggal')
            ->get()
            ->filter(fn (PenugasanInval $p) => $p->jadwal !== null)
            ->sortBy(fn (PenugasanInval $p) => $p->tanggal->format('Y-m-d') . $p->jadwal->jam_mulai->format('H:i'))
            ->values();
    }

    /** Jam yang sedang dibuka formnya — selalu divalidasi ulang. */
    #[Computed]
    public function dipilih(): ?array
    {
        if (! $this->jadwalId) {
            return null;
        }

        $item = $this->daftarKelas->get($this->jadwalId);

        return $item && $item['terbuka'] && $this->bolehIsi($item) ? $item : null;
    }

    /* ---- Dipakai bersama partial tabel-hadir-siswa ---- */

    #[Computed]
    public function daftarSiswa(): Collection
    {
        $jadwal = $this->dipilih['jadwal'] ?? null;

        return $jadwal ? $this->pencatat()->siswaKelas($jadwal) : collect();
    }

    #[Computed]
    public function hadirDiGerbang(): Collection
    {
        return $this->pencatat()->hadirDiGerbang($this->daftarSiswa->pluck('id'), today());
    }

    #[Computed]
    public function izinDariGerbang(): Collection
    {
        return $this->pencatat()->izinDariGerbang($this->daftarSiswa->pluck('id'), today());
    }

    #[Computed]
    public function pilihanStatus(): array
    {
        return StatusKbm::pilihanGuru();
    }

    /* ===================== AKSI: MENUNJUK ===================== */

    /** Tunjuk inval untuk SATU jam pelajaran. */
    public function tunjuk(int $jadwalId): void
    {
        $this->pastikanPenunjuk();
        $this->segarkan();

        $item = $this->daftarKelas->get($jadwalId);
        $inval = User::find((int) ($this->pilihan[$jadwalId] ?? 0));

        if (! $item || ! $inval) {
            $this->pesan('error', 'Belum bisa menunjuk', $item ? 'Pilih dulu guru atau staf penggantinya.' : 'Jam pelajaran ini tidak lagi membutuhkan inval. Muat ulang halaman.');

            return;
        }

        $this->laporkanHasil($this->aturan()->tunjuk([$item['jadwal']], $this->tanggalDipakai, $inval, auth()->user()), $inval);
    }

    /** Tunjuk SATU orang untuk semua jam seorang guru yang berhalangan. */
    public function tunjukSemua(int $guruId): void
    {
        $this->pastikanPenunjuk();
        $this->segarkan();

        $kelompok = $this->perGuru->get($guruId);
        $inval = User::find((int) ($this->pilihanSemua[$guruId] ?? 0));

        if (! $kelompok || ! $inval) {
            $this->pesan('error', 'Belum bisa menunjuk', $kelompok ? 'Pilih dulu guru atau staf penggantinya.' : 'Guru ini tidak lagi berhalangan. Muat ulang halaman.');

            return;
        }

        $jadwal = $kelompok['kelas']->map(fn (array $i) => $i['jadwal'])->values();

        $this->laporkanHasil($this->aturan()->tunjuk($jadwal, $this->tanggalDipakai, $inval, auth()->user()), $inval);
        $this->pilihanSemua[$guruId] = null;
    }

    public function batalkan(int $jadwalId): void
    {
        $this->pastikanPenunjuk();

        $this->aturan()->batalkan($jadwalId, $this->tanggalDipakai)
            ? $this->pesan('ok', 'Penunjukan dibatalkan', 'Jam pelajaran ini sekarang belum punya guru inval.')
            : $this->pesan('warn', 'Tidak ada yang dibatalkan', 'Jam ini memang belum punya guru inval.');

        $this->segarkan();
        $this->isiPilihan();
    }

    /* ===================== AKSI: MENGISI ABSENSI ===================== */

    public function pilih(int $jadwalId): void
    {
        abort_unless($this->boleh(), 403);
        $this->notif = null;
        $this->segarkan();

        $item = $this->daftarKelas->get($jadwalId);

        if (! $item || ! $this->bolehIsi($item)) {
            $this->pesan('error', 'Kelas tidak tersedia',
                'Anda tidak (lagi) ditugaskan untuk kelas ini, atau gurunya sudah tidak berhalangan. Muat ulang halaman.');

            return;
        }

        if (! $item['terbuka']) {
            $this->pesan('warn', 'Belum waktunya', $this->hariIni
                ? 'Absensi kelas ini baru bisa diisi mulai pukul ' . $item['buka_pukul'] . '.'
                : 'Absensi hanya bisa diisi pada hari pelajarannya.');

            return;
        }

        $this->jadwalId = $jadwalId;
        unset($this->dipilih, $this->daftarSiswa, $this->hadirDiGerbang, $this->izinDariGerbang);

        $awal = $this->pencatat()->statusAwal($item['jadwal'], today(), $this->daftarSiswa);
        $this->status = $awal['status'];
        $this->keterangan = $awal['keterangan'];
    }

    public function tutup(): void
    {
        $this->reset('jadwalId', 'status', 'keterangan');
        $this->segarkan();
    }

    public function simpan(): void
    {
        abort_unless($this->boleh(), 403);
        $this->notif = null;
        $this->segarkan();

        $item = $this->dipilih;

        // Diperiksa ulang SAAT MENYIMPAN: penunjukannya bisa saja dibatalkan,
        // atau guru aslinya datang dan izinnya dicabut, di antara saat form
        // dibuka dan saat disimpan.
        if (! $item) {
            $this->pesan('error', 'Tidak bisa menyimpan',
                'Kelas ini tidak lagi terbuka untuk Anda — penunjukannya mungkin dibatalkan atau gurunya sudah tidak berhalangan. Muat ulang halaman.');
            $this->reset('jadwalId');

            return;
        }

        try {
            $hasil = $this->pencatat()->simpan($item['jadwal'], today(), $this->daftarSiswa, $this->status, $this->keterangan, auth()->id());
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan absensi KBM guru inval.', [
                'jadwal_id' => $item['jadwal']->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal menyimpan', 'Terjadi kesalahan saat menyimpan absensi. Coba lagi sebentar.');

            return;
        }

        if ($hasil['jumlah'] === 0) {
            $this->pesan('warn', 'Tidak ada siswa', 'Kelas ini belum punya data siswa, jadi tidak ada yang bisa diabsen.');

            return;
        }

        // Honor inval: diberikan kepada inval yang DITUNJUK. Kalau belum ada
        // yang ditunjuk dan Kepsek/Admin sendiri yang masuk kelas, dialah
        // yang tercatat menggantikan.
        $honor = $this->catatHonorInval($item['jadwal'], $item['inval'] ?? auth()->user());

        $this->pesan('ok',
            'Absensi ' . ($item['jadwal']->kelas?->nama_kelas ?? 'kelas') . ' tersimpan',
            $this->pencatat()->kalimatHasil($hasil) . ' Tercatat diisi oleh Anda sebagai guru inval.'
                . ($honor && $honor->user_id === auth()->id()
                    ? ' Honor inval ' . AturanHonor::rupiah($honor->nominal) . ' masuk ke Rincian Pendapatan Anda.'
                    : '')
        );

        $this->reset('jadwalId', 'status', 'keterangan');
        $this->segarkan();
    }

    public function render()
    {
        return view('livewire.guru.guru-inval');
    }

    /* ===================== PEMBANTU ===================== */

    /** Siapa yang boleh membuka halaman ini sama sekali. */
    private function boleh(): bool
    {
        $peran = auth()->user()?->role;

        return in_array($peran, AturanInval::PERAN_PENUNJUK, true) || in_array($peran, AturanInval::PERAN_CALON, true);
    }

    private function bolehIsi(array $item): bool
    {
        return $this->penunjuk || ($item['inval']?->id === auth()->id());
    }

    private function pastikanPenunjuk(): void
    {
        abort_unless(AturanInval::bolehMenunjuk(auth()->user()), 403);
        $this->notif = null;
    }

    /** Isi dropdown per jam dengan inval yang sudah ditunjuk. */
    private function isiPilihan(): void
    {
        $this->pilihan = $this->daftarKelas
            ->map(fn (array $i) => $i['inval']?->id)
            ->all();
    }

    private function laporkanHasil(array $hasil, User $inval): void
    {
        $this->segarkan();
        $this->isiPilihan();

        $jumlah = count($hasil['ditunjuk']);
        $lewat = array_values($hasil['dilewati']);

        if ($jumlah === 0) {
            $this->pesan('error', 'Tidak ada yang ditunjuk', implode(' ', $lewat));

            return;
        }

        $this->pesan($lewat ? 'warn' : 'ok',
            "{$inval->name} ditunjuk untuk {$jumlah} jam pelajaran",
            'Notifikasi sudah dikirim ke yang bersangkutan.' . ($lewat ? ' Dilewati: ' . implode(' ', $lewat) : '')
        );
    }

    private function segarkan(): void
    {
        unset(
            $this->tanggalDipakai,
            $this->hariIni,
            $this->libur,
            $this->daftarKelas,
            $this->perGuru,
            $this->calon,
            $this->tugasMendatang,
            $this->dipilih,
            $this->daftarSiswa,
            $this->hadirDiGerbang,
            $this->izinDariGerbang,
        );
    }

    /**
     * Gagal mencatat honor TIDAK membatalkan absensi yang sudah tersimpan —
     * absensi siswa jauh lebih penting; honornya bisa ditelusuri dari log.
     */
    private function catatHonorInval(JadwalPelajaran $jadwal, User $inval): ?HonorMengajar
    {
        try {
            return app(PencatatHonor::class)->catatInval($jadwal, today(), $inval)['inval'] ?? null;
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat honor guru inval.', [
                'jadwal_id' => $jadwal->id,
                'inval_user_id' => $inval->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function aturan(): AturanInval
    {
        return app(AturanInval::class);
    }

    private function pencatat(): PencatatAbsensiKbm
    {
        return app(PencatatAbsensiKbm::class);
    }

    private function pesan(string $tipe, string $judul, string $isi): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $isi];
    }
}
