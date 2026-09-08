<?php

namespace App\Livewire\Ekskul;

use App\Enums\UserRole;
use App\Livewire\Concerns\BisaSweetAlert;
use App\Livewire\Ekskul\Concerns\PeranEkskul;
use App\Models\JadwalEkskul;
use App\Models\Pegawai;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Kelola Jadwal Ekstrakurikuler — Super Admin & Kepala Sekolah.
 *
 * ============ SEMUA AKSI MEMERIKSA HAK AKSES ULANG ============
 * Rutenya memang hanya didaftarkan untuk dua peran itu, tapi method Livewire
 * adalah endpoint HTTP tersendiri (/livewire/update): siapa pun yang sudah
 * login bisa memanggil simpan() atau hapus() dari konsol browser tanpa
 * pernah membuka halamannya. Karena itu bolehKelola() dipanggil di setiap
 * method yang mengubah data, bukan sekali di render().
 * ==============================================================
 */
class KelolaEkskul extends Component
{
    use BisaSweetAlert, PeranEkskul;

    /** Id yang sedang diubah; null berarti sedang menambah baru. */
    public ?int $ekskulId = null;

    public string $nama_ekskul = '';

    public string $hari = 'Senin';

    public string $jam_mulai = '';

    public string $jam_selesai = '';

    public ?int $pembina_id = null;

    public string $keterangan = '';

    /** Kotak pencarian di atas tabel. */
    public string $cari = '';

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /**
     * Pilihan hari untuk dropdown.
     *
     * Kolomnya string, jadi daftar ini cuma alat bantu isi — bukan
     * pembatas. Menambah pilihan baru ("Jumat & Sabtu", "Menyesuaikan")
     * cukup di sini, tanpa migration.
     */
    public const PILIHAN_HARI = [
        'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu',
    ];

    protected function rules(): array
    {
        return [
            'nama_ekskul' => ['required', 'string', 'min:3', 'max:100'],
            'hari' => ['required', 'string', 'max:40'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'pembina_id' => ['required', 'integer', 'exists:pegawai,id'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nama_ekskul.required' => 'Nama ekskul wajib diisi.',
            'nama_ekskul.min' => 'Nama ekskul terlalu pendek — tulis minimal 3 karakter.',
            'hari.required' => 'Pilih dulu harinya.',
            'jam_mulai.required' => 'Jam mulai wajib diisi.',
            'jam_mulai.date_format' => 'Jam mulai harus berformat jam:menit, misalnya 15:30.',
            'jam_selesai.required' => 'Jam selesai wajib diisi.',
            'jam_selesai.date_format' => 'Jam selesai harus berformat jam:menit, misalnya 17:00.',
            'pembina_id.required' => 'Pilih dulu pembinanya.',
            'pembina_id.exists' => 'Pembina yang dipilih sudah tidak ada di data pegawai.',
            'keterangan.max' => 'Keterangan maksimal 255 karakter.',
        ];
    }

    private function tolak(): void
    {
        $this->pesan('error', 'Tidak diizinkan',
            'Hanya Super Admin dan Kepala Sekolah yang boleh mengubah jadwal ekstrakurikuler.');
    }

    /**
     * Peta urutan hari untuk pengurutan.
     *
     * Pengurutannya DI PHP, bukan orderByRaw("FIELD(hari, ...)"): FIELD()
     * hanya ada di MySQL, dan halaman ini akan langsung mati di SQLite —
     * yang dipakai saat pengujian. Jumlah ekskul di satu sekolah paling
     * banyak puluhan baris, jadi mengurutkannya di PHP tidak terasa.
     *
     * Hari di luar daftar (mis. "Menyesuaikan") mendapat urutan besar
     * sehingga jatuh di akhir, bukan hilang atau bikin error.
     */
    private static function urutanHari(?string $hari): int
    {
        $indeks = array_search(
            Str::of((string) $hari)->trim()->lower()->value(),
            array_map('strtolower', self::PILIHAN_HARI),
            true,
        );

        return $indeks === false ? 99 : $indeks;
    }

    #[Computed]
    public function daftar()
    {
        $kunci = trim($this->cari);

        return JadwalEkskul::query()
            ->with('pembina:id,nama')
            ->withCount('anggota')
            ->when($kunci !== '', function ($q) use ($kunci) {
                $q->where(function ($w) use ($kunci) {
                    $w->where('nama_ekskul', 'like', '%' . $kunci . '%')
                        ->orWhere('hari', 'like', '%' . $kunci . '%')
                        ->orWhere('pembina_luar', 'like', '%' . $kunci . '%')
                        ->orWhereHas('pembina', fn ($p) => $p->where('nama', 'like', '%' . $kunci . '%'));
                });
            })
            ->get()
            ->sortBy([
                fn (JadwalEkskul $a, JadwalEkskul $b) => self::urutanHari($a->hari) <=> self::urutanHari($b->hari),
                fn (JadwalEkskul $a, JadwalEkskul $b) => strcmp((string) $a->jam_mulai, (string) $b->jam_mulai),
                fn (JadwalEkskul $a, JadwalEkskul $b) => strcmp((string) $a->nama_ekskul, (string) $b->nama_ekskul),
            ])
            ->values();
    }

    #[Computed]
    public function totalEkskul(): int
    {
        return JadwalEkskul::query()->distinct()->count('nama_ekskul');
    }

    /**
     * Isi dropdown pembina: pegawai yang akunnya berperan Guru atau Wali
     * Kelas. Wali kelas ikut karena ia tetap guru yang mengajar — memisahkan
     * keduanya hanya membuat separuh guru hilang dari pilihan.
     *
     * Kalau tidak ada satu pun yang cocok (sekolah baru, akun guru belum
     * dibuat), daftarnya JATUH ke seluruh pegawai. Dropdown kosong membuat
     * halaman ini mustahil dipakai sama sekali, dan sebabnya tidak kelihatan
     * dari layar — hanya select yang tidak berisi apa-apa.
     */
    #[Computed]
    public function daftarPembina()
    {
        $guru = Pegawai::query()
            ->select('id', 'nama', 'jabatan')
            ->whereHas('user', fn ($u) => $u->whereIn('role', [UserRole::Guru, UserRole::WaliKelas]))
            ->orderBy('nama')
            ->get();

        if ($guru->isNotEmpty()) {
            return $guru;
        }

        return Pegawai::query()->select('id', 'nama', 'jabatan')->orderBy('nama')->get();
    }

    /** Peran non-admin tetap boleh MEMBUKA halaman, tapi hanya membaca. */
    #[Computed]
    public function bisaKelola(): bool
    {
        return $this->bolehKelolaJadwal();
    }

    /** Ekskul yang dibina oleh pegawai yang sedang login. */
    #[Computed]
    public function idEkskulSayaBina(): array
    {
        $pegawai = $this->pegawaiSaya();

        if (! $pegawai) {
            return [];
        }

        return JadwalEkskul::where('pembina_id', $pegawai->id)->pluck('id')->all();
    }

    public function updatedCari(): void
    {
        unset($this->daftar);
    }

    public function simpan(): void
    {
        $this->notif = null;

        if (! $this->bolehKelolaJadwal()) {
            $this->tolak();

            return;
        }

        $data = $this->validate();

        // Perbandingan string "HH:MM" sudah cukup dan benar — format itu
        // terurut secara leksikografis, jadi "09:00" < "15:30" apa adanya.
        // Tidak perlu Carbon, dan tidak ada risiko salah urai zona waktu.
        if ($data['jam_selesai'] <= $data['jam_mulai']) {
            $this->addError('jam_selesai', 'Jam selesai harus lebih besar dari jam mulai.');

            return;
        }

        $data['nama_ekskul'] = trim($data['nama_ekskul']);
        $data['keterangan'] = trim((string) $data['keterangan']) ?: null;

        // Begitu pembinanya dipilih dari daftar pegawai, nama teks lama
        // (peninggalan sebelum ada relasi) tidak boleh ikut tertinggal —
        // kalau dibiarkan, halaman menampilkan dua nama pembina berbeda
        // untuk satu jadwal.
        $data['pembina_luar'] = null;

        // Penjaga duplikat: ekskul yang sama, di hari yang sama, jamnya
        // bertabrakan. Tanpa ini "Pramuka Sabtu 15:00" gampang masuk dua kali
        // karena tombol simpan tertekan dua kali, dan yang kelihatan di
        // jadwal siswa cuma dua baris identik tanpa penjelasan.
        $bentrok = JadwalEkskul::query()
            ->where('nama_ekskul', $data['nama_ekskul'])
            ->where('hari', $data['hari'])
            ->when($this->ekskulId, fn ($q) => $q->whereKeyNot($this->ekskulId))
            ->where('jam_mulai', '<', $data['jam_selesai'])
            ->where('jam_selesai', '>', $data['jam_mulai'])
            ->exists();

        if ($bentrok) {
            $this->addError('jam_mulai',
                'Sudah ada jadwal ' . $data['nama_ekskul'] . ' di hari ' . $data['hari']
                . ' yang jamnya bertabrakan dengan ini.');

            return;
        }

        if ($this->ekskulId !== null) {
            $baris = JadwalEkskul::find($this->ekskulId);

            if (! $baris) {
                $this->pesan('warn', 'Tidak ditemukan',
                    'Jadwal yang diubah sudah tidak ada — mungkin dihapus dari perangkat lain.');
                $this->batal();

                return;
            }

            $baris->update($data);

            $this->batal();
            unset($this->daftar, $this->totalEkskul);

            $this->swalToast('Perubahan jadwal tersimpan');
            $this->pesan('ok', 'Perubahan tersimpan',
                'Jadwal ' . $data['nama_ekskul'] . ' sudah diperbarui.');

            return;
        }

        JadwalEkskul::create($data);

        $this->batal();
        unset($this->daftar, $this->totalEkskul);

        $this->swalToast('Jadwal ekskul ditambahkan');
        $this->pesan('ok', 'Jadwal ditambahkan',
            $data['nama_ekskul'] . ' dijadwalkan setiap ' . $data['hari'] . ', '
            . JadwalEkskul::jam($data['jam_mulai']) . ' – ' . JadwalEkskul::jam($data['jam_selesai']) . '.');
    }

    public function edit(int $id): void
    {
        if (! $this->bolehKelolaJadwal()) {
            $this->tolak();

            return;
        }

        $baris = JadwalEkskul::find($id);

        if (! $baris) {
            $this->pesan('warn', 'Tidak ditemukan', 'Jadwal itu sudah tidak ada.');

            return;
        }

        $this->ekskulId = $baris->id;
        $this->nama_ekskul = (string) $baris->nama_ekskul;
        $this->hari = (string) $baris->hari;

        // Dipotong ke "HH:MM" karena <input type="time"> menolak
        // "15:30:00" dan diam-diam tampil kosong — form terlihat gagal
        // terisi padahal datanya ada.
        $this->jam_mulai = JadwalEkskul::jam($baris->jam_mulai);
        $this->jam_selesai = JadwalEkskul::jam($baris->jam_selesai);

        $this->pembina_id = $baris->pembina_id !== null ? (int) $baris->pembina_id : null;
        $this->keterangan = (string) $baris->keterangan;
        $this->notif = null;
        $this->resetValidation();

        $this->dispatch('gulir-ke-form-ekskul');
    }

    public function batal(): void
    {
        $this->reset(['ekskulId', 'nama_ekskul', 'jam_mulai', 'jam_selesai', 'pembina_id', 'keterangan']);
        $this->hari = 'Senin';
        $this->resetValidation();
    }

    public function hapus(int $id): void
    {
        $this->notif = null;

        if (! $this->bolehKelolaJadwal()) {
            $this->tolak();

            return;
        }

        $baris = JadwalEkskul::find($id);

        if (! $baris) {
            $this->pesan('warn', 'Tidak ditemukan', 'Jadwal itu sudah tidak ada.');

            return;
        }

        $nama = $baris->nama_ekskul;
        $hari = $baris->hari;

        $baris->delete();

        if ($this->ekskulId === $id) {
            $this->batal();
        }

        unset($this->daftar, $this->totalEkskul);

        $this->swalToast('Jadwal ekskul dihapus');
        $this->pesan('ok', 'Jadwal dihapus',
            'Jadwal ' . $nama . ' hari ' . $hari . ' sudah dihapus.');
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.ekskul.kelola-ekskul', [
            'pilihanHari' => self::PILIHAN_HARI,
        ]);
    }
}
