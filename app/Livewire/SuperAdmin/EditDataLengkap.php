<?php

namespace App\Livewire\SuperAdmin;

use App\Enums\UserRole;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Modal "Edit Data Lengkap" untuk Siswa & Pegawai.
 *
 * Dipasang SEKALI per halaman daftar, lalu dibuka dari tombol Edit di setiap
 * baris tabel lewat:
 *     Livewire.dispatch('edit-data', { jenis: 'siswa', id: 12 })
 *
 * Satu komponen menangani dua entitas, bukan dua komponen terpisah: aturan
 * yang benar-benar berbeda hanya daftar kolomnya. Logika yang gampang salah —
 * transaksi, unggah foto, penghapusan foto lama, penjagaan akses — jadi cuma
 * ada satu salinan yang harus dijaga.
 */
class EditDataLengkap extends Component
{
    use WithFileUploads;

    public bool $terbuka = false;

    /** 'siswa' | 'pegawai' */
    public string $jenis = '';

    public ?int $id = null;

    // ---- Kolom bersama ----
    public string $nama = '';
    public string $tempat_lahir = '';
    public string $tanggal_lahir = '';
    public string $jenis_kelamin = '';
    public string $agama = '';
    public string $alamat = '';

    // ---- Khusus siswa ----
    public string $nis = '';
    public ?int $kelas_id = null;
    public ?int $wali_murid_id = null;
    public string $no_hp_wali = '';

    // ---- Khusus pegawai ----
    public string $nip = '';
    public string $jabatan = '';
    public string $no_hp = '';

    public $foto_baru = null;

    /** Pilihan agama — dipakai select di form. */
    public const DAFTAR_AGAMA = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    #[On('edit-data')]
    public function buka(string $jenis, int $id): void
    {
        abort_unless(in_array($jenis, ['siswa', 'pegawai'], true), 404);

        $this->pastikanBerwenang($jenis);

        $this->reset();
        $this->resetValidation();
        unset($this->model, $this->pratinjauFoto, $this->bisaUnggahFoto);

        $this->jenis = $jenis;
        $this->id = $id;

        $jenis === 'siswa' ? $this->muatSiswa() : $this->muatPegawai();

        $this->terbuka = true;
    }

    public function tutup(): void
    {
        $this->reset();
        $this->resetValidation();
        unset($this->model, $this->pratinjauFoto, $this->bisaUnggahFoto);
    }

    /**
     * Penjagaan akses tingkat komponen.
     *
     * Halaman daftarnya memang sudah dijaga middleware role, tapi endpoint
     * Livewire adalah alamat tersendiri (/livewire/update) yang tidak melewati
     * middleware halaman. Tanpa pemeriksaan ini, siapa pun yang sudah login
     * bisa memanggil komponen ini dan menyunting data siswa/pegawai.
     *
     * Acuannya rute yang benar-benar dimiliki role tersebut, bukan daftar role
     * yang ditulis ulang — jadi kalau pembagian menu berubah, penjagaan ini
     * ikut berubah sendiri.
     */
    private function pastikanBerwenang(string $jenis): void
    {
        $prefix = auth()->user()?->role?->routePrefix();

        abort_if($prefix === null, 403);

        abort_unless(
            Route::has($prefix . '.' . $jenis . '.index'),
            403,
            'Anda tidak berwenang menyunting data ini.',
        );
    }

    private function muatSiswa(): void
    {
        $siswa = Siswa::findOrFail($this->id);

        $this->nis = (string) $siswa->nis;
        $this->nama = (string) $siswa->nama;
        $this->kelas_id = $siswa->kelas_id;
        $this->wali_murid_id = $siswa->wali_murid_id;
        $this->no_hp_wali = (string) $siswa->no_hp_wali;
        $this->isiBiodata($siswa);
    }

    private function muatPegawai(): void
    {
        $pegawai = Pegawai::findOrFail($this->id);

        $this->nip = (string) $pegawai->nip;
        $this->nama = (string) $pegawai->nama;
        $this->jabatan = (string) $pegawai->jabatan;
        $this->no_hp = (string) $pegawai->no_hp;
        $this->isiBiodata($pegawai);

        // Alamat pegawai boleh sudah pernah diisi lewat halaman Profil
        // (tersimpan di users.alamat) sebelum kolom pegawai.alamat ada.
        if ($this->alamat === '') {
            $this->alamat = (string) ($pegawai->user?->alamat ?? '');
        }
    }

    private function isiBiodata($model): void
    {
        $this->tempat_lahir = (string) $model->tempat_lahir;
        $this->tanggal_lahir = $model->tanggal_lahir?->format('Y-m-d') ?? '';
        $this->jenis_kelamin = (string) $model->jenis_kelamin;
        $this->agama = (string) $model->agama;
        $this->alamat = (string) $model->alamat;
    }

    /** Data induk yang sedang disunting. */
    #[Computed]
    public function model(): Siswa|Pegawai|null
    {
        if (! $this->terbuka || $this->id === null) {
            return null;
        }

        return $this->jenis === 'siswa'
            ? Siswa::find($this->id)
            : Pegawai::with('user')->find($this->id);
    }

    /**
     * Foto untuk pratinjau: berkas yang baru dipilih kalau ada, kalau tidak
     * foto tersimpan.
     */
    #[Computed]
    public function pratinjauFoto(): ?string
    {
        if ($this->foto_baru) {
            try {
                return $this->foto_baru->temporaryUrl();
            } catch (\Throwable $e) {
                return null;
            }
        }

        $model = $this->model;

        if (! $model) {
            return null;
        }

        // Siswa menyimpan fotonya sendiri; pegawai memakai foto akun login-nya
        // (yang sama dengan yang ia unggah lewat halaman Profil Saya).
        return $this->jenis === 'siswa'
            ? $model->foto_url
            : $model->user?->foto_url;
    }

    /**
     * Pegawai tanpa akun login tidak punya tempat menyimpan foto — kolom
     * `foto` ada di tabel users. Form-nya memberi tahu, bukan menampilkan
     * tombol unggah yang diam-diam tidak menyimpan apa pun.
     */
    #[Computed]
    public function bisaUnggahFoto(): bool
    {
        if ($this->jenis === 'siswa') {
            return true;
        }

        return $this->model?->user !== null;
    }

    /** @return \Illuminate\Support\Collection<int, Kelas> */
    #[Computed]
    public function daftarKelas()
    {
        return Kelas::orderBy('nama_kelas')->get();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    #[Computed]
    public function daftarWaliMurid()
    {
        return User::where('role', UserRole::WaliMurid)->orderBy('name')->get();
    }

    protected function rules(): array
    {
        $umum = [
            'nama' => ['required', 'string', 'min:3', 'max:255'],
            'tempat_lahir' => ['nullable', 'string', 'max:100'],

            // before:today — tanggal lahir di masa depan hampir pasti salah
            // ketik tahun, dan kalau lolos akan membuat perhitungan usia
            // menghasilkan angka negatif di laporan mana pun nanti.
            'tanggal_lahir' => ['nullable', 'date', 'before:today'],
            'jenis_kelamin' => ['nullable', Rule::in(['L', 'P'])],
            'agama' => ['nullable', Rule::in(self::DAFTAR_AGAMA)],
            'alamat' => ['nullable', 'string', 'max:500'],
            'foto_baru' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ];

        if ($this->jenis === 'siswa') {
            return $umum + [
                'nis' => ['required', 'string', 'max:50', Rule::unique('siswa', 'nis')->ignore($this->id)],
                'kelas_id' => ['required', 'exists:kelas,id'],
                'wali_murid_id' => [
                    'nullable',
                    Rule::exists('users', 'id')->where('role', UserRole::WaliMurid->value),
                ],
                'no_hp_wali' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+\-\s]+$/'],
            ];
        }

        return $umum + [
            // NIP opsional — lihat catatan di App\Livewire\Auth\Register.
            'nip' => ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nip')->ignore($this->id)],
            'jabatan' => ['required', 'string', 'min:3', 'max:100'],
            'no_hp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+\-\s]+$/'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nis.required' => 'NIS wajib diisi.',
            'nis.unique' => 'NIS ini sudah dipakai siswa lain.',
            'nip.unique' => 'NIP ini sudah dipakai pegawai lain.',
            'jabatan.required' => 'Jabatan wajib diisi.',
            'kelas_id.required' => 'Kelas wajib dipilih.',
            'tanggal_lahir.before' => 'Tanggal lahir tidak boleh hari ini atau di masa depan.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka dan tanda + - ( ).',
            'no_hp_wali.regex' => 'Nomor HP wali hanya boleh berisi angka dan tanda + - ( ).',
            'foto_baru.image' => 'Berkas yang dipilih bukan gambar.',
            'foto_baru.mimes' => 'Foto harus berformat JPG atau PNG.',
            'foto_baru.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }

    public function updatedFotoBaru(): void
    {
        $this->validateOnly('foto_baru');
        unset($this->pratinjauFoto);
    }

    public function simpan()
    {
        $data = $this->validate();

        $model = $this->model;

        abort_if($model === null, 404);

        $fotoLama = $this->jenis === 'siswa' ? $model->foto : $model->user?->foto;
        $jalurBaru = null;

        if ($this->foto_baru && $this->bisaUnggahFoto) {
            $jalurBaru = $this->foto_baru->store('profile_photos', 'public');
        }

        DB::transaction(function () use ($model, $data, $jalurBaru) {
            $biodata = [
                'nama' => $data['nama'],
                'tempat_lahir' => $data['tempat_lahir'] ?: null,
                'tanggal_lahir' => $data['tanggal_lahir'] ?: null,
                'jenis_kelamin' => $data['jenis_kelamin'] ?: null,
                'agama' => $data['agama'] ?: null,
                'alamat' => $data['alamat'] ?: null,
            ];

            if ($this->jenis === 'siswa') {
                $model->fill($biodata + [
                    'nis' => $data['nis'],
                    'kelas_id' => $data['kelas_id'],
                    'wali_murid_id' => $data['wali_murid_id'] ?: null,
                    'no_hp_wali' => $data['no_hp_wali'] ?: null,
                ]);

                if ($jalurBaru) {
                    $model->foto = $jalurBaru;
                }

                $model->save();

                return;
            }

            $model->fill($biodata + [
                // Kosong -> NULL, supaya beberapa pegawai tanpa NIP tidak
                // saling bentrok di kolom unique.
                'nip' => filled($data['nip'] ?? null) ? $data['nip'] : null,
                'jabatan' => $data['jabatan'],
                'no_hp' => $data['no_hp'] ?: null,
            ]);

            $model->save();

            // Turunkan ke akun login yang tertaut supaya nama di sidebar,
            // kontak di Kartu Identitas, dan halaman Profil Saya miliknya
            // tidak menampilkan data lama.
            if ($user = $model->user) {
                $user->name = $data['nama'];

                if (filled($data['no_hp'])) {
                    $user->no_hp = $data['no_hp'];
                }

                if (filled($data['alamat'])) {
                    $user->alamat = $data['alamat'];
                }

                if ($jalurBaru) {
                    $user->foto = $jalurBaru;
                }

                $user->save();
            }
        });

        // Foto lama dihapus SETELAH transaksi sukses — kalau dihapus lebih
        // dulu lalu penyimpanannya gagal, fotonya hilang tanpa ada gantinya.
        if ($jalurBaru && $fotoLama && $fotoLama !== $jalurBaru) {
            Storage::disk('public')->delete($fotoLama);
        }

        $label = $this->jenis === 'siswa' ? 'Data siswa' : 'Data pegawai';

        session()->flash('status', "{$label} {$data['nama']} berhasil diperbarui.");

        // Alamat tujuan DIHITUNG DULU, sebelum tutup().
        // tutup() memanggil $this->reset() yang mengosongkan $jenis, sehingga
        // nama rutenya berubah jadi "super-admin..index" (dua titik) dan
        // seluruh penyimpanan berakhir dengan error 500 — padahal datanya
        // sudah terlanjur tersimpan. Persis itu yang terjadi saat diuji.
        $tujuan = $this->alamatKembali();

        $this->tutup();

        // Baris tabelnya dirender server (Blade biasa, lengkap dengan filter &
        // paginasi), jadi ia tidak ikut berubah sendiri setelah modal ditutup.
        // Dikembalikan ke ALAMAT YANG SAMA supaya filter kelas dan halaman
        // paginasi yang sedang dibuka tidak hilang.
        return $this->redirect($tujuan, navigate: false);
    }

    /**
     * Alamat halaman daftar tempat modal ini dibuka.
     *
     * Header Referer dipakai karena ia membawa query string (?kelas_id=3&page=2)
     * yang tidak bisa disusun ulang dari nama rute saja. Nilainya DIPERIKSA
     * harus se-host dengan aplikasi — header dari browser tidak boleh dipercaya
     * mentah-mentah sebagai tujuan redirect, karena itulah celah open redirect.
     */
    private function alamatKembali(): string
    {
        $cadangan = route(auth()->user()->role->routePrefix() . '.' . $this->jenis . '.index');
        $referer = request()->header('Referer');

        if (! $referer) {
            return $cadangan;
        }

        return parse_url($referer, PHP_URL_HOST) === parse_url(config('app.url'), PHP_URL_HOST)
            || parse_url($referer, PHP_URL_HOST) === request()->getHost()
                ? $referer
                : $cadangan;
    }

    public function render()
    {
        return view('livewire.super-admin.edit-data-lengkap');
    }
}
