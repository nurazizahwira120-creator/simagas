{{--
    Form Pengajuan Izin — gaya form TailAdmin.

    CATATAN SOAL NAMA CLASS:
    Brief menyebut `border-stroke`, `dark:bg-form-input`,
    `dark:border-form-strokedark`, `bg-boxdark`. Nama-nama itu berasal dari
    TailAdmin v1 (Tailwind v3) dan TIDAK ADA satu pun di TailAdmin Pro 2.2.0
    yang dipakai project ini — saya periksa langsung ke style.css-nya. Kalau
    dipakai apa adanya, class-nya tidak menghasilkan CSS sama sekali: input
    kehilangan bingkai, kartu jadi kotak polos, dan tidak ada error apa pun
    yang memberi tahu kenapa. Padanan v4-nya yang dipakai di bawah:
        border-stroke            -> border-gray-300 dark:border-gray-700
        bg-boxdark               -> dark:bg-white/[0.03]
        dark:bg-form-input       -> dark:bg-gray-900
        shadow-default           -> shadow-theme-sm
    Hasil visualnya sama; hanya namanya mengikuti template yang benar-benar
    ada.
--}}
@php
    // Ditulis sekali, dipakai empat input. Kalau gaya input berubah, satu
    // tempat ini yang disunting.
    $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-5 py-3 font-medium text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 active:border-brand-500 disabled:cursor-not-allowed disabled:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:border-brand-500';
@endphp

<div class="grid gap-6 lg:grid-cols-5">

    {{-- ================= FORM ================= --}}
    <div class="lg:col-span-3">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">

            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Formulir Pengajuan</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Pengajuan langsung tercatat di absensi Anda dan terlihat oleh Kepala Sekolah.
                </p>
            </div>

            @if (! $this->pegawai)
                {{-- Akun tanpa baris `pegawai` tidak bisa punya absensi sama
                     sekali. Form-nya tidak dirender daripada memasang kolom
                     yang pasti gagal saat disimpan. --}}
                <div class="p-6">
                    <div class="flex items-start gap-3 rounded-xl border border-warning-200 bg-warning-500/10 p-4 dark:border-warning-500/30">
                        <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-600 dark:text-warning-400" />
                        <p class="text-sm text-warning-700 dark:text-warning-400">
                            Akun Anda belum ditautkan ke data pegawai, jadi pengajuan izin belum bisa dipakai.
                            Minta Super Admin melengkapinya lewat menu <strong>Data Pegawai</strong>.
                        </p>
                    </div>
                </div>
            @else
                <form wire:submit="simpanIzin" class="space-y-5 p-6">

                    @if (session('izin-sukses'))
                        {{-- Notifikasi sukses. Ditaruh DI DALAM form, bukan di
                             layout, supaya pegawai melihatnya tepat di tempat
                             ia baru saja menekan tombol — bukan di ujung atas
                             halaman yang mungkin sudah tergulung ke luar layar. --}}
                        <div id="izin-notif"
                            class="flex items-start gap-3 rounded-xl border border-success-200 bg-success-500/10 p-4 dark:border-success-500/30"
                            role="status">
                            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-600 dark:text-success-400" />
                            <p class="text-sm font-medium text-success-700 dark:text-success-400">{{ session('izin-sukses') }}</p>
                        </div>
                    @endif

                    {{-- ---- Tanggal ---------------------------------------- --}}
                    <div>
                        <label for="izin-tanggal" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Tanggal Izin <span class="text-error-500">*</span>
                        </label>
                        <input id="izin-tanggal" type="date" wire:model="tanggal" class="{{ $kelasInput }}">
                        @error('tanggal')
                            <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ---- Kategori --------------------------------------- --}}
                    <div>
                        <label for="izin-kategori" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Kategori <span class="text-error-500">*</span>
                        </label>
                        {{-- appearance-none + panah sendiri: panah bawaan
                             browser di Windows berwarna gelap tetap, dan di
                             mode gelap ia praktis tidak terlihat. --}}
                        <div class="relative">
                            <select id="izin-kategori" wire:model="kategori"
                                class="{{ $kelasInput }} appearance-none pr-12">
                                @foreach ($pilihanKategori as $opsi)
                                    <option value="{{ $opsi->value }}">{{ $opsi->label() }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-400">
                                <x-icon name="chevron-down" class="h-5 w-5" />
                            </span>
                        </div>
                        @error('kategori')
                            <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ---- Alasan ----------------------------------------- --}}
                    <div>
                        <label for="izin-alasan" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Alasan Ketidakhadiran <span class="text-error-500">*</span>
                        </label>
                        <textarea id="izin-alasan" rows="4" wire:model="alasan"
                            placeholder="Contoh: Mengantar orang tua kontrol ke RS Bhakti Asih, berangkat pagi."
                            class="{{ $kelasInput }} resize-y"></textarea>
                        @error('alasan')
                            <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @else
                            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                                Minimal 10 karakter. Kepala Sekolah membaca kalimat ini apa adanya.
                            </p>
                        @enderror
                    </div>

                    {{-- ---- Lampiran (opsional) ---------------------------- --}}
                    <div>
                        <label for="izin-bukti" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Lampiran Surat Dokter / Bukti
                            <span class="font-normal text-gray-400">(opsional)</span>
                        </label>
                        <input id="izin-bukti" type="file" wire:model="bukti" accept="image/*"
                            class="w-full cursor-pointer rounded-lg border-[1.5px] border-dashed border-gray-300 bg-transparent px-5 py-3 text-sm text-gray-600 outline-none transition file:mr-4 file:rounded file:border-0 file:bg-gray-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-gray-700 hover:border-brand-500 focus:border-brand-500 dark:border-gray-700 dark:text-gray-400 dark:file:bg-gray-800 dark:file:text-gray-300">

                        {{-- Umpan balik saat berkas sedang diunggah. Foto dari
                             kamera HP bisa 2 MB dan butuh beberapa detik; tanpa
                             ini pegawai mengira aplikasinya menggantung dan
                             menekan Kirim berkali-kali. --}}
                        <p wire:loading wire:target="bukti" class="mt-1.5 text-xs text-brand-600 dark:text-brand-400">
                            Mengunggah lampiran…
                        </p>

                        @error('bukti')
                            <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                        @enderror

                        @if ($bukti && ! $errors->has('bukti'))
                            <div class="mt-3 flex items-center gap-3 rounded-xl border border-gray-200 p-3 dark:border-gray-800">
                                <img src="{{ $bukti->temporaryUrl() }}" alt="Pratinjau lampiran"
                                    class="h-16 w-16 rounded-lg object-cover">
                                <p class="text-sm text-gray-500 dark:text-gray-400">Lampiran siap dikirim.</p>
                            </div>
                        @endif
                    </div>

                    {{-- ---- Tombol ----------------------------------------- --}}
                    <div class="pt-1">
                        <button type="submit" id="izin-kirim" wire:loading.attr="disabled" wire:target="simpanIzin, bukti"
                            class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 sm:w-auto">

                            {{-- Spinner hanya muncul saat proses berjalan.
                                 wire:target menyebut `bukti` juga supaya tombol
                                 ikut terkunci selama unggahan berlangsung —
                                 menekan Kirim di tengah unggahan menyimpan
                                 pengajuan TANPA lampirannya. --}}
                            <svg wire:loading wire:target="simpanIzin, bukti"
                                class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>

                            <span wire:loading.remove wire:target="simpanIzin, bukti">Kirim Pengajuan Izin</span>
                            <span wire:loading wire:target="simpanIzin">Menyimpan…</span>
                            <span wire:loading wire:target="bukti">Mengunggah…</span>
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>

    {{-- ================= RIWAYAT ================= --}}
    <div class="lg:col-span-2">
        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Pengajuan Terakhir</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">10 terbaru milik Anda.</p>
            </div>

            @if ($this->riwayat->isEmpty())
                <div class="flex flex-col items-center gap-3 p-8 text-center">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                        <x-icon name="inbox" class="h-6 w-6" />
                    </span>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pengajuan izin.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-800">
                    @foreach ($this->riwayat as $baris)
                        <li class="flex items-start gap-3 px-6 py-4">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">
                                    {{ $baris->tanggal->translatedFormat('d F Y') }}
                                </p>
                                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $baris->alasan ?: $baris->keterangan ?: '—' }}
                                </p>
                            </div>
                            <x-badge :status="$baris->status"
                                :warna="$baris->status === \App\Enums\AbsensiStatus::Sakit ? 'orange' : null">
                                {{ $baris->status->shortLabel() }}
                            </x-badge>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
