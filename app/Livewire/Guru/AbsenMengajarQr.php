<?php

namespace App\Livewire\Guru;

use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\Kelas;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Absen Mengajar (QR) — guru men-scan stiker QR ruangan saat masuk kelas.
 *
 * ATURAN UTAMA: tidak boleh absen mengajar sebelum absen kehadiran pagi.
 * Aturan itu ditegakkan DUA KALI, dan itu disengaja:
 *
 *   1. mount()                 -> menentukan tampilan (scanner atau alert).
 *   2. prosesAbsenMengajar()   -> menentukan apakah data BOLEH disimpan.
 *
 * Pemeriksaan di mount() saja tidak cukup. Method Livewire adalah endpoint
 * HTTP tersendiri (/livewire/update): siapa pun yang sudah login bisa
 * memanggil $wire.prosesAbsenMengajar('RUANG-X') dari konsol browser tanpa
 * pernah membuka halaman ini. Kalau gerbangnya hanya di mount(), aturan
 * "wajib absen pagi dulu" cuma jadi hiasan tampilan.
 *
 * Pelajaran yang sama sudah muncul di halaman Login (rate limit yang ikut
 * hilang saat form pindah ke Livewire) dan di modal Kartu Identitas.
 */
class AbsenMengajarQr extends Component
{
    /** Panjang & bentuk kode yang diterima dari stiker QR ruangan. */
    private const POLA_KODE = '/^[A-Za-z0-9][A-Za-z0-9 _.\-]{1,59}$/';

    /**
     * Jeda anti-dobel di sisi server (menit). Kamera membaca ~10 frame per
     * detik; JavaScript sudah menahan pengulangan, tapi guru yang menutup
     * lalu membuka halaman lagi bisa mengirim kode yang sama beberapa detik
     * kemudian. Tanpa jeda ini satu jam mengajar bisa jadi belasan baris.
     */
    private const JEDA_MENIT = 30;

    /**
     * true kalau pengguna SUDAH punya catatan kehadiran hari ini.
     * Dipakai view untuk memilih antara scanner dan alert penolakan.
     */
    public bool $sudahAbsenKehadiran = false;

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    public function mount(): void
    {
        $this->sudahAbsenKehadiran = $this->periksaAbsenKehadiran();
    }

    /**
     * Apakah pengguna yang login sudah absen kehadiran HARI INI?
     *
     * CATATAN PENTING soal kolomnya: brief menyebut
     * `where('user_id', auth()->user()->id)`, tapi tabel `absensi_pegawai`
     * di project ini TIDAK punya kolom user_id — kuncinya `pegawai_id`
     * (lihat migrasi 000005). Query versi brief akan melempar
     * "Unknown column 'user_id'". Jadi dipakai id pegawai yang tertaut ke
     * akun ini.
     *
     * Akun yang belum ditautkan ke baris `pegawai` dianggap BELUM absen —
     * gagal ke arah aman: lebih baik guru diminta menghubungi admin daripada
     * halaman menyimpan catatan mengajar tanpa dasar kehadiran.
     */
    private function periksaAbsenKehadiran(): bool
    {
        $pegawaiId = auth()->user()?->pegawai?->id;

        if (! $pegawaiId) {
            return false;
        }

        return AbsensiPegawai::where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal', today())
            ->exists();
    }

    /** Akun sudah tertaut ke data pegawai atau belum — untuk pesan yang tepat. */
    #[Computed]
    public function akunTertaut(): bool
    {
        return auth()->user()?->pegawai !== null;
    }

    /** Jam absen kehadiran pagi ini, untuk ditampilkan di kartu. */
    #[Computed]
    public function jamKehadiran(): ?string
    {
        $pegawaiId = auth()->user()?->pegawai?->id;

        if (! $pegawaiId) {
            return null;
        }

        return AbsensiPegawai::where('pegawai_id', $pegawaiId)
            ->whereDate('tanggal', today())
            ->first()?->jam_masuk?->format('H:i');
    }

    /**
     * Riwayat mengajar hari ini — supaya guru bisa memastikan scan-nya
     * benar-benar tersimpan, bukan hanya melihat notifikasi yang lewat.
     *
     * @return Collection<int, AbsensiMengajar>
     */
    #[Computed]
    public function riwayatHariIni(): Collection
    {
        return AbsensiMengajar::where('user_id', auth()->id())
            ->whereDate('waktu_mulai', today())
            ->orderByDesc('waktu_mulai')
            ->get();
    }

    /**
     * Dipanggil dari JavaScript lewat $wire.prosesAbsenMengajar(kode) setiap
     * kali kamera berhasil membaca satu QR.
     */
    public function prosesAbsenMengajar($kodeKelas): void
    {
        $this->notif = null;

        // ---- Gerbang utama, diperiksa ulang di server ------------------
        // Nilainya dihitung ulang dari database, BUKAN dibaca dari properti
        // $sudahAbsenKehadiran — properti publik Livewire ikut dikirim dari
        // browser dan bisa dipalsukan jadi true.
        if (! $this->periksaAbsenKehadiran()) {
            $this->sudahAbsenKehadiran = false;

            $this->pesan('error', 'Akses Ditolak!',
                'Anda belum melakukan Absen Kehadiran pagi ini. Silakan lakukan Absen Kehadiran berbasis Radius terlebih dahulu sebelum mengajar.');

            return;
        }

        $kode = trim((string) $kodeKelas);

        if ($kode === '' || ! preg_match(self::POLA_KODE, $kode)) {
            $this->pesan('error', 'QR tidak dikenali',
                'Kode yang terbaca bukan kode ruangan yang sah. Pastikan yang di-scan adalah stiker QR resmi di meja guru.');

            return;
        }

        // ---- Anti-scan ganda -------------------------------------------
        $terakhir = AbsensiMengajar::where('user_id', auth()->id())
            ->where('kode_kelas', $kode)
            ->where('waktu_mulai', '>=', now()->subMinutes(self::JEDA_MENIT))
            ->latest('waktu_mulai')
            ->first();

        if ($terakhir) {
            $this->pesan('warn', 'Sudah tercatat',
                'Ruangan ' . $this->labelRuangan($kode) . ' sudah Anda scan pukul '
                . $terakhir->waktu_mulai->format('H:i')
                . '. Tidak perlu scan ulang untuk jam mengajar yang sama.');

            return;
        }

        try {
            $catatan = AbsensiMengajar::create([
                'user_id' => auth()->id(),
                'kode_kelas' => $kode,
                'waktu_mulai' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan absen mengajar.', [
                'user_id' => auth()->id(),
                'kode_kelas' => $kode,
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal menyimpan',
                'Terjadi kesalahan saat menyimpan catatan mengajar. Coba scan lagi sebentar.');

            return;
        }

        // Properti terhitung di-reset supaya daftar riwayat ikut memuat
        // baris yang baru saja dibuat.
        unset($this->riwayatHariIni);

        $this->pesan('ok', 'Absen mengajar berhasil',
            'Anda tercatat masuk ' . $this->labelRuangan($kode) . ' pukul '
            . $catatan->waktu_mulai->format('H:i') . '. Selamat mengajar!');
    }

    /**
     * Ubah kode stiker jadi nama yang enak dibaca.
     *
     * Kalau kodenya cocok dengan nama kelas yang terdaftar (setelah "RUANG-"
     * dibuang dan tanda hubung dikembalikan jadi spasi), nama kelas itu yang
     * dipakai. Kalau tidak cocok — misalnya ruangan Lab yang bukan kelas —
     * kode mentahnya ditampilkan apa adanya, bukan "tidak dikenal", karena
     * kode itulah yang tertulis di stiker yang barusan di-scan.
     */
    private function labelRuangan(string $kode): string
    {
        $bersih = Str::of($kode)
            ->replaceMatches('/^ruang[-_ ]*/i', '')
            ->replace(['-', '_'], ' ')
            ->squish()
            ->upper()
            ->value();

        $kelas = Kelas::query()->get(['id', 'nama_kelas'])->first(
            fn (Kelas $k) => Str::upper(Str::squish($k->nama_kelas)) === $bersih
        );

        return $kelas ? 'kelas ' . $kelas->nama_kelas : '"' . $kode . '"';
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.guru.absen-mengajar-qr');
    }
}
