<?php

namespace App\Livewire\Auth;

use App\Enums\StatusAkun;
use App\Services\PencariAkunLogin;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
 * ============ LOGIN MULTI-KREDENSIAL ============
 * Kolom pertama menerima TIGA macam identitas: email, NIS anak, atau nomor
 * HP. Penerjemahannya ada di App\Services\PencariAkunLogin — termasuk
 * penjelasan kenapa NIS bermuara ke akun WALI MURID, bukan akun siswa
 * (siswa memang tidak punya akun di sistem ini).
 *
 * Yang penting dijaga di berkas ini: apa pun jenis identitasnya, jalur
 * pemeriksaan kata sandi, status akun, dan pembatasan percobaan HARUS tetap
 * satu. Menambah cabang "kalau NIS maka begini" adalah cara paling cepat
 * membuat salah satu jalur diam-diam kehilangan penjagaannya.
 * ================================================
 *
 * PENTING — kenapa pembatasan percobaan login ditulis manual di sini:
 * Versi lama memakai middleware `throttle:5,1` yang dipasang pada rute
 * POST /login. Begitu form ini pindah ke Livewire, submit-nya TIDAK lagi
 * lewat POST /login melainkan lewat endpoint internal Livewire
 * (POST /livewire/update). Middleware throttle di rute lama otomatis jadi
 * tidak berlaku — dan kalau tidak diganti, halaman login ini akan menerima
 * tebakan kata sandi tanpa batas sama sekali. Jadi pembatasannya dipindahkan
 * ke dalam komponen memakai RateLimiter, dengan aturan yang sama seperti
 * sebelumnya: 5 percobaan per menit per kombinasi identitas + alamat IP.
 */
#[Layout('layouts.tamu')]
#[Title('Masuk')]
class Login extends Component
{
    /** Maksimal percobaan gagal sebelum dikunci sementara. */
    private const BATAS_PERCOBAAN = 5;

    /** Lama penguncian dalam detik. */
    private const LAMA_KUNCI = 60;

    /**
     * Hash bcrypt dari teks acak yang tidak diketahui siapa pun.
     *
     * Dipakai HANYA untuk membakar waktu ketika identitasnya tidak ditemukan.
     * Tanpa ini, percobaan dengan identitas yang tidak terdaftar dijawab
     * hampir seketika, sedangkan identitas terdaftar dengan sandi salah
     * butuh ~100 ms untuk memeriksa hash. Selisih itu cukup untuk memilah
     * mana akun yang benar-benar ada — dan daftar nomor HP wali murid yang
     * valid adalah bahan mentah yang berguna bagi penipu.
     */
    private const HASH_PALSU = '$2y$12$tLzTBILP03p66Q81tSiz1.vtSFtXTLHRtWAxbIMjNse5YYFQZmMNW';

    /** Email, NIS anak, atau nomor HP. */
    public string $identitas = '';

    public string $password = '';

    public bool $ingatSaya = false;

    protected function rules(): array
    {
        return [
            // Sengaja TIDAK ada aturan 'email' di sini. Kolom ini menerima
            // tiga bentuk sekaligus, jadi validasinya hanya memastikan ada
            // isinya; benar atau tidaknya ditentukan saat pencarian akun.
            'identitas' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string'],
        ];
    }

    protected function messages(): array
    {
        return [
            'identitas.required' => 'Email, NIS, atau nomor HP wajib diisi.',
            'identitas.max' => 'Isian terlalu panjang.',
            'password.required' => 'Kata sandi wajib diisi.',
        ];
    }

    public function masuk(PencariAkunLogin $pencari)
    {
        $this->validate();

        $this->pastikanBelumDikunci();

        $temuan = $pencari->cari($this->identitas);

        /*
         | Satu nomor HP dipakai dua akun. Ini SATU-SATUNYA kegagalan yang
         | pesannya berbeda dari yang lain, dan itu disengaja.
         |
         | Membocorkan "nomor ini dipakai dua akun" memang memberi tahu
         | sedikit tentang data yang ada. Alternatifnya jauh lebih buruk:
         | menolak dengan pesan "salah" membuat orang tua yang nomornya
         | kebetulan kembar mencoba berulang kali, terkunci, lalu menelepon
         | sekolah — dan tidak seorang pun bisa menebak sebabnya. Yang
         | dibocorkan pun bukan identitas siapa pun, hanya fakta bahwa nomor
         | itu tidak bisa dipakai sebagai penanda tunggal.
         */
        if ($temuan['ganda']) {
            $this->reset('password');

            throw ValidationException::withMessages([
                'identitas' => 'Nomor HP ini terdaftar di lebih dari satu akun. '
                    . 'Silakan masuk memakai email, atau hubungi admin sekolah.',
            ]);
        }

        $user = $temuan['user'];

        if (! $user) {
            /*
             | Identitasnya tidak ditemukan. Waktu pemeriksaan hash tetap
             | dibakar supaya lamanya jawaban sama dengan kasus "akun ada,
             | sandi salah" — lihat catatan pada HASH_PALSU.
             */
            Hash::check($this->password, self::HASH_PALSU);

            $this->gagalkan();
        }

        /*
         | Auth::attempt() dipanggil dengan 'id', bukan dengan email/no_hp.
         |
         | Akunnya sudah ketemu di atas; yang tersisa hanyalah memverifikasi
         | kata sandinya. Menyerahkannya ke attempt() — dan bukan memanggil
         | Hash::check lalu Auth::login sendiri — membuat semua perilaku
         | bawaan Laravel tetap jalan: rehash otomatis kalau cost bcrypt-nya
         | berubah, event Attempting/Failed/Login untuk audit, dan penanganan
         | "ingat saya".
         */
        if (! Auth::attempt(['id' => $user->id, 'password' => $this->password], $this->ingatSaya)) {
            $this->gagalkan();
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

            throw ValidationException::withMessages(['identitas' => $pesan]);
        }

        RateLimiter::clear($this->kunciPembatas());

        // Cegah session fixation.
        session()->regenerate();

        return $this->redirectIntended(
            route($user->role->dashboardRouteName()),
            navigate: false,
        );
    }

    /**
     * Satu pesan untuk SEMUA kegagalan pencocokan.
     *
     * Baik identitasnya tidak terdaftar maupun sandinya yang salah,
     * jawabannya sama persis. Pesan yang membedakan keduanya ("NIS tidak
     * ditemukan" vs "sandi salah") berarti halaman ini bisa dipakai untuk
     * memastikan NIS atau nomor HP mana yang benar-benar terdaftar di
     * sekolah — cukup dengan mencoba satu per satu.
     */
    private function gagalkan(): never
    {
        RateLimiter::hit($this->kunciPembatas(), self::LAMA_KUNCI);

        // Kata sandi dikosongkan sebelum melempar error. Selain enak dipakai
        // (kolomnya bersih untuk diketik ulang), ini juga menjaga agar kata
        // sandi tidak ikut terbawa pulang di snapshot Livewire yang dikirim
        // balik ke browser bersama pesan errornya.
        $this->reset('password');

        throw ValidationException::withMessages([
            'identitas' => 'Email/NIS/No. HP atau kata sandi yang Anda masukkan salah.',
        ]);
    }

    private function pastikanBelumDikunci(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciPembatas(), self::BATAS_PERCOBAAN)) {
            return;
        }

        event(new Lockout(request()));

        $detik = RateLimiter::availableIn($this->kunciPembatas());

        throw ValidationException::withMessages([
            'identitas' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$detik} detik.",
        ]);
    }

    /**
     * Kunci pembatas dibuat per identitas + IP, bukan per IP saja: satu
     * sekolah biasanya keluar lewat satu alamat IP, jadi membatasi per IP
     * berarti satu guru yang salah ketik lima kali ikut mengunci seluruh
     * ruang guru.
     *
     * Nomor HP dibakukan lebih dulu, supaya "0812-3456-7890" dan
     * "+62 812 3456 7890" dihitung sebagai SATU identitas. Tanpa itu, satu
     * orang bisa mendapat 5 percobaan untuk setiap cara penulisan nomor yang
     * sama — dan batasnya berhenti menjadi batas.
     */
    private function kunciPembatas(): string
    {
        $identitas = trim($this->identitas);

        if (! str_contains($identitas, '@')) {
            $baku = app(PencariAkunLogin::class)->bakukanHp($identitas);
            $identitas = $baku !== '' ? $baku : $identitas;
        }

        return Str::transliterate(Str::lower($identitas) . '|' . request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
