<?php

namespace App\Livewire\Guru;

use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiMengajar;
use App\Models\HonorMengajar;
use App\Services\AturanHonor;
use App\Services\PencatatHonor;
use App\Services\PencatatAbsensiKbm;
use App\Services\PencocokSesiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use App\Services\PemampatFoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Jurnal & Absen Kelas — guru mengisi daftar hadir siswa untuk jam pelajaran
 * yang SEDANG BERLANGSUNG.
 *
 * ================== RANTAI SEBAB-AKIBATNYA ==================
 *   1. Guru absen kehadiran pagi (Radius GPS)      -> boleh Absen Mengajar.
 *   2. Guru scan stiker QR ruangan saat masuk      -> boleh mengisi jurnal.
 *   3. Guru menandai siswa Alpa di jam ke-3        -> orang tua melihat badge
 *      merah di halaman Pantauan KBM untuk jam itu.
 * Halaman ini adalah mata rantai nomor 2 -> 3.
 * ============================================================
 *
 * Syarat membuka form-nya ada DUA, dan keduanya harus terpenuhi:
 *   a. ada jadwal milik guru ini yang jamnya sedang berjalan HARI INI, dan
 *   b. sudah ada catatan Absen Mengajar hari ini yang kode ruangannya cocok
 *      dengan jadwal tersebut.
 *
 * Syarat (b) itulah yang membuat scan QR di meja guru punya arti: tanpa
 * benar-benar berada di ruangan itu, jurnalnya tidak bisa diisi.
 */
class JurnalAbsenKelas extends Component
{
    use WithFileUploads;

    /** Batas ukuran foto bukti dalam kilobyte (4 MB). */
    private const MAKS_FOTO_KB = 4096;

    /** Folder foto bukti di dalam disk 'public'. */
    private const FOLDER_BUKTI = 'bukti-mengajar';

    /**
     * Toleransi menit sebelum jam mulai & sesudah jam selesai.
     *
     * Guru biasanya masuk beberapa menit lebih awal dan menutup absensi
     * beberapa menit setelah bel. Tanpa toleransi, form-nya terkunci persis
     * di detik bel berbunyi dan guru kehilangan pekerjaannya yang belum
     * sempat disimpan.
     */
    public const TOLERANSI_MENIT = 15;

    /**
     * Status per siswa: [siswa_id => 'hadir'|'sakit'|'izin'|'alpa'].
     *
     * @var array<int, string>
     */
    public array $status = [];

    /** @var array<int, string> keterangan opsional per siswa */
    public array $keterangan = [];

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /**
     * Foto bukti mengajar yang SEDANG dipilih di form — belum tersimpan.
     *
     * Tipenya sengaja tidak dideklarasikan: saat form dibuka isinya null,
     * saat berkas dipilih isinya TemporaryUploadedFile. Menuliskan salah
     * satu tipe saja membuat Livewire melempar TypeError di salah satu dari
     * dua keadaan itu.
     *
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $fotoBukti = null;

    public function mount(): void
    {
        $this->siapkanStatusAwal();
    }

    /**
     * Jadwal milik guru ini yang jamnya sedang berjalan hari ini.
     *
     * Kolom jam disimpan sebagai teks, jadi perbandingannya dilakukan di PHP
     * dengan format 'H:i' — bukan lewat WHERE, yang akan membandingkan string
     * dan salah untuk jam satu digit.
     */
    #[Computed]
    public function jadwalAktif(): ?JadwalPelajaran
    {
        $pegawaiId = auth()->user()?->pegawai?->id;

        if (! $pegawaiId) {
            return null;
        }

        $sekarang = now();

        return JadwalPelajaran::with(['kelas', 'guru'])
            ->where('guru_id', $pegawaiId)
            ->where('hari', Hari::hariIni()->value)
            ->get()
            ->first(function (JadwalPelajaran $j) use ($sekarang) {
                $mulai = $sekarang->copy()->setTimeFromTimeString($j->jam_mulai->format('H:i:s'))
                    ->subMinutes(self::TOLERANSI_MENIT);
                $selesai = $sekarang->copy()->setTimeFromTimeString($j->jam_selesai->format('H:i:s'))
                    ->addMinutes(self::TOLERANSI_MENIT);

                return $sekarang->betweenIncluded($mulai, $selesai);
            });
    }

    /**
     * Catatan Absen Mengajar hari ini yang kode ruangannya cocok dengan
     * jadwal aktif — inilah bukti guru benar-benar masuk ke ruangan itu.
     */
    #[Computed]
    public function scanCocok(): ?AbsensiMengajar
    {
        $jadwal = $this->jadwalAktif;

        if (! $jadwal) {
            return null;
        }

        /*
         | Pencocokannya DIPINJAM dari App\Services\PencocokSesiMengajar,
         | bukan ditulis ulang di sini.
         |
         | Layar Live Monitoring milik kepala sekolah menjawab pertanyaan
         | yang sama ("sesi mana untuk jadwal ini?") lewat service itu juga.
         | Kalau keduanya punya salinan logika sendiri, cepat atau lambat
         | jawabannya berbeda — dan bentuk perbedaannya menyesatkan: kepsek
         | melihat "belum diakhiri" sementara guru melihat sesinya terkunci,
         | tanpa satu pun error yang menjelaskan.
         */
        return app(PencocokSesiMengajar::class)->untukJadwal($jadwal, auth()->id());
    }

    /**
     * Absen kedatangan (Radius GPS) guru ini HARI INI — mata rantai pertama.
     *
     * ============ KENAPA INI DIPERIKSA DI SINI ============
     * Sebelumnya jurnal hanya menuntut scan QR ruangan. Celahnya nyata:
     * stiker QR itu benda fisik yang bisa difoto sekali lalu di-scan dari
     * mana saja — termasuk dari rumah. Absen radius GPS-lah yang
     * membuktikan orangnya benar-benar berada di area sekolah hari itu.
     *
     * Dua-duanya diperlukan dan tidak saling menggantikan:
     *   GPS  menjawab "apakah ia datang ke sekolah?"
     *   QR   menjawab "apakah ia masuk ke ruangan yang benar?"
     * ======================================================
     */
    #[Computed]
    public function absenDatang(): ?AbsensiPegawai
    {
        $pegawaiId = auth()->user()?->pegawai?->id;

        if (! $pegawaiId) {
            return null;
        }

        return AbsensiPegawai::where('pegawai_id', $pegawaiId)
            ->wherePadaTanggal('tanggal', today())
            ->whereNotNull('jam_masuk')
            ->first();
    }

    /**
     * Form hanya boleh muncul & disimpan kalau KETIGANYA terpenuhi:
     * sudah absen datang, ada jam yang sedang berjalan, dan QR ruangannya
     * cocok dengan jadwal itu.
     */
    #[Computed]
    public function bolehMengisi(): bool
    {
        return $this->absenDatang !== null
            && $this->jadwalAktif !== null
            && $this->scanCocok !== null;
    }

    /**
     * Sesi ini sudah diakhiri guru? Kalau ya, form dikunci — jurnal yang
     * sudah ditutup tidak boleh diubah diam-diam.
     */
    #[Computed]
    public function sesiSelesai(): bool
    {
        return (bool) $this->scanCocok?->sudahSelesai();
    }

    /** Foto bukti mengajar sudah tersimpan untuk sesi ini? */
    #[Computed]
    public function buktiTersimpan(): bool
    {
        return (bool) $this->scanCocok?->adaBukti();
    }

    /**
     * Siswa di kelas jadwal aktif.
     *
     * @return Collection<int, Siswa>
     */
    #[Computed]
    public function daftarSiswa(): Collection
    {
        $jadwal = $this->jadwalAktif;

        return $jadwal ? $this->pencatat()->siswaKelas($jadwal) : collect();
    }

    /** @return array<int, StatusKbm> */
    #[Computed]
    public function pilihanStatus(): array
    {
        return StatusKbm::pilihanGuru();
    }

    /**
     * Siswa yang tercatat HADIR di gerbang pagi ini.
     *
     * Dipakai dua hal: menandai barisnya di tabel, dan mengubah "Alpa" jadi
     * "Bolos" saat menyimpan.
     *
     * @return Collection<int, int> siswa_id
     */
    #[Computed]
    public function hadirDiGerbang(): Collection
    {
        return $this->pencatat()->hadirDiGerbang($this->daftarSiswa->pluck('id'), today());
    }

    /**
     * Izin/sakit yang dicatat petugas piket di GERBANG pagi ini.
     *
     * ============ INI UJUNG DARI "PENCATATAN IZIN SATU PINTU" ============
     * Siswa di sekolah ini tidak membawa HP, jadi izin masuk lewat satu
     * pintu: petugas piket mencatatnya di halaman Gerbang, yang menuliskan
     * status hariannya ke `absensi_siswa` (lihat AbsensiGerbangController).
     *
     * Tanpa method ini, pencatatan itu berhenti di tabel dan tidak pernah
     * sampai ke guru — siapkanStatusAwal() di bawah mengisi SEMUA siswa
     * dengan 'hadir', sehingga anak yang sudah resmi diizinkan tetap muncul
     * sebagai hadir di layar guru jam ketiga. Guru yang tidak curiga akan
     * menyimpannya begitu saja, dan catatan izinnya jadi tidak berarti apa-apa.
     *
     * Yang diambil hanya izin & sakit. 'hadir' tidak perlu (itu sudah nilai
     * bawaannya) dan 'alpha' sengaja TIDAK ikut: alpa di gerbang berarti
     * anaknya tidak terdeteksi masuk, dan itu bukan alasan untuk memvonisnya
     * absen di kelas sebelum gurunya melihat sendiri.
     *
     * @return Collection<int, string> siswa_id => nilai StatusKbm
     */
    #[Computed]
    public function izinDariGerbang(): Collection
    {
        return $this->pencatat()->izinDariGerbang($this->daftarSiswa->pluck('id'), today());
    }

    /**
     * Isi $status awal: 'hadir' untuk semua — guru hanya perlu mengubah yang
     * tidak masuk, sesuai brief. Kalau jurnal jam ini sudah pernah disimpan,
     * nilai tersimpannya yang dipakai, supaya membuka ulang halaman tidak
     * diam-diam mengembalikan semua siswa jadi Hadir.
     */
    private function siapkanStatusAwal(): void
    {
        $jadwal = $this->jadwalAktif;

        if (! $jadwal) {
            return;
        }

        // Aturan urutan isian awal (jurnal tersimpan > izin gerbang > hadir)
        // ada di PencatatAbsensiKbm::statusAwal(), dipakai bersama halaman
        // Guru Inval.
        $awal = $this->pencatat()->statusAwal($jadwal, today(), $this->daftarSiswa);

        $this->status = $awal['status'];
        $this->keterangan = $awal['keterangan'];
    }

    /**
     * Logika penyimpanan absensi KBM tinggal di service ini, dipakai bersama
     * halaman Guru Inval. Lihat catatan di App\Services\PencatatAbsensiKbm.
     */
    private function pencatat(): PencatatAbsensiKbm
    {
        return app(PencatatAbsensiKbm::class);
    }

    public function simpan(): void
    {
        $this->notif = null;

        // Gerbangnya diperiksa ULANG di sini, bukan hanya di render.
        // Method Livewire adalah endpoint HTTP tersendiri: tanpa pemeriksaan
        // ini, siapa pun yang sudah login bisa memanggil $wire.simpan() dari
        // konsol tanpa pernah menyentuh stiker QR di ruangan mana pun.
        // Properti publik juga tidak dipercaya karena ikut dikirim browser.
        $this->segarkanPemeriksaan();

        if (! $this->bolehMengisi) {
            $this->pesan('error', 'Tidak bisa menyimpan',
                'Jam pelajaran ini sudah lewat, Anda belum absen kedatangan, atau belum men-scan QR ruangannya. Muat ulang halaman untuk melihat keadaan terkini.');

            return;
        }

        // Sesi yang sudah ditutup tidak boleh diubah lagi. Tanpa penjagaan
        // ini, jurnal yang sudah "diakhiri" dan sudah masuk laporan masih
        // bisa disunting lewat $wire.simpan() dari konsol browser.
        if ($this->sesiSelesai) {
            $this->pesan('warn', 'Sesi sudah diakhiri',
                'Sesi kelas ini sudah Anda akhiri, jadi jurnalnya terkunci. Hubungi admin bila ada yang perlu dikoreksi.');

            return;
        }

        $jadwal = $this->jadwalAktif;

        try {
            $hasil = $this->pencatat()->simpan(
                $jadwal,
                today(),
                $this->daftarSiswa,
                $this->status,
                $this->keterangan,
                auth()->id(),
            );
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan absensi KBM.', [
                'jadwal_id' => $jadwal->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal menyimpan',
                'Terjadi kesalahan saat menyimpan absensi. Coba lagi sebentar.');

            return;
        }

        if ($hasil['jumlah'] === 0) {
            $this->pesan('warn', 'Tidak ada siswa',
                'Kelas ini belum punya data siswa, jadi tidak ada yang bisa diabsen.');

            return;
        }

        $this->pesan('ok', 'Absensi KBM tersimpan', $this->pencatat()->kalimatHasil($hasil));
    }

    /* ================= BUKTI MENGAJAR & AKHIRI SESI ================= */

    /**
     * Buang cache semua #[Computed] yang menentukan hak akses.
     *
     * Wajib dipanggil di AWAL setiap method Livewire yang menulis data.
     * Setiap method Livewire adalah endpoint HTTP tersendiri, dan nilai
     * #[Computed] yang sudah ter-cache berasal dari permintaan sebelumnya —
     * bisa saja jam pelajarannya sudah lewat sejak halaman dibuka.
     */
    private function segarkanPemeriksaan(): void
    {
        unset(
            $this->absenDatang,
            $this->jadwalAktif,
            $this->scanCocok,
            $this->bolehMengisi,
            $this->sesiSelesai,
            $this->buktiTersimpan,
            $this->daftarSiswa,
        );
    }

    /**
     * Validasi berkas dijalankan SAAT DIPILIH, bukan hanya saat disimpan.
     *
     * Hook bawaan Livewire ini menembak begitu $fotoBukti berubah, jadi guru
     * langsung tahu fotonya kebesaran — bukan setelah menunggu unggahan
     * 8 MB selesai lalu ditolak.
     */
    public function updatedFotoBukti(): void
    {
        $this->validateOnly('fotoBukti', $this->aturanFoto());
    }

    /** @return array<string, array<int, string>> */
    private function aturanFoto(): array
    {
        return [
            // 'image' saja tidak cukup: ia hanya memeriksa MIME yang dikirim
            // browser, dan MIME bisa dipalsukan. 'mimes' memaksa Laravel
            // memeriksa isi berkasnya sungguhan lewat ekstensi + finfo.
            'fotoBukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAKS_FOTO_KB],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'fotoBukti.required' => 'Pilih dulu foto bukti mengajarnya.',
            'fotoBukti.image' => 'Berkas yang dipilih bukan gambar.',
            'fotoBukti.mimes' => 'Format yang diterima hanya JPG, PNG, atau WEBP.',
            'fotoBukti.max' => 'Ukuran foto maksimal 4 MB. Kecilkan dulu atau foto ulang dengan resolusi lebih rendah.',
        ];
    }

    /**
     * Simpan foto bukti mengajar ke storage, lalu tautkan ke baris sesi.
     *
     * Foto disimpan LEBIH DULU, barulah kolomnya diperbarui. Urutan ini
     * disengaja: kalau kolomnya diisi duluan dan penyimpanan berkasnya
     * gagal, database menunjuk berkas yang tidak ada — dan tombol "Akhiri
     * Sesi" terbuka untuk bukti yang sebenarnya tidak pernah ada.
     */
    public function unggahBukti(): void
    {
        $this->notif = null;
        $this->segarkanPemeriksaan();

        if (! $this->bolehMengisi) {
            $this->pesan('error', 'Tidak bisa mengunggah',
                'Jam pelajaran ini sudah lewat, atau Anda belum absen kedatangan / men-scan QR ruangannya.');

            return;
        }

        if ($this->sesiSelesai) {
            $this->pesan('warn', 'Sesi sudah diakhiri',
                'Sesi kelas ini sudah ditutup, jadi buktinya tidak bisa diganti lagi.');

            return;
        }

        $this->validate($this->aturanFoto());

        $sesi = $this->scanCocok;
        $jalurLama = $sesi->foto_bukti;

        try {
            // Foto dipampatkan SELAGI MASIH BERKAS SEMENTARA, sebelum pindah
            // ke penyimpanan permanen. Berkas sementara memang dirancang untuk
            // dibuang, jadi menulisinya di tempat tidak berisiko; kalau
            // pemampatannya gagal, yang tersimpan tinggal foto aslinya.
            //
            // Tanpa langkah ini satu foto kamera HP berukuran 3–5 MB, dan
            // ~1.100 foto per bulan berarti sekitar 3 GB per bulan yang tidak
            // pernah dihapus siapa pun. Lihat App\Services\PemampatFoto.
            $ekstensi = $this->fotoBukti->extension();

            // getRealPath() hanya masuk akal untuk disk lokal. Kalau suatu saat
            // disk unggahan sementara dipindah ke S3, panggilan ini melempar —
            // dan itu tidak boleh membuat unggahannya ikut gagal.
            try {
                $jalurSementara = $this->fotoBukti->getRealPath();
            } catch (\Throwable $e) {
                $jalurSementara = null;
            }

            if (is_string($jalurSementara) && PemampatFoto::keJpeg($jalurSementara)) {
                // Isinya sekarang JPEG, jadi ekstensinya harus ikut berubah.
                // Kalau tidak, berkas .png yang isinya JPEG akan dilayani
                // dengan Content-Type yang salah saat diunduh.
                $ekstensi = 'jpg';
            }

            // Nama berkas dibuat sistem (UUID + ekstensi), BUKAN memakai nama
            // asli dari HP guru. Nama asli bisa mengandung karakter yang
            // menyulitkan di server, dan yang lebih penting: nama yang bisa
            // ditebak membuat foto orang lain bisa dibuka dengan menerka URL.
            $jalur = $this->fotoBukti->storeAs(
                self::FOLDER_BUKTI,
                (string) Str::uuid() . '.' . $ekstensi,
                'public',
            );

            if ($jalur === false || blank($jalur)) {
                throw new \RuntimeException('Storage menolak menyimpan berkas.');
            }

            // bukti_dihapus_pada dikosongkan lagi: berkas yang BARU diunggah
            // jelas belum pernah dibuang pembersih bulanan. Tanpa baris ini,
            // sesi yang pernah dibersihkan lalu diisi ulang tetap dianggap
            // "fotonya sudah dibuang", sehingga gambarnya tidak muncul padahal
            // berkasnya ada — lihat AbsensiMengajar::urlBukti().
            $sesi->forceFill(['foto_bukti' => $jalur, 'bukti_dihapus_pada' => null])->save();
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan foto bukti mengajar.', [
                'absensi_mengajar_id' => $sesi->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal mengunggah',
                'Foto tidak berhasil disimpan. Coba lagi, atau pakai foto dengan ukuran lebih kecil.');

            return;
        }

        // Bukti lama dihapus SESUDAH yang baru tersimpan dan tercatat.
        // Kalau dihapus lebih dulu lalu penyimpanan baru gagal, guru
        // kehilangan bukti yang tadinya sudah sah.
        if ($jalurLama && $jalurLama !== $jalur) {
            try {
                Storage::disk('public')->delete($jalurLama);
            } catch (\Throwable $e) {
                // Berkas yatim di disk tidak merusak apa pun. Dicatat saja.
                Log::warning('Foto bukti lama gagal dihapus.', [
                    'jalur' => $jalurLama,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->reset('fotoBukti');
        $this->segarkanPemeriksaan();

        $this->pesan('ok', 'Bukti tersimpan',
            'Foto bukti mengajar berhasil diunggah. Tombol "Akhiri Sesi Kelas" sekarang aktif.');
    }

    /**
     * Tutup sesi mengajar. HANYA boleh kalau foto buktinya sudah tersimpan.
     *
     * Pemeriksaan buktiTersimpan dilakukan DI SINI, bukan cuma dengan
     * menonaktifkan tombolnya di layar. Atribut `disabled` pada tombol
     * adalah HTML biasa yang bisa dicabut siapa pun lewat inspect element,
     * dan wire:click tetap bisa dipanggil langsung dari konsol browser —
     * jadi tombol yang mati di layar sama sekali bukan pengaman.
     */
    public function akhiriSesi(): void
    {
        $this->notif = null;
        $this->segarkanPemeriksaan();

        if (! $this->bolehMengisi) {
            $this->pesan('error', 'Tidak bisa mengakhiri sesi',
                'Jam pelajaran ini sudah lewat, atau syarat kehadirannya belum terpenuhi.');

            return;
        }

        if ($this->sesiSelesai) {
            $this->pesan('warn', 'Sudah diakhiri',
                'Sesi kelas ini memang sudah ditutup sebelumnya.');

            return;
        }

        if (! $this->buktiTersimpan) {
            $this->pesan('error', 'Bukti mengajar belum ada',
                'Unggah dulu satu foto bukti mengajar sebelum mengakhiri sesi kelas.');

            return;
        }

        // Diambil SEBELUM sesi ditutup: sesudahnya cache pemeriksaan
        // disegarkan, dan jadwal ini yang menjadi dasar honor.
        $jadwal = $this->jadwalAktif;

        try {
            $this->scanCocok->forceFill(['waktu_selesai' => now()])->save();
        } catch (\Throwable $e) {
            Log::error('Gagal menutup sesi mengajar.', [
                'absensi_mengajar_id' => $this->scanCocok->id,
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal mengakhiri sesi',
                'Terjadi kesalahan saat menutup sesi. Coba lagi sebentar.');

            return;
        }

        $sesi = $this->scanCocok;
        $sesiId = $sesi->id;
        $honor = $jadwal ? $this->catatHonor($sesi, $jadwal) : null;
        $this->segarkanPemeriksaan();

        // Mematikan alarm pengingat "Akhiri Sesi" yang mungkin sedang
        // berbunyi di layar ini (partials/pengingat-akhiri-sesi). Tanpa
        // event ini alarmnya baru berhenti setelah halaman dimuat ulang —
        // guru yang sudah patuh malah terus "dimarahi" HP-nya.
        $this->dispatch('sesi-diakhiri', id: $sesiId);

        $this->pesan('ok', 'Sesi kelas diakhiri',
            'Kehadiran mengajar Anda tercatat lengkap dengan bukti. Jurnal jam ini sekarang terkunci.'
                . ($honor ? ' Honor ' . AturanHonor::rupiah($honor->nominal) . " ({$honor->jp} JP) masuk ke Rincian Pendapatan." : ''));
    }

    /**
     * Catat honor mengajar sesi yang baru diakhiri (null = fitur mati).
     *
     * Gagal mencatat honor TIDAK membatalkan "Akhiri Sesi" — sesi mengajar
     * yang sudah sah tidak boleh ikut gagal karena fitur uji coba.
     */
    private function catatHonor(AbsensiMengajar $sesi, JadwalPelajaran $jadwal): ?HonorMengajar
    {
        try {
            return app(PencatatHonor::class)->catatMengajar($sesi, $jadwal);
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat honor mengajar.', [
                'absensi_mengajar_id' => $sesi->id,
                'jadwal_id' => $jadwal->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Samakan bentuk kode ruangan & nama kelas supaya bisa dibandingkan:
     * "RUANG-X-RPL-1", "ruang x rpl 1", dan "X RPL 1" jadi satu bentuk.
     */
    /**
     * Delegasi tipis ke PencocokSesiMengajar::normalkan().
     *
     * Dipertahankan sebagai method (bukan dihapus dan pemanggilnya diubah
     * semua) supaya pemanggil lain di kelas ini tidak perlu ikut disunting —
     * tapi ISINYA satu, di service, sehingga tidak ada dua definisi yang
     * bisa berbeda.
     */
    private function normalkan(?string $teks): ?string
    {
        return PencocokSesiMengajar::normalkan($teks);
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.guru.jurnal-absen-kelas');
    }
}
