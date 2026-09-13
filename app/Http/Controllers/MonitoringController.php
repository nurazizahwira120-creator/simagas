<?php

namespace App\Http\Controllers;

use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\JadwalPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Live Monitoring KBM — apa yang SEDANG terjadi di kelas menit ini.
 *
 * Eksklusif untuk Kepala Sekolah & Super Admin. Pembatasannya dilakukan di
 * routes/web.php lewat middleware role, bukan di sini — supaya satu-satunya
 * tempat yang mengatur "siapa boleh membuka apa" tetap berkas rute.
 *
 * ============ PERTANYAAN YANG DIJAWAB HALAMAN INI ============
 * Bukan "berapa persen kehadiran bulan ini" — itu sudah dijawab Laporan
 * Bulanan. Yang ini menjawab satu pertanyaan yang hanya berlaku SEKARANG:
 *
 *     "Jam ini ada 6 kelas berjalan. Adakah yang gurunya belum masuk?"
 *
 * Karena itu isinya sengaja tidak menyimpan apa pun dan tidak punya filter
 * tanggal. Halaman yang menampilkan keadaan kemarin bukan live monitoring.
 * =============================================================
 */
class MonitoringController extends Controller
{
    /**
     * Ambang toleransi keterlambatan, dalam menit.
     *
     * Jadwal yang baru dimulai semenit lalu TIDAK langsung dicap merah. Guru
     * masih berjalan dari ruang guru, menyalakan proyektor, menenangkan kelas
     * — mengisi jurnal adalah hal terakhir yang ia lakukan, bukan pertama.
     *
     * Tanpa ambang ini, setiap pergantian jam pelajaran akan memerahkan
     * seluruh layar selama beberapa menit. Peringatan yang selalu menyala
     * berhenti dibaca orang, dan saat ada kelas yang benar-benar kosong,
     * warnanya tidak lagi berarti apa-apa.
     */
    private const TOLERANSI_MENIT = 10;

    public function index(Request $request)
    {
        /*
         | ============ PENGECEKAN WAKTU DENGAN CARBON ============
         |
         | now() mengikuti timezone aplikasi (config/app.php -> 'timezone').
         | Server hosting umumnya berjalan di UTC; kalau nilainya tidak diset
         | ke Asia/Jakarta, seluruh halaman ini akan meleset TUJUH JAM —
         | dan gejalanya sangat menyesatkan: pukul 09.00 WIB layarnya kosong
         | ("tidak ada kelas berlangsung"), lalu pukul 15.00 WIB tiba-tiba
         | menampilkan jadwal jam pertama.
         |
         | ---- Kenapa format('H:i:s') dikirim ke query ----
         | Perbandingannya terjadi di SQL, langsung terhadap kolom TIME —
         | BUKAN terhadap objek Carbon hasil cast model. Jadi yang dikirim
         | harus berbentuk jam apa adanya, '07:00:00'.
         |
         | Membandingkan jam sebagai teks aman karena urutan leksikografisnya
         | SAMA dengan urutan waktunya: '07:00:00' < '09:30:00' benar sebagai
         | teks maupun sebagai waktu. Itu hanya berlaku kalau digitnya selalu
         | dua (ada nol di depan), dan format('H') menjamin itu; 'G' tidak.
         |
         | Hati-hati membedakannya dengan sisi PHP: di sana $j->jam_mulai
         | SUDAH berupa Carbon (lihat JadwalPelajaran::casts), sehingga
         | memperlakukannya sebagai teks jam justru menghasilkan tanggal.
         | Kesalahan itu sempat terjadi di view halaman ini.
         |
         | ---- Kenapa perbandingannya di SQL, bukan di PHP ----
         | Menarik semua jadwal hari ini lalu menyaringnya dengan filter()
         | akan bekerja juga, tapi ia memindahkan seluruh baris ke memori
         | PHP untuk membuang sebagian besarnya. Sekolah dengan 12 kelas x 10
         | jam pelajaran punya ~120 baris per hari; hanya 6-12 yang sedang
         | berlangsung. Biarkan database yang menyaring.
         */
        $sekarang = Carbon::now();
        $hariIni = Hari::hariIni();
        $jamSekarang = $sekarang->format('H:i:s');

        $jadwalBerlangsung = JadwalPelajaran::query()
            /*
             | Eager load, BUKAN akses relasi di dalam perulangan.
             |
             | Tanpa dua baris ini, view di bawah memanggil $j->guru->nama dan
             | $j->kelas->nama_kelas untuk setiap kartu — 12 kartu berarti 24
             | query tambahan. Halaman ini dibuka kepala sekolah dan dibiarkan
             | terbuka di layar; beban itu berulang setiap kali ia di-refresh.
             |
             | Kolomnya disebut eksplisit, dan nama kolomnya BERBEDA antar
             | tabel — `pegawai` memakai `nama`, `kelas` memakai `nama_kelas`.
             | Menyebut kolom yang tidak ada LOLOS diam-diam di SQLite tapi
             | membalas 500 di MySQL; lihat tests/Feature/KolomEagerLoadTest.
             */
            ->with(['guru:id,nama', 'kelas:id,nama_kelas'])
            ->where('hari', $hariIni->value)
            ->where('jam_mulai', '<=', $jamSekarang)
            ->where('jam_selesai', '>', $jamSekarang)
            ->orderBy('jam_mulai')
            ->get(['id', 'hari', 'jam_mulai', 'jam_selesai', 'mata_pelajaran', 'ruangan', 'kelas_id', 'guru_id']);

        $baris = $this->hitungStatus($jadwalBerlangsung, $sekarang);

        return view('monitoring.live', [
            'baris' => $baris,
            'hariIni' => $hariIni,
            'sekarang' => $sekarang,
            'ringkas' => [
                'total' => count($baris),
                'aman' => collect($baris)->where('status', 'aman')->count(),
                'perhatian' => collect($baris)->where('status', 'perhatian')->count(),
                'kosong' => collect($baris)->where('status', 'kosong')->count(),
            ],
            'toleransi' => self::TOLERANSI_MENIT,
        ]);
    }

    /**
     * Menentukan status live tiap jadwal.
     *
     * ============ DUA QUERY AGREGAT, BUKAN DUA PER JADWAL ============
     * Cara yang paling mudah ditulis adalah memeriksa tabel absensi di dalam
     * perulangan jadwal. Untuk 12 kelas itu berarti 24 query, dan jumlahnya
     * tumbuh seiring sekolah membesar — persis bentuk N+1 yang diminta
     * dihindari.
     *
     * Di bawah, keduanya dikerjakan SEKALI untuk semua jadwal sekaligus,
     * dikelompokkan di database, dan hasilnya dipetakan per jadwal_id.
     *
     * @param  \Illuminate\Support\Collection<int, JadwalPelajaran>  $jadwal
     * @return array<int, array<string, mixed>>
     */
    private function hitungStatus($jadwal, Carbon $sekarang): array
    {
        if ($jadwal->isEmpty()) {
            return [];
        }

        $idJadwal = $jadwal->pluck('id');
        $tanggal = $sekarang->toDateString();

        // (1) Jadwal mana yang jurnalnya SUDAH diisi hari ini.
        //     Cukup tahu ada/tidak, jadi yang diambil hanya jadwal_id-nya.
        $sudahDiisi = AbsensiKbmSiswa::query()
            ->whereIn('jadwal_id', $idJadwal)
            ->whereDate('tanggal', $tanggal)
            ->distinct()
            ->pluck('jadwal_id')
            ->flip();

        /*
         | (2) Jumlah siswa yang TIDAK hadir per jadwal.
         |
         | SUM(CASE WHEN ...) dipakai, bukan COUNT dengan WHERE, supaya satu
         | query saja menghasilkan dua angka sekaligus. Bentuk ini juga
         | portabel: berjalan sama di MySQL produksi maupun SQLite yang
         | dipakai saat pengujian.
         |
         | groupBy + select(kolom yang sama) + selectRaw(agregat) memenuhi
         | ONLY_FULL_GROUP_BY, mode bawaan MySQL 5.7 ke atas. Menyebut kolom
         | yang tidak ikut di-group akan ditolak server dengan galat yang
         | tidak pernah muncul di SQLite.
         */
        $bolos = AbsensiKbmSiswa::query()
            ->whereIn('jadwal_id', $idJadwal)
            ->whereDate('tanggal', $tanggal)
            ->groupBy('jadwal_id')
            ->select('jadwal_id')
            ->selectRaw(
                'SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as jumlah_bolos',
                [StatusKbm::Alpa->value, StatusKbm::Bolos->value],
            )
            ->pluck('jumlah_bolos', 'jadwal_id');

        return $jadwal->map(function (JadwalPelajaran $j) use ($sudahDiisi, $bolos, $sekarang) {
            $terisi = $sudahDiisi->has($j->id);
            $jumlahBolos = (int) ($bolos[$j->id] ?? 0);

            /*
             | Sudah berapa menit jam ini berjalan.
             |
             | jam_mulai disalin ke TANGGAL HARI INI lebih dulu. Kalau tidak,
             | Carbon menguraikan '07:00:00' sebagai 1 Januari tahun berjalan
             | dan selisihnya menjadi ratusan ribu menit — angka yang selalu
             | melewati ambang berapa pun, sehingga setiap kelas langsung
             | dicap kosong sejak detik pertama.
             */
            // format('H:i:s') dipakai, BUKAN (string) $j->jam_mulai: kolomnya
            // di-cast ke Carbon, dan __toString sebuah Carbon menghasilkan
            // 'Y-m-d H:i:s' lengkap dengan tanggalnya — bukan jam yang
            // diharapkan setTimeFromTimeString.
            $mulai = $sekarang->copy()->setTimeFromTimeString($j->jam_mulai->format('H:i:s'));
            $menitBerjalan = (int) $mulai->diffInMinutes($sekarang);

            $status = match (true) {
                // Jurnal terisi DAN ada yang alpa/bolos -> perlu dilihat,
                // tapi bukan kelas kosong. Dibedakan warnanya supaya kepala
                // sekolah tidak mendatangi kelas yang gurunya justru sudah
                // bekerja dengan benar.
                $terisi && $jumlahBolos > 0 => 'perhatian',
                $terisi => 'aman',

                // Belum terisi tapi masih dalam masa toleransi — belum layak
                // disebut kosong. Lihat catatan pada TOLERANSI_MENIT.
                $menitBerjalan < self::TOLERANSI_MENIT => 'menunggu',

                default => 'kosong',
            };

            return [
                'jadwal' => $j,
                'status' => $status,
                'jumlah_bolos' => $jumlahBolos,
                'menit_berjalan' => $menitBerjalan,
                'gaya' => $this->gaya($status),
            ];
        })->all();
    }

    /**
     * Kelas Tailwind per status.
     *
     * Dikumpulkan di satu tempat, bukan ditulis sebagai rangkaian @if di
     * dalam view: warna dan artinya adalah satu keputusan, dan menyebarnya
     * ke belasan baris Blade membuat "merah artinya apa" mustahil dijawab
     * tanpa membaca seluruh view.
     *
     * @return array<string, string>
     */
    private function gaya(string $status): array
    {
        return match ($status) {
            'aman' => [
                'kartu' => 'border-emerald-300 dark:border-emerald-500/40',
                'pita' => 'bg-emerald-500',
                'chip' => 'border-emerald-300 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
                'ikon' => 'check-circle',
                'label' => 'Berjalan normal',
            ],
            'perhatian' => [
                'kartu' => 'border-amber-300 dark:border-amber-500/40',
                'pita' => 'bg-amber-500',
                'chip' => 'border-amber-300 bg-amber-500/10 text-amber-700 dark:text-amber-400',
                'ikon' => 'exclamation-triangle',
                'label' => 'Ada siswa tidak hadir',
            ],
            'menunggu' => [
                'kartu' => 'border-gray-200 dark:border-gray-800',
                'pita' => 'bg-gray-300 dark:bg-gray-700',
                'chip' => 'border-gray-300 bg-gray-500/10 text-brand-muted',
                'ikon' => 'clock',
                'label' => 'Baru dimulai',
            ],
            default => [
                'kartu' => 'border-red-400 dark:border-red-500/50',
                'pita' => 'bg-red-500',
                'chip' => 'border-red-300 bg-red-500/10 text-brand-danger-text',
                'ikon' => 'x-circle',
                'label' => 'Belum ada jurnal',
            ],
        };
    }
}
