<?php

namespace App\Livewire\Monitoring;

use App\Enums\StatusKbm;
use App\Enums\UserRole;
use App\Models\AbsensiKbmSiswa;
use App\Models\JadwalPelajaran;
use App\Models\PencatatanIzin;
use App\Services\PencocokSesiMengajar;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Panel detail satu sesi KBM — dibuka dari kartu di Live Monitoring.
 *
 * ============ KENAPA KOMPONEN TERPISAH, BUKAN DIMUAT BERSAMA KARTUNYA ============
 * Kartu di layar utama hanya butuh tiga angka per jadwal, dan MonitoringController
 * sudah mengambilnya dengan DUA query agregat untuk SELURUH jadwal sekaligus.
 *
 * Detail di bawah jauh lebih berat: daftar siswa, sesi mengajar, surat izin.
 * Kalau semuanya ikut dimuat di awal untuk 12 kartu sekaligus, halaman yang
 * dibuka kepala sekolah dan dibiarkan terbuka sepanjang hari akan menarik
 * data yang 11 dari 12-nya tidak pernah dilihat.
 *
 * Komponen ini memuat data HANYA untuk jadwal yang benar-benar diklik, dan
 * hanya saat diklik.
 * ================================================================================
 */
class DetailSesi extends Component
{
    /** Jadwal yang sedang dibuka; null = panel tertutup. */
    public ?int $jadwalId = null;

    /**
     * Peran yang boleh membuka panel ini.
     *
     * Diperiksa DI DALAM komponen, bukan hanya lewat rute halamannya.
     * Method Livewire adalah endpoint HTTP tersendiri: siapa pun yang sudah
     * login bisa memanggil $wire.buka({jadwal: 7}) dari konsol browser,
     * bahkan tanpa pernah bisa membuka halaman Live Monitoring.
     */
    private function pastikanBerhak(): void
    {
        abort_unless(
            in_array(auth()->user()?->role, [UserRole::SuperAdmin, UserRole::Kepsek], true),
            403,
        );
    }

    #[On('buka-detail-sesi')]
    public function buka(int $jadwal): void
    {
        $this->pastikanBerhak();

        $this->jadwalId = $jadwal;

        // Cache #[Computed] dibuang supaya panel yang dibuka berturut-turut
        // untuk dua jadwal berbeda tidak menampilkan isi jadwal sebelumnya.
        unset($this->jadwal, $this->sesi, $this->barisSiswa, $this->ringkasKehadiran, $this->suratPerSiswa);
    }

    public function tutup(): void
    {
        $this->jadwalId = null;
    }

    /* =================================================================
     * DATA
     * ================================================================= */

    #[Computed]
    public function jadwal(): ?JadwalPelajaran
    {
        if (! $this->jadwalId) {
            return null;
        }

        $this->pastikanBerhak();

        /*
         | Kolomnya disebut eksplisit, dan nama kolomnya BERBEDA antar tabel:
         | `pegawai` memakai `nama`, `kelas` memakai `nama_kelas`, `users`
         | memakai `name`. Menyebut kolom yang tidak ada LOLOS diam-diam di
         | SQLite tapi membalas 500 di MySQL produksi.
         | Penjaganya: tests/Feature/KolomEagerLoadTest.
         */
        return JadwalPelajaran::query()
            ->with(['guru:id,nama,user_id', 'kelas:id,nama_kelas'])
            ->find($this->jadwalId);
    }

    /**
     * Catatan Absen Mengajar (scan QR ruangan) untuk sesi ini.
     *
     * Inilah yang menjawab "sudah diakhiri belum" dan menyediakan foto
     * buktinya. Dicocokkan lewat PencocokSesiMengajar — service yang SAMA
     * yang dipakai layar guru, supaya keduanya tidak mungkin berbeda jawaban.
     */
    #[Computed]
    public function sesi()
    {
        $jadwal = $this->jadwal;

        if (! $jadwal) {
            return null;
        }

        return app(PencocokSesiMengajar::class)
            ->untukJadwal($jadwal, $jadwal->guru?->user_id);
    }

    /**
     * Daftar hadir siswa untuk jadwal ini HARI INI.
     *
     * @return Collection<int, AbsensiKbmSiswa>
     */
    #[Computed]
    public function barisSiswa(): Collection
    {
        if (! $this->jadwalId) {
            return collect();
        }

        $this->pastikanBerhak();

        return AbsensiKbmSiswa::query()
            ->with('siswa:id,nis,nama')
            ->where('jadwal_id', $this->jadwalId)
            ->whereDate('tanggal', today())
            ->get();
    }

    /**
     * Ringkasan per status, urut sesuai enum.
     *
     * @return array<string, int>
     */
    #[Computed]
    public function ringkasKehadiran(): array
    {
        $hitung = $this->barisSiswa->countBy(
            fn (AbsensiKbmSiswa $a) => $a->status instanceof StatusKbm ? $a->status->value : (string) $a->status,
        );

        $hasil = [];
        foreach (StatusKbm::cases() as $s) {
            $hasil[$s->value] = (int) $hitung->get($s->value, 0);
        }

        return $hasil;
    }

    /**
     * Siswa yang TIDAK hadir — inti dari panel ini.
     *
     * @return Collection<int, AbsensiKbmSiswa>
     */
    #[Computed]
    public function siswaTidakHadir(): Collection
    {
        return $this->barisSiswa
            ->filter(fn (AbsensiKbmSiswa $a) => $a->status !== StatusKbm::Hadir)
            // Urut: yang paling perlu ditindak lebih dulu (bolos, alpa), baru
            // yang sudah ada keterangannya (izin, sakit). Mengurutkan menurut
            // nama akan menyembunyikan satu anak bolos di tengah daftar izin.
            ->sortBy(fn (AbsensiKbmSiswa $a) => match ($a->status) {
                StatusKbm::Bolos => 1,
                StatusKbm::Alpa => 2,
                StatusKbm::Izin => 3,
                StatusKbm::Sakit => 4,
                default => 5,
            })
            ->values();
    }

    /**
     * Surat izin dari gerbang, dipetakan siswa_id => PencatatanIzin.
     *
     * ============ KENAPA SATU QUERY UNTUK SEMUA SISWA ============
     * Cara yang paling mudah ditulis adalah memanggil relasi di dalam
     * perulangan daftar siswa di view. Untuk satu kelas berisi 8 siswa tidak
     * hadir itu berarti 8 query tambahan — dan jumlahnya tumbuh persis
     * seiring jumlah siswa bermasalah, yaitu justru saat panel ini paling
     * sering dibuka.
     * =============================================================
     *
     * @return Collection<int, PencatatanIzin>
     */
    #[Computed]
    public function suratPerSiswa(): Collection
    {
        $idSiswa = $this->siswaTidakHadir->pluck('siswa_id')->filter();

        if ($idSiswa->isEmpty()) {
            return collect();
        }

        return PencatatanIzin::query()
            ->whereIn('siswa_id', $idSiswa)
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('siswa_id');
    }

    /**
     * Keadaan sesi mengajarnya, sebagai KALIMAT yang siap dibaca.
     *
     * Dirakit di sini, bukan di view, karena tiga cabangnya punya arti yang
     * berbeda tajam dan gampang tertukar kalau ditulis sebagai rantai @if:
     *
     *   belum   -> gurunya belum scan QR ruangan sama sekali
     *   jalan   -> sudah scan, sesi masih terbuka
     *   selesai -> guru sudah menekan "Akhiri Sesi"; jurnalnya terkunci
     *
     * @return array{kunci: string, label: string, rincian: string}
     */
    #[Computed]
    public function statusSesi(): array
    {
        $sesi = $this->sesi;

        if (! $sesi) {
            return [
                'kunci' => 'belum',
                'label' => 'Belum scan QR ruangan',
                'rincian' => 'Guru belum memindai stiker QR di ruangan ini, jadi jurnalnya belum bisa diisi.',
            ];
        }

        if ($sesi->waktu_selesai) {
            return [
                'kunci' => 'selesai',
                'label' => 'Sesi sudah diakhiri',
                'rincian' => 'Dimulai ' . $sesi->waktu_mulai->format('H:i')
                    . ', diakhiri ' . $sesi->waktu_selesai->format('H:i')
                    . '. Jurnalnya terkunci dan tidak bisa diubah lagi.',
            ];
        }

        return [
            'kunci' => 'jalan',
            'label' => 'Sesi masih berjalan',
            'rincian' => 'Dimulai ' . $sesi->waktu_mulai->format('H:i')
                . '. Guru belum menekan "Akhiri Sesi".',
        ];
    }

    /** Awalan rute peran yang sedang login — untuk tautan surat izin. */
    #[Computed]
    public function panelPrefix(): string
    {
        return auth()->user()?->role?->routePrefix() ?? '';
    }

    public function render()
    {
        return view('livewire.monitoring.detail-sesi');
    }
}
