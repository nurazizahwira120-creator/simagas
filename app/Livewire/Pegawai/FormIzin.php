<?php

namespace App\Livewire\Pegawai;

use App\Enums\AbsensiStatus;
use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Models\AbsensiPegawai;
use App\Models\User;
use App\Notifications\PengajuanIzinBaru;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Pengajuan Izin / Sakit oleh pegawai sendiri.
 *
 * BATASAN YANG DITEGAKKAN DI SINI (dan alasannya):
 *
 *  1. Tidak bisa mengajukan untuk tanggal yang SUDAH TERCATAT HADIR.
 *     Kalau dibiarkan, pegawai yang tadi pagi absen di gerbang bisa
 *     menimpanya jadi "izin" sore harinya, dan catatan kehadiran yang
 *     terverifikasi GPS/QR kalah oleh formulir yang diisi sendiri.
 *
 *  2. Tidak bisa mengajukan untuk tanggal yang sudah LEWAT lebih dari
 *     30 hari, dan tidak lebih dari 90 hari ke depan. Yang pertama
 *     mencegah "memperbaiki" rekap bulan-bulan lampau setelah laporan
 *     dikirim; yang kedua mencegah salah ketik tahun (2027 alih-alih
 *     2026) diam-diam masuk basis data.
 *
 *  3. Pengajuan pada tanggal yang sudah pernah diajukan akan MENGGANTI
 *     yang lama, bukan menambah baris kedua — tabel absensi_pegawai
 *     memang unik per (pegawai_id, tanggal). Pegawai diberi tahu
 *     bahwa pengajuannya diperbarui, bukan dibiarkan menebak.
 */
class FormIzin extends Component
{
    use WithFileUploads;

    public string $tanggal = '';

    public string $kategori = '';

    public string $alasan = '';

    public $bukti = null;

    /** Batas kewajaran tanggal pengajuan, dalam hari. */
    private const MUNDUR_MAKS = 30;

    private const MAJU_MAKS = 90;

    public function mount(): void
    {
        // Default hari ini, sesuai brief. Format Y-m-d supaya langsung
        // dikenali <input type="date">.
        $this->tanggal = now()->toDateString();
        $this->kategori = AbsensiStatus::Izin->value;
    }

    /**
     * Pegawai yang sedang login. Null kalau akun ini belum ditautkan ke
     * baris `pegawai` — mungkin, misalnya, untuk akun yang dibuat lewat
     * registrasi tapi datanya belum dilengkapi Super Admin.
     */
    #[Computed]
    public function pegawai()
    {
        return auth()->user()?->pegawai;
    }

    /** Riwayat pengajuan pegawai ini — supaya ia bisa melihat hasilnya. */
    #[Computed]
    public function riwayat()
    {
        if (! $this->pegawai) {
            return collect();
        }

        return AbsensiPegawai::query()
            ->where('pegawai_id', $this->pegawai->id)
            ->whereIn('status', [AbsensiStatus::Izin->value, AbsensiStatus::Sakit->value])
            ->orderByDesc('tanggal')
            ->limit(10)
            ->get();
    }

    protected function rules(): array
    {
        return [
            'tanggal' => 'required|date',
            'kategori' => 'required|in:izin,sakit',
            'alasan' => 'required|min:10',
            // 2 MB: cukup untuk foto surat dokter dari kamera HP setelah
            // dikompres, dan masih di bawah batas upload_max_filesize bawaan
            // PHP (2 MB) — kalau dinaikkan di sini tanpa menaikkan php.ini,
            // berkas besar gagal DIAM-DIAM sebelum Livewire sempat memvalidasi.
            'bukti' => 'nullable|image|max:2048',
        ];
    }

    protected function messages(): array
    {
        return [
            'tanggal.required' => 'Tanggal izin wajib diisi.',
            'tanggal.date' => 'Tanggal izin tidak dikenali.',
            'kategori.required' => 'Pilih dulu kategorinya: Izin atau Sakit.',
            'kategori.in' => 'Kategori hanya boleh Izin atau Sakit.',
            'alasan.required' => 'Kolom alasan wajib diisi dan jelaskan secara rinci (minimal 10 karakter).',
            'alasan.min' => 'Kolom alasan wajib diisi dan jelaskan secara rinci (minimal 10 karakter).',
            'bukti.image' => 'Lampiran harus berupa gambar (JPG/PNG).',
            'bukti.max' => 'Ukuran lampiran maksimal 2 MB.',
        ];
    }

    public function simpanIzin(): void
    {
        $data = $this->validate();

        // Gate dihitung ulang dari database DI DALAM method ini, bukan
        // dipercayakan ke properti publik. Method Livewire adalah endpoint
        // HTTP tersendiri (/livewire/update) — apa pun yang ada di properti
        // publik bisa dipalsukan dari browser, jadi keputusan boleh/tidak
        // boleh harus lahir dari data server.
        $pegawai = auth()->user()?->pegawai;

        if (! $pegawai) {
            $this->addError('tanggal', 'Akun Anda belum ditautkan ke data pegawai. Hubungi Super Admin.');

            return;
        }

        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();

        if ($tanggal->lt(now()->startOfDay()->subDays(self::MUNDUR_MAKS))) {
            $this->addError('tanggal', 'Tanggalnya sudah terlalu lampau (lebih dari ' . self::MUNDUR_MAKS . ' hari). Ajukan koreksi lewat Admin TU.');

            return;
        }

        if ($tanggal->gt(now()->startOfDay()->addDays(self::MAJU_MAKS))) {
            $this->addError('tanggal', 'Tanggalnya terlalu jauh ke depan. Periksa lagi tahunnya.');

            return;
        }

        $lama = AbsensiPegawai::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', $tanggal)
            ->first();

        if ($lama && $lama->status === AbsensiStatus::Hadir) {
            $this->addError('tanggal', 'Tanggal itu sudah tercatat HADIR (scan gerbang / absen radius) dan tidak bisa diubah dari sini. Hubungi Admin TU bila keliru.');

            return;
        }

        $memperbarui = $lama !== null;

        // Lampiran disimpan lebih dulu supaya kalau upload-nya gagal, tidak
        // ada baris absensi yang terlanjur tersimpan tanpa buktinya.
        $pathBukti = $lama?->bukti_foto;

        if ($this->bukti) {
            $pathBukti = $this->bukti->store('bukti-izin', 'public');
        }

        $isi = [
            'status' => $data['kategori'],
            'alasan' => $data['alasan'],
            'bukti_foto' => $pathBukti,
            // jam_masuk sengaja dikosongkan: orangnya memang tidak datang.
            'jam_masuk' => null,
            'keterangan' => 'Diajukan sendiri lewat halaman Pengajuan Izin',
        ];

        /*
         | SENGAJA TIDAK memakai updateOrCreate() di sini.
         |
         | updateOrCreate mencocokkan barisnya dengan perbandingan PERSIS
         | (`where tanggal = '2026-09-02'`), sedangkan kolom `tanggal`
         | di-cast ke date dan nilainya tersimpan sebagai '2026-09-02
         | 00:00:00'. Keduanya tidak cocok, sehingga updateOrCreate menyangka
         | barisnya belum ada lalu mencoba INSERT — dan langsung menabrak
         | unique(pegawai_id, tanggal) dengan error yang menyesatkan
         | ("Integrity constraint violation") padahal maksudnya cuma
         | memperbarui. Baris $lama di atas sudah dicari dengan whereDate()
         | yang menangani perbedaan itu, jadi tinggal dipakai.
         */
        if ($lama) {
            $lama->fill($isi)->save();
            $baris = $lama;
        } else {
            $baris = AbsensiPegawai::create($isi + [
                'pegawai_id' => $pegawai->id,
                'tanggal' => $tanggal->toDateString(),
            ]);
        }

        $this->beritahuAtasan($baris->fresh('pegawai'));

        $this->reset(['alasan', 'bukti']);
        $this->tanggal = now()->toDateString();
        $this->kategori = AbsensiStatus::Izin->value;

        // Cache #[Computed] harus dibuang, kalau tidak riwayatnya masih
        // menampilkan daftar sebelum pengajuan barusan.
        unset($this->riwayat);

        session()->flash('izin-sukses', $memperbarui
            ? 'Pengajuan untuk tanggal ' . $tanggal->translatedFormat('d F Y') . ' DIPERBARUI.'
            : 'Pengajuan izin terkirim untuk tanggal ' . $tanggal->translatedFormat('d F Y') . '.');
    }

    /**
     * Kirim pemberitahuan ke Kepala Sekolah & Super Admin.
     *
     * Dibungkus try/catch dan TIDAK pernah menggagalkan pengajuan: izinnya
     * sudah tersimpan, dan gagal membuat baris notifikasi bukan alasan untuk
     * menampilkan "pengajuan gagal" kepada pegawai yang sudah mengisi form
     * dengan benar.
     */
    private function beritahuAtasan(?AbsensiPegawai $absensi): void
    {
        if (! $absensi) {
            return;
        }

        try {
            $atasan = User::query()
                ->whereIn('role', [UserRole::Kepsek, UserRole::SuperAdmin])
                ->where('status', StatusAkun::Active)
                ->get();

            if ($atasan->isEmpty()) {
                return;
            }

            \Illuminate\Support\Facades\Notification::send(
                $atasan,
                PengajuanIzinBaru::dari($absensi)
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Gagal membuat notifikasi pengajuan izin.', [
                'absensi_id' => $absensi->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function render()
    {
        return view('livewire.pegawai.form-izin', [
            'pilihanKategori' => [AbsensiStatus::Izin, AbsensiStatus::Sakit],
        ]);
    }
}
