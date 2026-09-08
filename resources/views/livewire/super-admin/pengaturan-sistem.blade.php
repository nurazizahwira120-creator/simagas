{{--
    View komponen App\Livewire\SuperAdmin\PengaturanSistem — gaya TailAdmin.

    PANTANGAN: jangan menulis teks berbentuk tag HTML di dalam blok <script>
    pada view Livewire (termasuk di komentar JavaScript) — parser HTML akan
    keluar dari <script> dan Livewire melempar
    MultipleRootElementsDetectedException.
--}}
<div class="mx-auto w-full max-w-3xl space-y-6">

    {{-- ============ NOTIFIKASI ============ --}}
    @if ($notif)
        @php
            $gaya = match ($notif['tipe']) {
                'ok' => ['garis' => 'border-success-500 bg-success-500/10', 'ikon' => 'check-circle', 'warnaIkon' => 'text-success-500', 'judul' => 'text-brand-ink dark:text-white'],
                'warn' => ['garis' => 'border-warning-500 bg-warning-500/10', 'ikon' => 'exclamation-triangle', 'warnaIkon' => 'text-warning-500', 'judul' => 'text-[#9D5425]'],
                default => ['garis' => 'border-error-500 bg-error-500/10', 'ikon' => 'x-circle', 'warnaIkon' => 'text-error-500', 'judul' => 'text-[#B45454]'],
            };
        @endphp

        <div wire:key="notif-{{ md5($notif['pesan']) }}-{{ now()->format('Hisu') }}"
            class="flex w-full border-l-4 px-5 py-4 shadow-md {{ $gaya['garis'] }}">
            <x-icon name="{{ $gaya['ikon'] }}" class="mr-4 mt-0.5 h-5 w-5 shrink-0 {{ $gaya['warnaIkon'] }}" />
            <div class="min-w-0">
                <h5 class="mb-1 font-semibold {{ $gaya['judul'] }}">{{ $notif['judul'] }}</h5>
                <p class="text-sm leading-relaxed text-brand-muted dark:text-brand-faint">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    <form wire:submit="simpan" class="space-y-6">

        {{-- ============ SEKSI 1: INFORMASI SEKOLAH ============ --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Informasi Sekolah</h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    Nama ini dipakai pada kop laporan dan teks notifikasi.
                </p>
            </div>

            <div class="px-6 py-5">
                <label for="ps-nama" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                    Nama Sekolah
                </label>
                <input id="ps-nama" type="text" wire:model="nama_sekolah" maxlength="150"
                    class="w-full rounded-md border bg-transparent px-4 py-3 text-brand-ink outline-none transition focus:border-brand-500 dark:text-white
                           {{ $errors->has('nama_sekolah') ? 'border-error-500' : 'border-gray-200 dark:border-gray-800' }}">
                @error('nama_sekolah')
                    <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- ============ SEKSI 2: KONFIGURASI WHATSAPP GATEWAY ============ --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Konfigurasi WhatsApp Gateway</h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    Notifikasi otomatis ke wali murid saat anak scan di gerbang, dan
                    peringatan saat tercatat alpa/bolos di kelas.
                </p>
            </div>

            <div class="space-y-6 px-6 py-5">

                {{-- ---- Toggle switch ----
                     Checkbox aslinya disembunyikan (sr-only) tapi TETAP ADA di
                     DOM, bukan diganti div yang di-klik. Bedanya penting untuk
                     aksesibilitas: dengan checkbox sungguhan, sakelarnya bisa
                     dicapai lewat Tab dan diubah dengan spasi, dan pembaca
                     layar menyebutnya sebagai kotak centang yang menyala/mati.
                     Div yang "menyerupai switch" tidak bisa dipakai sama
                     sekali tanpa mouse. --}}
                <div>
                    <label for="ps-wa" class="flex cursor-pointer select-none items-center gap-4">
                        <div class="relative">
                            <input type="checkbox" id="ps-wa" wire:model.live="wa_gateway_status" class="peer sr-only">

                            <div class="h-8 w-14 rounded-full bg-gray-200 transition peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2 dark:bg-gray-800"></div>

                            <div class="absolute left-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-brand-surface shadow-sm transition peer-checked:translate-x-6"></div>
                        </div>

                        <span class="min-w-0">
                            <span class="block font-medium text-brand-ink dark:text-white">
                                Aktifkan Notifikasi WhatsApp Otomatis
                            </span>
                            <span class="block text-sm text-brand-muted dark:text-brand-faint">
                                @if ($wa_gateway_status)
                                    Aktif — pesan akan dikirim ke wali murid.
                                @else
                                    Nonaktif — tidak ada pesan yang dikirim, absensi tetap berjalan normal.
                                @endif
                            </span>
                        </span>
                    </label>
                </div>

                {{-- ---- Token Fonnte ---- --}}
                <div x-data="{ terlihat: false }">
                    <label for="ps-token" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                        Token API Fonnte
                        @unless ($wa_gateway_status)
                            <span class="font-normal text-brand-muted dark:text-brand-faint">(wajib kalau notifikasi dinyalakan)</span>
                        @endunless
                    </label>

                    <div class="relative">
                        <input id="ps-token" wire:model="fonnte_token" maxlength="255"
                            x-bind:type="terlihat ? 'text' : 'password'"
                            autocomplete="off" spellcheck="false"
                            placeholder="Tempel token dari dashboard Fonnte"
                            class="w-full rounded-md border bg-transparent px-4 py-3 pr-12 font-mono text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:text-white
                                   {{ $errors->has('fonnte_token') ? 'border-error-500' : 'border-gray-200 dark:border-gray-800' }}">

                        <button type="button" x-on:click="terlihat = ! terlihat"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-brand-muted transition hover:text-brand-500 dark:text-brand-faint"
                            x-bind:aria-label="terlihat ? 'Sembunyikan token' : 'Tampilkan token'">
                            <span x-show="! terlihat"><x-icon name="eye" class="h-5 w-5" /></span>
                            <span x-show="terlihat" style="display: none"><x-icon name="eye-off" class="h-5 w-5" /></span>
                        </button>
                    </div>

                    @error('fonnte_token')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-sm text-brand-muted dark:text-brand-faint">
                            Disimpan terenkripsi di database. Kalau APP_KEY di berkas .env
                            diganti, token ini harus diisi ulang.
                        </p>
                    @enderror
                </div>

                {{-- ---- Jeda pengiriman ---- --}}
                <div>
                    <label for="ps-delay" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                        Jeda Pengiriman (Detik)
                    </label>

                    <input id="ps-delay" type="number" wire:model.live="wa_delay" min="0" max="60"
                        class="w-full max-w-[180px] rounded-md border bg-transparent px-4 py-3 text-brand-ink outline-none transition focus:border-brand-500 dark:text-white
                               {{ $errors->has('wa_delay') ? 'border-error-500' : 'border-gray-200 dark:border-gray-800' }}">

                    @error('wa_delay')
                        <p class="mt-1.5 text-sm text-error-500">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-sm text-brand-muted dark:text-brand-faint">
                            Direkomendasikan 2&ndash;5 detik untuk menghindari pemblokiran nomor oleh WhatsApp.
                        </p>
                    @enderror

                    @if ($this->delayBerisiko)
                        <p class="mt-2 inline-flex items-start gap-2 rounded-md bg-warning-500/10 px-3 py-2 text-sm text-[#9D5425]">
                            <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                            Jeda di bawah 2 detik berisiko: saat 200 siswa scan gerbang dalam
                            waktu berdekatan, WhatsApp bisa menganggap nomor sekolah sebagai spam
                            dan memblokirnya.
                        </p>
                    @endif
                </div>

                {{-- ---- Uji koneksi ---- --}}
                <div class="rounded-md border border-dashed border-gray-200 px-4 py-4 dark:border-gray-800">
                    <p class="text-sm font-medium text-brand-ink dark:text-white">Uji koneksi</p>
                    <p class="mt-1 text-sm leading-relaxed text-brand-muted dark:text-brand-faint">
                        Kirim satu pesan percobaan ke nomor HP Anda sendiri. Kegagalan gateway
                        (token salah, kuota habis) tidak menimbulkan gejala apa pun di layar —
                        pesannya cuma tidak pernah sampai. Lebih baik ketahuan di sini.
                    </p>

                    <button type="button" wire:click="kirimUji" wire:loading.attr="disabled"
                        class="mt-3 inline-flex items-center gap-2 rounded-md border border-gray-200 px-5 py-2.5 text-sm font-medium text-brand-ink transition hover:border-brand-500 hover:text-brand-500 disabled:opacity-60 dark:border-gray-800 dark:text-white">
                        <x-icon name="phone" class="h-4 w-4" />
                        <span wire:loading.remove wire:target="kirimUji">Kirim Pesan Uji</span>
                        <span wire:loading wire:target="kirimUji">Mengirim…</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ============ TOMBOL SIMPAN ============ --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-brand-muted dark:text-brand-faint">
                Pesan dikirim lewat antrean — pastikan <span class="font-mono text-xs">php artisan queue:work</span> berjalan.
            </p>

            <button type="submit" wire:loading.attr="disabled" wire:target="simpan"
                class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-500/90 disabled:opacity-60">
                <svg wire:loading wire:target="simpan" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                </svg>
                <span wire:loading.remove wire:target="simpan">Simpan Pengaturan</span>
                <span wire:loading wire:target="simpan">Menyimpan…</span>
            </button>
        </div>
    </form>
</div>
