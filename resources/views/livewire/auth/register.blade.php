{{--
    View untuk App\Livewire\Auth\Register.

    Satu elemen akar (wajib di Livewire), berisi dua keadaan: form pendaftaran
    dan layar sukses setelah terkirim.
--}}
<div class="flex min-h-screen items-center justify-center bg-brand-surface-muted p-4 py-10 sm:p-6 sm:py-12">

    <div class="paksa-terang w-full max-w-3xl overflow-hidden rounded-[2rem] bg-brand-surface shadow-xl shadow-gray-900/10">

        @if ($berhasil)

            {{-- ================= LAYAR SUKSES =================
                 Isinya menjelaskan apa yang SEBENARNYA terjadi berikutnya
                 (menunggu persetujuan admin), bukan menyuruh orang mengecek
                 email yang memang tidak pernah dikirim. --}}
            <div class="flex flex-col items-center p-10 text-center sm:p-14">
                <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
                    <x-icon name="check-circle" class="h-9 w-9" />
                </span>

                <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-navy-700">Pendaftaran Terkirim</h1>

                <p class="mt-3 max-w-md text-sm leading-relaxed text-brand-muted">
                    Terima kasih. Akun Anda sudah masuk antrean persetujuan admin sekolah.
                    Anda <span class="font-semibold text-navy-700">belum bisa masuk</span>
                    sampai admin menyetujuinya.
                </p>

                <div class="mt-7 w-full max-w-md rounded-2xl bg-brand-surface-muted p-5 text-left">
                    <p class="text-xs font-bold uppercase tracking-wide text-brand-faint">Langkah berikutnya</p>
                    <ol class="mt-3 space-y-2.5 text-sm text-brand-muted">
                        <li class="flex gap-2.5">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-navy-700 text-[11px] font-bold text-white">1</span>
                            Admin sekolah memeriksa data Anda di menu Approval Akun Baru.
                        </li>
                        <li class="flex gap-2.5">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-navy-700 text-[11px] font-bold text-white">2</span>
                            Setelah disetujui, akun langsung aktif — silakan masuk dengan email &amp; kata sandi yang tadi Anda buat.
                        </li>
                        <li class="flex gap-2.5">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-navy-700 text-[11px] font-bold text-white">3</span>
                            Kalau lebih dari satu hari kerja belum aktif, hubungi Tata Usaha sekolah.
                        </li>
                    </ol>
                </div>

                <a href="{{ route('login') }}"
                    class="mt-8 inline-flex items-center justify-center gap-2 rounded-xl bg-teal-500 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600">
                    Kembali ke Halaman Masuk
                    <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>

        @else

            {{-- ================= FORM PENDAFTARAN ================= --}}
            <div class="p-8 sm:p-10 lg:p-12">

                <div class="flex flex-col items-center text-center">
                    <img src="{{ asset('logo.png') }}" alt="SIMAGAS — Sistem Absensi Digital"
                        class="h-auto w-40 sm:w-44">
                    <h1 class="mt-6 text-2xl font-extrabold tracking-tight text-navy-700">Daftar Akun Baru</h1>
                    <p class="mt-1.5 max-w-md text-sm text-brand-muted">
                        Isi data di bawah ini. Akun akan aktif setelah disetujui admin sekolah.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="mt-7 flex items-start gap-2.5 rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-400">
                        <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p class="font-semibold">Periksa lagi isian berikut:</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $pesan)
                                    <li>{{ $pesan }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                @php
                    // Satu definisi kelas input, dipakai ulang di semua kolom —
                    // supaya gaya fokus teal-nya tidak perlu ditulis 8 kali dan
                    // tidak bisa jadi beda-beda antar kolom.
                    $kelasInput = 'w-full rounded-xl border-0 bg-gray-50 px-4 py-3.5 text-sm text-brand-ink ring-1 ring-inset ring-gray-200 transition placeholder:text-brand-faint focus:bg-brand-surface focus:outline-none focus:ring-2 focus:ring-teal-500';
                    $kelasLabel = 'mb-1.5 block text-sm font-semibold text-navy-700';
                @endphp

                <form wire:submit.prevent="daftar" class="mt-7 space-y-5">

                    {{-- Baris 1: Nama Lengkap & Email --}}
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="nama" class="{{ $kelasLabel }}">Nama Lengkap</label>
                            <input id="nama" type="text" wire:model="nama" required autofocus
                                placeholder="Nama sesuai identitas" autocomplete="name"
                                class="{{ $kelasInput }}">
                        </div>
                        <div>
                            <label for="email" class="{{ $kelasLabel }}">Email</label>
                            <input id="email" type="email" wire:model="email" required
                                placeholder="nama@email.com" autocomplete="email"
                                class="{{ $kelasInput }}">
                        </div>
                    </div>

                    {{-- Baris 2: Nomor HP/WA & Password --}}
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="no_hp" class="{{ $kelasLabel }}">Nomor HP / WhatsApp</label>
                            <input id="no_hp" type="tel" wire:model="no_hp" required
                                placeholder="08xxxxxxxxxx" autocomplete="tel" inputmode="tel"
                                class="{{ $kelasInput }}">
                        </div>
                        <div x-data="{ terlihat: false }">
                            <label for="password" class="{{ $kelasLabel }}">Kata Sandi</label>
                            <div class="relative">
                                <input id="password" x-bind:type="terlihat ? 'text' : 'password'"
                                    wire:model="password" required autocomplete="new-password"
                                    placeholder="Minimal 8 karakter"
                                    class="{{ $kelasInput }} pr-12">
                                <button type="button" x-on:click="terlihat = ! terlihat"
                                    class="absolute inset-y-0 right-3 flex items-center rounded-lg px-1.5 text-brand-faint hover:text-brand-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500"
                                    x-bind:aria-label="terlihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'">
                                    <span x-show="! terlihat"><x-icon name="eye" class="h-5 w-5" /></span>
                                    <span x-show="terlihat" style="display: none"><x-icon name="eye-off" class="h-5 w-5" /></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- Konfirmasi kata sandi — tambahan di luar brief.
                         Aplikasi ini belum punya fitur "lupa kata sandi", jadi
                         satu salah ketik di sini berarti akunnya tidak bisa
                         dipakai sampai admin turun tangan. --}}
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <label for="password_confirmation" class="{{ $kelasLabel }}">Ulangi Kata Sandi</label>
                            <input id="password_confirmation" type="password" wire:model="password_confirmation"
                                required autocomplete="new-password" placeholder="Ketik ulang kata sandi"
                                class="{{ $kelasInput }}">
                        </div>

                        {{-- Mengisi kolom kanan yang kalau tidak akan kosong
                             melompong, sekaligus memberi tahu hal yang memang
                             perlu diketahui pendaftar sebelum menekan Daftar. --}}
                        <div class="flex items-start gap-2.5 rounded-xl bg-brand-surface-muted p-4 text-xs leading-relaxed text-brand-muted sm:mt-7">
                            <x-icon name="lock" class="mt-0.5 h-4 w-4 shrink-0 text-brand-faint" />
                            <p>
                                Kata sandi minimal <span class="font-semibold text-brand-muted">8 karakter</span>.
                                Simpan baik-baik — sistem ini belum punya fitur reset sandi mandiri,
                                jadi kalau lupa Anda harus menghubungi admin sekolah.
                            </p>
                        </div>
                    </div>

                    {{-- Baris 3: Alamat --}}
                    <div>
                        <label for="alamat" class="{{ $kelasLabel }}">Alamat</label>
                        <textarea id="alamat" wire:model="alamat" rows="3" required
                            placeholder="Jalan, RT/RW, desa/kelurahan, kecamatan"
                            class="{{ $kelasInput }} resize-y"></textarea>
                    </div>

                    {{-- Pilihan peran --}}
                    <div>
                        <label for="role" class="{{ $kelasLabel }}">Peran Anda di Sekolah</label>
                        <div class="relative">
                            {{-- wire:model.live — harus .live (bukan model biasa),
                                 karena blok NIP/Jabatan di bawah baru bisa muncul
                                 kalau nilainya dikirim ke server SEKETIKA pilihan
                                 berubah, bukan menunggu tombol submit ditekan. --}}
                            <select id="role" wire:model.live="role" required
                                class="{{ $kelasInput }} appearance-none pr-11">
                                <option value="">— Pilih peran —</option>
                                @foreach ($daftarRole as $pilihan)
                                    <option value="{{ $pilihan->value }}">{{ $pilihan->label() }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-brand-faint">
                                <x-icon name="chevron-down" class="h-4 w-4" />
                            </span>
                        </div>
                    </div>

                    {{-- Blok dinamis: hanya untuk peran yang merupakan pegawai
                         sekolah (Kepala Sekolah, Guru, Staff). Wali Murid tidak
                         punya NIP, jadi dua kolom ini tidak ditampilkan DAN
                         tidak divalidasi untuknya.

                         Aturannya dibaca dari $this->butuhDataPegawai (yang
                         bersumber ke UserRole::adalahPegawai()) — bukan ditulis
                         ulang sebagai in_array(...) di sini. Dengan begitu
                         syarat "tampil" dan syarat "divalidasi" dijamin selalu
                         sama, karena keduanya membaca satu sumber yang sama. --}}
                    @if ($this->butuhDataPegawai)
                        <div class="animate-turun rounded-2xl bg-teal-50/60 p-5 ring-1 ring-inset ring-teal-100">
                            <p class="mb-4 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-teal-700 dark:text-teal-400">
                                <x-icon name="briefcase" class="h-4 w-4" />
                                Data Kepegawaian — {{ $this->roleTerpilih->label() }}
                            </p>

                            <div class="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <label for="nip" class="{{ $kelasLabel }}">
                                        NIP (Nomor Induk Pegawai)
                                        <span class="font-normal text-brand-faint">(Opsional)</span>
                                    </label>
                                    <input id="nip" type="text" wire:model="nip" inputmode="numeric"
                                        placeholder="Contoh: 198703142010011005"
                                        class="{{ $kelasInput }}">
                                </div>
                                <div>
                                    <label for="jabatan" class="{{ $kelasLabel }}">Jabatan</label>
                                    <input id="jabatan" type="text" wire:model="jabatan"
                                        placeholder="Contoh: Guru Matematika"
                                        class="{{ $kelasInput }}">
                                </div>
                            </div>
                        </div>
                    @endif

                    <button type="submit" wire:loading.attr="disabled" wire:target="daftar"
                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-teal-500 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                        <span wire:loading.remove wire:target="daftar">Daftar &amp; Kirim ke Admin</span>
                        <span wire:loading wire:target="daftar" class="inline-flex items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                            </svg>
                            Menyimpan…
                        </span>
                    </button>

                    <p class="text-center text-xs leading-relaxed text-brand-faint">
                        Akun dibuat dengan status <span class="font-semibold text-brand-muted">menunggu persetujuan</span>
                        dan baru bisa dipakai setelah disetujui admin sekolah.
                    </p>
                </form>

                <p class="mt-6 text-center text-sm text-brand-muted">
                    Sudah memiliki akun?
                    <a href="{{ route('login') }}" class="font-bold text-teal-600 dark:text-teal-400 hover:text-teal-700 hover:dark:text-teal-400 hover:underline">
                        Masuk di sini
                    </a>
                </p>
            </div>

        @endif
    </div>
</div>
