<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Laporan Absensi Harian — kehadiran siswa DAN pegawai untuk satu tanggal
 * dalam satu tampilan.
 *
 * Berbeda dari LaporanController & AbsensiPegawaiLaporanController yang
 * merekap satu bulan penuh per orang; halaman ini menjawab pertanyaan
 * "hari ini siapa saja yang belum hadir?" dan ikut menandai yang terlambat
 * berdasarkan batas jam di menu Pengaturan.
 *
 * ============ SARINGAN ============
 *   ?kelas=ID    hanya siswa kelas itu (panel pegawai disembunyikan supaya
 *                fokus — pegawai tidak punya kelas).
 *   ?status=...  semua | belum | terlambat | izin_sakit | alpa — berlaku
 *                untuk siswa dan pegawai.
 * Ringkasan "per kelas" selalu dihitung dari SELURUH siswa, sehingga
 * kelas yang paling banyak belum absen langsung terlihat dan bisa diklik.
 * ==================================
 */
class LaporanHarianController extends Controller
{
    /** Pilihan saringan status => label. */
    public const STATUS = [
        'semua' => 'Semua status',
        'belum' => 'Belum absen',
        'terlambat' => 'Terlambat',
        'izin_sakit' => 'Izin / Sakit',
        'alpa' => 'Alpa',
    ];

    public function index(Request $request)
    {
        $tanggalInput = $request->query('tanggal');

        $tanggal = ($tanggalInput && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalInput))
            ? Carbon::createFromFormat('Y-m-d', $tanggalInput)->startOfDay()
            : now()->startOfDay();

        $batas = Pengaturan::ambilBanyak([
            'batas_terlambat_siswa' => '07:15',
            'batas_terlambat_pegawai' => '07:00',
        ]);

        $daftarKelas = Kelas::query()->orderBy('nama_kelas')->get(['id', 'nama_kelas']);

        // Nilai saringan dari URL tidak dipercaya: kelas harus ada di daftar,
        // status harus salah satu pilihan. Selain itu dianggap "semua".
        $kelas = $daftarKelas->firstWhere('id', (int) $request->query('kelas'));
        $status = array_key_exists((string) $request->query('status'), self::STATUS) ? (string) $request->query('status') : 'semua';

        $siswa = $this->rekapSiswa($tanggal, $batas['batas_terlambat_siswa'], $kelas, $status);

        return view('super-admin.laporan-harian', [
            'tanggal' => $tanggal,
            'batasSiswa' => $batas['batas_terlambat_siswa'],
            'batasPegawai' => $batas['batas_terlambat_pegawai'],
            'daftarKelas' => $daftarKelas,
            'kelas' => $kelas,
            'status' => $status,
            'pilihanStatus' => self::STATUS,
            'perKelas' => $siswa['per_kelas'],
            'siswa' => $siswa,
            // Pegawai tidak punya kelas: saat satu kelas dipilih, panelnya
            // disembunyikan agar halaman fokus pada siswa kelas itu.
            'pegawai' => $kelas ? null : $this->rekapPegawai($tanggal, $batas['batas_terlambat_pegawai'], $status),
        ]);
    }

    /**
     * Kategori kehadiran satu orang — dipakai saringan DAN warna label,
     * supaya keduanya tidak mungkin berbeda.
     *
     * @return 'belum'|'terlambat'|'hadir'|'izin'|'sakit'|'alpa'
     */
    public static function kategori(AbsensiSiswa|AbsensiPegawai|null $absensi, string $batas): string
    {
        if ($absensi === null) {
            return 'belum';
        }

        return match ($absensi->status) {
            // Terlambat dihitung dari batas jam di menu Pengaturan.
            AbsensiStatus::Hadir => ($absensi->jam_masuk && $absensi->jam_masuk->format('H:i') > $batas) ? 'terlambat' : 'hadir',
            AbsensiStatus::Izin => 'izin',
            AbsensiStatus::Sakit => 'sakit',
            default => 'alpa',
        };
    }

    /**
     * @return array{baris: Collection, total: int, hadir: int, belum: int, per_kelas: Collection}
     */
    private function rekapSiswa(Carbon $tanggal, string $batas, ?Kelas $kelas, string $status): array
    {
        $absensi = AbsensiSiswa::query()
            ->wherePadaTanggal('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        // SELURUH siswa sekali ambil: ringkasan per kelas membutuhkan semua
        // kelas, dan menyaring kelas di memori lebih murah daripada query kedua.
        $semua = Siswa::query()
            ->with('kelas:id,nama_kelas')
            ->orderBy('nama')
            ->get(['id', 'nama', 'nis', 'kelas_id'])
            ->map(fn (Siswa $s) => [
                'nama' => $s->nama,
                'nomor' => $s->nis,
                'grup' => $s->kelas?->nama_kelas ?? '-',
                'kelas_id' => $s->kelas_id,
                'absensi' => $absensi->get($s->id),
                'kategori' => self::kategori($absensi->get($s->id), $batas),
            ]);

        $perKelas = $semua
            ->filter(fn (array $b) => $b['kelas_id'])
            ->groupBy('kelas_id')
            ->map(fn (Collection $baris, $id) => [
                'id' => (int) $id,
                'nama' => $baris->first()['grup'],
                'total' => $baris->count(),
                'belum' => $baris->where('kategori', 'belum')->count(),
            ])
            // Kelas dengan siswa belum absen terbanyak di depan.
            ->sortBy([['belum', 'desc'], ['nama', 'asc']])
            ->values();

        $sekelas = $kelas ? $semua->where('kelas_id', $kelas->id)->values() : $semua;

        return $this->ringkas($sekelas, $status) + ['per_kelas' => $perKelas];
    }

    /**
     * @return array{baris: Collection, total: int, hadir: int, belum: int}
     */
    private function rekapPegawai(Carbon $tanggal, string $batas, string $status): array
    {
        $absensi = AbsensiPegawai::query()
            ->wherePadaTanggal('tanggal', $tanggal)
            ->get()
            ->keyBy('pegawai_id');

        $semua = Pegawai::query()
            ->orderBy('nama')
            ->get()
            ->map(fn (Pegawai $p) => [
                'nama' => $p->nama,
                'nomor' => $p->nip,
                'grup' => $p->jabatan,
                'absensi' => $absensi->get($p->id),
                'kategori' => self::kategori($absensi->get($p->id), $batas),
            ]);

        return $this->ringkas($semua, $status);
    }

    /**
     * Hitungan ringkas dari SEMUA baris (sebelum saringan status), lalu baris
     * yang ditampilkan disaring statusnya.
     *
     * @return array{baris: Collection, total: int, hadir: int, belum: int}
     */
    private function ringkas(Collection $baris, string $status): array
    {
        $cocok = match ($status) {
            'belum' => ['belum'],
            'terlambat' => ['terlambat'],
            'izin_sakit' => ['izin', 'sakit'],
            'alpa' => ['alpa'],
            default => null,
        };

        return [
            'baris' => $cocok ? $baris->whereIn('kategori', $cocok)->values() : $baris,
            'total' => $baris->count(),
            // Terlambat tetap hadir — sama seperti hitungan sebelumnya.
            'hadir' => $baris->whereIn('kategori', ['hadir', 'terlambat'])->count(),
            'belum' => $baris->where('kategori', 'belum')->count(),
        ];
    }
}
