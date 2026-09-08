<?php

namespace App\Livewire\Profile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Halaman "Profil Saya" — setiap pengguna mengubah datanya sendiri.
 *
 * Komponen ini SELALU bekerja pada auth()->user(), tidak pernah menerima id
 * dari luar. Itu disengaja: begitu sebuah komponen menerima "profil siapa yang
 * mau diedit" dari browser, ia jadi harus memeriksa kewenangan di setiap aksi,
 * dan satu jalur yang terlewat berarti orang bisa menyunting akun orang lain.
 * Dengan mengunci ke pengguna yang sedang login, celah itu tidak ada sejak awal.
 */
class UpdateProfile extends Component
{
    use WithFileUploads;

    public string $nama = '';

    public string $email = '';

    public string $no_hp = '';

    public string $alamat = '';

    /** Kata sandi lama — hanya diminta kalau ingin mengganti sandi. */
    public string $password_lama = '';

    public string $password_baru = '';

    public string $password_baru_confirmation = '';

    /** Berkas yang baru dipilih tapi BELUM disimpan. */
    public $foto_baru = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->nama = $user->name;
        $this->email = $user->email;
        $this->no_hp = (string) $user->no_hp;
        $this->alamat = (string) $user->alamat;

        // Kalau kolom di users masih kosong, ambil dari tabel relasinya —
        // data pegawai sudah lebih dulu ada di aplikasi ini sebelum kolom
        // no_hp/alamat ditambahkan ke users, jadi banyak akun lama yang
        // nomornya baru terisi di sana.
        if ($this->no_hp === '') {
            $this->no_hp = (string) ($user->pegawai?->no_hp ?? '');
        }
    }

    /**
     * Foto yang harus ditampilkan di pratinjau: berkas yang baru dipilih
     * (belum disimpan) kalau ada, kalau tidak ya foto tersimpan.
     */
    #[Computed]
    public function pratinjauFoto(): ?string
    {
        if ($this->foto_baru) {
            // temporaryUrl() hanya tersedia untuk berkas gambar; kalau
            // pengguna sempat memilih berkas lain, jangan sampai halamannya
            // ikut error sebelum validasi sempat memberi tahu.
            try {
                return $this->foto_baru->temporaryUrl();
            } catch (\Throwable $e) {
                return null;
            }
        }

        return auth()->user()->foto_url;
    }

    /**
     * Apakah `php artisan storage:link` sudah dijalankan.
     *
     * Tanpa symlink itu, foto TETAP tersimpan dengan benar tapi tampil sebagai
     * gambar rusak di mana-mana — gejala yang menyesatkan, karena kelihatan
     * seperti unggahannya gagal padahal tidak. Karena itu kondisinya dideteksi
     * dan dijelaskan, bukan dibiarkan jadi teka-teki.
     */
    #[Computed]
    public function symlinkStorageSiap(): bool
    {
        return File::exists(public_path('storage'));
    }

    protected function rules(): array
    {
        $user = auth()->user();

        return [
            'nama' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id)],
            'no_hp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9()+\-\s]+$/'],
            'alamat' => ['nullable', 'string', 'max:500'],

            // 2048 KB = 2 MB. Format dibatasi jpg/jpeg/png sesuai permintaan;
            // 'image' ikut dipasang supaya berkas yang cuma DIGANTI NAMANYA
            // jadi .jpg tetap ditolak — ekstensi bukan bukti isi.
            'foto_baru' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],

            'password_baru' => ['nullable', 'string', 'min:8', 'confirmed'],

            // Sandi lama wajib HANYA kalau sandi baru diisi. Ini yang mencegah
            // orang lain yang menemukan laptop tertinggal dalam keadaan login
            // mengganti sandi dan mengunci pemilik aslinya.
            'password_lama' => [
                Rule::requiredIf(fn () => filled($this->password_baru)),
                'nullable', 'string',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'nama.required' => 'Nama wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah dipakai akun lain.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka dan tanda + - ( ).',
            'foto_baru.image' => 'Berkas yang dipilih bukan gambar.',
            'foto_baru.mimes' => 'Foto harus berformat JPG atau PNG.',
            'foto_baru.max' => 'Ukuran foto maksimal 2 MB.',
            'password_baru.min' => 'Kata sandi baru minimal 8 karakter.',
            'password_baru.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
            'password_lama.required' => 'Masukkan kata sandi lama untuk mengganti kata sandi.',
        ];
    }

    /**
     * Validasi foto SEGERA setelah dipilih, tidak menunggu tombol Simpan.
     * Kalau ditunda, pengguna menunggu unggahan 5 MB selesai dulu baru diberi
     * tahu bahwa batasnya 2 MB.
     */
    public function updatedFotoBaru(): void
    {
        $this->validateOnly('foto_baru');
        unset($this->pratinjauFoto);
    }

    public function hapusFoto(): void
    {
        $user = auth()->user();

        if ($user->foto) {
            Storage::disk('public')->delete($user->foto);
            $user->forceFill(['foto' => null])->save();
        }

        $this->reset('foto_baru');
        unset($this->pratinjauFoto);

        session()->flash('profil-sukses', 'Foto profil dihapus.');
    }

    public function simpanProfil(): void
    {
        $data = $this->validate();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (filled($this->password_baru) && ! Hash::check($this->password_lama, $user->password)) {
            $this->addError('password_lama', 'Kata sandi lama salah.');

            return;
        }

        $fotoLama = $user->foto;
        $jalurBaru = null;

        if ($this->foto_baru) {
            $jalurBaru = $this->foto_baru->store('profile_photos', 'public');

            $this->perbaikiOrientasi(Storage::disk('public')->path($jalurBaru));
        }

        // Satu transaksi: kalau update tabel relasi gagal, kolom users tidak
        // ikut berubah setengah jalan.
        DB::transaction(function () use ($user, $data, $jalurBaru) {
            $user->name = $data['nama'];
            $user->email = $data['email'];
            $user->no_hp = $data['no_hp'] ?: null;
            $user->alamat = $data['alamat'] ?: null;

            if ($jalurBaru) {
                $user->foto = $jalurBaru;
            }

            if (filled($this->password_baru)) {
                // Cast 'hashed' di model User yang meng-hash-nya.
                $user->password = $this->password_baru;
            }

            $user->save();

            $this->sinkronkanRelasi($user, $data);
        });

        // Foto lama dihapus SETELAH transaksi sukses. Kalau dihapus lebih dulu
        // lalu penyimpanannya gagal, pengguna kehilangan foto lamanya tanpa
        // mendapat yang baru.
        if ($jalurBaru && $fotoLama && $fotoLama !== $jalurBaru) {
            Storage::disk('public')->delete($fotoLama);
        }

        $this->reset('password_lama', 'password_baru', 'password_baru_confirmation', 'foto_baru');
        unset($this->pratinjauFoto);

        // Ganti sandi membuat "remember token" lama tetap sah; di-refresh
        // supaya sesi di perangkat lain ikut terputus.
        if (filled($data['password_baru'] ?? null)) {
            session()->regenerate();
        }

        session()->flash('profil-sukses', 'Perubahan profil berhasil disimpan.');
    }

    /**
     * Tegakkan foto yang direkam miring oleh kamera HP.
     *
     * Kamera ponsel hampir selalu menyimpan piksel dalam orientasi sensor,
     * lalu menaruh penanda "sebenarnya diputar sekian derajat" di data EXIF.
     * Aplikasi galeri menghormati penanda itu; <img> di HTML dan html2canvas
     * TIDAK. Akibatnya pas foto yang di HP terlihat tegak akan tampil miring
     * 90 derajat di halaman profil maupun di Kartu Identitas.
     *
     * Perbaikannya dilakukan sekali saat unggah — bukan setiap kali gambar
     * ditampilkan — dan seluruhnya dibungkus penjagaan: kalau ekstensi exif
     * atau gd tidak ada di server, atau apa pun gagal, berkas aslinya
     * dibiarkan apa adanya. Foto miring masih jauh lebih baik daripada foto
     * yang rusak.
     */
    private function perbaikiOrientasi(string $jalur): void
    {
        if (! function_exists('exif_read_data') || ! function_exists('imagecreatefromjpeg')) {
            return;
        }

        try {
            if (@mime_content_type($jalur) !== 'image/jpeg') {
                return;   // PNG tidak menyimpan orientasi EXIF
            }

            $exif = @exif_read_data($jalur);
            $orientasi = $exif['Orientation'] ?? 1;

            $derajat = match ((int) $orientasi) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };

            if ($derajat === 0) {
                return;
            }

            $gambar = @imagecreatefromjpeg($jalur);

            if (! $gambar) {
                return;
            }

            $diputar = imagerotate($gambar, $derajat, 0);
            imagejpeg($diputar, $jalur, 90);
            imagedestroy($gambar);
            imagedestroy($diputar);
        } catch (\Throwable $e) {
            // Sengaja diam: berkas aslinya tetap ada dan tetap terpakai.
        }
    }

    /**
     * Turunkan perubahan ke tabel data induk yang terkait.
     *
     * @param  array<string, mixed>  $data
     */
    private function sinkronkanRelasi(User $user, array $data): void
    {
        // --- Pegawai (hubungan satu-ke-satu, tidak ambigu) ---
        if ($pegawai = $user->pegawai) {
            $pegawai->nama = $data['nama'];

            if (filled($data['no_hp'])) {
                $pegawai->no_hp = $data['no_hp'];
            }

            $pegawai->save();
        }

        // --- Wali murid: turunkan nomor HP ke data anak-anaknya ---
        // siswa.no_hp_wali adalah nomor yang dipakai notifikasi WhatsApp
        // kehadiran dan tercetak di Kartu Pelajar. Kalau tidak ikut diperbarui,
        // wali murid mengganti nomornya di sini tapi notifikasi tetap terkirim
        // ke nomor lama — dan tidak ada yang tahu kenapa.
        //
        // Perilaku ini TIDAK disembunyikan: halaman profilnya menyebutkan
        // bahwa nomor ini juga dipakai untuk notifikasi kehadiran anak.
        if ($user->role === UserRole::WaliMurid && filled($data['no_hp'])) {
            $user->siswaWali()->update(['no_hp_wali' => $data['no_hp']]);
        }
    }

    public function render()
    {
        return view('livewire.profile.update-profile', [
            'pengguna' => auth()->user(),
        ]);
    }
}
