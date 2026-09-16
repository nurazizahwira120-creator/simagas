<?php

namespace App\Livewire\Kepsek;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pengaturan;
use App\Models\Siswa;
use App\Services\KalenderAkademik;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pantauan Kehadiran Siswa — layar pengawasan Kepala Sekolah.
 *
 * MURNI HANYA BACA, sama seperti Pantauan Pegawai: tidak ada method yang
 * menulis ke database. Koreksi absensi tetap dikerjakan wali kelas (absensi
 * gerbang) dan guru mapel (absensi KBM), bukan dari layar ini.
 *
 * Yang membuat halaman ini berguna adalah SELISIH antara dua sumber:
 * kolom "Status Kedatangan" berasal dari scan gerbang, sedangkan kolom
 * "Pantauan KBM" berasal dari jurnal yang diisi guru per jam pelajaran.
 * Anak yang hadir di gerbang tapi hilang di jam ke-3 hanya kelihatan kalau
 * keduanya dibaca berdampingan.
 */
class PantauanSiswa extends Component
{
    public string $tanggal = '';

    public ?int $kelas_id = null;

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
        $this->kelas_id = $this->daftarKelas->first()?->id;
    }

    public function updatedTanggal(): void
    {
        if (! $this->tanggalValid()) {
            $this->tanggal = today()->toDateString();
        }
    }

    /**
     * kelas_id datang dari browser. Nilai yang bukan kelas terdaftar
     * dikembalikan ke kelas pertama — kalau dibiarkan, tabelnya kosong tanpa
     * alasan yang terlihat dan pengguna mengira datanya hilang.
     */
    public function updatedKelasId(): void
    {
        if (! $this->daftarKelas->contains('id', (int) $this->kelas_id)) {
            $this->kelas_id = $this->daftarKelas->first()?->id;
        }
    }

    private function tanggalValid(): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggal)
            && Carbon::hasFormat($this->tanggal, 'Y-m-d');
    }

    #[Computed]
    public function tanggalDipakai(): Carbon
    {
        return $this->tanggalValid()
            ? Carbon::parse($this->tanggal)->startOfDay()
            : today();
    }

    /** @return Collection<int, Kelas> */
    #[Computed]
    public function daftarKelas(): Collection
    {
        return Kelas::orderBy('nama_kelas')->get();
    }

    #[Computed]
    public function kelasTerpilih(): ?Kelas
    {
        return $this->daftarKelas->firstWhere('id', (int) $this->kelas_id);
    }

    /**
     * Jumlah jam pelajaran kelas ini pada HARI dari tanggal yang dipilih —
     * jadi penyebut rasio "4/5 Jam Pelajaran Diikuti".
     *
     * Dihitung dari jadwal, bukan dari jumlah baris absensi KBM yang ada.
     * Bedanya penting: kalau memakai jumlah baris, kelas yang gurunya baru
     * mengisi 2 dari 5 jam akan tampil "2/2 diikuti" alias sempurna —
     * padahal 3 jam sisanya belum diketahui sama sekali.
     */
    #[Computed]
    public function totalJamHariItu(): int
    {
        if (! $this->kelasTerpilih) {
            return 0;
        }

        return JadwalPelajaran::where('kelas_id', $this->kelasTerpilih->id)
            ->where('hari', $this->hariDariTanggal()->value)
            ->count();
    }

    private function hariDariTanggal(): Hari
    {
        return match ($this->tanggalDipakai->dayOfWeek) {
            0 => Hari::Minggu,
            1 => Hari::Senin,
            2 => Hari::Selasa,
            3 => Hari::Rabu,
            4 => Hari::Kamis,
            5 => Hari::Jumat,
            default => Hari::Sabtu,
        };
    }

    /**
     * Satu baris per siswa, lengkap dengan absensi gerbang & rekap KBM-nya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function baris(): Collection
    {
        $kelas = $this->kelasTerpilih;

        if (! $kelas) {
            return collect();
        }

        $siswa = Siswa::where('kelas_id', $kelas->id)->orderBy('nama')->get();
        $tanggal = $this->tanggalDipakai;

        $gerbang = AbsensiSiswa::whereIn('siswa_id', $siswa->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        $kbm = AbsensiKbmSiswa::whereIn('siswa_id', $siswa->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->groupBy('siswa_id');

        return $siswa->map(function (Siswa $s) use ($gerbang, $kbm) {
            $absen = $gerbang->get($s->id);
            $catatanKbm = $kbm->get($s->id, collect());

            $hadirKbm = $catatanKbm->filter(fn (AbsensiKbmSiswa $a) => $a->status === StatusKbm::Hadir)->count();
            $bermasalah = $catatanKbm->filter(
                fn (AbsensiKbmSiswa $a) => in_array($a->status, [StatusKbm::Alpa, StatusKbm::Bolos], true)
            );

            return [
                'siswa' => $s,
                'absen' => $absen,
                'jam' => $absen?->jam_masuk?->format('H:i'),
                'statusGerbang' => $absen?->status,
                'terisiKbm' => $catatanKbm->count(),
                'hadirKbm' => $hadirKbm,
                'bermasalah' => $bermasalah,
                'adaBolos' => $bermasalah->contains(fn (AbsensiKbmSiswa $a) => $a->status === StatusKbm::Bolos),
            ];
        });
    }

    /**
     * Keadaan HARI yang sedang dilihat — dijawab sebelum satu angka pun dibaca.
     *
     * ============ KENAPA INI YANG PALING ATAS DI LAYAR ============
     * Tanpa blok ini, kepala sekolah yang membuka halaman pada hari Jumat
     * melihat SELURUH siswa berstatus "belum tercatat" dan tidak ada satu
     * pun petunjuk kenapa. Angka yang benar tapi tanpa konteks terbaca
     * sebagai sistem rusak — atau lebih buruk, sebagai satu sekolah penuh
     * yang membolos.
     *
     * 'sudahLewatJamPulang' sama pentingnya. Jam 08:00, "belum tercatat"
     * berarti "mungkin masih di jalan". Jam 12:00, artinya sudah berubah
     * total jadi "tidak datang hari ini". Angkanya sama; maknanya tidak.
     * =============================================================
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function statusHari(): array
    {
        $kalender = app(KalenderAkademik::class);
        $tanggal = $this->tanggalDipakai;

        $jamPulang = $kalender->jamPulangPada($tanggal);

        /*
         | "Sudah lewat jam pulang" hanya masuk akal untuk HARI INI.
         |
         | Untuk tanggal lampau jawabannya selalu ya (harinya sudah habis),
         | dan untuk tanggal yang akan datang selalu tidak. Membandingkan
         | now() dengan jam pulang tanggal lampau kebetulan memberi jawaban
         | yang benar, tapi hanya karena tanggalnya ikut dibandingkan —
         | ditulis eksplisit di sini supaya tidak terlihat seperti kebetulan.
         */
        $sudahLewat = $tanggal->isPast() && ! $tanggal->isToday()
            ? true
            : ($tanggal->isToday() ? now()->greaterThanOrEqualTo($jamPulang) : false);

        return [
            'tanggal' => $tanggal,
            'hariKbm' => $kalender->adalahHariKbm($tanggal),
            'alasan' => $kalender->alasanBukanKbm($tanggal),
            'agenda' => $kalender->agendaPada($tanggal),
            'jamPulang' => $jamPulang->format('H:i'),
            'sudahLewatJamPulang' => $sudahLewat,
            'hari' => KalenderAkademik::hariDari($tanggal)->label(),
        ];
    }

    /**
     * Apakah penyapu alpa otomatis BENAR-BENAR sudah berjalan hari itu.
     *
     * ============ KENAPA INI PERLU DIPERIKSA TERPISAH ============
     * "Jam pulang sudah lewat" dan "alpa sudah ditandai" adalah dua hal
     * berbeda, dan menyamakannya pernah menimbulkan masalah nyata di project
     * ini: perintah simagas:laporan-bulanan sempat dijadwalkan padahal
     * kodenya belum pernah sampai ke server, dan tidak ada satu pun layar
     * yang memberi tahu — laporannya hanya tidak pernah muncul.
     *
     * Bahaya yang sama ada di sini, tapi lebih halus. Kalau cron di cPanel
     * belum dipasang atau mati, layar ini akan terus berkata "ditandai alpa
     * otomatis" sementara kolom Alpa tetap nol selamanya. Kepala sekolah
     * menyimpulkan tidak ada yang membolos — kesimpulan yang persis
     * terbalik dari kenyataannya.
     *
     * Dideteksi lewat penanda keterangan yang ditulis perintahnya, bukan
     * lewat status 'alpha' saja: alpa bisa juga datang dari input manual
     * wali kelas, dan itu tidak membuktikan cron-nya hidup.
     * ============================================================
     *
     * Nilai 'status' yang mungkin:
     *   libur          — bukan hari KBM, tidak ada yang perlu disapu
     *   belum_waktunya — jam pulang belum lewat
     *   sudah_jalan    — penyapunya berjalan, sekian siswa ditandai
     *   belum_jalan    — jam pulang lewat, masih ada yang belum tercatat,
     *                    dan tidak ada tanda otomatis sama sekali (cron mati)
     *   tidak_perlu    — semua siswa sudah tercatat, tidak ada yang disapu
     *   galat_skema    — struktur database belum diperbarui (lihat try/catch)
     *
     * @return array{status: string, jumlah: int}
     */
    #[Computed]
    public function penyapuanAlpa(): array
    {
        $h = $this->statusHari;

        if (! $h['hariKbm']) {
            return ['status' => 'libur', 'jumlah' => 0];
        }

        if (! $h['sudahLewatJamPulang']) {
            return ['status' => 'belum_waktunya', 'jumlah' => 0];
        }

        /*
         | ============ KENAPA QUERY INI DIBUNGKUS try/catch ============
         | Halaman ini pernah MATI TOTAL karena baris di bawah.
         |
         | Kolom `keterangan` belum ada di database server (baris itu dulu
         | ditambahkan ke migration yang SUDAH terlanjur jalan, jadi
         | `migrate` menjawab "Nothing to migrate" dan kolomnya tidak pernah
         | dibuat). MySQL menjawab dengan galat, Livewire meneruskannya, dan
         | kepala sekolah melihat "500 Server Error" — tanpa daftar siswa,
         | tanpa rekap sekolah, tanpa satu pun petunjuk penyebabnya.
         |
         | Padahal yang gagal cuma SATU LENCANA STATUS. Seluruh isi halaman
         | yang benar-benar dibutuhkan — siapa yang hadir, siapa yang izin,
         | siapa yang belum tercatat — sama sekali tidak bergantung padanya.
         |
         | Ini BUKAN menyembunyikan kesalahan. Galatnya tetap ditampilkan,
         | justru lebih jelas daripada sebelumnya: halaman tetap terbuka dan
         | memuat peringatan yang menyebutkan perintah perbaikannya. Yang
         | dihapus hanyalah kemampuannya menjatuhkan seluruh halaman.
         |
         | Hanya QueryException yang ditangkap. Galat lain tetap dilempar —
         | kesalahan logika tidak boleh diam-diam berubah jadi lencana.
         | ==============================================================
         */
        try {
            $jumlah = AbsensiSiswa::whereDate('tanggal', $this->tanggalDipakai->toDateString())
                ->where('keterangan', \App\Console\Commands\TandaiAlpaSiswa::PENANDA)
                ->count();
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);

            return ['status' => 'galat_skema', 'jumlah' => 0];
        }

        if ($jumlah > 0) {
            return ['status' => 'sudah_jalan', 'jumlah' => $jumlah];
        }

        /*
         | Tidak ada baris bertanda otomatis. Dua kemungkinan, dan keduanya
         | harus dibedakan:
         |
         |   - Semua siswa memang sudah tercatat (hadir/izin/sakit). Ini
         |     keadaan yang BAIK, bukan kegagalan.
         |   - Masih ada yang belum tercatat. Berarti penyapunya tidak
         |     berjalan — inilah yang perlu diperingatkan.
         */
        $belum = $this->totalSekolah['belum'];

        return [
            'status' => $belum > 0 ? 'belum_jalan' : 'tidak_perlu',
            'jumlah' => $belum,
        ];
    }

    /** Batas terlambat siswa sebagai teks 'H:i'. */
    #[Computed]
    public function batasTerlambat(): string
    {
        return (string) Pengaturan::ambil('batas_terlambat_siswa', '07:15');
    }

    /**
     * Metrik kelas terpilih — dipecah sampai ke akar, bukan hanya hadir/tidak.
     *
     * "Tidak hadir" yang digabung jadi satu angka menyembunyikan perbedaan
     * yang paling menentukan tindakan: anak yang IZIN sudah diurus dan tidak
     * perlu ditindak, sedangkan anak yang BELUM TERCATAT justru yang harus
     * segera ditelepon. Menyatukan keduanya membuat kepala sekolah menelepon
     * orang tua yang sudah mengirim surat pagi itu.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function metrik(): array
    {
        $b = $this->baris;
        $batas = $this->batasTerlambat;

        $hadir = $b->filter(fn (array $r) => $r['statusGerbang'] === AbsensiStatus::Hadir);

        return [
            'total' => $b->count(),
            'hadir' => $hadir->count(),

            // Terlambat adalah BAGIAN dari hadir, bukan lawannya. Anak yang
            // datang jam 07:30 tetap hadir — yang perlu diketahui hanya
            // bahwa ia datang sesudah batas.
            'terlambat' => $hadir->filter(fn (array $r) => $r['jam'] && $r['jam'] > $batas)->count(),

            'izin' => $b->filter(fn (array $r) => $r['statusGerbang'] === AbsensiStatus::Izin)->count(),
            'sakit' => $b->filter(fn (array $r) => $r['statusGerbang'] === AbsensiStatus::Sakit)->count(),
            'alpa' => $b->filter(fn (array $r) => $r['statusGerbang'] === AbsensiStatus::Alpha)->count(),

            // Belum tercatat = tidak ada baris absensi sama sekali.
            'belum' => $b->filter(fn (array $r) => $r['statusGerbang'] === null)->count(),

            // Yang lama, dipertahankan supaya arti kolomnya tidak berubah
            // diam-diam bagi siapa pun yang sudah terbiasa membacanya.
            'tidakHadir' => $b->filter(fn (array $r) => $r['statusGerbang'] !== AbsensiStatus::Hadir)->count(),

            // Bolos KBM dihitung per SISWA, bukan per jam pelajaran: satu anak
            // yang bolos tiga jam tetap satu anak yang perlu ditindaklanjuti.
            'bolos' => $b->filter(fn (array $r) => $r['adaBolos'])->count(),
        ];
    }

    /**
     * Rekap SELURUH SEKOLAH per kelas — bukan hanya kelas yang dipilih.
     *
     * ============ KENAPA INI YANG DICARI KEPALA SEKOLAH ============
     * Tabel per kelas menjawab "siapa saja di kelas XI RPL 1 yang tidak
     * masuk". Pertanyaan kepala sekolah justru satu tingkat di atasnya:
     * "kelas mana yang bermasalah hari ini" — dan menjawabnya dengan tabel
     * per kelas berarti membuka dua belas kelas satu per satu.
     * ==============================================================
     *
     * ============ DUA QUERY, BUKAN SATU PER KELAS ============
     * Dua belas kelas akan jadi 24 query kalau dihitung per kelas. Di sini
     * keduanya agregat: satu menghitung jumlah siswa per kelas, satu
     * menghitung status absensi per kelas.
     *
     * SUM(CASE WHEN ... THEN 1 ELSE 0 END) dipakai, bukan COUNT(CASE ...),
     * karena itu bentuk yang berperilaku sama di MySQL maupun SQLite.
     * =========================================================
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function rekapSekolah(): Collection
    {
        $tanggal = $this->tanggalDipakai->toDateString();

        $jumlahSiswa = Siswa::query()
            ->select('kelas_id')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        $hitung = function (AbsensiStatus $s): string {
            return "SUM(CASE WHEN absensi_siswa.status = '{$s->value}' THEN 1 ELSE 0 END)";
        };

        $absensi = AbsensiSiswa::query()
            ->join('siswa', 'siswa.id', '=', 'absensi_siswa.siswa_id')
            ->whereDate('absensi_siswa.tanggal', $tanggal)
            ->groupBy('siswa.kelas_id')
            ->select('siswa.kelas_id')
            ->selectRaw($hitung(AbsensiStatus::Hadir) . ' as hadir')
            ->selectRaw($hitung(AbsensiStatus::Izin) . ' as izin')
            ->selectRaw($hitung(AbsensiStatus::Sakit) . ' as sakit')
            ->selectRaw($hitung(AbsensiStatus::Alpha) . ' as alpa')
            ->get()
            ->keyBy('kelas_id');

        return $this->daftarKelas->map(function (Kelas $k) use ($jumlahSiswa, $absensi) {
            $total = (int) $jumlahSiswa->get($k->id, 0);
            $a = $absensi->get($k->id);

            $hadir = (int) ($a->hadir ?? 0);
            $izin = (int) ($a->izin ?? 0);
            $sakit = (int) ($a->sakit ?? 0);
            $alpa = (int) ($a->alpa ?? 0);

            return [
                'kelas' => $k,
                'total' => $total,
                'hadir' => $hadir,
                'izin' => $izin,
                'sakit' => $sakit,
                'alpa' => $alpa,
                'belum' => max(0, $total - $hadir - $izin - $sakit - $alpa),
                // Pembagi nol menghasilkan galat fatal di PHP 8; kelas yang
                // belum punya siswa memang harus menampilkan strip, bukan 0%.
                'persen' => $total > 0 ? round($hadir / $total * 100) : null,
            ];
        });
    }

    /**
     * Angka seluruh sekolah — satu baris kesimpulan di paling atas.
     *
     * @return array<string, int|null>
     */
    #[Computed]
    public function totalSekolah(): array
    {
        $r = $this->rekapSekolah;

        $total = (int) $r->sum('total');
        $hadir = (int) $r->sum('hadir');

        return [
            'total' => $total,
            'hadir' => $hadir,
            'izin' => (int) $r->sum('izin'),
            'sakit' => (int) $r->sum('sakit'),
            'alpa' => (int) $r->sum('alpa'),
            'belum' => (int) $r->sum('belum'),
            'persen' => $total > 0 ? (int) round($hadir / $total * 100) : null,
        ];
    }

    public function render()
    {
        return view('livewire.kepsek.pantauan-siswa');
    }
}
