{{--
    View untuk App\Livewire\Auth\Login.

    Komponen Livewire wajib punya tepat satu elemen akar — semuanya dibungkus
    <div> paling luar di bawah ini.
--}}
<div class="flex min-h-screen items-center justify-center bg-brand-surface-muted p-4 sm:p-6">

    <div class="paksa-terang grid w-full max-w-5xl overflow-hidden rounded-[2rem] bg-brand-surface shadow-xl shadow-gray-900/10 lg:grid-cols-2">

        {{-- ============ SISI KIRI — BRANDING ============
             Disembunyikan di bawah lg: di HP, panel dekoratif setinggi layar
             hanya mendorong formnya turun dan memaksa orang men-scroll untuk
             sekadar masuk. --}}
        <div class="relative hidden overflow-hidden bg-gradient-to-br from-navy-900 via-navy-700 to-teal-700 p-12 text-white lg:flex lg:flex-col lg:justify-between">

            {{-- Corak abstrak: lingkaran besar yang di-blur + garis lengkung.
                 Semuanya murni CSS/SVG, tidak menambah request gambar baru. --}}
            <div class="pointer-events-none absolute -right-24 -top-28 h-80 w-80 rounded-full bg-teal-400/25 blur-3xl"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-navy-500/40 blur-3xl"></div>
            <svg class="pointer-events-none absolute inset-0 h-full w-full opacity-[0.13]" viewBox="0 0 400 500" fill="none" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
                <path d="M-40 380 C 90 300, 130 180, 300 150 S 470 60, 520 -20" stroke="white" stroke-width="1.5" fill="none" />
                <path d="M-40 440 C 90 360, 130 240, 300 210 S 470 120, 520 40" stroke="white" stroke-width="1.5" fill="none" />
                <path d="M-40 500 C 90 420, 130 300, 300 270 S 470 180, 520 100" stroke="white" stroke-width="1.5" fill="none" />
                <circle cx="330" cy="90" r="46" stroke="white" stroke-width="1.5" />
                <circle cx="330" cy="90" r="74" stroke="white" stroke-width="1" />
            </svg>

            <div class="relative flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-surface p-1.5">
                    <img src="{{ asset('logo-mark.png') }}" alt="" class="h-full w-full object-contain">
                </span>
                <div>
                    <p class="text-sm font-bold leading-tight tracking-wide">SIMAGAS</p>
                    <p class="text-xs text-white/60">Sistem Absensi Digital</p>
                </div>
            </div>

            <div class="relative max-w-sm">
                <h1 class="text-3xl font-extrabold leading-tight tracking-tight">
                    Selamat Datang di SIMAGAS
                </h1>
                <p class="mt-3 text-sm leading-relaxed text-white/70">
                    Satu portal untuk seluruh kehadiran SMK Islam Assya'roniyyah — scan di gerbang,
                    validasi wali kelas, dan pantauan kepala sekolah, semuanya dalam satu sistem.
                </p>

                <ul class="mt-8 space-y-3 text-sm text-white/80">
                    <li class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/10"><x-icon name="qr-code" class="h-4 w-4" /></span>
                        Absensi QR otomatis di gerbang
                    </li>
                    <li class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/10"><x-icon name="map-pin" class="h-4 w-4" /></span>
                        Absen radius berbasis lokasi untuk pegawai
                    </li>
                    <li class="flex items-center gap-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white/10"><x-icon name="phone" class="h-4 w-4" /></span>
                        Notifikasi WhatsApp ke wali murid
                    </li>
                </ul>
            </div>

            <p class="relative text-xs text-white/40">&copy; {{ now()->year }} SMK Islam Assya'roniyyah</p>
        </div>

        {{-- ============ SISI KANAN — FORM ============ --}}
        <div class="p-8 sm:p-10 lg:p-12">

            <div class="mb-8 flex justify-center lg:justify-start">
                <img src="{{ asset('logo.png') }}" alt="SIMAGAS — Sistem Absensi Digital"
                    class="h-auto w-44 sm:w-48">
            </div>

            <h2 class="text-2xl font-extrabold tracking-tight text-navy-700">Masuk Akun</h2>
            <p class="mt-1 text-sm text-brand-muted">
                Gunakan akun yang sudah terdaftar di sekolah.
            </p>

            @if ($errors->any())
                <div class="mt-6 flex items-start gap-2.5 rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-400">
                    <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <ul class="space-y-0.5">
                        @foreach ($errors->all() as $pesan)
                            <li>{{ $pesan }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- wire:submit.prevent — tanpa .prevent, browser tetap melakukan
                 submit HTML biasa dan halaman ikut ter-reload. --}}
            <form wire:submit.prevent="masuk" class="mt-7 space-y-5">

                <div>
                    <label for="identitas" class="mb-1.5 block text-sm font-semibold text-navy-700">
                        Email, NIS, atau No. HP
                    </label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-brand-faint">
                            <x-icon name="identification" class="h-5 w-5" />
                        </span>
                        {{-- type="text", BUKAN type="email".

                             Kalau tetap type="email", browser menolak
                             "1234567890" sebagai isian tidak sah dan formnya
                             tidak terkirim sama sekali — validasi bawaan
                             browser itu berjalan sebelum satu baris pun kode
                             kita dijalankan, dan pesannya ("Sertakan tanda @")
                             tidak bisa diubah. Wali murid yang mengetik NIS
                             anaknya akan mengira sistemnya rusak.

                             inputmode dibiarkan bawaan (bukan "numeric"):
                             kolom ini juga menerima email, dan memaksa papan
                             angka di HP membuat pengguna email harus
                             berpindah papan ketik lebih dulu. --}}
                        <input id="identitas" name="identitas" type="text" wire:model="identitas"
                            autocomplete="username" autocapitalize="none" spellcheck="false"
                            required autofocus
                            placeholder="nama@sekolah.id / 1234567890 / 0812xxxxxxx"
                            class="w-full rounded-xl border-0 bg-gray-50 py-3.5 pl-12 pr-4 text-sm text-brand-ink ring-1 ring-inset ring-gray-200 transition placeholder:text-brand-faint focus:bg-brand-surface focus:outline-none focus:ring-2 focus:ring-teal-500">
                    </div>
                    <p class="mt-1.5 text-xs text-brand-muted">
                        Wali murid boleh memakai <strong>NIS anak</strong> atau <strong>nomor HP</strong>
                        yang terdaftar di sekolah.
                    </p>
                </div>

                {{-- x-data lokal hanya untuk tombol lihat/sembunyikan sandi.
                     Alpine sudah ikut terpasang bersama Livewire v3. --}}
                <div x-data="{ terlihat: false }">
                    <label for="password" class="mb-1.5 block text-sm font-semibold text-navy-700">Kata Sandi</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-brand-faint">
                            <x-icon name="lock" class="h-5 w-5" />
                        </span>
                        <input id="password" x-bind:type="terlihat ? 'text' : 'password'"
                            wire:model="password" autocomplete="current-password" required
                            placeholder="••••••••"
                            class="w-full rounded-xl border-0 bg-gray-50 py-3.5 pl-12 pr-12 text-sm text-brand-ink ring-1 ring-inset ring-gray-200 transition placeholder:text-brand-faint focus:bg-brand-surface focus:outline-none focus:ring-2 focus:ring-teal-500">
                        <button type="button" x-on:click="terlihat = ! terlihat"
                            class="absolute inset-y-0 right-3 flex items-center rounded-lg px-1.5 text-brand-faint hover:text-brand-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                            x-bind:aria-label="terlihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                            <span x-show="! terlihat"><x-icon name="eye" class="h-5 w-5" /></span>
                            <span x-show="terlihat" style="display: none"><x-icon name="eye-off" class="h-5 w-5" /></span>
                        </button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label class="flex cursor-pointer select-none items-center gap-2.5 text-sm text-brand-muted">
                        <input type="checkbox" wire:model="ingatSaya"
                            class="h-4 w-4 rounded border-gray-300 text-teal-500 accent-teal-500 focus:ring-teal-500">
                        Ingat Saya
                    </label>

                    {{-- Rute reset kata sandi belum ada di aplikasi ini, jadi
                         tautannya menunjuk ke rute itu HANYA kalau benar-benar
                         terdaftar. Route::has() dipakai supaya halaman ini tidak
                         jatuh ke RouteNotFoundException sekarang, dan tautannya
                         muncul sendiri begitu fitur lupa sandi dibuat nanti. --}}
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}"
                            class="text-sm font-semibold text-navy-700 hover:text-navy-500 hover:underline">
                            Lupa Password?
                        </a>
                    @else
                        <span class="text-sm text-brand-faint" title="Hubungi admin sekolah untuk mengatur ulang kata sandi Anda.">
                            Lupa Password?
                        </span>
                    @endif
                </div>

                <button type="submit" wire:loading.attr="disabled" wire:target="masuk"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                    <span wire:loading.remove wire:target="masuk">Masuk Akun</span>
                    <span wire:loading wire:target="masuk" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                        Memeriksa…
                    </span>
                </button>
            </form>

            <p class="mt-8 text-center text-sm text-brand-muted">
                Belum mendaftar?
                <a href="{{ route('register') }}" class="font-bold text-teal-600 dark:text-teal-400 hover:text-teal-700 hover:dark:text-teal-400 hover:underline">
                    Buat akun sekarang
                </a>
            </p>
        </div>
    </div>
</div>
