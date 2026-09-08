<?php

namespace App\Livewire\Auth;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Registrasi mandiri. Akun yang dibuat di sini TIDAK langsung aktif:
 * statusnya 'pending', dan baru bisa dipakai setelah Super Admin
 * menyetujuinya di Pengaturan Sistem > Approval Akun Baru.
 *
 * CATATAN soal label tombol:
 * Brief menyebut tombol "Daftar & Verifikasi Email". Aplikasi ini belum punya
 * konfigurasi pengiriman email sama sekali, sehingga tombol dengan label itu
 * akan membuat pendaftar menunggu email yang tidak akan pernah datang. Yang
 * benar-benar terjadi adalah persetujuan admin, jadi tombol & layar suksesnya
 * mengatakan itu apa adanya. (Dikonfirmasi ke pemilik project sebelum ditulis.)
 */
#[Layout('layouts.tamu')]
#[Title('Daftar Akun Baru')]
class Register extends Component
{
    /** Maksimal pendaftaran per menit dari satu alamat IP. */
    private const BATAS_DAFTAR = 5;

    public string $nama = '';

    public string $email = '';

    public string $no_hp = '';

    public string $password = '';

    /**
     * Konfirmasi kata sandi — TIDAK diminta di brief, tapi ditambahkan
     * dengan sengaja: aplikasi ini belum punya fitur "lupa kata sandi".
     * Artinya satu salah ketik saat mendaftar = akun tidak bisa dipakai
     * selamanya sampai admin meresetnya manual lewat database. Satu kolom
     * tambahan jauh lebih murah daripada itu.
     */
    public string $password_confirmation = '';

    public string $alamat = '';

    /**
     * Disimpan sebagai string (bukan enum) karena inilah nilai mentah yang
     * dikirim <select> dari browser; dikonversi ke UserRole setelah lolos
     * validasi Rule::in.
     */
    public string $role = '';

    public string $nip = '';

    public string $jabatan = '';

    /** Jadi true setelah pendaftaran tersimpan — layar diganti pesan sukses. */
    public bool $berhasil = false;

    /**
     * Role yang dipilih dalam bentuk enum, atau null kalau belum memilih /
     * nilainya tidak dikenali.
     *
     * Dipakai view lewat $this->roleTerpilih (properti terhitung Livewire v3).
     */
    #[Computed]
    public function roleTerpilih(): ?UserRole
    {
        return UserRole::tryFrom($this->role);
    }

    /**
     * Apakah blok NIP + Jabatan harus tampil DAN divalidasi.
     *
     * Aturannya diambil dari UserRole::adalahPegawai(), bukan ditulis ulang
     * sebagai in_array($role, ['kepsek','guru','staff']) di dua tempat
     * terpisah (Blade untuk menampilkan, PHP untuk memvalidasi). Kalau ditulis
     * dua kali, cukup salah satu yang lupa diubah dan NIP bisa lolos tanpa
     * validasi — atau sebaliknya, form meminta NIP yang tidak pernah dipakai.
     */
    #[Computed]
    public function butuhDataPegawai(): bool
    {
        return $this->roleTerpilih?->adalahPegawai() ?? false;
    }

    protected function rules(): array
    {
        $aturan = [
            'nama' => ['required', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],

            // Nomor HP dipakai untuk notifikasi WhatsApp, jadi hanya menerima
            // angka, spasi, +, -, dan tanda kurung — bukan sembarang teks.
            'no_hp' => ['required', 'string', 'max:30', 'regex:/^[0-9()+\-\s]+$/'],
            'alamat' => ['required', 'string', 'min:10', 'max:500'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in($this->nilaiRoleSah())],
        ];

        if ($this->butuhDataPegawai) {
            // NIP OPSIONAL. Guru honorer & staf baru sering belum punya NIP,
            // dan mewajibkannya membuat mereka tidak bisa mendaftar sama
            // sekali. Rule::unique tetap dipasang: kalau diisi, tidak boleh
            // bentrok — dan MySQL maupun SQLite mengizinkan banyak baris NULL
            // pada kolom unique, jadi banyak pegawai tanpa NIP tidak saling
            // menabrak. Aturan 'regex angka' ikut dilepas supaya NIP berformat
            // lain (mis. berawalan huruf) tidak ditolak.
            $aturan['nip'] = ['nullable', 'string', 'max:50', Rule::unique('pegawai', 'nip')];
            $aturan['jabatan'] = ['required', 'string', 'min:3', 'max:100'];
        }

        return $aturan;
    }

    protected function messages(): array
    {
        return [
            'nama.required' => 'Nama lengkap wajib diisi.',
            'nama.min' => 'Nama lengkap terlalu pendek.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'email.unique' => 'Email ini sudah terdaftar. Silakan masuk atau gunakan email lain.',
            'no_hp.required' => 'Nomor HP/WA wajib diisi.',
            'no_hp.regex' => 'Nomor HP hanya boleh berisi angka dan tanda + - ( ).',
            'alamat.required' => 'Alamat wajib diisi.',
            'alamat.min' => 'Alamat terlalu singkat — tulis minimal nama jalan dan desa/kelurahan.',
            'password.required' => 'Kata sandi wajib diisi.',
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak sama.',
            'role.required' => 'Pilih dulu peran Anda di sekolah.',
            'role.in' => 'Peran yang dipilih tidak tersedia untuk pendaftaran mandiri.',
            'nip.unique' => 'NIP ini sudah terdaftar di data pegawai.',
            'jabatan.required' => 'Jabatan wajib diisi untuk peran ini.',
        ];
    }

    /**
     * Bersihkan pesan error NIP/Jabatan saat pengguna berpindah ke role yang
     * tidak membutuhkannya — kalau tidak, error dari pilihan sebelumnya
     * menempel di layar padahal kolomnya sudah tidak ada.
     */
    public function updatedRole(): void
    {
        // Properti terhitung di-cache per request; role baru saja berubah,
        // jadi cache-nya harus dibuang dulu supaya tidak menjawab pakai
        // pilihan sebelumnya.
        unset($this->roleTerpilih, $this->butuhDataPegawai);

        if (! $this->butuhDataPegawai) {
            $this->reset('nip', 'jabatan');
            $this->resetValidation(['nip', 'jabatan']);
        }
    }

    public function daftar()
    {
        // Pembatas laju: tanpa ini satu skrip bisa membanjiri tabel users dan
        // antrean Approval dengan ratusan pendaftaran palsu. Endpoint Livewire
        // tidak tersentuh middleware throttle di rute, jadi harus di sini.
        $kunci = 'daftar|' . request()->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_DAFTAR)) {
            $detik = RateLimiter::availableIn($kunci);

            throw ValidationException::withMessages([
                'email' => "Terlalu banyak percobaan pendaftaran. Coba lagi dalam {$detik} detik.",
            ]);
        }

        $data = $this->validate();

        RateLimiter::hit($kunci, 60);

        $role = UserRole::from($data['role']);

        // Transaksi: user & pegawai harus jadi bersama-sama. Kalau NIP-nya
        // ternyata bentrok di detik terakhir, akun user-nya pun ikut batal —
        // daripada meninggalkan akun pegawai tanpa data kepegawaian yang
        // membuat dashboard-nya error "belum ditautkan".
        DB::transaction(function () use ($data, $role) {
            $user = User::create([
                'name' => $data['nama'],
                'email' => $data['email'],
                'no_hp' => $data['no_hp'],
                'alamat' => $data['alamat'],
                'password' => $data['password'],   // di-hash otomatis oleh cast 'hashed'
                'role' => $role,

                // Inti alurnya: akun baru TIDAK aktif. StatusAkun::bolehLogin()
                // hanya mengizinkan Active, jadi selama masih Pending akun ini
                // benar-benar tidak bisa masuk — bukan sekadar diberi label.
                'status' => StatusAkun::Pending,
            ]);

            if ($this->butuhDataPegawai) {
                Pegawai::create([
                    // String kosong disimpan sebagai NULL, bukan ''. Kalau
                    // disimpan '' , pegawai KEDUA yang tidak mengisi NIP akan
                    // ditolak database karena dianggap NIP kembar — sedangkan
                    // NULL boleh berulang di kolom unique.
                    'nip' => filled($data['nip'] ?? null) ? $data['nip'] : null,
                    'nama' => $data['nama'],
                    'jabatan' => $data['jabatan'],
                    'no_hp' => $data['no_hp'],
                    'user_id' => $user->id,
                ]);
            }
        });

        // Kata sandi dibuang dari memori komponen supaya tidak ikut terkirim
        // balik ke browser di snapshot Livewire berikutnya.
        $this->reset('password', 'password_confirmation', 'nip', 'jabatan');

        $this->berhasil = true;
    }

    /** @return array<int, string> */
    private function nilaiRoleSah(): array
    {
        return array_map(fn (UserRole $r) => $r->value, UserRole::untukPendaftaran());
    }

    public function render()
    {
        return view('livewire.auth.register', [
            'daftarRole' => UserRole::untukPendaftaran(),
        ]);
    }
}
