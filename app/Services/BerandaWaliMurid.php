<?php

namespace App\Services;

use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Pengumuman;
use App\Models\Siswa;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use App\Support\AnakTerpilih;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi BERANDA wali murid — satu layar yang menjawab pertanyaan orang tua
 * tanpa perlu membuka menu:
 *
 *   1. Anakku sudah sampai sekolah?        -> kartu status (StatusKedatanganAnak)
 *   2. Hari ini belajar apa, sudah masuk?   -> pelajaran hari ini
 *   3. Bulan ini rajin atau tidak?          -> persentase kehadiran bulan ini
 *   4. Ada kabar dari sekolah?              -> pengumuman terbaru
 *
 * Jumlah query tetap — tidak bertambah mengikuti jumlah jam pelajaran atau
 * jumlah anak. Semua pencocokan per baris terjadi di memori.
 */
class BerandaWaliMurid
{
    /** Pengumuman yang lebih tua dari ini tidak lagi dipajang di Beranda. */
    public const HARI_PENGUMUMAN = 30;

    public function __construct(
        private readonly StatusKedatanganAnak $status,
        private readonly KalenderAkademik $kalender,
    ) {
    }

    /**
     * @return array{
     *     anakAnak: Collection<int, Siswa>, anak: Siswa|null, status: array|null,
     *     kontak: array|null, pelajaran: array<int, array<string, mixed>>,
     *     libur: bool, bulan: array<string, mixed>|null, pengumuman: array<string, mixed>|null
     * }
     */
    public function susun(User $user, mixed $anakDiminta = null, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();

        // kelas.waliKelas.pegawai ikut dimuat: tombol "Hubungi Wali Kelas"
        // membaca nomor HP dari sana tanpa query tambahan.
        $anakAnak = $user->siswaWali()
            ->with(['kelas:id,nama_kelas,wali_kelas_id', 'kelas.waliKelas:id,name,no_hp', 'kelas.waliKelas.pegawai:id,user_id,no_hp'])
            ->orderBy('nama')
            ->get();

        $anak = AnakTerpilih::pilih($anakAnak, $anakDiminta);

        if (! $anak) {
            return [
                'anakAnak' => $anakAnak, 'anak' => null, 'status' => null, 'kontak' => null,
                'pelajaran' => [], 'libur' => false, 'bulan' => null,
                'pengumuman' => $this->pengumumanTerbaru($user, $sekarang),
            ];
        }

        $absensiHariIni = AbsensiSiswa::query()
            ->where('siswa_id', $anak->id)
            ->wherePadaTanggal('tanggal', $sekarang->toDateString())
            ->first();

        $status = $this->status->untuk($absensiHariIni, $sekarang);

        // Libur dibaca dari kartu status yang sudah menanyakannya ke
        // kalender — tidak ditanyakan dua kali. Hanya berlaku bila belum ada
        // catatan; siswa yang tetap tercatat hadir di hari libur (kegiatan
        // khusus) tetap ditampilkan jadwalnya.
        $libur = $status['jenis'] === 'libur';

        return [
            'anakAnak' => $anakAnak,
            'anak' => $anak,
            'status' => $status,
            'kontak' => $status['hubungi'] ? $this->status->kontakWaliKelas($anak, $sekarang) : null,
            'pelajaran' => $libur ? [] : $this->pelajaranHariIni($anak, $sekarang),
            'libur' => $libur,
            'bulan' => $this->kehadiranBulanIni($anak, $sekarang),
            'pengumuman' => $this->pengumumanTerbaru($user, $sekarang),
        ];
    }

    /**
     * Jadwal kelas anak hari ini + catatan guru per jam pelajaran.
     *
     * Status "belum diisi guru" sengaja berwarna abu-abu, bukan merah: jurnal
     * yang belum disimpan guru bukan kesalahan anak, dan orang tua tidak
     * boleh membacanya sebagai "anak saya tidak masuk kelas".
     *
     * @return array<int, array<string, mixed>>
     */
    public function pelajaranHariIni(Siswa $anak, Carbon $sekarang): array
    {
        if (! $anak->kelas_id) {
            return [];
        }

        $jadwal = JadwalPelajaran::query()
            ->with('guru:id,nama')
            ->where('kelas_id', $anak->kelas_id)
            ->where('hari', KalenderAkademik::hariDari($sekarang)->value)
            ->orderBy('jam_mulai')
            ->get();

        if ($jadwal->isEmpty()) {
            return [];
        }

        $catatan = AbsensiKbmSiswa::query()
            ->where('siswa_id', $anak->id)
            ->wherePadaTanggal('tanggal', $sekarang->toDateString())
            ->whereIn('jadwal_id', $jadwal->pluck('id'))
            ->get(['id', 'jadwal_id', 'status'])
            ->keyBy('jadwal_id');

        return $jadwal->map(function (JadwalPelajaran $j) use ($catatan, $sekarang) {
            $mulai = $j->jam_mulai ? $sekarang->copy()->setTime($j->jam_mulai->hour, $j->jam_mulai->minute) : null;
            $selesai = $j->jam_selesai ? $sekarang->copy()->setTime($j->jam_selesai->hour, $j->jam_selesai->minute) : null;
            $c = $catatan->get($j->id);

            [$teks, $kelas] = match (true) {
                $c !== null && $c->status !== null => [$c->status->label(), $c->status->kelasBadge()],
                $mulai !== null && $sekarang->lt($mulai) => ['Belum mulai', 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'],
                $selesai !== null && $sekarang->lt($selesai) => ['Berlangsung', 'bg-brand-500/10 text-brand-600 dark:text-brand-400'],
                default => ['Belum diisi guru', 'bg-gray-100 text-gray-600 dark:bg-white/[0.06] dark:text-gray-300'],
            };

            return [
                'jam' => $j->jam_mulai?->format('H.i') ?? '--.--',
                'selesai' => $j->jam_selesai?->format('H.i'),
                'mapel' => $j->mata_pelajaran,
                'guru' => $j->guru?->nama,
                'status' => $teks,
                'kelas_status' => $kelas,
            ];
        })->all();
    }

    /**
     * Kehadiran di gerbang sejak awal bulan sampai hari ini.
     *
     * Rumusnya SAMA dengan halaman Rekap Akademik (hadir ÷ hari yang punya
     * catatan) supaya angka di Beranda dan di halaman rinciannya tidak
     * pernah berbeda.
     *
     * @return array{total: int, hadir: int, sakit: int, izin: int, alpa: int, persen: int|null, bulan: string}
     */
    public function kehadiranBulanIni(Siswa $anak, Carbon $sekarang): array
    {
        $hitung = AbsensiSiswa::query()
            ->where('siswa_id', $anak->id)
            ->whereAntaraTanggal('tanggal', $sekarang->copy()->startOfMonth(), $sekarang->copy()->startOfDay())
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->toBase()
            ->pluck('jumlah', 'status')
            ->map(fn ($n) => (int) $n);

        $total = $hitung->sum();
        $hadir = $hitung->get('hadir', 0);

        return [
            'total' => $total,
            'hadir' => $hadir,
            'sakit' => $hitung->get('sakit', 0),
            'izin' => $hitung->get('izin', 0),
            'alpa' => $hitung->get('alpha', 0),
            'persen' => $total > 0 ? (int) round($hadir / $total * 100) : null,
            'bulan' => $sekarang->translatedFormat('F'),
        ];
    }

    /**
     * Pengumuman terbaru yang DIKIRIM KEPADA akun ini (dari lonceng), dengan
     * isi lengkap dari tabel pengumuman — versi terbaru bila disunting.
     *
     * @return array{judul: string, isi: string, waktu: Carbon, baru: bool}|null
     */
    public function pengumumanTerbaru(User $user, Carbon $sekarang): ?array
    {
        $n = $user->notifications()
            ->where('type', PengumumanBaru::class)
            ->where('created_at', '>=', $sekarang->copy()->subDays(self::HARI_PENGUMUMAN))
            ->latest()
            ->first();

        if (! $n) {
            return null;
        }

        $id = $n->data['pengumuman_id'] ?? null;
        $p = is_numeric($id) ? Pengumuman::query()->find((int) $id, ['id', 'judul', 'isi_pesan']) : null;

        return [
            'judul' => $p?->judul ?? ($n->data['judul'] ?? 'Pengumuman'),
            'isi' => $p?->isi_pesan ?? ($n->data['cuplikan'] ?? ''),
            'waktu' => $n->created_at,
            'baru' => $n->read_at === null,
        ];
    }
}
