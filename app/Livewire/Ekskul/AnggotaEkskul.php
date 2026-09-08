<?php

namespace App\Livewire\Ekskul;

use App\Livewire\Concerns\BisaSweetAlert;
use App\Livewire\Ekskul\Concerns\PeranEkskul;
use App\Models\JadwalEkskul;
use App\Models\Kelas;
use App\Models\Siswa;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kelola anggota satu ekskul — Super Admin, Kepala Sekolah, dan PEMBINA
 * ekskul yang bersangkutan.
 *
 * ============ PEMBINA HANYA BOLEH EKSKULNYA SENDIRI ============
 * Seorang guru pembina Futsal tidak boleh mengubah anggota Pramuka. Itu
 * ditegakkan di bolehKelolaEkskul() (trait PeranEkskul) yang dipanggil di
 * mount() DAN di setiap method yang mengubah data. Pemeriksaan di mount()
 * saja tidak cukup: id jadwal disimpan sebagai properti komponen, dan
 * properti komponen bisa diubah dari konsol browser sebelum method
 * berikutnya dipanggil — jadi setiap method memuat ulang jadwalnya dari
 * database dan memeriksa lagi, bukan mempercayai apa yang sudah tersimpan.
 * ===============================================================
 */
class AnggotaEkskul extends Component
{
    use BisaSweetAlert, PeranEkskul;

    public int $jadwalId;

    public string $cari = '';

    /** Filter kelas untuk daftar calon anggota; '' berarti semua kelas. */
    public string $kelasId = '';

    /** @var array<int, string> Id siswa yang dicentang di daftar calon. */
    public array $pilih = [];

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /** Batas calon yang ditampilkan sekaligus. */
    private const BATAS_KANDIDAT = 60;

    public function mount(int $jadwal): void
    {
        $baris = JadwalEkskul::find($jadwal);

        abort_if($baris === null, 404, 'Jadwal ekskul tidak ditemukan.');
        abort_unless($this->bolehKelolaEkskul($baris), 403,
            'Hanya Super Admin, Kepala Sekolah, dan pembina ekskul ini yang boleh mengubah anggotanya.');

        $this->jadwalId = $baris->id;
    }

    #[Computed]
    public function jadwal(): ?JadwalEkskul
    {
        return JadwalEkskul::with('pembina:id,nama')->find($this->jadwalId);
    }

    #[Computed]
    public function anggota()
    {
        $jadwal = $this->jadwal;

        if (! $jadwal) {
            return collect();
        }

        return $jadwal->anggota()
            ->with('kelas:id,nama_kelas')
            ->orderBy('nama')
            ->get();
    }

    #[Computed]
    public function daftarKelas()
    {
        return Kelas::query()->orderBy('nama_kelas')->get(['id', 'nama_kelas']);
    }

    /**
     * Calon anggota: siswa yang BELUM tergabung di ekskul ini.
     *
     * whereDoesntHave lewat sub-query, bukan mengambil semua siswa lalu
     * membuang yang sudah anggota di PHP: sekolah ini punya ratusan siswa,
     * dan menyaring di PHP berarti seluruh barisnya diambil dulu setiap kali
     * kotak pencarian diketik.
     */
    #[Computed]
    public function kandidat()
    {
        $jadwal = $this->jadwal;

        if (! $jadwal) {
            return collect();
        }

        $kunci = trim($this->cari);

        return Siswa::query()
            ->with('kelas:id,nama_kelas')
            ->whereNotIn('id', function ($q) use ($jadwal) {
                $q->select('siswa_id')
                    ->from('ekskul_siswa')
                    ->where('jadwal_ekskul_id', $jadwal->id);
            })
            ->when($this->kelasId !== '', fn ($q) => $q->where('kelas_id', (int) $this->kelasId))
            ->when($kunci !== '', function ($q) use ($kunci) {
                $q->where(function ($w) use ($kunci) {
                    $w->where('nama', 'like', '%' . $kunci . '%')
                        ->orWhere('nis', 'like', '%' . $kunci . '%');
                });
            })
            ->orderBy('nama')
            ->limit(self::BATAS_KANDIDAT)
            ->get();
    }

    #[Computed]
    public function jumlahKandidat(): int
    {
        return $this->kandidat->count();
    }

    public function updatedCari(): void
    {
        unset($this->kandidat, $this->jumlahKandidat);
    }

    public function updatedKelasId(): void
    {
        // Centang ikut dibersihkan: kalau tidak, siswa yang dicentang lalu
        // hilang dari layar karena ganti filter tetap ikut tersimpan saat
        // tombol ditekan — penambahan yang tidak pernah dilihat penggunanya.
        $this->pilih = [];

        unset($this->kandidat, $this->jumlahKandidat);
    }

    private function segarkan(): void
    {
        unset($this->jadwal, $this->anggota, $this->kandidat, $this->jumlahKandidat);
    }

    /** Muat ulang jadwal + periksa izin. null berarti tidak boleh / tidak ada. */
    private function jadwalTerizinkan(): ?JadwalEkskul
    {
        $jadwal = JadwalEkskul::find($this->jadwalId);

        if (! $jadwal || ! $this->bolehKelolaEkskul($jadwal)) {
            $this->notif = ['tipe' => 'error', 'judul' => 'Tidak diizinkan',
                'pesan' => 'Anda tidak berhak mengubah anggota ekskul ini.'];

            return null;
        }

        return $jadwal;
    }

    public function tambahTerpilih(): void
    {
        $this->notif = null;

        $jadwal = $this->jadwalTerizinkan();

        if (! $jadwal) {
            return;
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($this->pilih))));

        if ($ids === []) {
            $this->notif = ['tipe' => 'warn', 'judul' => 'Belum ada yang dipilih',
                'pesan' => 'Centang dulu siswa yang mau dimasukkan ke ekskul ini.'];

            return;
        }

        // Hanya id yang benar-benar ada di tabel siswa. $pilih datang dari
        // browser dan bisa berisi apa saja; attach() dengan id palsu akan
        // melempar error kunci asing yang tidak berguna bagi pengguna.
        $sah = Siswa::whereIn('id', $ids)->pluck('id')->all();

        if ($sah === []) {
            $this->notif = ['tipe' => 'warn', 'judul' => 'Data tidak ditemukan',
                'pesan' => 'Siswa yang dipilih sudah tidak ada di data sekolah.'];

            return;
        }

        // syncWithoutDetaching, BUKAN attach: attach melempar pelanggaran
        // kunci unik kalau siswanya (misalnya karena tab kedua) sudah lebih
        // dulu masuk. Yang benar adalah tidak terjadi apa-apa, bukan halaman
        // error.
        $jadwal->anggota()->syncWithoutDetaching($sah);

        $jumlah = count($sah);
        $this->pilih = [];
        $this->segarkan();

        $this->swalToast($jumlah . ' siswa ditambahkan');
        $this->notif = ['tipe' => 'ok', 'judul' => 'Anggota ditambahkan',
            'pesan' => $jumlah . ' siswa masuk ke ekskul ' . $jadwal->nama_ekskul . '.'];
    }

    public function tambahSatu(int $siswaId): void
    {
        $this->pilih = [(string) $siswaId];
        $this->tambahTerpilih();
    }

    public function keluarkan(int $siswaId): void
    {
        $this->notif = null;

        $jadwal = $this->jadwalTerizinkan();

        if (! $jadwal) {
            return;
        }

        $siswa = Siswa::find($siswaId);
        $jadwal->anggota()->detach($siswaId);

        $this->segarkan();

        // Riwayat absensinya SENGAJA tidak ikut dihapus. Anak yang keluar dari
        // ekskul di tengah semester tetap pernah hadir, dan rekap kehadiran
        // semester itu harus tetap bisa dipertanggungjawabkan.
        $this->swalToast('Anggota dikeluarkan');
        $this->notif = ['tipe' => 'ok', 'judul' => 'Anggota dikeluarkan',
            'pesan' => ($siswa?->nama ?? 'Siswa') . ' dikeluarkan dari ekskul ini. '
                . 'Riwayat kehadirannya tetap tersimpan.'];
    }

    public function render()
    {
        return view('livewire.ekskul.anggota-ekskul');
    }
}
