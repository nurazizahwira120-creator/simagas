<?php

namespace App\Livewire\Kepsek;

use App\Enums\AbsensiStatus;
use App\Enums\UserRole;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\Pengaturan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pantauan Kehadiran Pegawai — layar pengawasan Kepala Sekolah.
 *
 * MURNI HANYA BACA. Tidak ada satu pun method yang menulis ke database di
 * kelas ini, dan itu disengaja: kepsek mengawasi, bukan mengoreksi absensi.
 * Perbaikan data tetap satu pintu lewat Super Admin, supaya tidak ada dua
 * orang yang bisa mengubah catatan kehadiran yang sama.
 */
class PantauanPegawai extends Component
{
    /** Jam batas terlambat kalau Pengaturan Sistem belum diisi. */
    private const BATAS_CADANGAN = '07:00';

    /** Tanggal yang dilihat, format Y-m-d. */
    public string $tanggal = '';

    /** 'semua' | 'hadir' | 'belum' | 'terlambat' */
    public string $status = 'semua';

    public function mount(): void
    {
        $this->tanggal = today()->toDateString();
    }

    /**
     * Nilai dari browser tidak dipercaya. Tanggal yang tidak berbentuk
     * Y-m-d dikembalikan ke hari ini — bukan dibiarkan membuat Carbon
     * melempar exception dan halamannya jadi 500.
     */
    public function updatedTanggal(): void
    {
        if (! $this->tanggalValid()) {
            $this->tanggal = today()->toDateString();
        }
    }

    public function updatedStatus(): void
    {
        if (! in_array($this->status, ['semua', 'hadir', 'belum', 'terlambat'], true)) {
            $this->status = 'semua';
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

    /** Kolom "Status Mengajar" hanya masuk akal untuk HARI INI. */
    #[Computed]
    public function melihatHariIni(): bool
    {
        return $this->tanggalDipakai->isSameDay(today());
    }

    #[Computed]
    public function batasTerlambat(): string
    {
        return (string) Pengaturan::ambil('batas_terlambat_pegawai', self::BATAS_CADANGAN);
    }

    /**
     * Satu baris per pegawai, sudah lengkap dengan kehadiran & status
     * mengajarnya. Disusun sekali di sini supaya view tidak melakukan query
     * di dalam perulangan (N+1) — dengan 40-an pegawai itu berarti 80-an
     * query per halaman.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function baris(): Collection
    {
        $pegawai = Pegawai::with('user')
            ->orderBy('nama')
            ->get()
            // Super Admin punya baris pegawai tapi SENGAJA tidak punya fitur
            // absensi sama sekali, jadi ia akan selamanya tampil "Belum
            // Hadir" — gangguan tetap di layar pengawasan. Pegawai yang belum
            // punya akun user tetap ditampilkan: mereka orang sungguhan yang
            // kehadirannya bisa diinput manual admin.
            ->reject(fn (Pegawai $p) => $p->user?->role === UserRole::SuperAdmin)
            ->values();

        $tanggal = $this->tanggalDipakai;

        $kehadiran = AbsensiPegawai::whereIn('pegawai_id', $pegawai->pluck('id'))
            ->whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('pegawai_id');

        // Status mengajar dibaca dari absensi_mengajar, yang kuncinya user_id
        // (bukan pegawai_id) — lihat catatan di migrasi 000015.
        $mengajar = $this->melihatHariIni
            ? AbsensiMengajar::whereIn('user_id', $pegawai->pluck('user.id')->filter())
                ->whereDate('waktu_mulai', $tanggal)
                ->orderBy('waktu_mulai')
                ->get()
                ->groupBy('user_id')
            : collect();

        $batas = $this->batasTerlambat;

        return $pegawai->map(function (Pegawai $p) use ($kehadiran, $mengajar, $batas) {
            $absen = $kehadiran->get($p->id);
            $jam = $absen?->jam_masuk?->format('H:i');

            // Urutan pemeriksaannya penting. Catatan yang statusnya izin/
            // sakit/alpa BUKAN "belum hadir" — orangnya sudah dilaporkan,
            // hanya tidak masuk. Menyamakan keduanya membuat kepsek mengejar
            // orang yang sudah berkirim kabar.
            $kategori = match (true) {
                $absen === null => 'belum',
                $absen->status !== AbsensiStatus::Hadir => 'berhalangan',
                $jam !== null && $jam > $batas => 'terlambat',
                default => 'hadir',
            };

            // Scan terakhir yang dipakai: guru bisa mengajar di beberapa
            // ruangan sehari, dan yang ingin diketahui kepsek adalah
            // "sekarang ada di mana".
            $scanTerakhir = $p->user
                ? $mengajar->get($p->user->id)?->last()
                : null;

            return [
                'pegawai' => $p,
                'absen' => $absen,
                'jam' => $jam,
                'kategori' => $kategori,
                'statusAbsen' => $absen?->status,
                'mengajar' => $scanTerakhir,
            ];
        });
    }

    /** Baris sesudah disaring dropdown Status. */
    #[Computed]
    public function barisTersaring(): Collection
    {
        $hasil = match ($this->status) {
            // "Hadir" di dropdown berarti "sudah datang", termasuk yang
            // terlambat — kepsek yang ingin memisahkan keduanya punya
            // pilihan "Terlambat" tersendiri.
            'hadir' => $this->baris->whereIn('kategori', ['hadir', 'terlambat']),
            'terlambat' => $this->baris->where('kategori', 'terlambat'),
            'belum' => $this->baris->whereIn('kategori', ['belum', 'berhalangan']),
            default => $this->baris,
        };

        return $hasil->values();
    }

    /**
     * Angka ringkas di atas tabel.
     *
     * @return array{total: int, hadir: int, terlambat: int, belum: int, berhalangan: int, mengajar: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $b = $this->baris;

        return [
            'total' => $b->count(),
            'hadir' => $b->where('kategori', 'hadir')->count(),
            'terlambat' => $b->where('kategori', 'terlambat')->count(),
            'belum' => $b->where('kategori', 'belum')->count(),
            'berhalangan' => $b->where('kategori', 'berhalangan')->count(),
            'mengajar' => $b->filter(fn (array $r) => $r['mengajar'] !== null)->count(),
        ];
    }

    public function render()
    {
        return view('livewire.kepsek.pantauan-pegawai');
    }
}
