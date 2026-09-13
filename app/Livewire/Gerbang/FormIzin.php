<?php

namespace App\Livewire\Gerbang;

use App\Enums\JenisIzin;
use App\Models\PencatatanIzin;
use App\Models\Siswa;
use App\Services\PemampatFoto;
use App\Services\PencatatIzin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Form Pencatatan Izin di halaman Gerbang — versi real-time.
 *
 * ============ KENAPA LIVEWIRE, BUKAN FORM BIASA ============
 * Bentuk sebelumnya adalah form POST biasa: tekan Simpan, halaman dimuat
 * ulang seluruhnya. Di gerbang pagi hari itu mahal — memuat ulang halaman
 * berarti KAMERA SCANNER DI SEBELAH KIRI IKUT MATI dan harus dinyalakan
 * lagi, sementara antrean anak tetap berjalan.
 *
 * Dengan Livewire, hanya panel kanan yang digambar ulang. Kameranya tidak
 * pernah tersentuh, dan petugas bisa mencatat surat sakit tanpa kehilangan
 * scanner sedetik pun. Itulah alasan sebenarnya fitur ini dibuat real-time
 * — bukan karena terasa lebih modern.
 *
 * ============ PENCARIAN SISWA ============
 * Sekolah ini punya ratusan siswa. Satu <select> berisi semuanya berarti
 * petugas menggulir daftar panjang sambil memegang HP di gerbang. Kotak
 * pencarian di atasnya menyaring daftar itu SELAGI diketik.
 *
 * <select>-nya sendiri tetap dipertahankan (bukan diganti daftar tombol):
 * elemen itu punya perilaku bawaan yang sudah dikenal semua orang, bisa
 * dipakai keyboard, dan di HP memunculkan pemilih layar penuh milik sistem
 * yang jauh lebih nyaman daripada daftar buatan sendiri.
 */
class FormIzin extends Component
{
    use WithFileUploads;

    /**
     * Kata kunci pencarian.
     *
     * Di view WAJIB memakai wire:model.live.debounce — BUKAN wire:model
     * biasa. Sejak Livewire 3, wire:model bersifat DEFERRED: nilainya baru
     * dikirim ke server saat ada aksi lain, sehingga kotak pencariannya
     * terlihat mengetik tapi daftarnya tidak pernah berubah. Persis bug yang
     * pernah terjadi di halaman Jurnal & Absen Kelas project ini.
     *
     * debounce 300ms: tanpa itu, setiap huruf yang diketik mengirim satu
     * request. Nama "Muhammad" berarti 8 request beruntun ke hosting bersama.
     */
    public string $cari = '';

    public ?int $siswaId = null;

    public string $status = '';

    public string $keterangan = '';

    public $fotoSurat = null;

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /** Folder surat izin di dalam disk PRIVAT ('local'). */
    private const FOLDER_SURAT = 'surat-izin';

    /**
     * Batas hasil pencarian.
     *
     * Bukan pembatasan tampilan semata: tanpa batas, kotak pencarian yang
     * masih kosong akan merender SELURUH siswa sekolah ke dalam payload
     * Livewire — dan payload itu dikirim bolak-balik pada setiap interaksi
     * berikutnya, bukan sekali saja.
     */
    private const MAKS_HASIL = 50;

    public function mount(): void
    {
        $this->status = JenisIzin::Sakit->value;
    }

    /**
     * Daftar siswa yang cocok dengan kata kunci.
     *
     * #[Computed] — dihitung saat dibutuhkan lalu di-cache untuk sisa
     * request ini. Tanpa atribut itu, view yang memanggilnya dua kali
     * menjalankan query dua kali.
     */
    #[Computed]
    public function hasilPencarian(): Collection
    {
        $kata = trim($this->cari);

        return Siswa::query()
            // Nama kelas ikut ditampilkan di tiap opsi supaya dua siswa
            // bernama sama bisa dibedakan. Eager load, bukan akses relasi
            // di dalam perulangan view (N+1).
            ->with('kelas:id,nama_kelas')
            ->when($kata !== '', function ($q) use ($kata) {
                // Dibungkus closure supaya OR-nya tidak bocor keluar dan
                // menganulir kondisi lain yang mungkin ditambahkan nanti.
                $q->where(function ($sub) use ($kata) {
                    $sub->where('nama', 'like', '%' . $kata . '%')
                        ->orWhere('nis', 'like', '%' . $kata . '%');
                });
            })
            ->orderBy('nama')
            ->limit(self::MAKS_HASIL)
            ->get(['id', 'nis', 'nama', 'kelas_id']);
    }

    /** Jumlah seluruh siswa — dipakai menjelaskan kalau hasilnya dipotong. */
    #[Computed]
    public function totalCocok(): int
    {
        $kata = trim($this->cari);

        return Siswa::query()
            ->when($kata !== '', function ($q) use ($kata) {
                $q->where(function ($sub) use ($kata) {
                    $sub->where('nama', 'like', '%' . $kata . '%')
                        ->orWhere('nis', 'like', '%' . $kata . '%');
                });
            })
            ->count();
    }

    /**
     * Izin yang sudah tercatat hari ini.
     *
     * Digambar ulang otomatis setiap kali komponen ini di-render — jadi
     * sesudah simpan(), baris barunya langsung muncul tanpa reload apa pun.
     */
    #[Computed]
    public function izinHariIni(): Collection
    {
        return PencatatanIzin::query()
            ->with(['siswa:id,nis,nama,kelas_id', 'siswa.kelas:id,nama_kelas', 'petugas:id,name'])
            ->whereDate('tanggal', today())
            ->latest('id')
            ->get();
    }

    /**
     * Siswa yang sedang dipilih — untuk pratinjau di bawah dropdown.
     */
    #[Computed]
    public function siswaTerpilih(): ?Siswa
    {
        if (! $this->siswaId) {
            return null;
        }

        return Siswa::with('kelas:id,nama_kelas')->find($this->siswaId);
    }

    /**
     * Mengosongkan pilihan saat kata kuncinya berubah.
     *
     * Tanpa ini, petugas bisa memilih "Budi", lalu mengetik ulang pencarian
     * mencari anak lain, dan menekan Simpan — sementara $siswaId masih
     * menunjuk Budi yang sudah tidak terlihat lagi di daftar. Izin tercatat
     * untuk anak yang salah, dan tidak ada satu pun tanda di layar yang
     * menunjukkan hal itu akan terjadi.
     */
    public function updatedCari(): void
    {
        $this->siswaId = null;
        $this->notif = null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'siswaId' => ['required', 'integer', 'exists:siswa,id'],
            'status' => ['required', Rule::in(array_column(JenisIzin::cases(), 'value'))],
            'keterangan' => ['nullable', 'string', 'max:500'],

            /*
             | ============ PENJAGAAN BERKAS UNGGAHAN ============
             | Empat lapis, dan tidak ada satu pun yang boleh dihapus:
             |
             |   image     -> Laravel memeriksa ISI berkas lewat getimagesize(),
             |                bukan sekadar ekstensinya. Berkas .php yang
             |                dinamai ulang jadi .jpg tertangkap di sini.
             |   mimes     -> daftar putih ekstensi. Sengaja TIDAK memuat svg:
             |                SVG adalah XML yang boleh berisi <script>, dan ia
             |                lolos pemeriksaan "image" di banyak konfigurasi.
             |   max:4096  -> 4 MB.
             */
            'fotoSurat' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'siswaId' => 'siswa',
            'fotoSurat' => 'bukti surat',
        ];
    }

    public function simpan(PencatatIzin $pencatat): void
    {
        $this->notif = null;

        $this->validate();

        $siswa = Siswa::find($this->siswaId);

        if (! $siswa) {
            $this->pesan('error', 'Siswa tidak ditemukan', 'Data siswanya mungkin baru saja dihapus. Muat ulang halaman.');

            return;
        }

        // Ditolak di sini dengan pesan yang jelas, supaya petugas tidak
        // bertemu error constraint database yang tidak berarti apa-apa
        // baginya. Unique index di migrasi tetap ada sebagai penjaga terakhir
        // untuk dua petugas yang menyimpan nyaris bersamaan.
        if ($pencatat->sudahAda($siswa)) {
            $this->pesan('warn', 'Sudah ada catatan', "{$siswa->nama} sudah punya catatan izin hari ini.");

            return;
        }

        try {
            $jalurSurat = $this->simpanSurat();
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan berkas surat izin.', [
                'petugas_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Berkas gagal disimpan',
                'Izinnya BELUM dicatat sama sekali. Coba unggah ulang; kalau tetap gagal, hubungi admin.');

            return;
        }

        $jenis = JenisIzin::from($this->status);

        try {
            $pencatat->catat($siswa, auth()->user(), $jenis, $this->keterangan ?: null, $jalurSurat);
        } catch (\Throwable $e) {
            // Transaksinya batal, jadi tidak ada baris yang tersimpan — tapi
            // berkas suratnya sudah terlanjur ditulis ke disk. Dibuang di sini
            // supaya tidak menumpuk jadi berkas yatim yang tidak tertunjuk
            // baris mana pun dan tidak pernah ada yang membersihkan.
            if ($jalurSurat) {
                Storage::disk('local')->delete($jalurSurat);
            }

            Log::error('Gagal menyimpan pencatatan izin.', [
                'siswa_id' => $siswa->id,
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal menyimpan', 'Data TIDAK tersimpan. Silakan coba lagi.');

            return;
        }

        $this->pesan('ok', $siswa->nama, sprintf(
            'Izin %s tercatat. Status hari ini otomatis menjadi "%s" dan sudah terlihat oleh guru mata pelajaran.',
            $jenis->label(),
            $jenis->statusAbsensi()->label(),
        ));

        $this->kosongkanForm();

        // Cache #[Computed] dibuang supaya daftar "Izin hari ini" benar-benar
        // digambar ulang dengan baris yang baru saja dibuat. Tanpa ini,
        // petugas menyimpan dan daftarnya tampak tidak berubah — gejala yang
        // membuat orang menekan Simpan berkali-kali.
        unset($this->izinHariIni);
    }

    private function kosongkanForm(): void
    {
        $this->siswaId = null;
        $this->keterangan = '';
        $this->status = JenisIzin::Sakit->value;
        $this->fotoSurat = null;
        $this->cari = '';

        // Input file TIDAK bisa dikosongkan dari sisi server: nilainya
        // dikendalikan browser demi keamanan. $refresh pada elemennya di view
        // (lewat wire:key) yang membuat elemennya dibuat ulang bersih.
        $this->resetErrorBag();
    }

    /**
     * Menyimpan berkas surat, atau null kalau petugas tidak mengunggah apa pun.
     *
     * ============ KENAPA DISK PRIVAT, BUKAN 'public' ============
     * Berkas ini seringkali SURAT KETERANGAN DOKTER: berisi nama anak,
     * diagnosis, dan kop klinik. Itu data kesehatan seorang anak di bawah umur.
     *
     * Disk 'public' berarti berkasnya dilayani langsung oleh web server tanpa
     * melewati Laravel sama sekali — siapa pun yang memegang URL-nya bisa
     * membukanya. Nama berkas acak membuatnya sulit ditebak, tapi "sulit
     * ditebak" bukan kendali akses.
     *
     * @throws \RuntimeException kalau disk menolak menyimpan
     */
    private function simpanSurat(): ?string
    {
        if (! $this->fotoSurat) {
            return null;
        }

        $ekstensi = $this->fotoSurat->extension();

        /*
         | Dipampatkan SELAGI MASIH BERKAS SEMENTARA, sebelum dipindah ke
         | penyimpanan tetap. Berkas sementara memang dirancang untuk dibuang,
         | jadi menulisinya di tempat tidak berisiko; kalau pemampatannya
         | gagal, yang tersimpan tinggal foto aslinya.
         */
        try {
            $jalurSementara = $this->fotoSurat->getRealPath();
        } catch (\Throwable $e) {
            $jalurSementara = null;
        }

        if (is_string($jalurSementara) && PemampatFoto::keJpeg($jalurSementara)) {
            $ekstensi = 'jpg';
        }

        $jalur = $this->fotoSurat->storeAs(
            self::FOLDER_SURAT,
            (string) Str::uuid() . '.' . $ekstensi,
            'local',
        );

        /*
         | MELEMPAR, bukan mengembalikan null.
         |
         | Disk 'local' di project ini diset 'throw' => false, artinya Storage
         | TIDAK melempar apa pun saat gagal menulis — ia hanya mengembalikan
         | false. Mengembalikan null di sini akan membuat izinnya tetap
         | tersimpan tanpa bukti: petugas melampirkan foto surat dokter,
         | layarnya menjawab "tersimpan", dan buktinya tidak pernah ada di
         | mana pun.
         */
        if ($jalur === false || blank($jalur)) {
            throw new \RuntimeException('Storage menolak menyimpan berkas surat izin.');
        }

        return $jalur;
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.gerbang.form-izin', [
            'jenisIzin' => JenisIzin::semua(),

            // Prefix panel diambil dari peran pengguna, BUKAN di-hardcode.
            // Halaman ini dibuka empat peran di URL yang berbeda-beda
            // (guru., piket., admin-tu., super-admin.), dan tautan "Surat" di
            // daftar bawah harus menunjuk rute milik peran yang sedang login.
            'panelPrefix' => auth()->user()->role->routePrefix(),
        ]);
    }
}
