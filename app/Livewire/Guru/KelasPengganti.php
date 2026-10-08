<?php

namespace App\Livewire\Guru;

use App\Enums\StatusKbm;
use App\Enums\UserRole;
use App\Models\AbsensiKbmSiswa;
use App\Models\JadwalPelajaran;
use App\Models\User;
use App\Services\KalenderAkademik;
use App\Services\PencatatAbsensiKbm;
use App\Services\StatusBerhalanganGuru;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * KELAS PENGGANTI — absensi KBM siswa untuk kelas yang gurunya berhalangan.
 *
 * ============ MASALAH YANG DISELESAIKAN ============
 * Jurnal & Absen Kelas biasa (JurnalAbsenKelas) hanya terbuka untuk guru
 * PEMILIK jadwal, dan hanya setelah guru itu absen datang + scan QR ruangan.
 * Ketika gurunya izin atau sakit, ketiga syarat itu mustahil terpenuhi —
 * akibatnya daftar hadir siswa di kelas itu TERKUNCI sepanjang jam
 * pelajaran: anak yang bolos di jam itu tidak tercatat, wali muridnya tidak
 * mendapat peringatan, dan rekap KBM menampilkan jam itu sebagai "belum
 * diisi" tanpa penjelasan.
 * ===================================================
 *
 * ============ ATURAN HALAMAN INI ============
 *  - Yang tampil hanya jadwal HARI INI yang gurunya BERHALANGAN menurut
 *    StatusBerhalanganGuru (izin disetujui, izin/sakit/alpa di absensi
 *    harian). Kelas yang gurunya hadir tetap hanya bisa diisi gurunya
 *    sendiri lewat halaman Jurnal & Absen Kelas.
 *  - Pengisi: guru lain, wali kelas, kepala sekolah, guru piket, super
 *    admin. TIDAK
 *    perlu scan QR ruangan (keputusan sekolah): guru piket menangani banyak
 *    kelas sekaligus. Sebagai gantinya, AKUN pengisinya tercatat di setiap
 *    baris (kolom diisi_oleh) dan tampil di layar ini.
 *  - Guru yang berhalangan tidak bisa mengisi kelasnya sendiri dari sini:
 *    secara resmi ia sedang tidak hadir.
 *  - Terbuka mulai 15 menit sebelum jam pelajaran sampai akhir hari yang
 *    sama. Batas akhirnya lebih longgar daripada jurnal guru karena guru
 *    piket sering baru sempat mencatat setelah berkeliling.
 *  - Penyimpanannya memakai PencatatAbsensiKbm — aturan bolos, izin gerbang,
 *    dan peringatan WhatsApp ke wali murid SAMA PERSIS dengan jurnal guru.
 * ============================================
 *
 * Seluruh aturan akses diperiksa ULANG di setiap method publik. Method
 * Livewire adalah endpoint HTTP tersendiri; properti publik ($jadwalId)
 * ikut dikirim browser dan tidak boleh dipercaya.
 */
class KelasPengganti extends Component
{
    /** Peran yang boleh mengisi sebagai pengganti. */
    public const PERAN_BOLEH = [UserRole::Guru, UserRole::WaliKelas, UserRole::Kepsek, UserRole::GuruPiket, UserRole::SuperAdmin];

    /** Kelas terbuka sekian menit sebelum jam pelajarannya dimulai. */
    public const TOLERANSI_MENIT = 15;

    public ?int $jadwalId = null;

    /** @var array<int|string, string> siswa_id => nilai StatusKbm */
    public array $status = [];

    /** @var array<int|string, string> */
    public array $keterangan = [];

    public ?array $notif = null;

    public function mount(): void
    {
        $this->pastikanBerhak();
    }

    /* ===================== DATA ===================== */

    /** Alasan hari ini bukan hari KBM, atau null. */
    #[Computed]
    public function libur(): ?string
    {
        try {
            return app(KalenderAkademik::class)->alasanBukanKbm(today());
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Jadwal hari ini yang gurunya berhalangan, beserta keadaan pengisiannya.
     *
     * Jumlah query tetap: jadwal (+ kelas & guru), status berhalangan,
     * rekap pengisian, nama pengisi.
     *
     * @return Collection<int, array<string, mixed>> dikunci jadwal_id
     */
    #[Computed]
    public function daftarKelas(): Collection
    {
        if ($this->libur) {
            return collect();
        }

        $hariIni = today();

        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama_kelas', 'guru:id,nama,user_id'])
            ->where('hari', KalenderAkademik::hariDari($hariIni)->value)
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();

        $berhalangan = app(StatusBerhalanganGuru::class)->pada($jadwal->pluck('guru')->filter(), $hariIni);

        $saya = auth()->id();

        $jadwal = $jadwal
            ->filter(fn (JadwalPelajaran $j) => $j->guru_id && $berhalangan->has($j->guru_id))
            // Guru yang berhalangan tidak mengisi kelasnya sendiri dari sini.
            ->reject(fn (JadwalPelajaran $j) => $j->guru?->user_id !== null && $j->guru->user_id === $saya)
            ->values();

        if ($jadwal->isEmpty()) {
            return collect();
        }

        // Satu query agregat untuk seluruh kelas: sudah diisi? oleh siapa?
        // MAX(diisi_oleh) cukup karena satu kali simpan menulis pengisi yang
        // sama ke seluruh baris kelas itu.
        $rekap = AbsensiKbmSiswa::query()
            ->whereIn('jadwal_id', $jadwal->pluck('id'))
            ->wherePadaTanggal('tanggal', $hariIni->toDateString())
            ->groupBy('jadwal_id')
            ->select('jadwal_id')
            ->selectRaw('COUNT(*) as jumlah')
            ->selectRaw('MAX(diisi_oleh) as pengisi')
            ->selectRaw('MAX(updated_at) as terakhir')
            ->get()
            ->keyBy('jadwal_id');

        $namaPengisi = User::query()
            ->whereIn('id', $rekap->pluck('pengisi')->filter()->unique())
            ->pluck('name', 'id');

        $sekarang = now();

        return $jadwal->mapWithKeys(function (JadwalPelajaran $j) use ($berhalangan, $rekap, $namaPengisi, $sekarang, $hariIni) {
            $r = $rekap->get($j->id);
            $buka = $hariIni->copy()
                ->setTime($j->jam_mulai->hour, $j->jam_mulai->minute)
                ->subMinutes(self::TOLERANSI_MENIT);

            return [$j->id => [
                'jadwal' => $j,
                'alasan' => $berhalangan->get($j->guru_id),
                'terisi' => $r !== null && (int) $r->jumlah > 0,
                'jumlah' => (int) ($r->jumlah ?? 0),
                // NULL pada catatan lama = diisi guru jadwalnya sendiri.
                'pengisi' => $r?->pengisi ? ($namaPengisi[$r->pengisi] ?? 'Akun terhapus') : ($r ? $j->guru?->nama : null),
                'diisi_pada' => $r?->terakhir ? Carbon::parse($r->terakhir)->format('H:i') : null,
                'buka_pukul' => $buka->format('H:i'),
                'terbuka' => $sekarang->gte($buka),
            ]];
        });
    }

    /** Jadwal yang sedang dibuka formnya — selalu divalidasi ulang. */
    #[Computed]
    public function dipilih(): ?array
    {
        if (! $this->jadwalId) {
            return null;
        }

        $item = $this->daftarKelas->get($this->jadwalId);

        return $item && $item['terbuka'] ? $item : null;
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

    /* ===================== AKSI ===================== */

    public function pilih(int $jadwalId): void
    {
        $this->pastikanBerhak();
        $this->notif = null;
        $this->segarkan();

        $item = $this->daftarKelas->get($jadwalId);

        if (! $item) {
            $this->pesan('error', 'Kelas tidak tersedia',
                'Kelas ini tidak sedang membutuhkan pengganti. Mungkin status gurunya sudah berubah — muat ulang halaman.');

            return;
        }

        if (! $item['terbuka']) {
            $this->pesan('warn', 'Belum waktunya',
                'Absensi kelas ini baru bisa diisi mulai pukul ' . $item['buka_pukul'] . '.');

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
        $this->pastikanBerhak();
        $this->notif = null;
        $this->segarkan();

        $item = $this->dipilih;

        // Diperiksa ulang SAAT MENYIMPAN, bukan hanya saat dibuka: guru
        // aslinya bisa saja datang dan izinnya dibatalkan di antara kedua
        // saat itu. Sejak saat itu kelasnya kembali menjadi milik gurunya.
        if (! $item) {
            $this->pesan('error', 'Tidak bisa menyimpan',
                'Kelas ini tidak lagi terbuka untuk pengganti — gurunya mungkin sudah tidak berhalangan, atau jadwalnya berubah. Muat ulang halaman.');
            $this->reset('jadwalId');

            return;
        }

        try {
            $hasil = $this->pencatat()->simpan(
                $item['jadwal'],
                today(),
                $this->daftarSiswa,
                $this->status,
                $this->keterangan,
                auth()->id(),
            );
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan absensi KBM pengganti.', [
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

        $j = $item['jadwal'];

        $this->pesan('ok',
            'Absensi ' . ($j->kelas?->nama_kelas ?? 'kelas') . ' tersimpan',
            $this->pencatat()->kalimatHasil($hasil) . ' Tercatat diisi oleh Anda sebagai pengganti.'
        );

        $this->reset('jadwalId', 'status', 'keterangan');
        $this->segarkan();
    }

    public function render()
    {
        return view('livewire.guru.kelas-pengganti');
    }

    /* ===================== PEMBANTU ===================== */

    private function pastikanBerhak(): void
    {
        abort_unless(in_array(auth()->user()?->role, self::PERAN_BOLEH, true), 403);
    }

    private function segarkan(): void
    {
        unset(
            $this->libur,
            $this->daftarKelas,
            $this->dipilih,
            $this->daftarSiswa,
            $this->hadirDiGerbang,
            $this->izinDariGerbang,
        );
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
