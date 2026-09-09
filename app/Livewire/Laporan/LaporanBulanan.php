<?php

namespace App\Livewire\Laporan;

use App\Enums\UserRole;
use App\Models\MonthlyReport;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Executive View — daftar laporan bulanan hasil Cron Job.
 *
 * Hanya menampilkan; TIDAK membuat laporan. Pembuatannya sepenuhnya
 * pekerjaan App\Console\Commands\BuatLaporanBulanan yang dijalankan
 * penjadwal setiap tanggal 1 pukul 01:00 WIB.
 *
 * ============ KENAPA TIDAK ADA TOMBOL "BUAT SEKARANG" ============
 * Godaannya besar: satu tombol untuk menghasilkan laporan bulan berjalan.
 * Sengaja tidak dibuat, karena merekap seluruh siswa & pegawai sebulan
 * penuh adalah pekerjaan berat yang bisa memakan puluhan detik pada hosting
 * bersama — dan tombol web punya batas waktu. Yang akan terjadi: kepala
 * sekolah menekan tombol, layar mati di tengah jalan, laporannya setengah
 * jadi, lalu ia menekannya lagi.
 *
 * Kalau laporan di luar jadwal memang dibutuhkan, jalurnya lewat Terminal:
 *   php artisan simagas:laporan-bulanan --bulan=2026-08 --paksa
 * ================================================================
 */
class LaporanBulanan extends Component
{
    use WithPagination;

    /** Halaman ini memakai tema pagination TailAdmin yang sama dengan menu lain. */
    protected string $paginationTheme = 'tailwind';

    /** Peran yang boleh membuka halaman ini. */
    public const PERAN_BOLEH = [UserRole::Kepsek, UserRole::SuperAdmin];

    /** Saring per tahun; kosong = semua tahun. */
    public string $tahun = '';

    public function updatedTahun(): void
    {
        // Pindah halaman WAJIB direset saat penyaringnya berubah. Tanpa ini,
        // pengguna yang sedang di halaman 3 lalu memilih tahun yang cuma
        // punya 2 laporan akan melihat tabel kosong — dan mengira datanya
        // hilang.
        $this->resetPage();
    }

    /**
     * Penjagaan hak akses ditegakkan DI KOMPONEN, bukan hanya di rute.
     *
     * Rute memang sudah dibatasi middleware 'role:...', tapi komponen
     * Livewire punya endpoint sendiri (/livewire/update) yang TIDAK melewati
     * middleware rute halaman. Tanpa pemeriksaan di sini, siapa pun yang
     * sudah login bisa memanggil metode komponen ini dari konsol browser.
     */
    private function bolehLihat(): bool
    {
        return in_array(auth()->user()?->role, self::PERAN_BOLEH, true);
    }

    public function mount(): void
    {
        abort_unless($this->bolehLihat(), 403);
    }

    /** @return LengthAwarePaginator<int, MonthlyReport> */
    #[Computed]
    public function daftar(): LengthAwarePaginator
    {
        if (! $this->bolehLihat()) {
            abort(403);
        }

        return MonthlyReport::query()
            ->when($this->tahun !== '', fn ($q) => $q->whereYear('periode', (int) $this->tahun))
            ->orderByDesc('periode')
            ->paginate(12);
    }

    /**
     * Tahun yang benar-benar punya laporan — untuk mengisi dropdown.
     *
     * Diambil dari data, bukan dari rentang tahun yang ditebak. Dropdown
     * berisi tahun yang laporannya tidak ada hanya menghasilkan tabel kosong
     * yang membingungkan.
     *
     * @return array<int, int>
     */
    #[Computed]
    public function daftarTahun(): array
    {
        return MonthlyReport::query()
            ->selectRaw('DISTINCT ' . $this->ekspresiTahun() . ' as thn')
            ->orderByDesc('thn')
            ->pluck('thn')
            ->map(fn ($t) => (int) $t)
            ->all();
    }

    /**
     * Ekspresi SQL "ambil tahun dari kolom tanggal".
     *
     * MySQL dan SQLite menuliskannya berbeda, dan aplikasi ini berjalan di
     * MySQL (produksi) sekaligus SQLite (pengujian). Satu bentuk saja akan
     * membuat halaman ini meledak di salah satunya.
     */
    private function ekspresiTahun(): string
    {
        return MonthlyReport::query()->getConnection()->getDriverName() === 'sqlite'
            ? "strftime('%Y', periode)"
            : 'YEAR(periode)';
    }

    /** Ringkasan untuk kartu di atas tabel. */
    #[Computed]
    public function ringkas(): array
    {
        $terbaru = MonthlyReport::orderByDesc('periode')->first();

        return [
            'total' => MonthlyReport::count(),
            'terbaru' => $terbaru,
        ];
    }

    public function render()
    {
        return view('livewire.laporan.laporan-bulanan');
    }
}
