<?php

namespace App\Livewire\Guru;

use App\Enums\AbsensiStatus;
use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Jobs\SendWhatsAppNotification;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

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
    /**
     * Toleransi menit sebelum jam mulai & sesudah jam selesai.
     *
     * Guru biasanya masuk beberapa menit lebih awal dan menutup absensi
     * beberapa menit setelah bel. Tanpa toleransi, form-nya terkunci persis
     * di detik bel berbunyi dan guru kehilangan pekerjaannya yang belum
     * sempat disimpan.
     */
    private const TOLERANSI_MENIT = 15;

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

        $sasaran = array_filter([
            $this->normalkan($jadwal->kelas?->nama_kelas),
            $this->normalkan($jadwal->ruangan),
        ]);

        if (! $sasaran) {
            return null;
        }

        return AbsensiMengajar::where('user_id', auth()->id())
            ->whereDate('waktu_mulai', today())
            ->get()
            ->first(fn (AbsensiMengajar $a) => in_array($this->normalkan($a->kode_kelas), $sasaran, true));
    }

    /** Form hanya boleh muncul & disimpan kalau keduanya terpenuhi. */
    #[Computed]
    public function bolehMengisi(): bool
    {
        return $this->jadwalAktif !== null && $this->scanCocok !== null;
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

        if (! $jadwal || ! $jadwal->kelas_id) {
            return collect();
        }

        return Siswa::where('kelas_id', $jadwal->kelas_id)->orderBy('nama')->get();
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
        return AbsensiSiswa::whereIn('siswa_id', $this->daftarSiswa->pluck('id'))
            ->whereDate('tanggal', today())
            ->where('status', AbsensiStatus::Hadir)
            ->pluck('siswa_id');
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

        $tersimpan = AbsensiKbmSiswa::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', today())
            ->get()
            ->keyBy('siswa_id');

        foreach ($this->daftarSiswa as $siswa) {
            $baris = $tersimpan->get($siswa->id);

            // 'bolos' tidak ada di tombol radio; kalau baris tersimpan
            // berstatus itu, radionya ditampilkan sebagai 'alpa' (asalnya),
            // dan akan dihitung ulang jadi 'bolos' lagi saat disimpan.
            $nilai = $baris?->status?->value ?? StatusKbm::Hadir->value;

            $this->status[$siswa->id] = $nilai === StatusKbm::Bolos->value
                ? StatusKbm::Alpa->value
                : $nilai;

            $this->keterangan[$siswa->id] = (string) ($baris?->keterangan ?? '');
        }
    }

    public function simpan(): void
    {
        $this->notif = null;

        // Gerbangnya diperiksa ULANG di sini, bukan hanya di render.
        // Method Livewire adalah endpoint HTTP tersendiri: tanpa pemeriksaan
        // ini, siapa pun yang sudah login bisa memanggil $wire.simpan() dari
        // konsol tanpa pernah menyentuh stiker QR di ruangan mana pun.
        // Properti publik juga tidak dipercaya karena ikut dikirim browser.
        unset($this->jadwalAktif, $this->scanCocok, $this->bolehMengisi, $this->daftarSiswa);

        if (! $this->bolehMengisi) {
            $this->pesan('error', 'Tidak bisa menyimpan',
                'Jam pelajaran ini sudah lewat, atau Anda belum men-scan QR ruangannya. Muat ulang halaman untuk melihat keadaan terkini.');

            return;
        }

        $jadwal = $this->jadwalAktif;
        $siswaKelas = $this->daftarSiswa->keyBy('id');
        $hadirGerbang = $this->hadirDiGerbang;
        $sahStatus = array_map(fn (StatusKbm $s) => $s->value, StatusKbm::pilihanGuru());

        // Status yang SUDAH tersimpan sebelum tombol ini ditekan.
        //
        // Dipakai untuk satu hal penting: notifikasi WhatsApp hanya dikirim
        // untuk siswa yang statusnya BARU BERUBAH jadi alpa/bolos. Tanpa
        // pembanding ini, guru yang menekan Simpan dua kali (mengoreksi satu
        // nama, lalu menyimpan lagi) akan mengirim peringatan yang sama dua
        // kali ke orang tua yang sama — cara tercepat membuat wali murid
        // memblokir nomor sekolah.
        $sebelumnya = AbsensiKbmSiswa::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', today())
            ->pluck('status', 'siswa_id')
            ->map(fn ($s) => $s instanceof StatusKbm ? $s->value : (string) $s);

        $baris = [];
        $jumlahBolos = 0;

        /** @var array<int, array{siswa: \App\Models\Siswa, status: StatusKbm}> */
        $perluDiberitahu = [];

        foreach ($siswaKelas as $id => $siswa) {
            $dipilih = $this->status[$id] ?? StatusKbm::Hadir->value;

            // Nilai di luar daftar (dikirim manual dari browser) dianggap
            // Hadir, bukan ditolak semuanya — satu nilai iseng tidak boleh
            // membuang pekerjaan guru untuk seluruh kelas.
            if (! in_array($dipilih, $sahStatus, true)) {
                $dipilih = StatusKbm::Hadir->value;
            }

            // Alpa + tercatat masuk gerbang pagi ini = BOLOS. Perbedaan ini
            // dihitung sistem, bukan diminta ke guru: guru tidak mungkin
            // hafal siapa saja yang tadi pagi lewat gerbang.
            if ($dipilih === StatusKbm::Alpa->value && $hadirGerbang->contains($id)) {
                $dipilih = StatusKbm::Bolos->value;
                $jumlahBolos++;
            }

            // Hanya perubahan BARU yang memicu pesan ke wali murid.
            if (in_array($dipilih, [StatusKbm::Alpa->value, StatusKbm::Bolos->value], true)
                && ($sebelumnya[$id] ?? null) !== $dipilih) {
                $perluDiberitahu[] = [
                    'siswa' => $siswa,
                    'status' => StatusKbm::from($dipilih),
                ];
            }

            $ket = trim((string) ($this->keterangan[$id] ?? ''));

            $baris[] = [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $id,
                'tanggal' => today()->toDateString(),
                'status' => $dipilih,
                'keterangan' => $ket === '' ? null : Str::limit($ket, 255, ''),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! $baris) {
            $this->pesan('warn', 'Tidak ada siswa',
                'Kelas ini belum punya data siswa, jadi tidak ada yang bisa diabsen.');

            return;
        }

        try {
            DB::transaction(function () use ($baris) {
                // upsert, bukan insert: tombol Simpan boleh ditekan berkali-
                // kali (guru mengoreksi status seorang siswa) tanpa menumpuk
                // baris ganda. Kuncinya unique(jadwal_id, siswa_id, tanggal)
                // dari migrasi 000017.
                AbsensiKbmSiswa::upsert(
                    $baris,
                    ['jadwal_id', 'siswa_id', 'tanggal'],
                    ['status', 'keterangan', 'updated_at'],
                );
            });
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

        // Notifikasi diantrekan SESUDAH transaksi berhasil, bukan di dalamnya.
        // Kalau di dalam dan transaksinya kemudian gagal, pesan sudah terlanjur
        // masuk antrean dan orang tua menerima peringatan tentang data yang
        // tidak pernah tersimpan.
        $this->antrekanPeringatan($perluDiberitahu, $jadwal);

        $ringkas = collect($baris)->countBy('status');

        $this->pesan('ok', 'Absensi KBM tersimpan', trim(
            'Tercatat ' . $ringkas->get(StatusKbm::Hadir->value, 0) . ' hadir'
            . ', ' . $ringkas->get(StatusKbm::Sakit->value, 0) . ' sakit'
            . ', ' . $ringkas->get(StatusKbm::Izin->value, 0) . ' izin'
            . ', ' . $ringkas->get(StatusKbm::Alpa->value, 0) . ' alpa'
            . ($jumlahBolos > 0
                ? ". {$jumlahBolos} siswa ditandai BOLOS karena tadi pagi tercatat masuk gerbang."
                : '.')
        ));
    }

    /**
     * Samakan bentuk kode ruangan & nama kelas supaya bisa dibandingkan:
     * "RUANG-X-RPL-1", "ruang x rpl 1", dan "X RPL 1" jadi satu bentuk.
     */
    private function normalkan(?string $teks): ?string
    {
        if ($teks === null || trim($teks) === '') {
            return null;
        }

        return Str::of($teks)
            ->replaceMatches('/^ruang[-_ ]*/i', '')
            ->replace(['-', '_'], ' ')
            ->squish()
            ->upper()
            ->value();
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.guru.jurnal-absen-kelas');
    }

    /**
     * Antrekan peringatan WhatsApp untuk siswa yang baru ditandai alpa/bolos.
     *
     * @param  array<int, array{siswa: \App\Models\Siswa, status: StatusKbm}>  $daftar
     */
    private function antrekanPeringatan(array $daftar, JadwalPelajaran $jadwal): void
    {
        if (! $daftar) {
            return;
        }

        $jamKe = $this->jamKe($jadwal);

        foreach ($daftar as $item) {
            $siswa = $item['siswa'];

            // Sama seperti di scan gerbang: nomor khusus wali lebih dulu,
            // baru nomor akun wali murid yang tertaut.
            $tujuan = $siswa->no_hp_wali ?: $siswa->waliMurid?->no_hp;

            if (blank($tujuan)) {
                Log::warning('Peringatan WhatsApp KBM dilewati: wali murid tidak punya nomor HP.', [
                    'siswa_id' => $siswa->id,
                    'jadwal_id' => $jadwal->id,
                ]);

                continue;
            }

            $pesan = sprintf(
                'PERINGATAN SIMAGAS: Ananda %s tercatat %s pada mata pelajaran %s jam ke-%s. Mohon pantau kehadiran putra/putri Anda.',
                $siswa->nama,
                Str::upper($item['status']->label()),
                $jadwal->mata_pelajaran,
                $jamKe,
            );

            SendWhatsAppNotification::dispatch((string) $tujuan, $pesan);
        }
    }

    /**
     * Urutan jam pelajaran ini di antara jadwal kelas yang sama pada hari
     * yang sama — inilah "jam ke-berapa" yang disebut di pesan.
     *
     * Dihitung, bukan dibaca dari kolom: tabel jadwal_pelajaran tidak punya
     * kolom "jam ke". Menambah kolom itu berarti dua sumber kebenaran yang
     * bisa berselisih setiap kali jam mulai diubah.
     */
    private function jamKe(JadwalPelajaran $jadwal): string
    {
        $urut = JadwalPelajaran::where('kelas_id', $jadwal->kelas_id)
            ->where('hari', $jadwal->hari->value)
            ->get()
            ->sortBy(fn (JadwalPelajaran $j) => $j->jam_mulai->format('H:i'))
            ->values()
            ->search(fn (JadwalPelajaran $j) => $j->id === $jadwal->id);

        // Kalau entah kenapa tidak ketemu, jam mulainya lebih berguna
        // daripada angka yang salah.
        return $urut === false
            ? $jadwal->jam_mulai->format('H:i')
            : (string) ($urut + 1);
    }
}
