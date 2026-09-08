<?php

namespace App\Livewire\Kepsek;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pantauan Kehadiran Siswa — layar pengawasan Kepala Sekolah.
 *
 * MURNI HANYA BACA, sama seperti Pantauan Pegawai: tidak ada method yang
 * menulis ke database. Koreksi absensi tetap dikerjakan wali kelas (absensi
 * gerbang) dan guru mapel (absensi KBM), bukan dari layar ini.
 *
 * Yang membuat halaman ini berguna adalah SELISIH antara dua sumber:
 * kolom "Status Kedatangan" berasal dari scan gerbang, sedangkan kolom
 * "Pantauan KBM" berasal dari jurnal yang diisi guru per jam pelajaran.
 * Anak yang hadir di gerbang tapi hilang di jam ke-3 hanya kelihatan kalau
 * keduanya dibaca berdampingan.
 */
class PantauanSiswa extends Component
{
    public string $tanggal = '';

    public ?int $kelas_id = null;

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
        $this->kelas_id = $this->daftarKelas->first()?->id;
    }

    public function updatedTanggal(): void
    {
        if (! $this->tanggalValid()) {
            $this->tanggal = today()->toDateString();
        }
    }

    /**
     * kelas_id datang dari browser. Nilai yang bukan kelas terdaftar
     * dikembalikan ke kelas pertama — kalau dibiarkan, tabelnya kosong tanpa
     * alasan yang terlihat dan pengguna mengira datanya hilang.
     */
    public function updatedKelasId(): void
    {
        if (! $this->daftarKelas->contains('id', (int) $this->kelas_id)) {
            $this->kelas_id = $this->daftarKelas->first()?->id;
        }
    }

    private function tanggalValid(): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggal)
            && Carbon::hasFormat($this->tanggal, 'Y-m-d');
    }

    #[Computed]
    public function tanggalDipakai(): Carbon
    {
        return $this->tanggalValid()
            ? Carbon::parse($this->tanggal)->startOfDay()
            : today();
    }

    /** @return Collection<int, Kelas> */
    #[Computed]
    public function daftarKelas(): Collection
    {
        return Kelas::orderBy('nama_kelas')->get();
    }

    #[Computed]
    public function kelasTerpilih(): ?Kelas
    {
        return $this->daftarKelas->firstWhere('id', (int) $this->kelas_id);
    }

    /**
     * Jumlah jam pelajaran kelas ini pada HARI dari tanggal yang dipilih —
     * jadi penyebut rasio "4/5 Jam Pelajaran Diikuti".
     *
     * Dihitung dari jadwal, bukan dari jumlah baris absensi KBM yang ada.
     * Bedanya penting: kalau memakai jumlah baris, kelas yang gurunya baru
     * mengisi 2 dari 5 jam akan tampil "2/2 diikuti" alias sempurna —
     * padahal 3 jam sisanya belum diketahui sama sekali.
     */
    #[Computed]
    public function totalJamHariItu(): int
    {
        if (! $this->kelasTerpilih) {
            return 0;
        }

        return JadwalPelajaran::where('kelas_id', $this->kelasTerpilih->id)
            ->where('hari', $this->hariDariTanggal()->value)
            ->count();
    }

    private function hariDariTanggal(): Hari
    {
        return match ($this->tanggalDipakai->dayOfWeek) {
            0 => Hari::Minggu,
            1 => Hari::Senin,
            2 => Hari::Selasa,
            3 => Hari::Rabu,
            4 => Hari::Kamis,
            5 => Hari::Jumat,
            default => Hari::Sabtu,
        };
    }

    /**
     * Satu baris per siswa, lengkap dengan absensi gerbang & rekap KBM-nya.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function baris(): Collection
    {
        $kelas = $this->kelasTerpilih;

        if (! $kelas) {
            return collect();
        }

        $siswa = Siswa::where('kelas_id', $kelas->id)->orderBy('nama')->get();
        $tanggal = $this->tanggalDipakai;

        $gerbang = AbsensiSiswa::whereIn('siswa_id', $siswa->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('siswa_id');

        $kbm = AbsensiKbmSiswa::whereIn('siswa_id', $siswa->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->groupBy('siswa_id');

        return $siswa->map(function (Siswa $s) use ($gerbang, $kbm) {
            $absen = $gerbang->get($s->id);
            $catatanKbm = $kbm->get($s->id, collect());

            $hadirKbm = $catatanKbm->filter(fn (AbsensiKbmSiswa $a) => $a->status === StatusKbm::Hadir)->count();
            $bermasalah = $catatanKbm->filter(
                fn (AbsensiKbmSiswa $a) => in_array($a->status, [StatusKbm::Alpa, StatusKbm::Bolos], true)
            );

            return [
                'siswa' => $s,
                'absen' => $absen,
                'jam' => $absen?->jam_masuk?->format('H:i'),
                'statusGerbang' => $absen?->status,
                'terisiKbm' => $catatanKbm->count(),
                'hadirKbm' => $hadirKbm,
                'bermasalah' => $bermasalah,
                'adaBolos' => $bermasalah->contains(fn (AbsensiKbmSiswa $a) => $a->status === StatusKbm::Bolos),
            ];
        });
    }

    /**
     * Empat kartu metrik di atas tabel.
     *
     * @return array{total: int, hadir: int, tidakHadir: int, bolos: int}
     */
    #[Computed]
    public function metrik(): array
    {
        $b = $this->baris;

        return [
            'total' => $b->count(),
            'hadir' => $b->filter(fn (array $r) => $r['statusGerbang'] === AbsensiStatus::Hadir)->count(),
            // "Tidak Hadir" mencakup yang belum tercatat sama sekali DAN yang
            // tercatat izin/sakit/alpa — dari sudut pandang kepsek, ketiganya
            // sama-sama berarti "anak ini tidak ada di sekolah hari ini".
            'tidakHadir' => $b->filter(fn (array $r) => $r['statusGerbang'] !== AbsensiStatus::Hadir)->count(),
            // Bolos KBM dihitung per SISWA, bukan per jam pelajaran: satu anak
            // yang bolos tiga jam tetap satu anak yang perlu ditindaklanjuti.
            'bolos' => $b->filter(fn (array $r) => $r['adaBolos'])->count(),
        ];
    }

    public function render()
    {
        return view('livewire.kepsek.pantauan-siswa');
    }
}
