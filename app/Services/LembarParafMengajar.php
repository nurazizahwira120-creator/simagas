<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AbsensiMengajar;
use App\Models\JadwalEkskul;
use App\Models\JadwalPelajaran;
use App\Models\SesiEkskul;
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
 * ============ BAGIAN B: PEMBINA EKSTRAKURIKULER ============
 * Ekskul yang terjadwal pada hari itu dicetak di tabel kedua dengan aturan
 * kolom paraf yang SAMA: pembina yang izinnya sah tertulis "GURU IZIN" /
 * "GURU SAKIT", izin menunggu hanya diberi catatan kecil. Catatan Sistem
 * membaca sesi ekskul (scan QR ekskul -> Akhiri Sesi).
 *
 * Pelatih dari luar sekolah (kolom pembina_luar, tanpa baris pegawai) tetap
 * mendapat baris paraf — justru merekalah yang paling butuh bukti kertas,
 * karena tidak punya akun untuk scan QR.
 *
 * Libur yang dipakai untuk ekskul hanya AGENDA libur di Kalender
 * Pendidikan, bukan pola libur mingguan — aturan yang sama dengan
 * AturanSesiEkskul::libur(), karena ekskul memang sering berjalan di hari
 * tanpa KBM.
 * ===========================================================
 *
 * Seluruh data diambil dengan jumlah query yang TETAP (tidak bertambah
 * mengikuti jumlah jadwal): jadwal, jadwal ekskul + pembinanya, izin
 * disetujui, izin menunggu, absensi pegawai, sesi mengajar, sesi ekskul,
 * dan nama kepala sekolah. Pencocokan per baris terjadi di memori.
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
        private readonly StatusBerhalanganGuru $berhalangan,
    ) {
    }

    /**
     * @return array{
     *     tanggal: Carbon, hari: string, urut: string, libur: string|null,
     *     baris: array<int, array<string, mixed>>, ekskul: array<int, array<string, mixed>>,
     *     ringkas: array<string, int>,
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

        // (2) Ekskul hari itu. Kolom `hari` ekskul berupa teks bebas
        //     ("Senin", "senin ") — dibandingkan setelah LOWER+TRIM, pola
        //     yang sama dengan Monitoring KBM dan AturanSesiEkskul.
        $jadwalEkskul = JadwalEkskul::query()
            ->with('pembina:id,nama,nip,user_id')
            ->whereRaw('LOWER(TRIM(hari)) = ?', [AturanSesiEkskul::hariDari($tanggal)->value])
            ->orderBy('jam_mulai')
            ->orderBy('nama_ekskul')
            ->orderBy('id')
            ->get();

        $jumlahBaris = $jadwal->count() + $jadwalEkskul->count();

        if ($jumlahBaris > self::MAKS_BARIS) {
            throw new RuntimeException(
                'Jadwal pada hari ini terlalu banyak (' . $jumlahBaris . ' baris). '
                . 'Periksa kemungkinan data jadwal terimpor ganda.'
            );
        }

        $idAkun = $jadwal->pluck('guru.user_id')->filter()->unique()->values()->all();

        // Aturan "berhalangan" dipusatkan di StatusBerhalanganGuru supaya
        // lembar ini, halaman Guru Inval, dan Live Monitoring selalu
        // sepakat. Daftar gurunya diambil dari eager load di atas, jadi
        // tidak ada query tambahan per guru.
        //
        // Pembina ekskul DIGABUNG ke daftar yang sama sebelum ditanyakan:
        // satu kali tanya untuk guru mapel + pembina, bukan dua kali.
        // Hasilnya dikunci pegawai.id, jadi guru yang sekaligus pembina
        // otomatis mendapat keadaan yang sama di kedua tabel.
        $daftarGuru = $jadwal->pluck('guru')
            ->concat($jadwalEkskul->map(fn (JadwalEkskul $e) => $this->pembinaPegawai($e)))
            ->filter()
            ->unique('id')
            ->values();
        $berhalangan = $this->berhalangan->pada($daftarGuru, $tanggal);
        $izinMenunggu = $this->berhalangan->menunggu($daftarGuru, $tanggal);
        $sesiPerGuru = $this->sesiMengajar($idAkun, $tanggal);

        $sekarang = now();

        // Sesi ↔ jadwal dipasangkan per guru untuk sehari penuh — satu sesi
        // hanya untuk satu jadwal, aturan yang sama dengan Jurnal guru dan
        // Monitoring KBM (PencocokSesiMengajar::pasangkan).
        $pasangan = [];

        foreach ($jadwal->filter(fn (JadwalPelajaran $j) => $j->guru?->user_id)->groupBy('guru.user_id') as $akun => $jadwalGuru) {
            $pasangan += $this->pencocok->pasangkan($jadwalGuru, $sesiPerGuru->get($akun, collect()));
        }

        $baris = $jadwal->map(function (JadwalPelajaran $j) use (
            $tanggal, $sekarang, $berhalangan, $izinMenunggu, $pasangan
        ) {
            $sesi = $pasangan[$j->id] ?? null;

            return [
                'jadwal_id' => $j->id,
                'jam_mulai' => $j->jam_mulai?->format('H:i') ?? '--:--',
                'jam' => $j->jam_mulai && $j->jam_selesai ? $j->rentangJam() : '—',
                'kelas' => $j->kelas?->nama_kelas ?? '—',
                'mapel' => $j->mata_pelajaran,
                'ruangan' => $j->ruangan ?: null,
                'guru' => $j->guru?->nama ?? '(guru belum diatur)',
                'nip' => $j->guru?->nip ?: null,
                'izin' => $this->keadaanIzin($j->guru_id ? $berhalangan->get($j->guru_id) : null),
                'izin_menunggu' => $j->guru_id !== null && $izinMenunggu->has($j->guru_id),
                'sistem' => $this->catatanSistem($sesi, $j, $tanggal, $sekarang),
            ];
        });

        $baris = $this->urutkan($baris, $urut)->values()
            ->map(fn (array $b, int $i) => ['no' => $i + 1] + $b)
            ->all();

        $ekskul = $this->barisEkskul($jadwalEkskul, $tanggal, $sekarang, $berhalangan, $izinMenunggu, $urut);

        return [
            'tanggal' => $tanggal,
            'hari' => $hari->label(),
            'urut' => $urut,
            'libur' => $this->alasanLibur($tanggal),
            'baris' => $baris,
            'ekskul' => $ekskul,
            'ringkas' => $this->ringkas($baris) + $this->ringkasEkskul($ekskul),
            'kepsek' => $this->kepalaSekolah(),
        ];
    }

    /* ===================== BAGIAN B: EKSKUL ===================== */

    /**
     * Baris paraf pembina ekskul. Nomor urut dimulai lagi dari 1 karena
     * dicetak sebagai tabel tersendiri (bagian B).
     *
     * @param  Collection<int, JadwalEkskul>  $jadwalEkskul
     * @return array<int, array<string, mixed>>
     */
    private function barisEkskul(
        Collection $jadwalEkskul,
        Carbon $tanggal,
        Carbon $sekarang,
        Collection $berhalangan,
        Collection $izinMenunggu,
        string $urut,
    ): array {
        if ($jadwalEkskul->isEmpty()) {
            return [];
        }

        // Satu query untuk seluruh sesi ekskul hari itu. Indeks unik
        // (jadwal_ekskul_id, tanggal) menjamin paling banyak satu sesi per
        // ekskul per hari, jadi keyBy aman.
        $sesi = SesiEkskul::query()
            ->whereIn('jadwal_ekskul_id', $jadwalEkskul->pluck('id'))
            ->wherePadaTanggal('tanggal', $tanggal->toDateString())
            ->get(['id', 'jadwal_ekskul_id', 'waktu_mulai', 'waktu_selesai'])
            ->keyBy('jadwal_ekskul_id');

        // Agenda libur dibaca SEKALI untuk seluruh ekskul, memakai aturan
        // AturanSesiEkskul::libur() — aturan yang sama yang menolak scan QR
        // ekskul di hari libur. AturanSesiEkskul sengaja dibuat dengan
        // instans kalender MILIK lembar ini: KalenderAkademik menyimpan
        // agenda per bulan di memori, jadi alasanLibur() dan pemeriksaan
        // ini berbagi satu query agenda, bukan dua.
        // Kalender yang gagal dibaca tidak boleh menggagalkan cetakan.
        try {
            $libur = (new AturanSesiEkskul($this->kalender))->libur($tanggal);
        } catch (\Throwable $e) {
            report($e);
            $libur = null;
        }

        $baris = $jadwalEkskul->map(function (JadwalEkskul $e) use (
            $tanggal, $sekarang, $berhalangan, $izinMenunggu, $sesi, $libur
        ) {
            $pembina = $this->pembinaPegawai($e);

            return [
                'jadwal_ekskul_id' => $e->id,
                // Jam kosong diurutkan paling akhir, bukan paling awal.
                'jam_mulai' => filled($e->jam_mulai) ? JadwalEkskul::jam($e->jam_mulai) : '99:99',
                'jam' => filled($e->jam_mulai) && filled($e->jam_selesai) ? $e->rentangJam() : '—',
                'ekskul' => $e->nama_ekskul ?: '(tanpa nama)',
                'pembina' => ($nama = $e->namaPembina()) !== '-' ? $nama : '(pembina belum diatur)',
                'nip' => $pembina?->nip ?: null,
                'pembina_luar' => $pembina === null && $e->namaPembina() !== '-',
                'izin' => $this->keadaanIzin($pembina ? $berhalangan->get($pembina->id) : null),
                'izin_menunggu' => $pembina !== null && $izinMenunggu->has($pembina->id),
                'sistem' => $this->catatanSistemEkskul($sesi->get($e->id), $e, $tanggal, $sekarang, $libur),
            ];
        });

        // Nama dibandingkan secara "natural" & tanpa beda huruf besar:
        // "Futsal 2" sebelum "Futsal 10", "rohis" sejajar "Rohis".
        $nama = fn (string $kolom) => fn (array $a, array $b) => strnatcasecmp($a[$kolom], $b[$kolom]);
        $jam = fn (array $a, array $b) => strcmp($a['jam_mulai'], $b['jam_mulai']);

        $baris = $urut === 'guru'
            ? $baris->sortBy([$nama('pembina'), $jam, $nama('ekskul')])
            : $baris->sortBy([$jam, $nama('ekskul')]);

        return $baris->values()
            ->map(fn (array $b, int $i) => ['no' => $i + 1] + $b)
            ->all();
    }

    /**
     * Pembina ekskul sebagai model Pegawai, atau null untuk pelatih luar.
     *
     * getRelationValue(), bukan $e->pembina — alasannya sama dengan
     * JadwalEkskul::namaPembina(): di skema lama `pembina` adalah KOLOM
     * teks, dan atribut didahulukan di atas relasi.
     */
    private function pembinaPegawai(JadwalEkskul $e): ?\App\Models\Pegawai
    {
        if ($e->pembina_id === null) {
            return null;
        }

        $orang = $e->getRelationValue('pembina');

        return $orang instanceof \App\Models\Pegawai ? $orang : null;
    }

    /**
     * Catatan Sistem untuk ekskul — kalimatnya sengaja SAMA dengan KBM
     * supaya kepala sekolah membaca kedua tabel dengan cara yang sama.
     *
     * @return array{teks: string, nada: string}
     */
    private function catatanSistemEkskul(?SesiEkskul $sesi, JadwalEkskul $e, Carbon $tanggal, Carbon $sekarang, ?string $libur): array
    {
        if ($sesi && $sesi->waktu_mulai) {
            $mulai = $sesi->waktu_mulai->format('H:i');

            return $sesi->waktu_selesai
                ? ['teks' => 'Scan ' . $mulai . '–' . $sesi->waktu_selesai->format('H:i'), 'nada' => 'ok']
                : ['teks' => 'Scan ' . $mulai . ', belum diakhiri', 'nada' => 'kurang'];
        }

        // Hari libur agenda: sesi ekskul memang DITOLAK sistem pada hari
        // itu (AturanSesiEkskul::alasanTidakBisaMulai), jadi "Tidak ada
        // scan" akan menyalahkan pembina atas sesuatu yang mustahil.
        if ($libur !== null) {
            return ['teks' => 'Libur', 'nada' => 'netral'];
        }

        // Jam mulai kosong/rusak -> dianggap akhir hari, sehingga tidak
        // pernah tercetak "Tidak ada scan" untuk jadwal yang jamnya pun
        // tidak jelas sebelum hari itu berakhir.
        $mulaiJadwal = filled($e->jam_mulai) && preg_match('/^\s*(\d{1,2}):(\d{2})/', (string) $e->jam_mulai, $m)
            ? $tanggal->copy()->setTime((int) $m[1], (int) $m[2])
            : $tanggal->copy()->endOfDay();

        if ($mulaiJadwal->gt($sekarang)) {
            return ['teks' => 'Belum waktunya', 'nada' => 'netral'];
        }

        return ['teks' => 'Tidak ada scan', 'nada' => 'nihil'];
    }

    /**
     * @param  array<int, array<string, mixed>>  $ekskul
     * @return array<string, int>
     */
    private function ringkasEkskul(array $ekskul): array
    {
        $koleksi = collect($ekskul);

        return [
            'ekskul' => $koleksi->count(),
            'ekskul_izin' => $koleksi->whereNotNull('izin')->pluck('pembina')->unique()->count(),
            'ekskul_tercatat' => $koleksi->filter(fn ($b) => in_array($b['sistem']['nada'], ['ok', 'kurang'], true))->count(),
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

    /** @return Collection<int, Collection<int, AbsensiMengajar>> dikelompokkan per users.id */
    private function sesiMengajar(array $idAkun, Carbon $tanggal): Collection
    {
        if ($idAkun === []) {
            return collect();
        }

        return AbsensiMengajar::query()
            ->whereIn('user_id', $idAkun)
            ->wherePadaTanggal('waktu_mulai', $tanggal->toDateString())
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
     * Isi kolom paraf untuk guru yang tidak hadir secara SAH — atau null
     * kalau kolomnya harus dibiarkan kosong untuk ditandatangani.
     *
     * Hanya keadaan yang sah (izin disetujui, izin/sakit di absensi harian)
     * yang dicetak. Guru ALPA sengaja tidak: mencetak "GURU ALPA" di kolom
     * paraf akan membuatnya terlihat seperti keterangan resmi, dan kolom itu
     * tetap harus kosong sebagai bukti tidak ada paraf.
     *
     * @param  array{label:string, rinci:string|null, sah:bool}|null  $keadaan
     * @return array{label: string, rinci: string|null}|null
     */
    private function keadaanIzin(?array $keadaan): ?array
    {
        if (! $keadaan || ! $keadaan['sah']) {
            return null;
        }

        return ['label' => $keadaan['label'], 'rinci' => $keadaan['rinci']];
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
