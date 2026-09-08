<?php

namespace App\Livewire;

use App\Enums\Hari;
use App\Enums\UserRole;
use App\Models\JadwalPelajaran as ModelJadwal;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Halaman Jadwal Pelajaran — HANYA BACA.
 *
 * Tidak ada aksi tambah/ubah/hapus di sini, dan itu bukan cuma soal tombol
 * yang disembunyikan: komponen ini memang tidak punya satu pun method yang
 * menulis ke database. Penyuntingan jadwal tetap satu pintu, yaitu menu
 * Super Admin > Manajemen Akademik > Jadwal Pelajaran.
 *
 * Yang ditampilkan berbeda per peran:
 *   - Guru / Wali Kelas : hanya jadwal yang ia ajar sendiri.
 *   - Wali Murid        : jadwal KELAS ANAKNYA (bisa lebih dari satu anak).
 *   - Staff             : seluruh jadwal sekolah — staff tidak mengajar, jadi
 *                         "jadwalnya sendiri" tidak ada artinya; yang berguna
 *                         baginya adalah melihat kelas mana sedang dipakai.
 */
class JadwalPelajaran extends Component
{
    /** Nilai filter untuk "Semua Hari" — bukan salah satu case enum Hari. */
    public const SEMUA = 'semua';

    /** Hari yang sedang dipilih di filter; default hari ini. */
    public string $hari_terpilih = '';

    public function mount(): void
    {
        // Hari::hariIni() mengembalikan Minggu kalau hari ini Minggu, padahal
        // tidak ada jadwal di hari itu. Dulu jatuh ke Senin; sekarang jatuh ke
        // "Semua Hari" — di hari libur, yang lebih berguna adalah melihat
        // jadwal sepekan penuh daripada dilempar ke Senin tanpa penjelasan.
        $hariIni = Hari::hariIni();

        $this->hari_terpilih = in_array($hariIni, Hari::hariSekolah(), true)
            ? $hariIni->value
            : self::SEMUA;
    }

    /**
     * Properti publik Livewire bisa diisi apa saja dari browser, jadi nilai
     * yang tidak ada di daftar pilihan dikembalikan ke "Semua Hari". Tanpa ini
     * halaman tidak bocor apa-apa (query-nya tetap terikat parameter), tapi
     * tampilannya jadi membingungkan: dropdown kosong dan tabel kosong tanpa
     * alasan yang terlihat.
     */
    public function updatedHariTerpilih(): void
    {
        if (! in_array($this->hari_terpilih, $this->nilaiPilihanSah(), true)) {
            $this->hari_terpilih = self::SEMUA;
        }
    }

    /** @return array<int, string> */
    private function nilaiPilihanSah(): array
    {
        return array_merge(
            [self::SEMUA],
            array_map(fn (Hari $h) => $h->value, Hari::hariSekolah()),
        );
    }

    public function semuaHariDipilih(): bool
    {
        return $this->hari_terpilih === self::SEMUA;
    }

    /** @return array<int, Hari> */
    #[Computed]
    public function pilihanHari(): array
    {
        return Hari::hariSekolah();
    }

    #[Computed]
    public function pegawai()
    {
        return auth()->user()?->pegawai;
    }

    /**
     * Kelas milik anak-anak wali murid yang sedang login.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function kelasAnak(): Collection
    {
        return auth()->user()
            ->siswaWali()
            ->pluck('kelas_id')
            ->unique()
            ->values();
    }

    /**
     * Query dasar sesuai peran. Dipisah dari jadwalHari() supaya dipakai juga
     * oleh notifikasi "jadwal hari ini" tanpa menduplikasi aturan aksesnya.
     */
    private function query()
    {
        $peran = auth()->user()->role;

        $query = ModelJadwal::query()->with(['kelas', 'guru']);

        if ($peran === UserRole::WaliMurid) {
            return $query->whereIn('kelas_id', $this->kelasAnak);
        }

        if ($peran === UserRole::Staff) {
            return $query;   // seluruh sekolah
        }

        // Guru & Wali Kelas: hanya jadwal miliknya. Kalau akunnya belum
        // ditautkan ke data pegawai, hasilnya harus KOSONG — bukan seluruh
        // jadwal sekolah, yang akan membocorkan data yang bukan haknya.
        return $query->where('guru_id', $this->pegawai?->id ?? 0);
    }

    /** @return Collection<int, ModelJadwal> */
    #[Computed]
    public function jadwalHari(): Collection
    {
        $query = $this->query();

        // "Semua Hari" -> tanpa filter hari sama sekali. Hari yang bukan
        // pilihan sah sudah dikembalikan ke SEMUA oleh updatedHariTerpilih().
        if (! $this->semuaHariDipilih()) {
            $query->where('hari', $this->hari_terpilih);
        }

        return $query->get()
            // Diurutkan di PHP, bukan lewat ORDER BY, karena DUA kolomnya
            // sama-sama tidak bisa diurutkan database dengan benar:
            //   - `hari` disimpan sebagai teks, jadi ORDER BY menghasilkan
            //     urutan alfabetis (jumat, kamis, minggu, rabu, ...);
            //   - `jam_mulai` juga teks, sehingga "10:00" mendarat sebelum
            //     "07:00".
            // Hari::urutan() memberi urutan yang benar untuk yang pertama.
            ->sortBy(fn (ModelJadwal $j) => sprintf(
                '%d-%s',
                $j->hari->urutan(),
                $j->jam_mulai->format('H:i'),
            ))
            ->values();
    }

    /**
     * Jadwal mengajar HARI INI — dipakai untuk notifikasi di atas tabel.
     * Hanya relevan bagi yang benar-benar mengajar.
     *
     * @return Collection<int, ModelJadwal>
     */
    #[Computed]
    public function jadwalHariIni(): Collection
    {
        if (! in_array(auth()->user()->role, [UserRole::Guru, UserRole::WaliKelas], true)) {
            return collect();
        }

        return $this->query()
            ->where('hari', Hari::hariIni()->value)
            ->get()
            ->sortBy(fn (ModelJadwal $j) => $j->jam_mulai->format('H:i'))
            ->values();
    }

    /**
     * Kolom "Guru" hanya berguna bagi yang melihat jadwal ORANG LAIN.
     * Guru & wali kelas hanya melihat jadwalnya sendiri, jadi kolom itu akan
     * berisi namanya sendiri di setiap baris — ruang terbuang.
     */
    #[Computed]
    public function tampilkanGuru(): bool
    {
        return in_array(auth()->user()->role, [UserRole::Staff, UserRole::WaliMurid], true);
    }

    /** Label hari yang sedang dipilih, untuk kalimat di empty state. */
    #[Computed]
    public function labelHariTerpilih(): string
    {
        if ($this->semuaHariDipilih()) {
            return 'Semua Hari';
        }

        return Hari::tryFrom($this->hari_terpilih)?->label() ?? '—';
    }

    #[Computed]
    public function judulSumber(): string
    {
        return match (auth()->user()->role) {
            UserRole::WaliMurid => 'Jadwal kelas anak Anda',
            UserRole::Staff => 'Seluruh jadwal sekolah',
            default => 'Jadwal mengajar Anda',
        };
    }

    public function render()
    {
        return view('livewire.jadwal-pelajaran');
    }
}
