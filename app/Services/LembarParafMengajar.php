<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Enums\UserRole;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\JadwalPelajaran;
use App\Models\PengajuanIzinGuru;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Menyusun data LEMBAR PARAF GURU MENGAJAR untuk satu hari.
 *
 * ============ UNTUK APA LEMBAR INI ============
 * Lembar kertas cadangan untuk laporan mengajar. Setiap guru membubuhkan
 * paraf manual pada jam pelajarannya. Kalau suatu saat catatan di sistem
 * dipersoalkan — HP guru mati sehingga tidak bisa scan QR, server sempat
 * gangguan, atau ada guru yang merasa sudah mengajar tapi tercatat tidak —
 * lembar bertanda tangan inilah pembandingnya.
 *
 * Karena tujuannya pembuktian, lembar ini juga mencetak APA YANG DICATAT
 * SISTEM pada saat dicetak (kolom "Catatan Sistem"), dan waktu cetaknya
 * tercantum di setiap halaman. Lembar yang dicetak pukul 06.30 wajar
 * kosong seluruhnya; lembar yang dicetak pukul 14.00 bisa dibandingkan
 * baris per baris dengan paraf di sebelahnya.
 * ==============================================
 *
 * ============ ATURAN KOLOM PARAF ============
 *   Izin DISETUJUI (pengajuan izin guru)  -> "GURU IZIN" + jenis ITT/IDT
 *   Absensi harian berstatus izin          -> "GURU IZIN"
 *   Absensi harian berstatus sakit         -> "GURU SAKIT"
 *   Izin masih MENUNGGU persetujuan        -> kolom paraf tetap KOSONG,
 *                                            hanya diberi catatan kecil
 *
 * Pengajuan yang belum disetujui sengaja tidak mengisi kolom paraf.
 * Pengajuan itu masih bisa ditolak; kalau kolomnya sudah tercetak
 * "GURU IZIN", lembar bukti justru memuat keterangan yang belum sah.
 * Aturannya sama dengan PenerapIzinGuru: izin baru berlaku setelah
 * disetujui.
 * ============================================
 *
 * Seluruh data diambil dengan jumlah query yang TETAP (tidak bertambah
 * mengikuti jumlah jadwal): jadwal, izin disetujui, izin menunggu, absensi
 * pegawai, sesi mengajar, dan nama kepala sekolah. Pencocokan per baris
 * terjadi di memori.
 */
class LembarParafMengajar
{
    /** Pilihan urutan baris yang boleh diminta. */
    public const URUTAN = [
        'jam' => 'Urut jam pelajaran',
        'guru' => 'Urut nama guru',
    ];

    /**
     * Batas jumlah baris. Satu hari di SMK normalnya puluhan jadwal;
     * ratusan berarti data jadwalnya bermasalah (mis. impor ganda), dan
     * merender PDF sebesar itu di hosting bersama berakhir dengan timeout.
     */
    public const MAKS_BARIS = 400;

    public function __construct(
        private readonly PencocokSesiMengajar $pencocok,
        private readonly KalenderAkademik $kalender,
    ) {
    }

    /**
     * @return array{
     *     tanggal: Carbon, hari: string, urut: string, libur: string|null,
     *     baris: array<int, array<string, mixed>>, ringkas: array<string, int>,
     *     kepsek: array{nama: string|null, nip: string|null}
     * }
     */
    public function susun(Carbon $tanggal, string $urut = 'jam'): array
    {
        $tanggal = $tanggal->copy()->startOfDay();
        $urut = array_key_exists($urut, self::URUTAN) ? $urut : 'jam';
        $hari = KalenderAkademik::hariDari($tanggal);

        // (1) Jadwal hari itu, beserta kelas & guru dalam satu eager load.
        //     user_id guru ikut diambil karena izin dan sesi mengajar
        //     berkunci pada AKUN (users.id), sedangkan jadwal berkunci pada
        //     data PEGAWAI (pegawai.id).
        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama_kelas', 'guru:id,nama,nip,user_id'])
            ->where('hari', $hari->value)
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();

        if ($jadwal->count() > self::MAKS_BARIS) {
            throw new RuntimeException(
                'Jadwal pada hari ini terlalu banyak (' . $jadwal->count() . ' baris). '
                . 'Periksa kemungkinan data jadwal terimpor ganda.'
            );
        }

        $idPegawai = $jadwal->pluck('guru_id')->filter()->unique()->values()->all();
        $idAkun = $jadwal->pluck('guru.user_id')->filter()->unique()->values()->all();

        $izinDisetujui = $this->izinDisetujui($idAkun, $tanggal);
        $izinMenunggu = $this->izinMenunggu($idAkun, $tanggal);
        $absensiHarian = $this->absensiHarian($idPegawai, $tanggal);
        $sesiPerGuru = $this->sesiMengajar($idAkun, $tanggal);

        $sekarang = now();

        // Sesi yang sudah dipasangkan ke satu jadwal tidak boleh dipakai
        // jadwal lain. Diproses berurutan jam (urutan query di atas) supaya
        // sesi pagi jatuh ke jadwal pagi lebih dulu.
        $sesiDipakai = [];

        $baris = $jadwal->map(function (JadwalPelajaran $j) use (
            $tanggal, $sekarang, $izinDisetujui, $izinMenunggu, $absensiHarian, $sesiPerGuru, &$sesiDipakai
        ) {
            $akun = $j->guru?->user_id;

            $sesi = $akun
                ? $this->pencocok->pilihDari($sesiPerGuru->get($akun, collect()), $j, $sesiDipakai)
                : null;

            if ($sesi) {
                $sesiDipakai[] = $sesi->id;
            }

            return [
                'jadwal_id' => $j->id,
                'jam_mulai' => $j->jam_mulai?->format('H:i') ?? '--:--',
                'jam' => $j->jam_mulai && $j->jam_selesai ? $j->rentangJam() : '—',
                'kelas' => $j->kelas?->nama_kelas ?? '—',
                'mapel' => $j->mata_pelajaran,
                'ruangan' => $j->ruangan ?: null,
                'guru' => $j->guru?->nama ?? '(guru belum diatur)',
                'nip' => $j->guru?->nip ?: null,
                'izin' => $this->keadaanIzin(
                    $akun ? $izinDisetujui->get($akun) : null,
                    $j->guru_id ? $absensiHarian->get($j->guru_id) : null,
                ),
                'izin_menunggu' => $akun !== null && $izinMenunggu->has($akun),
                'sistem' => $this->catatanSistem($sesi, $j, $tanggal, $sekarang),
            ];
        });

        $baris = $this->urutkan($baris, $urut)->values()
            ->map(fn (array $b, int $i) => ['no' => $i + 1] + $b)
            ->all();

        return [
            'tanggal' => $tanggal,
            'hari' => $hari->label(),
            'urut' => $urut,
            'libur' => $this->alasanLibur($tanggal),
            'baris' => $baris,
            'ringkas' => $this->ringkas($baris),
            'kepsek' => $this->kepalaSekolah(),
        ];
    }

    /**
     * Merender PDF A4 potrait. Mengembalikan isi berkas, tidak menyimpannya:
     * lembar ini dicetak sesuai kebutuhan hari itu dan paraf aslinya ada di
     * kertas, bukan di server.
     */
    public function render(array $data): string
    {
        $kelas = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($kelas)) {
            throw new RuntimeException(
                'Paket PDF belum terpasang. Jalankan di server: composer require barryvdh/laravel-dompdf'
            );
        }

        $pdf = $kelas::loadView('laporan.lembar-paraf-mengajar-pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption(['isRemoteEnabled' => false]);

        /*
         | Nomor "Halaman x dari y" digambar SESUDAH render, langsung ke
         | canvas. Jumlah halaman baru diketahui setelah seluruh tabel
         | ditata; counter(pages) di CSS dicetak dompdf sebagai "dari 0".
         | page_text() mengganti {PAGE_NUM}/{PAGE_COUNT} di setiap halaman
         | tanpa perlu mengaktifkan PHP di dalam template (isPhpEnabled),
         | yang sengaja tetap mati.
         |
         | Koordinat dalam poin; A4 potrait = 595 x 842. Posisi y sejajar
         | dengan kaki halaman di template (margin bawah 18 mm).
         */
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $huruf = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        // 5.25 pt = 7 px, ukuran yang sama dengan teks kaki di template.
        // Rata kanan terhadap margin kanan 12 mm (34 pt); lebarnya diukur
        // dari contoh dua digit supaya halaman ke-10 pun tidak keluar tepi.
        $ukuran = 5.25;
        $lebar = $dompdf->getFontMetrics()->getTextWidth('Halaman 99 dari 99', $huruf, $ukuran);
        $dompdf->getCanvas()->page_text(595.28 - 34 - $lebar, 800, 'Halaman {PAGE_NUM} dari {PAGE_COUNT}', $huruf, $ukuran, [0.29, 0.33, 0.39]);

        return $pdf->output();
    }

    /* ===================== PENGAMBILAN DATA (jumlah query tetap) ===================== */

    /** @return Collection<int, PengajuanIzinGuru> dikunci users.id */
    private function izinDisetujui(array $idAkun, Carbon $tanggal): Collection
    {
        if ($idAkun === []) {
            return collect();
        }

        // orderBy id naik + keyBy: kalau ada dua pengajuan yang tumpang
        // tindih, yang TERBARU menimpa yang lama.
        return PengajuanIzinGuru::query()
            ->berlakuPada($tanggal->toDateString())
            ->whereIn('guru_id', $idAkun)
            ->orderBy('id')
            ->get(['id', 'guru_id', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai'])
            ->keyBy('guru_id');
    }

    /** @return Collection<int, PengajuanIzinGuru> dikunci users.id */
    private function izinMenunggu(array $idAkun, Carbon $tanggal): Collection
    {
        if ($idAkun === []) {
            return collect();
        }

        return PengajuanIzinGuru::query()
            ->menunggu()
            ->whereIn('guru_id', $idAkun)
            ->whereDate('tanggal_mulai', '<=', $tanggal->toDateString())
            ->whereDate('tanggal_selesai', '>=', $tanggal->toDateString())
            ->get(['id', 'guru_id'])
            ->keyBy('guru_id');
    }

    /**
     * Absensi harian pegawai pada tanggal itu.
     *
     * Dibaca SELAIN pengajuan izin guru karena izin juga bisa tercatat lewat
     * jalur lain (form izin/sakit pegawai). Tanpa ini, guru yang sakit dan
     * sudah tercatat sakit di absensi hariannya tetap tercetak dengan kolom
     * paraf kosong seolah ia mangkir.
     *
     * whereDate(), bukan where(): kolom `tanggal` tersimpan sebagai
     * '2026-09-13 00:00:00' — lihat catatan panjang di PenerapIzinGuru.
     *
     * @return Collection<int, AbsensiPegawai> dikunci pegawai.id
     */
    private function absensiHarian(array $idPegawai, Carbon $tanggal): Collection
    {
        if ($idPegawai === []) {
            return collect();
        }

        return AbsensiPegawai::query()
            ->whereIn('pegawai_id', $idPegawai)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->get(['id', 'pegawai_id', 'status'])
            ->keyBy('pegawai_id');
    }

    /** @return Collection<int, Collection<int, AbsensiMengajar>> dikelompokkan per users.id */
    private function sesiMengajar(array $idAkun, Carbon $tanggal): Collection
    {
        if ($idAkun === []) {
            return collect();
        }

        return AbsensiMengajar::query()
            ->whereIn('user_id', $idAkun)
            ->whereDate('waktu_mulai', $tanggal->toDateString())
            ->orderBy('waktu_mulai')
            ->get(['id', 'user_id', 'kode_kelas', 'waktu_mulai', 'waktu_selesai'])
            ->groupBy('user_id');
    }

    /** @return array{nama: string|null, nip: string|null} */
    private function kepalaSekolah(): array
    {
        $kepsek = User::query()
            ->where('role', UserRole::Kepsek->value)
            ->with('pegawai:id,user_id,nip')
            ->orderBy('id')
            ->first(['id', 'name']);

        return [
            'nama' => $kepsek?->name,
            'nip' => $kepsek?->pegawai?->nip ?: null,
        ];
    }

    /* ===================== ATURAN PER BARIS ===================== */

    /**
     * Isi kolom paraf untuk guru yang tidak hadir secara sah — atau null
     * kalau kolomnya harus dibiarkan kosong untuk ditandatangani.
     *
     * Pengajuan izin guru didahulukan karena membawa jenisnya (ITT/IDT),
     * yang penting bagi guru piket: IDT berarti ada tugas yang harus
     * dibagikan ke kelas.
     *
     * @return array{label: string, rinci: string|null}|null
     */
    private function keadaanIzin(?PengajuanIzinGuru $pengajuan, ?AbsensiPegawai $harian): ?array
    {
        if ($pengajuan) {
            return [
                'label' => 'GURU IZIN',
                'rinci' => $pengajuan->jenis_izin->label(),
            ];
        }

        return match ($harian?->status) {
            AbsensiStatus::Izin => ['label' => 'GURU IZIN', 'rinci' => null],
            AbsensiStatus::Sakit => ['label' => 'GURU SAKIT', 'rinci' => null],
            default => null,
        };
    }

    /**
     * Teks kolom "Catatan Sistem" — apa yang tercatat dari scan QR ruangan.
     *
     * @return array{teks: string, nada: string}  nada: ok | kurang | nihil | netral
     */
    private function catatanSistem(?AbsensiMengajar $sesi, JadwalPelajaran $j, Carbon $tanggal, Carbon $sekarang): array
    {
        if ($sesi) {
            $mulai = $sesi->waktu_mulai->format('H:i');

            return $sesi->waktu_selesai
                ? ['teks' => 'Scan ' . $mulai . '–' . $sesi->waktu_selesai->format('H:i'), 'nada' => 'ok']
                : ['teks' => 'Scan ' . $mulai . ', belum diakhiri', 'nada' => 'kurang'];
        }

        // Jam pelajarannya belum tiba saat dicetak -> "belum waktunya",
        // BUKAN "tidak ada scan". Mencetak "tidak ada scan" pukul 06.30
        // untuk pelajaran pukul 10.00 akan terbaca sebagai tuduhan.
        $mulaiJadwal = $j->jam_mulai
            ? $tanggal->copy()->setTime($j->jam_mulai->hour, $j->jam_mulai->minute)
            : $tanggal->copy()->endOfDay();

        if ($mulaiJadwal->gt($sekarang)) {
            return ['teks' => 'Belum waktunya', 'nada' => 'netral'];
        }

        return ['teks' => 'Tidak ada scan', 'nada' => 'nihil'];
    }

    private function urutkan(Collection $baris, string $urut): Collection
    {
        return $urut === 'guru'
            ? $baris->sortBy([['guru', 'asc'], ['jam_mulai', 'asc'], ['kelas', 'asc']])
            : $baris->sortBy([['jam_mulai', 'asc'], ['kelas', 'asc']]);
    }

    private function alasanLibur(Carbon $tanggal): ?string
    {
        // Kalender yang gagal dibaca tidak boleh menggagalkan cetakan —
        // keterangan libur hanya pelengkap, jadwalnya tetap dicetak.
        try {
            return $this->kalender->alasanBukanKbm($tanggal);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $baris
     * @return array<string, int>
     */
    private function ringkas(array $baris): array
    {
        $koleksi = collect($baris);

        return [
            'jadwal' => $koleksi->count(),
            'guru' => $koleksi->pluck('guru')->unique()->count(),

            // Dihitung per GURU, bukan per baris: guru izin yang punya
            // empat jam mengajar tetap satu orang yang izin.
            'guru_izin' => $koleksi->whereNotNull('izin')->pluck('guru')->unique()->count(),
            'tercatat' => $koleksi->filter(fn ($b) => in_array($b['sistem']['nada'], ['ok', 'kurang'], true))->count(),
        ];
    }
}
