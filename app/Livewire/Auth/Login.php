<?php

namespace App\Livewire\Auth;

use App\Enums\StatusAkun;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Satu pintu masuk untuk SEMUA 8 role. Tidak ada halaman login per role —
 * yang membedakan hanya tujuan redirect, lihat UserRole::dashboardRouteName().
 *
 * PENTING — kenapa pembatasan percobaan login ditulis manual di sini:
 * Versi lama memakai middleware `throttle:5,1` yang dipasang pada rute
 * POST /login. Begitu form ini pindah ke Livewire, submit-nya TIDAK lagi
 * lewat POST /login melainkan lewat endpoint internal Livewire
 * (POST /livewire/update). Middleware throttle di rute lama otomatis jadi
 * tidak berlaku — dan kalau tidak diganti, halaman login ini akan menerima
 * tebakan kata sandi tanpa batas sama sekali. Jadi pembatasannya dipindahkan
 * ke dalam komponen memakai RateLimiter, dengan aturan yang sama seperti
 * sebelumnya: 5 percobaan per menit per kombinasi email + alamat IP.
 */
#[Layout('layouts.tamu')]
#[Title('Masuk')]
class Login extends Component
{
    /** Maksimal percobaan gagal sebelum dikunci sementara. */
    private const BATAS_PERCOBAAN = 5;

    /** Lama penguncian dalam detik. */
    private const LAMA_KUNCI = 60;

    public string $email = '';

    public string $password = '';

    public bool $ingatSaya = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }

    public function masuk()
    {
        $this->validate();

        $this->pastikanBelumDikunci();

        if (! Auth::attempt(
            ['email' => $this->email, 'password' => $this->password],
            $this->ingatSaya,
        )) {
            RateLimiter::hit($this->kunciPembatas(), self::LAMA_KUNCI);

            // Kata sandi dikosongkan sebelum melempar error. Selain enak
            // dipakai (kolomnya bersih untuk diketik ulang), ini juga menjaga
            // agar kata sandi tidak ikut terbawa pulang di snapshot Livewire
            // yang dikirim balik ke browser bersama pesan errornya.
            $this->reset('password');

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi yang Anda masukkan salah.',
            ]);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Akun yang belum disetujui (pending) atau ditolak (rejected) TIDAK
        // boleh masuk. Pengecekan ditaruh di sini — setelah kata sandi
        // terbukti benar, sebelum session di-regenerate — supaya:
        //   1. kolom `status` benar-benar menjaga akses, bukan sekadar label
        //      di tabel Approval;
        //   2. pesannya tidak membocorkan status akun ke orang yang asal
        //      menebak, karena hanya muncul kalau kata sandinya sudah benar.
        if (! $user->status->bolehLogin()) {
            $pesan = $user->status === StatusAkun::Rejected
                ? 'Pendaftaran akun ini ditolak. Hubungi admin sekolah.'
                : 'Akun Anda belum disetujui admin. Silakan tunggu konfirmasi.';

            Auth::guard('web')->logout();
            session()->invalidate();
            session()->regenerateToken();

            $this->reset('password');

            throw ValidationException::withMessages(['email' => $pesan]);
        }

        RateLimiter::clear($this->kunciPembatas());

        // Cegah session fixation.
        session()->regenerate();

        return $this->redirectIntended(
            route($user->role->dashboardRouteName()),
            navigate: false,
        );
    }

    private function pastikanBelumDikunci(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciPembatas(), self::BATAS_PERCOBAAN)) {
            return;
        }

        event(new Lockout(request()));

        $detik = RateLimiter::availableIn($this->kunciPembatas());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
        ]);
    }

    /**
     * Kunci pembatas dibuat per email + IP, bukan per IP saja: satu sekolah
     * biasanya keluar lewat satu alamat IP, jadi membatasi per IP berarti
     * satu guru yang salah ketik lima kali ikut mengunci seluruh ruang guru.
     */
    private function kunciPembatas(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
