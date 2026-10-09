<?php

namespace App\Livewire\Ekskul;

use App\Enums\AbsensiStatus;
use App\Livewire\Concerns\BisaSweetAlert;
use App\Livewire\Ekskul\Concerns\PeranEkskul;
use App\Models\AbsensiEkskul as AbsensiEkskulModel;
use App\Models\HonorMengajar;
use App\Models\JadwalEkskul;
use App\Models\SesiEkskul;
use App\Services\AturanHonor;
use App\Services\AturanSesiEkskul;
use App\Services\PencatatHonor;
use App\Services\PenyimpanBuktiFoto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Absensi & pantauan kehadiran satu ekskul — tampilannya berbeda per peran.
 *
 *   PEMBINA (dan Super Admin / Kepsek) : form isi kehadiran per tanggal.
 *   WALI MURID                          : hanya riwayat kehadiran ANAKNYA.
 *   Peran lain                          : rekap baca-saja.
 *
 * ============ SATU KOMPONEN, BUKAN TIGA ============
 * Godaannya adalah membuat halaman terpisah per peran. Tapi ketiganya membaca
 * tabel yang sama dengan aturan yang sama (ekskul mana, tanggal mana, siapa
 * anggotanya); memisahkannya berarti menyalin aturan itu tiga kali, dan
 * perbaikan di satu tempat diam-diam tidak ikut di dua tempat lain. Yang
 * dipisahkan cukup APA YANG BOLEH DILAKUKAN, dan itu satu method: peranSaya().
 * ===================================================
 *
 * ============ WALI MURID TIDAK PERNAH MELIHAT SISWA LAIN ============
 * Query riwayat wali murid dibatasi ke id anaknya DI DALAM QUERY, bukan
 * disaring setelah semua baris terambil. Kalau disaring belakangan, nama
 * siswa lain sudah terlanjur ada di memori dan satu kesalahan kecil di view
 * cukup untuk membocorkannya.
 * =====================================================================
 *
 * ============ SESI EKSKUL (Mulai/Akhiri, seperti KBM) ============
 * Khusus PEMBINA, pada hari ekskulnya: scan QR ekskul -> absensi anggota
 * -> foto bukti -> Akhiri Sesi (+ honor). Aturannya di
 * App\Services\AturanSesiEkskul. Koreksi absensi tanggal lampau tetap
 * bisa seperti sebelumnya, tetapi tidak membuat sesi maupun honor.
 * ==================================================================
 */
class AbsensiEkskul extends Component
{
    use BisaSweetAlert, PeranEkskul, WithFileUploads;

    /** Folder foto bukti ekskul di disk 'public'. */
    public const FOLDER_BUKTI = 'bukti-ekskul';

    private const MAKS_FOTO_KB = 4096;

    /** Foto bukti kegiatan yang sedang dipilih (sebelum disimpan). */
    public $fotoBukti = null;

    public int $jadwalId;

    /** Tanggal pertemuan yang sedang diisi/dilihat (format Y-m-d). */
    public string $tanggal = '';

    /** @var array<int, string> status per siswa: [siswa_id => 'hadir'] */
    public array $status = [];

    /** @var array<int, string> keterangan per siswa */
    public array $keterangan = [];

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /** Batas mundur pengisian, dalam hari. */
    private const MUNDUR_MAKS = 60;

    public function mount(int $jadwal): void
    {
        $baris = JadwalEkskul::find($jadwal);

        abort_if($baris === null, 404, 'Jadwal ekskul tidak ditemukan.');

        $this->jadwalId = $baris->id;
        $this->tanggal = now()->toDateString();

        $this->muatIsian();
    }

    /* ===================== PERAN ===================== */

    /** 'isi' | 'wali' | 'lihat' */
    #[Computed]
    public function peranSaya(): string
    {
        if ($this->bolehKelolaEkskul($this->jadwal)) {
            return 'isi';
        }

        return $this->sayaWaliMurid() ? 'wali' : 'lihat';
    }

    #[Computed]
    public function jadwal(): ?JadwalEkskul
    {
        return JadwalEkskul::with('pembina:id,nama')->find($this->jadwalId);
    }

    /* ===================== DATA ===================== */

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

    /**
     * Anak-anak si wali murid yang MEMANG anggota ekskul ini.
     *
     * Perpotongan dua daftar, dikerjakan di database: anak siapa (dibatasi
     * id anaknya) DAN anggota ekskul ini. Wali murid yang anaknya tidak ikut
     * ekskul ini mendapat daftar kosong, bukan daftar anak orang lain.
     */
    #[Computed]
    public function anakSaya()
    {
        $jadwal = $this->jadwal;
        $ids = $this->idAnakSaya();

        if (! $jadwal || $ids === []) {
            return collect();
        }

        return $jadwal->anggota()
            ->whereIn('siswa.id', $ids)
            ->with('kelas:id,nama_kelas')
            ->orderBy('nama')
            ->get();
    }

    /** Riwayat kehadiran anak-anaknya di ekskul ini, terbaru dulu. */
    #[Computed]
    public function riwayatAnak()
    {
        $ids = $this->anakSaya->pluck('id')->all();

        if ($ids === []) {
            return collect();
        }

        return AbsensiEkskulModel::query()
            ->where('jadwal_ekskul_id', $this->jadwalId)
            ->whereIn('siswa_id', $ids)
            ->with('siswa:id,nama')
            ->orderByDesc('tanggal')
            ->limit(100)
            ->get();
    }

    /**
     * Rekap jumlah per status BULAN BERJALAN (baca-saja).
     *
     * Dulu dihitung sejak awal tanpa batas waktu — angkanya terus membesar
     * dan tidak bisa dibandingkan dari bulan ke bulan. Rekap lengkap per
     * bulan ada di halaman Rekap Absensi Ekskul.
     */
    #[Computed]
    public function rekap(): array
    {
        $baris = AbsensiEkskulModel::query()
            ->where('jadwal_ekskul_id', $this->jadwalId)
            ->whereAntaraTanggal('tanggal', now()->startOfMonth(), now()->endOfMonth())
            ->selectRaw('status_kehadiran, COUNT(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran')
            ->all();

        $hasil = [];

        foreach (AbsensiStatus::cases() as $s) {
            $hasil[$s->value] = (int) ($baris[$s->value] ?? 0);
        }

        return $hasil;
    }

    /** Tanggal-tanggal pertemuan yang sudah pernah diisi. */
    #[Computed]
    public function tanggalTerisi()
    {
        return AbsensiEkskulModel::query()
            ->where('jadwal_ekskul_id', $this->jadwalId)
            ->select('tanggal')
            ->distinct()
            ->orderByDesc('tanggal')
            ->limit(30)
            ->pluck('tanggal');
    }

    /** Sudah ada isian untuk tanggal yang sedang dibuka? */
    #[Computed]
    public function sudahTerisi(): bool
    {
        return AbsensiEkskulModel::query()
            ->where('jadwal_ekskul_id', $this->jadwalId)
            ->wherePadaTanggal('tanggal', $this->tanggal)
            ->exists();
    }

    /* ===================== ISIAN ===================== */

    /**
     * Isi $status & $keterangan dari database untuk tanggal yang dipilih.
     *
     * Siswa yang belum punya catatan diberi nilai bawaan 'hadir' — itu yang
     * paling sering benar di ekskul, jadi pembina hanya perlu mengubah yang
     * tidak datang. Bawaan kosong berarti setiap pertemuan harus mencentang
     * dua puluh baris satu per satu, dan yang terlewat tersimpan tanpa status.
     */
    private function muatIsian(): void
    {
        $tersimpan = AbsensiEkskulModel::query()
            ->where('jadwal_ekskul_id', $this->jadwalId)
            ->wherePadaTanggal('tanggal', $this->tanggal)
            ->get()
            ->keyBy('siswa_id');

        $status = [];
        $keterangan = [];

        foreach ($this->anggota as $siswa) {
            $baris = $tersimpan->get($siswa->id);

            $status[$siswa->id] = $baris
                ? $baris->status_kehadiran->value
                : AbsensiStatus::Hadir->value;

            $keterangan[$siswa->id] = $baris?->keterangan ?? '';
        }

        $this->status = $status;
        $this->keterangan = $keterangan;
    }

    public function updatedTanggal(): void
    {
        unset($this->sudahTerisi);

        $this->notif = null;
        $this->muatIsian();
    }

    public function simpan(): void
    {
        $this->notif = null;

        $jadwal = JadwalEkskul::find($this->jadwalId);

        if (! $jadwal || ! $this->bolehKelolaEkskul($jadwal)) {
            $this->notif = ['tipe' => 'error', 'judul' => 'Tidak diizinkan',
                'pesan' => 'Hanya pembina ekskul ini (atau Super Admin / Kepala Sekolah) yang boleh mengisi absensi.'];

            return;
        }

        $tanggal = $this->tanggalSah();

        if ($tanggal === null) {
            return;
        }

        $anggota = $this->anggota;

        if ($anggota->isEmpty()) {
            $this->notif = ['tipe' => 'warn', 'judul' => 'Belum ada anggota',
                'pesan' => 'Tambahkan dulu siswa lewat halaman Anggota sebelum mengisi absensi.'];

            return;
        }

        $sah = array_column(AbsensiStatus::cases(), 'value');
        $tersimpan = 0;

        DB::transaction(function () use ($anggota, $tanggal, $sah, &$tersimpan) {
            foreach ($anggota as $siswa) {
                $pilihan = $this->status[$siswa->id] ?? AbsensiStatus::Hadir->value;

                // Nilai dari browser tidak dipercaya begitu saja: $status
                // adalah properti komponen yang bisa diisi apa pun dari
                // konsol. Yang tidak dikenal jatuh ke 'alpha', bukan
                // tersimpan apa adanya dan merusak rekap.
                if (! in_array($pilihan, $sah, true)) {
                    $pilihan = AbsensiStatus::Alpha->value;
                }

                $isi = [
                    'status_kehadiran' => $pilihan,
                    'keterangan' => trim((string) ($this->keterangan[$siswa->id] ?? '')) ?: null,
                ];

                /*
                 | Baris yang sudah ada dicari dengan wherePadaTanggal(), BUKAN
                 | updateOrCreate(['tanggal' => '2026-09-05', ...]).
                 |
                 | Kolomnya DATE dan modelnya meng-cast 'tanggal' => 'date',
                 | jadi Eloquent MENYIMPANNYA sebagai "2026-09-05 00:00:00".
                 | Pencocokan updateOrCreate memakai nilai apa adanya
                 | ("2026-09-05"), tidak pernah menemukan baris itu, lalu
                 | mencoba INSERT dan menabrak indeks unik:
                 |
                 |   UNIQUE constraint failed: absensi_ekskuls.jadwal_ekskul_id,
                 |   absensi_ekskuls.siswa_id, absensi_ekskuls.tanggal
                 |
                 | Gejalanya: absensi pertama tersimpan, PERBAIKAN status
                 | sesudahnya selalu error 500 — persis saat pembina sadar ada
                 | yang salah dan ingin membetulkannya.
                 */
                $baris = AbsensiEkskulModel::query()
                    ->where('jadwal_ekskul_id', $this->jadwalId)
                    ->where('siswa_id', $siswa->id)
                    ->wherePadaTanggal('tanggal', $tanggal->toDateString())
                    ->first();

                if ($baris) {
                    $baris->fill($isi)->save();
                } else {
                    AbsensiEkskulModel::create($isi + [
                        'jadwal_ekskul_id' => $this->jadwalId,
                        'siswa_id' => $siswa->id,
                        'tanggal' => $tanggal->toDateString(),
                    ]);
                }

                $tersimpan++;
            }
        });

        unset($this->rekap, $this->tanggalTerisi, $this->sudahTerisi, $this->riwayatAnak, $this->sesiHariIni);

        $this->swalToast('Absensi tersimpan');
        $this->notif = ['tipe' => 'ok', 'judul' => 'Absensi tersimpan',
            'pesan' => $tersimpan . ' siswa tercatat untuk pertemuan '
                . $tanggal->locale('id')->translatedFormat('l, d F Y') . '.'];
    }

    /**
     * Validasi tanggal, dengan batas maju & mundur.
     *
     * Tanggal masa depan ditolak karena kehadiran yang belum terjadi tidak
     * bisa dicatat; tanggal terlalu lampau ditolak supaya salah ketik tahun
     * ("2025" jadi "2015") tidak diam-diam membuat catatan di masa yang tidak
     * pernah ada — dan tidak pernah ditemukan lagi karena tidak muncul di
     * daftar mana pun.
     */
    private function tanggalSah(): ?Carbon
    {
        try {
            $tanggal = Carbon::createFromFormat('Y-m-d', $this->tanggal)->startOfDay();
        } catch (\Throwable $e) {
            $this->notif = ['tipe' => 'error', 'judul' => 'Tanggal tidak valid',
                'pesan' => 'Pilih tanggal pertemuan yang benar.'];

            return null;
        }

        if ($tanggal->isAfter(now()->endOfDay())) {
            $this->notif = ['tipe' => 'warn', 'judul' => 'Tanggal di masa depan',
                'pesan' => 'Absensi hanya bisa diisi untuk hari ini atau sebelumnya.'];

            return null;
        }

        if ($tanggal->lt(now()->subDays(self::MUNDUR_MAKS)->startOfDay())) {
            $this->notif = ['tipe' => 'warn', 'judul' => 'Tanggal terlalu lampau',
                'pesan' => 'Pengisian mundur dibatasi ' . self::MUNDUR_MAKS . ' hari.'];

            return null;
        }

        return $tanggal;
    }

    /* ===================== SESI EKSKUL ===================== */

    /** Pengguna ini pembina (pegawai tertaut) ekskul ini? Admin/Kepsek TIDAK termasuk. */
    #[Computed]
    public function sayaPembina(): bool
    {
        $jadwal = $this->jadwal;
        $pegawai = $this->pegawaiSaya();

        return $jadwal && $pegawai && $jadwal->pembina_id && (int) $jadwal->pembina_id === (int) $pegawai->id;
    }

    /**
     * Keadaan sesi HARI INI untuk kartu sesi di layar pembina. null = hari
     * ini bukan hari ekskulnya (kartu tidak ditampilkan).
     *
     * @return array{sesi: ?SesiEkskul, waktu: array, alasan: ?string, absensi: bool}|null
     */
    #[Computed]
    public function sesiHariIni(): ?array
    {
        $jadwal = $this->jadwal;

        if (! $jadwal || ! $this->sayaPembina || ! $this->aturanSesi()->hariCocok($jadwal, now())) {
            return null;
        }

        $sesi = $this->aturanSesi()->sesiPada($jadwal, now());

        return [
            'sesi' => $sesi,
            'waktu' => $this->aturanSesi()->waktu($jadwal, now()),
            'alasan' => $sesi ? null : $this->aturanSesi()->alasanTidakBisaMulai($jadwal, auth()->user(), now()),
            'absensi' => $this->aturanSesi()->absensiTerisi($jadwal, now()),
        ];
    }

    /** Dipanggil kamera ($wire.mulaiSesi(kode)) atau isian kode manual. */
    public function mulaiSesi($kode): void
    {
        $this->notif = null;
        $jadwal = $this->jadwal;

        if (! $jadwal) {
            return;
        }

        $hasil = $this->aturanSesi()->mulai($jadwal, auth()->user(), (string) $kode);
        unset($this->sesiHariIni);

        // Nada & getar hasil scan (partials/scan-kamera), sama dengan Absen Mengajar.
        $this->dispatch('hasil-scan', tipe: $hasil['tipe']);

        if ($hasil['tipe'] !== 'error') {
            $this->tanggal = now()->toDateString();
            unset($this->sudahTerisi);
            $this->muatIsian();
        }

        $this->notif = ['tipe' => $hasil['tipe'], 'judul' => $hasil['judul'], 'pesan' => $hasil['pesan']];
    }

    public function updatedFotoBukti(): void
    {
        $this->validateOnly('fotoBukti', $this->aturanFoto());
    }

    public function unggahBukti(): void
    {
        $this->notif = null;
        unset($this->sesiHariIni);
        $keadaan = $this->sesiHariIni;
        $sesi = $keadaan['sesi'] ?? null;

        if (! $sesi || $sesi->sudahSelesai() || now()->gt($keadaan['waktu']['batas'])) {
            $this->notif = ['tipe' => 'error', 'judul' => 'Tidak bisa mengunggah',
                'pesan' => 'Foto bukti hanya bisa diunggah selama sesi ekskul hari ini masih terbuka.'];

            return;
        }

        $this->validate($this->aturanFoto());
        $jalurLama = $sesi->foto_bukti;

        try {
            $jalur = PenyimpanBuktiFoto::simpan($this->fotoBukti, self::FOLDER_BUKTI);
            $sesi->forceFill(['foto_bukti' => $jalur, 'bukti_dihapus_pada' => null])->save();
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan foto bukti ekskul.', ['sesi_ekskul_id' => $sesi->id, 'error' => $e->getMessage()]);
            $this->notif = ['tipe' => 'error', 'judul' => 'Gagal mengunggah',
                'pesan' => 'Foto tidak berhasil disimpan. Coba lagi, atau pakai foto dengan ukuran lebih kecil.'];

            return;
        }

        // Bukti lama dihapus SESUDAH yang baru tersimpan (lihat JurnalAbsenKelas).
        if ($jalurLama && $jalurLama !== $jalur) {
            try {
                Storage::disk('public')->delete($jalurLama);
            } catch (\Throwable $e) {
                Log::warning('Foto bukti ekskul lama gagal dihapus.', ['jalur' => $jalurLama]);
            }
        }

        $this->reset('fotoBukti');
        unset($this->sesiHariIni);
        $this->notif = ['tipe' => 'ok', 'judul' => 'Bukti tersimpan', 'pesan' => 'Foto bukti kegiatan tersimpan.'];
    }

    public function akhiriSesi(): void
    {
        $this->notif = null;
        unset($this->sesiHariIni);
        $jadwal = $this->jadwal;
        $sesi = $this->sesiHariIni['sesi'] ?? null;

        if (! $jadwal || ! $sesi) {
            $this->notif = ['tipe' => 'error', 'judul' => 'Belum ada sesi', 'pesan' => 'Mulai dulu sesinya dengan men-scan QR ekskul.'];

            return;
        }

        if ($alasan = $this->aturanSesi()->alasanTidakBisaAkhiri($sesi, $jadwal, now())) {
            $this->notif = ['tipe' => $sesi->sudahSelesai() ? 'warn' : 'error', 'judul' => 'Sesi belum bisa diakhiri', 'pesan' => $alasan];

            return;
        }

        $sesi->forceFill(['waktu_selesai' => now()])->save();
        $honor = $this->catatHonor($sesi, $jadwal);
        unset($this->sesiHariIni);

        // Mematikan alarm pengingat di layar ini (partials/pengingat-akhiri-sesi).
        $this->dispatch('sesi-diakhiri', id: 'e' . $sesi->id);

        $this->notif = ['tipe' => 'ok', 'judul' => 'Sesi ekskul diakhiri',
            'pesan' => 'Kegiatan ' . $jadwal->nama_ekskul . ' tercatat lengkap dengan absensi dan bukti foto.'
                . ($honor ? ' Honor ' . AturanHonor::rupiah($honor->nominal) . " ({$honor->jp} JP) masuk ke Rincian Pendapatan." : '')];
    }

    /** Gagal mencatat honor TIDAK membatalkan sesi yang sudah diakhiri. */
    private function catatHonor(SesiEkskul $sesi, JadwalEkskul $jadwal): ?HonorMengajar
    {
        try {
            return app(PencatatHonor::class)->catatEkskul($sesi, $jadwal);
        } catch (\Throwable $e) {
            Log::error('Gagal mencatat honor ekskul.', ['sesi_ekskul_id' => $sesi->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, array<int, string>> */
    private function aturanFoto(): array
    {
        return ['fotoBukti' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:' . self::MAKS_FOTO_KB]];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'fotoBukti.required' => 'Pilih dulu foto bukti kegiatannya.',
            'fotoBukti.image' => 'Berkas yang dipilih bukan gambar.',
            'fotoBukti.mimes' => 'Format yang diterima hanya JPG, PNG, atau WEBP.',
            'fotoBukti.max' => 'Ukuran foto maksimal 4 MB.',
        ];
    }

    private function aturanSesi(): AturanSesiEkskul
    {
        return app(AturanSesiEkskul::class);
    }

    public function render()
    {
        return view('livewire.ekskul.absensi-ekskul', [
            'pilihanStatus' => AbsensiStatus::cases(),
        ]);
    }
}
