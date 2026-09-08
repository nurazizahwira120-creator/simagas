<?php

namespace App\Livewire\Kepsek;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiPegawai;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar Izin Pegawai — HANYA BACA.
 *
 * Tidak ada satu pun method yang mengubah data di komponen ini, dan itu
 * disengaja: peran Kepala Sekolah di sistem ini mengawasi, bukan mengedit.
 * Kalau nanti ada kebutuhan "menyetujui/menolak", itu fitur baru dengan
 * kolom statusnya sendiri — bukan tombol yang menimpa data absensi yang
 * sudah tercatat.
 *
 * Filternya bulan+tahun, bukan rentang tanggal bebas, karena pertanyaan yang
 * dijawab halaman ini memang bulanan ("bulan ini siapa saja yang izin") dan
 * dua kotak tanggal justru menambah dua cara untuk salah isi.
 */
class DaftarIzinPegawai extends Component
{
    use WithPagination;

    public int $bulan;

    public int $tahun;

    /** Kata kunci nama pegawai — kosong berarti semua. */
    public string $cari = '';

    public function mount(): void
    {
        $this->bulan = (int) now()->month;
        $this->tahun = (int) now()->year;
    }

    /**
     * Reset ke halaman 1 setiap kali filternya berubah. Tanpa ini, pindah ke
     * halaman 3 lalu mengganti bulan menampilkan "tidak ada data" padahal
     * datanya ada — cuma tidak sampai halaman 3.
     */
    public function updated($properti): void
    {
        if (in_array($properti, ['bulan', 'tahun', 'cari'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Rentang tahun yang punya data, plus tahun berjalan.
     *
     * Sengaja TIDAK memakai `selectRaw('YEAR(tanggal)')`. YEAR() adalah
     * fungsi khusus MySQL — jalan di Laragon, tapi langsung melempar
     * "no such function: YEAR" di SQLite, jadi halaman ini tidak bisa diuji
     * otomatis sama sekali. Mengambil tanggal terkecil & terbesar lalu
     * membentang tahunnya di PHP memberi hasil yang sama tanpa terikat
     * satu database.
     */
    #[Computed]
    public function pilihanTahun(): array
    {
        $paling = AbsensiPegawai::query()
            ->selectRaw('MIN(tanggal) as awal, MAX(tanggal) as akhir')
            ->first();

        $sekarang = (int) now()->year;

        if (! $paling || ! $paling->awal) {
            return [$sekarang];
        }

        $dari = min((int) Carbon::parse($paling->awal)->year, $sekarang);
        $sampai = max((int) Carbon::parse($paling->akhir)->year, $sekarang);

        return collect(range($dari, $sampai))->sortDesc()->values()->all();
    }

    #[Computed]
    public function daftar()
    {
        return AbsensiPegawai::query()
            ->with('pegawai')
            ->whereIn('status', [AbsensiStatus::Izin->value, AbsensiStatus::Sakit->value])
            ->whereYear('tanggal', $this->tahun)
            ->whereMonth('tanggal', $this->bulan)
            ->when($this->cari !== '', function ($q) {
                $q->whereHas('pegawai', fn ($p) => $p->where('nama', 'like', '%' . $this->cari . '%'));
            })
            ->orderByDesc('tanggal')
            ->orderBy('id')
            ->paginate(15);
    }

    /** Ringkasan kecil di atas tabel. */
    #[Computed]
    public function ringkasan(): array
    {
        $dasar = AbsensiPegawai::query()
            ->whereYear('tanggal', $this->tahun)
            ->whereMonth('tanggal', $this->bulan);

        return [
            'izin' => (clone $dasar)->where('status', AbsensiStatus::Izin->value)->count(),
            'sakit' => (clone $dasar)->where('status', AbsensiStatus::Sakit->value)->count(),
        ];
    }

    #[Computed]
    public function namaBulan(): string
    {
        return Carbon::create($this->tahun, $this->bulan, 1)->translatedFormat('F Y');
    }

    public function render()
    {
        return view('livewire.kepsek.daftar-izin-pegawai', [
            'daftarBulan' => collect(range(1, 12))
                ->mapWithKeys(fn ($b) => [$b => Carbon::create(null, $b, 1)->translatedFormat('F')])
                ->all(),
        ]);
    }
}
