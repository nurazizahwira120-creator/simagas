<?php

namespace App\Services;

use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\JadwalPelajaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Rekap kehadiran KBM dengan JADWAL PELAJARAN sebagai satuan acuan.
 *
 * ================== BEDANYA DENGAN LAPORAN YANG SUDAH ADA ==================
 * Sekolah ini sudah punya tiga rekap kehadiran siswa, dan tidak satu pun
 * menjawab pertanyaan yang sama:
 *
 *   Rekap Bulanan Siswa      : satu baris per SISWA, sumbernya absensi
 *                              GERBANG. Menjawab "siapa yang sering tidak
 *                              masuk sekolah".
 *   Pantauan Kehadiran Siswa : satu baris per SISWA, HANYA SATU HARI.
 *                              Menjawab "hari ini siapa yang bolos".
 *   Rekap Akademik wali murid: per mata pelajaran, tapi untuk SATU ANAK dan
 *                              hanya bisa dibuka orang tuanya sendiri.
 *
 * Yang belum ada: satu baris per JADWAL. Itulah yang menjawab pertanyaan
 * manajemen — mata pelajaran mana yang paling banyak ditinggalkan, jam ke
 * berapa siswa mulai menghilang, dan jadwal mana yang jurnalnya tidak pernah
 * diisi guru sama sekali.
 * ==========================================================================
 *
 * Semua angka dihitung ULANG dari absensi_kbm_siswa setiap kali dipanggil —
 * tidak ada tabel ringkasan yang disimpan. Untuk rentang satu semester pun
 * ini hanya dua query, dan hasilnya tidak pernah bisa basi terhadap koreksi
 * yang dilakukan guru pada jurnalnya.
 */
class RekapKbmService
{
    /**
     * Batas jumlah baris jadwal yang diproses.
     *
     * Bukan hiasan: satu SMK bisa punya ratusan jadwal, dan permintaan
     * rentang "setahun penuh tanpa saringan" pada hosting bersama berujung
     * timeout 500 yang tidak menjelaskan apa-apa. Lebih baik berhenti dengan
     * pesan yang menyuruh mempersempit periode.
     */
    public const MAKS_JADWAL = 400;

    /** Rentang hari terpanjang yang boleh diminta sekaligus. */
    public const MAKS_HARI = 400;

    /**
     * Rekap per jadwal.
     *
     * @param  array{dari:Carbon, sampai:Carbon, kelas_id?:int|null, mapel?:string|null, guru_id?:int|null}  $saring
     * @return array{
     *     dari:Carbon, sampai:Carbon, label_periode:string, dibuat_pada:Carbon,
     *     baris:array<int, array<string, mixed>>, ringkas:array<string, mixed>
     * }
     */
    public function perJadwal(array $saring): array
    {
        $dari = $saring['dari']->copy()->startOfDay();
        $sampai = $saring['sampai']->copy()->startOfDay();

        if ($sampai->lt($dari)) {
            throw new RuntimeException('Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
        }

        if ($dari->diffInDays($sampai) + 1 > self::MAKS_HARI) {
            throw new RuntimeException('Rentang tanggalnya terlalu panjang. Maksimal ' . self::MAKS_HARI . ' hari sekali tarik.');
        }

        $kelasId = $saring['kelas_id'] ?? null;
        $mapel = $saring['mapel'] ?? null;
        $guruId = $saring['guru_id'] ?? null;

        /*
         | Daftar jadwalnya diambil dari tabel JADWAL, bukan dari absensi yang
         | sudah tercatat.
         |
         | Kalau titik berangkatnya absensi, jadwal yang jurnalnya TIDAK PERNAH
         | diisi tidak akan muncul sama sekali — dan justru baris itulah yang
         | paling ingin dilihat kepala sekolah. Rekap yang hanya menampilkan
         | pekerjaan yang sudah dikerjakan tidak bisa menunjukkan pekerjaan
         | yang belum.
         */
        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama_kelas', 'guru:id,nama'])
            ->when($kelasId, fn ($q) => $q->where('kelas_id', $kelasId))
            ->when($mapel, fn ($q) => $q->where('mata_pelajaran', $mapel))
            ->when($guruId, fn ($q) => $q->where('guru_id', $guruId))
            ->get();

        if ($jadwal->count() > self::MAKS_JADWAL) {
            throw new RuntimeException(
                'Jadwal yang cocok terlalu banyak (' . $jadwal->count() . '). '
                . 'Persempit dengan memilih kelas atau mata pelajaran lebih dulu.'
            );
        }

        $hitungHari = $this->hitungKemunculanHari($dari, $sampai);
        $agregat = $this->agregatPerJadwal($jadwal->pluck('id')->all(), $dari, $sampai);

        $baris = $jadwal
            // Jadwal yang HARINYA tidak pernah jatuh di rentang ini dibuang.
            // Menampilkannya dengan angka nol akan terbaca sebagai "guru
            // tidak mengisi jurnal", padahal memang tidak ada pelajarannya.
            ->filter(fn (JadwalPelajaran $j) => ($hitungHari[$this->kunciHari($j)] ?? 0) > 0)
            ->map(function (JadwalPelajaran $j) use ($agregat, $hitungHari) {
                $a = $agregat->get($j->id);

                $total = (int) ($a->total ?? 0);
                $hadir = (int) ($a->hadir ?? 0);
                $pertemuan = (int) ($a->pertemuan ?? 0);
                $mungkin = (int) ($hitungHari[$this->kunciHari($j)] ?? 0);

                return [
                    'jadwal_id' => $j->id,
                    'hari' => $j->hari instanceof Hari ? $j->hari->label() : (string) $j->hari,
                    'urut_hari' => $this->urutHari($j),
                    'jam_mulai' => $j->jam_mulai?->format('H:i') ?? '--:--',
                    'jam' => $j->rentangJam(),
                    'mapel' => $j->mata_pelajaran,
                    'kelas' => $j->kelas?->nama_kelas ?? '—',
                    'guru' => $j->guru?->nama ?? '—',
                    'ruangan' => $j->ruangan ?: '—',

                    'pertemuan' => $pertemuan,
                    'pertemuan_mungkin' => $mungkin,

                    'total' => $total,
                    'hadir' => $hadir,
                    'sakit' => (int) ($a->sakit ?? 0),
                    'izin' => (int) ($a->izin ?? 0),
                    'alpa' => (int) ($a->alpa ?? 0),
                    'bolos' => (int) ($a->bolos ?? 0),

                    // null, BUKAN 0, ketika belum ada catatan sama sekali.
                    // Menuliskan 0% untuk jam yang jurnalnya belum diisi
                    // menuduh seluruh kelas tidak hadir.
                    'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,

                    // Seberapa patuh jurnalnya diisi: berapa dari sekian
                    // pertemuan yang seharusnya ada benar-benar tercatat.
                    'persen_terisi' => $mungkin > 0 ? round($pertemuan / $mungkin * 100, 1) : null,
                ];
            })
            ->sortBy([
                ['urut_hari', 'asc'],
                ['jam_mulai', 'asc'],
                ['kelas', 'asc'],
            ])
            ->values()
            ->all();

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'label_periode' => $this->labelPeriode($dari, $sampai),
            'dibuat_pada' => now(),
            'baris' => $baris,
            'ringkas' => $this->ringkas($baris),
        ];
    }

    /**
     * Rincian per siswa untuk SATU jadwal — dipakai saat baris dibuka.
     *
     * @return array<int, array<string, mixed>>
     */
    public function perSiswa(int $jadwalId, Carbon $dari, Carbon $sampai): array
    {
        $baris = AbsensiKbmSiswa::query()
            ->join('siswa', 'siswa.id', '=', 'absensi_kbm_siswa.siswa_id')
            ->where('absensi_kbm_siswa.jadwal_id', $jadwalId)
            ->whereBetween('absensi_kbm_siswa.tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->groupBy('absensi_kbm_siswa.siswa_id', 'siswa.nama', 'siswa.nis')
            ->orderBy('siswa.nama')
            ->select([
                'absensi_kbm_siswa.siswa_id',
                'siswa.nama',
                'siswa.nis',
            ])
            ->selectRaw('COUNT(*) as total')
            ->selectRaw($this->jumlahStatus(StatusKbm::Hadir) . ' as hadir')
            ->selectRaw($this->jumlahStatus(StatusKbm::Sakit) . ' as sakit')
            ->selectRaw($this->jumlahStatus(StatusKbm::Izin) . ' as izin')
            ->selectRaw($this->jumlahStatus(StatusKbm::Alpa) . ' as alpa')
            ->selectRaw($this->jumlahStatus(StatusKbm::Bolos) . ' as bolos')
            ->get();

        return $baris->map(function ($b) {
            $total = (int) $b->total;
            $hadir = (int) $b->hadir;

            return [
                'nama' => $b->nama,
                'nis' => $b->nis,
                'total' => $total,
                'hadir' => $hadir,
                'sakit' => (int) $b->sakit,
                'izin' => (int) $b->izin,
                'alpa' => (int) $b->alpa,
                'bolos' => (int) $b->bolos,
                'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,
            ];
        })->all();
    }

    /**
     * Merender PDF-nya. Mengembalikan isi berkas (binary), bukan menyimpannya.
     *
     * Laporan ini SELALU dibuat sesuai saringan yang sedang dipakai, jadi
     * tidak masuk akal diarsipkan seperti laporan bulanan otomatis — dua
     * pengguna dengan saringan berbeda menghasilkan dokumen berbeda.
     */
    public function render(array $data): string
    {
        $kelas = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($kelas)) {
            throw new RuntimeException(
                'Paket PDF belum terpasang. Jalankan di server: '
                . 'composer require barryvdh/laravel-dompdf'
            );
        }

        return $kelas::loadView('laporan.rekap-kbm-pdf', $data)
            // Landscape: tabelnya 12 kolom. Di potrait kolom persentasenya
            // terdorong keluar halaman dan hilang tanpa peringatan.
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false])
            ->output();
    }

    /* ===================== PEMBANTU ===================== */

    /**
     * Satu query agregat untuk seluruh jadwal sekaligus.
     *
     * Alternatifnya — menghitung per jadwal di dalam perulangan — berarti
     * ratusan query untuk satu halaman. Di hosting bersama itu bukan sekadar
     * lambat, itu yang membuat halaman mati di tengah jalan.
     */
    private function agregatPerJadwal(array $idJadwal, Carbon $dari, Carbon $sampai): Collection
    {
        if ($idJadwal === []) {
            return collect();
        }

        return AbsensiKbmSiswa::query()
            ->whereIn('jadwal_id', $idJadwal)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->groupBy('jadwal_id')
            ->select('jadwal_id')
            ->selectRaw('COUNT(*) as total')

            // Jumlah pertemuan = banyaknya TANGGAL BERBEDA yang punya catatan,
            // bukan jumlah barisnya. Satu pertemuan menghasilkan sebanyak
            // siswa barisnya; menghitung baris akan melaporkan "32 pertemuan"
            // untuk satu kali pelajaran di kelas berisi 32 anak.
            ->selectRaw('COUNT(DISTINCT tanggal) as pertemuan')

            ->selectRaw($this->jumlahStatus(StatusKbm::Hadir) . ' as hadir')
            ->selectRaw($this->jumlahStatus(StatusKbm::Sakit) . ' as sakit')
            ->selectRaw($this->jumlahStatus(StatusKbm::Izin) . ' as izin')
            ->selectRaw($this->jumlahStatus(StatusKbm::Alpa) . ' as alpa')
            ->selectRaw($this->jumlahStatus(StatusKbm::Bolos) . ' as bolos')
            ->get()
            ->keyBy('jadwal_id');
    }

    /**
     * Potongan SQL "hitung baris berstatus X".
     *
     * Ditulis sebagai SUM(CASE WHEN ...) — bentuk yang dimengerti MySQL
     * (produksi) maupun SQLite (pengujian). FILTER (WHERE ...) lebih rapi
     * tapi tidak ada di MySQL, dan COUNT(IF(...)) tidak ada di SQLite.
     *
     * Nilainya diambil dari enum, bukan diketik ulang sebagai string —
     * 'alpa' di tabel ini beda satu huruf dari 'alpha' di absensi_siswa, dan
     * salah ketik satu huruf menghasilkan kolom nol yang terlihat wajar.
     */
    private function jumlahStatus(StatusKbm $status): string
    {
        return "SUM(CASE WHEN status = '" . $status->value . "' THEN 1 ELSE 0 END)";
    }

    /**
     * Berapa kali setiap hari dalam seminggu muncul di rentang tanggal ini.
     *
     * @return array<string, int>  ['senin' => 4, 'selasa' => 5, ...]
     */
    private function hitungKemunculanHari(Carbon $dari, Carbon $sampai): array
    {
        $peta = [];
        $kursor = $dari->copy();

        while ($kursor->lte($sampai)) {
            // 'senin', 'selasa', ... — sama dengan nilai enum Hari.
            $nama = strtolower($kursor->locale('id')->isoFormat('dddd'));
            $peta[$nama] = ($peta[$nama] ?? 0) + 1;
            $kursor->addDay();
        }

        return $peta;
    }

    private function kunciHari(JadwalPelajaran $j): string
    {
        return $j->hari instanceof Hari ? $j->hari->value : strtolower((string) $j->hari);
    }

    private function urutHari(JadwalPelajaran $j): int
    {
        $urutan = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];
        $posisi = array_search($this->kunciHari($j), $urutan, true);

        return $posisi === false ? 99 : $posisi;
    }

    /** @param  array<int, array<string, mixed>>  $baris */
    private function ringkas(array $baris): array
    {
        $total = array_sum(array_column($baris, 'total'));
        $hadir = array_sum(array_column($baris, 'hadir'));

        return [
            'jumlah_jadwal' => count($baris),
            'pertemuan' => array_sum(array_column($baris, 'pertemuan')),
            'pertemuan_mungkin' => array_sum(array_column($baris, 'pertemuan_mungkin')),
            'belum_terisi' => count(array_filter($baris, fn ($b) => $b['pertemuan'] === 0)),
            'total_catatan' => $total,
            'hadir' => $hadir,
            'sakit' => array_sum(array_column($baris, 'sakit')),
            'izin' => array_sum(array_column($baris, 'izin')),
            'alpa' => array_sum(array_column($baris, 'alpa')),
            'bolos' => array_sum(array_column($baris, 'bolos')),
            'persen' => $total > 0 ? round($hadir / $total * 100, 1) : null,
        ];
    }

    private function labelPeriode(Carbon $dari, Carbon $sampai): string
    {
        if ($dari->isSameDay($sampai)) {
            return $dari->translatedFormat('d F Y');
        }

        if ($dari->isSameMonth($sampai) && $dari->isSameYear($sampai)) {
            return $dari->translatedFormat('d') . '–' . $sampai->translatedFormat('d F Y');
        }

        return $dari->translatedFormat('d M Y') . ' – ' . $sampai->translatedFormat('d M Y');
    }
}
