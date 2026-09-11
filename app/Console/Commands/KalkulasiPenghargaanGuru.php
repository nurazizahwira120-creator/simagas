<?php

namespace App\Console\Commands;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\Penghargaan;
use App\Services\NotifikasiPenghargaan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Memilih "Guru Teladan & Tertib Administrasi" bulan lalu.
 *
 * Dijalankan penjadwal setiap tanggal 1 pukul 00:01 WIB — lihat
 * routes/console.php.
 *
 * ============ APA YANG DIUKUR, DAN KENAPA ITU ============
 * Kedisiplinan seorang guru di sini dinilai dari dua hal yang SAMA-SAMA
 * tercatat sistem, bukan dari penilaian orang:
 *
 *   1. KEHADIRAN  — berapa hari ia tercatat hadir di bulan itu.
 *   2. ADMINISTRASI — berapa sesi mengajar yang ia TUNTASKAN: sudah scan QR
 *      ruangan, mengunggah foto bukti, DAN menutup sesinya. Sesi yang cuma
 *      di-scan lalu ditinggalkan tidak dihitung.
 *
 * Yang sengaja TIDAK dipakai: jumlah jam mengajar. Guru dengan jadwal lebih
 * padat akan selalu menang, dan penghargaannya berubah menjadi hadiah bagi
 * yang jadwalnya paling banyak — bukan bagi yang paling tertib.
 *
 * Skornya = jumlah sesi tuntas + jumlah hari hadir. Keduanya berbobot sama
 * karena keduanya sama-sama sulit: hadir tiap hari tanpa mengisi jurnal, atau
 * rajin mengisi jurnal tapi sering tidak masuk, dua-duanya bukan teladan.
 * =========================================================
 */
class KalkulasiPenghargaanGuru extends Command
{
    protected $signature = 'simagas:penghargaan-guru
                            {--bulan= : Bulan yang dinilai, format YYYY-MM. Kosongkan untuk bulan lalu.}
                            {--paksa : Hitung ulang walau penghargaan bulan itu sudah ada.}';

    protected $description = 'Memilih Guru Teladan & Tertib Administrasi untuk bulan lalu.';

    /** Jabatan yang ikut dinilai. Dicocokkan longgar (LIKE) karena kolomnya teks bebas. */
    private const JABATAN_GURU = 'guru';

    /** Skor minimal sebelum seseorang layak disebut teladan. */
    private const SKOR_MINIMAL = 5;

    public function handle(NotifikasiPenghargaan $notifikasi): int
    {
        $bulan = $this->tentukanBulan();

        if (! $bulan) {
            $this->error('Format --bulan tidak dikenali. Contoh yang benar: --bulan=2026-08');

            return self::INVALID;
        }

        $periode = Penghargaan::periodeBulan($bulan);
        $labelBulan = $bulan->locale('id')->isoFormat('MMMM Y');

        $this->info('Menghitung Guru Teladan untuk ' . $labelBulan . '...');

        $sudahAda = Penghargaan::where('kategori', Penghargaan::KATEGORI_GURU_TELADAN)
            ->where('periode', $periode)
            ->exists();

        if ($sudahAda && ! $this->option('paksa')) {
            $this->warn('Penghargaan bulan ' . $labelBulan . ' sudah pernah dibuat. Pakai --paksa untuk menghitung ulang.');

            return self::SUCCESS;
        }

        $juara = $this->cariJuara($bulan);

        if (! $juara) {
            $this->warn('Tidak ada guru yang memenuhi syarat pada ' . $labelBulan . '. Tidak ada penghargaan yang dibuat.');

            return self::SUCCESS;
        }

        $penghargaan = $this->simpan($juara, $bulan, $periode, $labelBulan);

        if (! $penghargaan) {
            $this->warn('Guru terpilih (' . $juara['pegawai']->nama . ') belum punya akun login, jadi penghargaannya tidak bisa dicatat.');

            return self::SUCCESS;
        }

        // Pemberitahuan ditaruh paling akhir dan tidak pernah menggagalkan
        // perintah ini — lihat catatan di NotifikasiPenghargaan.
        $notifikasi->kirim($penghargaan);

        $this->newLine();
        $this->info('Guru Teladan ' . $labelBulan . ': ' . $juara['pegawai']->nama);
        $this->table(
            ['Sesi mengajar tuntas', 'Hari hadir', 'Skor'],
            [[$juara['sesi'], $juara['hadir'], $juara['skor']]],
        );

        return self::SUCCESS;
    }

    /* ===================== PERHITUNGAN ===================== */

    /**
     * Mencari guru dengan rekam jejak terbaik pada bulan tersebut.
     *
     * @return array{pegawai: Pegawai, sesi: int, hadir: int, skor: int}|null
     */
    private function cariJuara(Carbon $bulan): ?array
    {
        $awal = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth();

        /*
         | Daftar gurunya diambil SEKALI, lengkap dengan akun user-nya
         | (eager load). Tanpa `with('user')`, memeriksa akun tiap kandidat di
         | bawah akan melahirkan satu query per guru.
         */
        $guru = Pegawai::query()
            ->with('user:id,name,email')
            ->where('jabatan', 'like', '%' . self::JABATAN_GURU . '%')
            ->get(['id', 'user_id', 'nama', 'jabatan']);

        if ($guru->isEmpty()) {
            return null;
        }

        /*
         | ============ DUA QUERY AGREGAT, BUKAN DUA PER GURU ============
         | Keduanya mengelompokkan di database dan mengembalikan satu baris
         | per guru. Untuk 11 guru bedanya belum terasa; pada sekolah dengan
         | 80 guru, versi "hitung di dalam perulangan" berarti 160 query
         | dijalankan pukul 00:01 — persis saat tidak ada seorang pun yang
         | akan melihat kalau prosesnya menggantung.
         */

        // (1) Sesi mengajar yang BENAR-BENAR tuntas: ada foto bukti DAN sudah
        //     ditutup. Inilah ukuran "tertib administrasi"-nya.
        $sesiTuntas = AbsensiMengajar::query()
            ->whereBetween('waktu_mulai', [$awal, $akhir])
            ->whereNotNull('foto_bukti')
            ->whereNotNull('waktu_selesai')
            ->groupBy('user_id')
            ->select('user_id')
            ->selectRaw('COUNT(*) as jumlah')
            ->pluck('jumlah', 'user_id');

        // (2) Hari hadir. Statusnya dibaca dari enum, bukan diketik ulang
        //     sebagai string — 'alpha' vs 'alpa' sudah pernah menjadi sumber
        //     kolom nol yang terlihat wajar di project ini.
        $hariHadir = AbsensiPegawai::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->where('status', AbsensiStatus::Hadir->value)
            ->groupBy('pegawai_id')
            ->select('pegawai_id')
            ->selectRaw('COUNT(DISTINCT tanggal) as jumlah')
            ->pluck('jumlah', 'pegawai_id');

        $kandidat = $guru
            ->map(function (Pegawai $p) use ($sesiTuntas, $hariHadir) {
                $sesi = (int) ($sesiTuntas[$p->user_id] ?? 0);
                $hadir = (int) ($hariHadir[$p->id] ?? 0);

                return [
                    'pegawai' => $p,
                    'sesi' => $sesi,
                    'hadir' => $hadir,
                    'skor' => $sesi + $hadir,
                ];
            })
            ->filter(fn (array $k) => $k['skor'] >= self::SKOR_MINIMAL)
            ->values()
            ->all();

        if ($kandidat === []) {
            return null;
        }

        /*
         | Urutan pemenang dibuat PASTI sampai langkah terakhir:
         |   1. skor tertinggi
         |   2. seri -> sesi mengajar tuntas lebih banyak (administrasi lebih
         |      berat daripada sekadar hadir)
         |   3. masih seri -> abjad nama
         |
         | Tanpa langkah ketiga, dua kali menjalankan perintah yang sama bisa
         | menghasilkan pemenang berbeda tergantung urutan baris dari database.
         */
        usort($kandidat, function (array $a, array $b) {
            if ($a['skor'] !== $b['skor']) {
                return $b['skor'] <=> $a['skor'];
            }

            if ($a['sesi'] !== $b['sesi']) {
                return $b['sesi'] <=> $a['sesi'];
            }

            return strcasecmp($a['pegawai']->nama, $b['pegawai']->nama);
        });

        return $kandidat[0];
    }

    /**
     * @param  array{pegawai: Pegawai, sesi: int, hadir: int, skor: int}  $juara
     */
    private function simpan(array $juara, Carbon $bulan, string $periode, string $labelBulan): ?Penghargaan
    {
        /** @var Pegawai $pegawai */
        $pegawai = $juara['pegawai'];

        // Penghargaan menempel pada AKUN, karena akun itulah yang membuka
        // dashboard dan menerima notifikasi. Guru honorer yang belum
        // dibuatkan akun tidak bisa dicatat di sini.
        if (! $pegawai->user_id) {
            return null;
        }

        $pesan = sprintf(
            'Selamat! %s terpilih sebagai Guru Teladan & Tertib Administrasi bulan %s. '
                . 'Sepanjang bulan itu Anda menuntaskan %d sesi mengajar secara lengkap '
                . '(scan QR, foto bukti, dan menutup sesi) serta hadir %d hari. '
                . 'Terima kasih atas keteladanannya.',
            $pegawai->nama,
            $labelBulan,
            $juara['sesi'],
            $juara['hadir'],
        );

        return Penghargaan::updateOrCreate(
            [
                'user_id' => $pegawai->user_id,
                'kategori' => Penghargaan::KATEGORI_GURU_TELADAN,
                'periode' => $periode,
            ],
            [
                'peran' => Penghargaan::PERAN_GURU,
                'pesan_apresiasi' => $pesan,
                'nilai_acuan' => $juara['skor'],
            ],
        );
    }

    /**
     * Bawaannya BULAN LALU, bukan bulan berjalan.
     *
     * Perintah ini menyala tanggal 1 pukul 00:01 — bulan berjalan baru berumur
     * satu menit dan belum punya data apa pun. Yang dinilai adalah bulan yang
     * baru saja selesai.
     */
    private function tentukanBulan(): ?Carbon
    {
        $masukan = $this->option('bulan');

        if (blank($masukan)) {
            return now()->subMonthNoOverflow()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m', (string) $masukan)->startOfMonth();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
