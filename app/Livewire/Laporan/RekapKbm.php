<?php

namespace App\Livewire\Laporan;

use App\Enums\UserRole;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Services\RekapKbmService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Rekap Kehadiran KBM per Jadwal Pelajaran.
 *
 * Satu baris = satu slot jadwal (hari, jam, mapel, kelas, guru) selama
 * rentang tanggal yang dipilih. Lihat App\Services\RekapKbmService untuk
 * penjelasan kenapa laporan ini berbeda dari tiga rekap yang sudah ada.
 *
 * ============ SARINGANNYA IKUT DI URL (#[Url]) ============
 * Supaya halaman yang sudah disaring bisa di-bookmark, di-refresh tanpa
 * kehilangan pilihan, dan yang paling penting: supaya tombol "Unduh PDF"
 * cukup meneruskan query string yang sama ke controller. Tanpa itu, PDF-nya
 * harus dibangun ulang dari state komponen lewat jalur terpisah — dan dua
 * jalur berbeda untuk satu angka adalah cara paling andal membuat layar dan
 * cetakan tidak lagi sama.
 * =========================================================
 */
class RekapKbm extends Component
{
    /** Peran yang boleh membuka halaman ini. */
    public const PERAN_BOLEH = [UserRole::Kepsek, UserRole::SuperAdmin];

    #[Url(as: 'dari', keep: false)]
    public string $dari = '';

    #[Url(as: 'sampai', keep: false)]
    public string $sampai = '';

    #[Url(as: 'kelas', keep: false)]
    public string $kelasId = '';

    #[Url(as: 'mapel', keep: false)]
    public string $mapel = '';

    #[Url(as: 'guru', keep: false)]
    public string $guruId = '';

    /** Baris yang sedang dibuka rinciannya. Tidak ikut ke URL. */
    public ?int $jadwalDibuka = null;

    /** Pesan kalau saringannya terlalu lebar untuk diproses. */
    public ?string $galat = null;

    public function mount(): void
    {
        abort_unless($this->bolehLihat(), 403);

        // Bawaan: dari awal bulan berjalan sampai hari ini. Bukan sebulan
        // penuh ke belakang — kepala sekolah membuka halaman ini untuk
        // melihat bulan yang SEDANG berjalan.
        if ($this->dari === '') {
            $this->dari = now()->startOfMonth()->toDateString();
        }

        if ($this->sampai === '') {
            $this->sampai = now()->toDateString();
        }
    }

    /**
     * Hak akses ditegakkan DI KOMPONEN, bukan hanya di rute.
     *
     * Setiap method Livewire adalah endpoint HTTP tersendiri
     * (/livewire/update) yang TIDAK melewati middleware rute halaman. Tanpa
     * pemeriksaan di sini, siapa pun yang sudah login bisa memanggil method
     * komponen ini langsung dari konsol browser.
     */
    private function bolehLihat(): bool
    {
        return in_array(auth()->user()?->role, self::PERAN_BOLEH, true);
    }

    /* ===================== REAKSI SARINGAN ===================== */

    public function updated($nama, $nilai = null): void
    {
        // Rincian yang sedang terbuka ditutup begitu saringannya berubah.
        // Kalau dibiarkan, panel rincian tetap menampilkan angka jadwal lama
        // di bawah tabel yang isinya sudah berganti.
        if ($nama !== 'jadwalDibuka') {
            $this->jadwalDibuka = null;
            unset($this->rekap);
        }
    }

    public function bukaRincian(int $jadwalId): void
    {
        abort_unless($this->bolehLihat(), 403);

        $this->jadwalDibuka = $this->jadwalDibuka === $jadwalId ? null : $jadwalId;
    }

    public function aturCepat(string $pilihan): void
    {
        abort_unless($this->bolehLihat(), 403);

        [$dari, $sampai] = match ($pilihan) {
            'bulan-ini' => [now()->startOfMonth(), now()],
            'bulan-lalu' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
            'pekan-ini' => [now()->startOfWeek(), now()],
            'semester' => [now()->subMonthsNoOverflow(5)->startOfMonth(), now()],
            default => [now()->startOfMonth(), now()],
        };

        $this->dari = $dari->toDateString();
        $this->sampai = $sampai->toDateString();
        $this->jadwalDibuka = null;
        unset($this->rekap);
    }

    public function bersihkanSaringan(): void
    {
        abort_unless($this->bolehLihat(), 403);

        $this->kelasId = '';
        $this->mapel = '';
        $this->guruId = '';
        $this->jadwalDibuka = null;
        unset($this->rekap);
    }

    /* ===================== DATA ===================== */

    /**
     * Saringan yang sudah dibersihkan dan siap dikirim ke service.
     *
     * Tanggal dari URL adalah masukan pengguna — bisa berupa "abcd" atau
     * "2026-99-99". Diparse dengan penjagaan supaya halamannya menolak
     * dengan sopan, bukan melempar 500.
     */
    private function saringan(): array
    {
        return [
            'dari' => $this->keTanggal($this->dari, now()->startOfMonth()),
            'sampai' => $this->keTanggal($this->sampai, now()),
            'kelas_id' => $this->kelasId !== '' ? (int) $this->kelasId : null,
            'mapel' => $this->mapel !== '' ? $this->mapel : null,
            'guru_id' => $this->guruId !== '' ? (int) $this->guruId : null,
        ];
    }

    private function keTanggal(string $nilai, Carbon $cadangan): Carbon
    {
        try {
            $t = Carbon::createFromFormat('Y-m-d', $nilai);

            return $t === false ? $cadangan->copy() : $t->startOfDay();
        } catch (\Throwable $e) {
            return $cadangan->copy();
        }
    }

    #[Computed]
    public function rekap(): array
    {
        if (! $this->bolehLihat()) {
            abort(403);
        }

        $this->galat = null;

        try {
            return app(RekapKbmService::class)->perJadwal($this->saringan());
        } catch (\Throwable $e) {
            // Batas MAKS_JADWAL / MAKS_HARI sampai ke sini sebagai pesan yang
            // memang ditulis untuk dibaca pengguna, bukan sebagai layar error.
            $this->galat = $e->getMessage();

            return [
                'label_periode' => '—',
                'baris' => [],
                'ringkas' => [
                    'jumlah_jadwal' => 0, 'pertemuan' => 0, 'pertemuan_mungkin' => 0,
                    'belum_terisi' => 0, 'total_catatan' => 0, 'hadir' => 0,
                    'sakit' => 0, 'izin' => 0, 'alpa' => 0, 'bolos' => 0, 'persen' => null,
                ],
            ];
        }
    }

    /** @return array<int, array<string, mixed>> */
    #[Computed]
    public function rincian(): array
    {
        if ($this->jadwalDibuka === null || ! $this->bolehLihat()) {
            return [];
        }

        $s = $this->saringan();

        return app(RekapKbmService::class)->perSiswa($this->jadwalDibuka, $s['dari'], $s['sampai']);
    }

    /** Baris jadwal yang sedang dibuka, untuk judul panel rincian. */
    #[Computed]
    public function barisDibuka(): ?array
    {
        if ($this->jadwalDibuka === null) {
            return null;
        }

        foreach ($this->rekap['baris'] as $b) {
            if ($b['jadwal_id'] === $this->jadwalDibuka) {
                return $b;
            }
        }

        return null;
    }

    /** @return Collection<int, Kelas> */
    #[Computed]
    public function daftarKelas(): Collection
    {
        return Kelas::orderBy('nama_kelas')->get(['id', 'nama_kelas']);
    }

    /**
     * Mata pelajaran yang benar-benar ada di jadwal — bukan daftar tebakan.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function daftarMapel(): array
    {
        return JadwalPelajaran::query()
            ->distinct()
            ->orderBy('mata_pelajaran')
            ->pluck('mata_pelajaran')
            ->all();
    }

    /** @return Collection<int, Pegawai> */
    #[Computed]
    public function daftarGuru(): Collection
    {
        return Pegawai::query()
            ->whereIn('id', JadwalPelajaran::query()->distinct()->pluck('guru_id'))
            ->orderBy('nama')
            ->get(['id', 'nama']);
    }

    /** Query string untuk tombol Unduh PDF — persis saringan di layar. */
    #[Computed]
    public function paramUnduh(): array
    {
        return array_filter([
            'dari' => $this->dari,
            'sampai' => $this->sampai,
            'kelas' => $this->kelasId,
            'mapel' => $this->mapel,
            'guru' => $this->guruId,
        ], fn ($v) => $v !== '' && $v !== null);
    }

    public function render()
    {
        return view('livewire.laporan.rekap-kbm');
    }
}
