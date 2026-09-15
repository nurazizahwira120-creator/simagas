<?php

namespace App\Livewire;

use App\Enums\JenisAgenda;
use App\Enums\UserRole;
use App\Models\AgendaAkademik;
use App\Services\KalenderAkademik;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kalender Pendidikan — tampilan bulanan seperti kalender pada umumnya.
 *
 * ============ APA YANG MEMBUATNYA BUKAN SEKADAR KALENDER ============
 * Kalender biasa menjawab "tanggal berapa hari Rabu depan". Yang ini
 * menjawab pertanyaan yang jauh lebih sering ditanyakan di sekolah:
 * "besok masuk tidak?" — dan jawabannya menggabungkan DUA hal yang tidak
 * terlihat di kalender mana pun: pola libur mingguan sekolah ini (Jumat
 * libur, Minggu masuk) dan kalender pendidikan Dinas.
 *
 * Warna kotaknya karena itu bukan hiasan. Kotak merah berarti hari itu
 * benar-benar TIDAK dihitung sebagai hari sekolah oleh sistem: tidak ada
 * penandaan alpa, dan tidak ikut jadi penyebut persentase kehadiran.
 * ====================================================================
 */
class KalenderPendidikan extends Component
{
    /** Bulan yang sedang dilihat, format Y-m. */
    public string $bulan = '';

    /** Tanggal yang sedang dibuka detailnya (Y-m-d), null = belum ada. */
    public ?string $tanggalDipilih = null;

    /* ---- form tambah/ubah agenda ---- */
    public ?int $agendaId = null;

    public string $judul = '';

    public string $tanggalMulai = '';

    public string $tanggalSelesai = '';

    public string $jenis = 'libur';

    public string $keterangan = '';

    public bool $formTerbuka = false;

    public function mount(): void
    {
        $this->bulan = today()->format('Y-m');
        $this->tanggalDipilih = today()->toDateString();
    }

    /* =================================================================
     * NAVIGASI BULAN
     * ================================================================= */

    #[Computed]
    public function bulanDipakai(): Carbon
    {
        // Nilai dari browser tidak dipercaya: 'bulan' bisa diisi apa saja
        // lewat konsol. Format yang tidak dikenali dikembalikan ke bulan ini
        // — bukan dilempar exception di tengah halaman.
        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            return today()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $this->bulan . '-01')->startOfMonth();
        } catch (\Throwable) {
            return today()->startOfMonth();
        }
    }

    public function bulanSebelumnya(): void
    {
        $this->bulan = $this->bulanDipakai->copy()->subMonthNoOverflow()->format('Y-m');
        $this->bersihkanCache();
    }

    public function bulanBerikutnya(): void
    {
        $this->bulan = $this->bulanDipakai->copy()->addMonthNoOverflow()->format('Y-m');
        $this->bersihkanCache();
    }

    public function keBulanIni(): void
    {
        $this->bulan = today()->format('Y-m');
        $this->tanggalDipilih = today()->toDateString();
        $this->bersihkanCache();
    }

    public function pilihTanggal(string $tanggal): void
    {
        $this->tanggalDipilih = $tanggal;
    }

    private function bersihkanCache(): void
    {
        // Cache #[Computed] harus dibuang setiap kali bulannya berpindah,
        // kalau tidak grid-nya masih menggambar bulan sebelumnya walau
        // judulnya sudah berganti — dan itu terbaca sebagai halaman rusak.
        unset($this->bulanDipakai, $this->kotakKalender, $this->agendaBulanIni, $this->ringkasBulan);
    }

    /* =================================================================
     * GRID KALENDER
     * ================================================================= */

    /**
     * Enam baris tujuh kolom, dimulai dari Minggu — bentuk kalender dinding.
     *
     * Selalu 42 kotak, termasuk ekor bulan sebelumnya dan kepala bulan
     * berikutnya. Jumlah kotak yang berubah-ubah membuat tinggi halaman
     * meloncat setiap kali bulannya diganti.
     *
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function kotakKalender(): Collection
    {
        $kalender = app(KalenderAkademik::class);
        $bulan = $this->bulanDipakai;

        // startOfWeek(SUNDAY): kolom pertama kalender ini Minggu, mengikuti
        // kalender dinding Indonesia — dan di sekolah ini Minggu kebetulan
        // juga hari masuk, jadi ia memang layak di kolom pertama.
        $mulai = $bulan->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);

        $kotak = collect();

        for ($i = 0; $i < 42; $i++) {
            $tanggal = $mulai->copy()->addDays($i);

            $agenda = $kalender->agendaPada($tanggal);
            $alasan = $kalender->alasanBukanKbm($tanggal);

            $kotak->push([
                'tanggal' => $tanggal,
                'kunci' => $tanggal->toDateString(),
                'angka' => $tanggal->day,
                'bulanIni' => $tanggal->month === $bulan->month,
                'hariIni' => $tanggal->isToday(),
                'hariKbm' => $alasan === null,
                'alasan' => $alasan,
                'agenda' => $agenda,
            ]);
        }

        return $kotak;
    }

    /** @return Collection<int, AgendaAkademik> */
    #[Computed]
    public function agendaBulanIni(): Collection
    {
        return app(KalenderAkademik::class)->agendaBulan($this->bulanDipakai);
    }

    /**
     * Angka ringkas di atas grid: berapa hari KBM, berapa hari libur.
     *
     * @return array{kbm: int, libur: int, total: int, agenda: int}
     */
    #[Computed]
    public function ringkasBulan(): array
    {
        $kalender = app(KalenderAkademik::class);
        $awal = $this->bulanDipakai->copy()->startOfMonth();
        $akhir = $this->bulanDipakai->copy()->endOfMonth();

        $kbm = $kalender->jumlahHariKbm($awal, $akhir);
        $total = (int) $awal->diffInDays($akhir) + 1;

        return [
            'kbm' => $kbm,
            'libur' => $total - $kbm,
            'total' => $total,
            'agenda' => $this->agendaBulanIni->count(),
        ];
    }

    /** Agenda pada tanggal yang sedang dibuka detailnya. */
    #[Computed]
    public function detailTanggal(): ?array
    {
        if (! $this->tanggalDipilih) {
            return null;
        }

        try {
            $tanggal = Carbon::parse($this->tanggalDipilih);
        } catch (\Throwable) {
            return null;
        }

        $kalender = app(KalenderAkademik::class);

        return [
            'tanggal' => $tanggal,
            'hariKbm' => $kalender->adalahHariKbm($tanggal),
            'alasan' => $kalender->alasanBukanKbm($tanggal),
            'agenda' => $kalender->agendaPada($tanggal),
        ];
    }

    #[Computed]
    public function hariLiburMingguan(): array
    {
        return app(KalenderAkademik::class)->hariLiburMingguan();
    }

    /* =================================================================
     * KELOLA AGENDA — hanya Super Admin & Kepala Sekolah
     * ================================================================= */

    /**
     * Boleh mengubah kalender?
     *
     * Diperiksa ULANG di setiap method yang menulis, bukan hanya dipakai
     * untuk menyembunyikan tombol. Method Livewire adalah endpoint HTTP
     * tersendiri: siapa pun yang sudah login bisa memanggil $wire.simpan()
     * dari konsol browser, dan tombol yang tidak digambar tidak menahan
     * apa pun.
     */
    #[Computed]
    public function bolehKelola(): bool
    {
        return in_array(auth()->user()?->role, [UserRole::SuperAdmin, UserRole::Kepsek], true);
    }

    public function bukaForm(?string $tanggal = null): void
    {
        abort_unless($this->bolehKelola(), 403);

        $this->reset(['agendaId', 'judul', 'keterangan']);
        $this->jenis = JenisAgenda::Libur->value;

        $awal = $tanggal ?: ($this->tanggalDipilih ?: today()->toDateString());
        $this->tanggalMulai = $awal;
        $this->tanggalSelesai = $awal;

        $this->formTerbuka = true;
        $this->resetValidation();
    }

    public function ubah(int $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $agenda = AgendaAkademik::findOrFail($id);

        /*
         | Agenda dari kalender Dinas ('kaldik') TIDAK boleh diubah dari sini.
         |
         | Bukan karena datanya suci, tapi karena seeder kalender tahun ajaran
         | berikutnya MENIMPA seluruh baris ber-sumber 'kaldik'. Perubahan
         | yang dikerjakan susah payah di sini akan hilang tanpa peringatan
         | saat kalender berikutnya dipasang. Lebih jujur menolaknya sekarang
         | daripada menghapusnya diam-diam nanti.
         */
        abort_if($agenda->dariKaldik(), 403, 'Agenda dari Kalender Dinas tidak bisa diubah.');

        $this->agendaId = $agenda->id;
        $this->judul = $agenda->judul;
        $this->tanggalMulai = $agenda->tanggal_mulai->toDateString();
        $this->tanggalSelesai = $agenda->tanggal_selesai->toDateString();
        $this->jenis = $agenda->jenis->value;
        $this->keterangan = (string) $agenda->keterangan;

        $this->formTerbuka = true;
        $this->resetValidation();
    }

    public function tutupForm(): void
    {
        $this->formTerbuka = false;
        $this->reset(['agendaId', 'judul', 'keterangan']);
        $this->resetValidation();
    }

    public function simpan(): void
    {
        abort_unless($this->bolehKelola(), 403);

        $data = $this->validate([
            'judul' => ['required', 'string', 'min:3', 'max:150'],
            'tanggalMulai' => ['required', 'date'],
            // after_or_equal memakai NAMA FIELD lain, bukan tanggal tetap:
            // aturannya mengikuti apa pun yang diisi pengguna.
            'tanggalSelesai' => ['required', 'date', 'after_or_equal:tanggalMulai'],
            'jenis' => ['required', 'in:libur,kegiatan,ujian'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'judul.required' => 'Judul agenda wajib diisi.',
            'judul.min' => 'Judul terlalu pendek — tuliskan supaya bisa dipahami orang lain.',
            'tanggalSelesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal daripada tanggal mulai.',
        ]);

        $isi = [
            'judul' => $data['judul'],
            'tanggal_mulai' => $data['tanggalMulai'],
            'tanggal_selesai' => $data['tanggalSelesai'],
            'jenis' => $data['jenis'],
            'keterangan' => $data['keterangan'] ?: null,
            'sumber' => 'sekolah',
            'dibuat_oleh' => auth()->id(),
        ];

        if ($this->agendaId) {
            $agenda = AgendaAkademik::findOrFail($this->agendaId);
            abort_if($agenda->dariKaldik(), 403, 'Agenda dari Kalender Dinas tidak bisa diubah.');
            $agenda->update($isi);
            $pesan = 'Agenda diperbarui.';
        } else {
            AgendaAkademik::create($isi);
            $pesan = 'Agenda ditambahkan.';
        }

        $this->tutupForm();
        $this->bersihkanCache();
        unset($this->detailTanggal);

        session()->flash('kalender-sukses', $pesan);
    }

    public function hapus(int $id): void
    {
        abort_unless($this->bolehKelola(), 403);

        $agenda = AgendaAkademik::findOrFail($id);
        abort_if($agenda->dariKaldik(), 403, 'Agenda dari Kalender Dinas tidak bisa dihapus.');

        $agenda->delete();

        $this->bersihkanCache();
        unset($this->detailTanggal);

        session()->flash('kalender-sukses', 'Agenda dihapus.');
    }

    public function render()
    {
        return view('livewire.kalender-pendidikan');
    }
}
