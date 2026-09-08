<?php

namespace App\Livewire\WaliMurid;

use App\Enums\AbsensiStatus;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\Siswa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Rekap & Laporan Akademik — ringkasan kehadiran anak dalam satu bulan.
 *
 * ================== BATAS FITUR INI ==================
 * Halaman ini SENGAJA hanya berisi KEHADIRAN, belum nilai.
 * Nilai/rapor butuh subsistem tersendiri yang belum ada di database ini
 * sama sekali: tabel nilai, KKM, bobot (tugas/UTS/UAS), dan form input nilai
 * untuk guru. Menampilkan kolom "Nilai" yang isinya selalu kosong akan
 * terlihat seperti fitur rusak, jadi bagiannya diberi keterangan jujur di
 * view. Begitu modul nilai dibuat, halaman inilah tempatnya bergabung.
 * =====================================================
 */
class RekapAkademik extends Component
{
    public ?int $anak_id = null;

    /** Bulan yang dilihat, format 'Y-m'. */
    public string $bulan = '';

    public function mount(): void
    {
        $this->anak_id = $this->daftarAnak->first()?->id;
        $this->bulan = today()->format('Y-m');
    }

    /**
     * Nilai bulan datang dari browser, jadi bentuknya diperiksa. Bulan yang
     * tidak masuk akal dikembalikan ke bulan berjalan — bukan dibiarkan
     * membuat Carbon melempar exception dan halaman jadi 500.
     */
    public function updatedBulan(): void
    {
        if (! $this->bulanValid()) {
            $this->bulan = today()->format('Y-m');
        }
    }

    private function bulanValid(): bool
    {
        return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->bulan);
    }

    #[Computed]
    public function awalBulan(): Carbon
    {
        return $this->bulanValid()
            ? Carbon::createFromFormat('Y-m-d', $this->bulan . '-01')->startOfMonth()
            : today()->startOfMonth();
    }

    /**
     * Pilihan bulan: 12 bulan terakhir sampai bulan berjalan.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function pilihanBulan(): array
    {
        $hasil = [];

        for ($i = 0; $i < 12; $i++) {
            $b = today()->startOfMonth()->subMonths($i);
            $hasil[$b->format('Y-m')] = $b->translatedFormat('F Y');
        }

        return $hasil;
    }

    /** @return Collection<int, Siswa> */
    #[Computed]
    public function daftarAnak(): Collection
    {
        return auth()->user()->siswaWali()->with('kelas')->orderBy('nama')->get();
    }

    /** Dicari di dalam koleksi anak sendiri — lihat catatan di PantauanKbm. */
    #[Computed]
    public function anak(): ?Siswa
    {
        return $this->daftarAnak->firstWhere('id', $this->anak_id)
            ?? $this->daftarAnak->first();
    }

    /**
     * Rekap kehadiran di GERBANG (absensi_siswa) untuk bulan terpilih.
     *
     * @return array{total: int, hadir: int, sakit: int, izin: int, alpa: int, persen: float}
     */
    #[Computed]
    public function rekapGerbang(): array
    {
        $anak = $this->anak;

        if (! $anak) {
            return ['total' => 0, 'hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpa' => 0, 'persen' => 0.0];
        }

        $awal = $this->awalBulan;

        $data = AbsensiSiswa::where('siswa_id', $anak->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->get()
            ->countBy(fn (AbsensiSiswa $a) => $a->status->value);

        $total = $data->sum();
        $hadir = $data->get(AbsensiStatus::Hadir->value, 0);

        return [
            'total' => $total,
            'hadir' => $hadir,
            'sakit' => $data->get(AbsensiStatus::Sakit->value, 0),
            'izin' => $data->get(AbsensiStatus::Izin->value, 0),
            'alpa' => $data->get(AbsensiStatus::Alpha->value, 0),
            'persen' => $total > 0 ? round($hadir / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Rekap kehadiran per MATA PELAJARAN dari absensi KBM.
     *
     * @return Collection<int, array{mapel: string, total: int, hadir: int, sakit: int, izin: int, alpa: int, bolos: int, persen: float}>
     */
    #[Computed]
    public function rekapMapel(): Collection
    {
        $anak = $this->anak;

        if (! $anak) {
            return collect();
        }

        $awal = $this->awalBulan;

        return AbsensiKbmSiswa::with('jadwal')
            ->where('siswa_id', $anak->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->get()
            // Jadwal yang sudah dihapus menyisakan absensi tanpa mapel; baris
            // begitu dikelompokkan sebagai "(jadwal dihapus)" alih-alih
            // membuat halaman error saat mengakses ->mata_pelajaran pada null.
            ->groupBy(fn (AbsensiKbmSiswa $a) => $a->jadwal?->mata_pelajaran ?? '(jadwal dihapus)')
            ->map(function (Collection $baris, string $mapel) {
                $hitung = $baris->countBy(fn (AbsensiKbmSiswa $a) => $a->status->value);
                $total = $baris->count();
                $hadir = $hitung->get(StatusKbm::Hadir->value, 0);

                return [
                    'mapel' => $mapel,
                    'total' => $total,
                    'hadir' => $hadir,
                    'sakit' => $hitung->get(StatusKbm::Sakit->value, 0),
                    'izin' => $hitung->get(StatusKbm::Izin->value, 0),
                    'alpa' => $hitung->get(StatusKbm::Alpa->value, 0),
                    'bolos' => $hitung->get(StatusKbm::Bolos->value, 0),
                    'persen' => $total > 0 ? round($hadir / $total * 100, 1) : 0.0,
                ];
            })
            ->sortBy('mapel')
            ->values();
    }

    /**
     * Daftar jam pelajaran yang berstatus alpa/bolos — bagian yang paling
     * dicari orang tua, dan yang paling merepotkan kalau harus dicari sendiri
     * satu per satu di timeline harian.
     *
     * @return Collection<int, AbsensiKbmSiswa>
     */
    #[Computed]
    public function daftarBolos(): Collection
    {
        $anak = $this->anak;

        if (! $anak) {
            return collect();
        }

        $awal = $this->awalBulan;

        return AbsensiKbmSiswa::with('jadwal.guru')
            ->where('siswa_id', $anak->id)
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->copy()->endOfMonth()->toDateString()])
            ->whereIn('status', [StatusKbm::Alpa->value, StatusKbm::Bolos->value])
            ->orderByDesc('tanggal')
            ->get();
    }

    public function render()
    {
        return view('livewire.wali-murid.rekap-akademik');
    }
}
