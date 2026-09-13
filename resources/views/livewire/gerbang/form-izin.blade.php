{{--
    View komponen App\Livewire\Gerbang\FormIzin.

    PANTANGAN (pelajaran mahal di project ini, jangan diulang):
    JANGAN menulis teks berbentuk tag HTML di dalam blok <script> pada view
    Livewire — parser HTML akan keluar dari <script> di situ dan membuat
    elemen nyata di luar root div, sehingga Livewire melempar
    MultipleRootElementsDetectedException dan halamannya jadi 500.
--}}

<div class="min-w-0">

    {{-- ============ HASIL SIMPAN ============
         Digambar Livewire tanpa memuat ulang halaman, jadi kamera scanner di
         panel sebelah kiri TIDAK ikut mati. --}}
    @if ($notif)
        @php
            $gaya = match ($notif['tipe']) {
                'ok' => ['kotak' => 'border-emerald-300 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400', 'ikon' => 'check-circle'],
                'warn' => ['kotak' => 'border-amber-300 bg-amber-500/10 text-amber-800 dark:text-amber-400', 'ikon' => 'clock'],
                default => ['kotak' => 'border-red-300 bg-red-500/10 text-red-800 dark:text-red-400', 'ikon' => 'x-circle'],
            };
        @endphp

        <div class="muncul mb-4 flex items-start gap-3 rounded-2xl border p-4 {{ $gaya['kotak'] }}" role="status">
            <x-icon name="{{ $gaya['ikon'] }}" class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="min-w-0">
                <p class="truncate text-sm font-bold">{{ $notif['judul'] }}</p>
                <p class="mt-0.5 text-sm leading-relaxed">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    {{-- ============ FORM IZIN ============ --}}
    <form wire:submit="simpan"
        class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">

        <div class="border-b border-brand-border px-5 py-4 dark:border-gray-800">
            <h2 class="font-semibold text-brand-ink dark:text-white">Catat Izin Siswa</h2>
            <p class="mt-0.5 text-xs leading-relaxed text-brand-muted dark:text-brand-faint">
                Status hariannya otomatis tersimpan, dan langsung terlihat guru
                mata pelajaran di Jurnal &amp; Absen Kelas.
            </p>
        </div>

        <div class="space-y-4 p-5">

            {{-- ---- Pencarian real-time ---- --}}
            <div>
                <label for="cari-siswa" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                    Cari Nama / NIS
                </label>

                <div class="relative">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-brand-muted">
                        <x-icon name="search" class="h-4 w-4" />
                    </span>

                    {{-- .live.debounce.300ms — dan keduanya WAJIB.

                         Tanpa .live: sejak Livewire 3, wire:model bersifat
                         DEFERRED. Kotaknya terlihat menerima ketikan tapi
                         daftarnya tidak pernah berubah sampai ada aksi lain.

                         Tanpa .debounce: satu request per huruf. Mengetik
                         "Muhammad" berarti 8 request beruntun ke hosting
                         bersama, dan hasil yang datang bisa saling menyalip. --}}
                    <input id="cari-siswa" type="search" autocomplete="off"
                        wire:model.live.debounce.300ms="cari"
                        placeholder="Ketik nama atau NIS…"
                        class="w-full min-w-0 rounded-lg border border-brand-border bg-brand-surface py-2.5 pl-9 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">

                    {{-- Penanda "sedang mencari". wire:loading menyala HANYA
                         selama request pencariannya berjalan, jadi petugas tahu
                         daftarnya sedang diperbarui — bukan sedang kosong. --}}
                    <span wire:loading wire:target="cari"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-brand-accent-text">
                        <svg class="h-4 w-4 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                    </span>
                </div>
            </div>

            {{-- ---- Pilihan siswa ---- --}}
            <div>
                <label for="siswa_id" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                    Pilih Siswa <span class="text-brand-danger-text">*</span>
                </label>

                {{-- Tetap <select>, bukan daftar tombol buatan sendiri:
                     elemen ini punya perilaku bawaan yang sudah dikenal semua
                     orang, bisa dipakai keyboard, dan di HP memunculkan
                     pemilih layar penuh milik sistem yang jauh lebih nyaman.

                     size="6" saat ada kata kunci: hasil penyaringan terlihat
                     langsung tanpa perlu membuka dropdown-nya dulu. --}}
                <select id="siswa_id" wire:model.live="siswaId" required
                    @if (trim($cari) !== '') size="6" @endif
                    class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                    <option value="">— pilih siswa —</option>
                    @forelse ($this->hasilPencarian as $s)
                        <option value="{{ $s->id }}">
                            {{ $s->nama }} — {{ $s->nis }}{{ $s->kelas ? ' (' . $s->kelas->nama_kelas . ')' : '' }}
                        </option>
                    @empty
                        <option value="" disabled>Tidak ada siswa yang cocok</option>
                    @endforelse
                </select>

                @if ($this->totalCocok > $this->hasilPencarian->count())
                    <p class="mt-1 text-xs text-brand-muted">
                        Menampilkan {{ $this->hasilPencarian->count() }} dari {{ $this->totalCocok }} siswa yang cocok —
                        ketik lebih spesifik untuk mempersempit.
                    </p>
                @endif

                @error('siswaId')
                    <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p>
                @enderror

                {{-- Pratinjau siswa terpilih. Satu baris kecil, tapi inilah
                     yang mencegah kesalahan paling mahal di halaman ini:
                     mencatat izin atas nama anak yang salah. --}}
                @if ($this->siswaTerpilih)
                    <div class="muncul mt-2 flex items-center gap-2.5 rounded-lg border border-brand-accent/30 bg-brand-accent-soft px-3 py-2">
                        <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-brand-accent-text" />
                        <p class="min-w-0 truncate text-xs font-semibold text-brand-accent-text">
                            {{ $this->siswaTerpilih->nama }}
                            <span class="font-normal">
                                &middot; {{ $this->siswaTerpilih->nis }}
                                @if ($this->siswaTerpilih->kelas)
                                    &middot; {{ $this->siswaTerpilih->kelas->nama_kelas }}
                                @endif
                            </span>
                        </p>
                    </div>
                @endif
            </div>

            {{-- ---- Status ----
                 Radio, bukan dropdown. Tiga pilihan yang harus dibandingkan
                 sebaiknya semuanya terlihat sekaligus; dropdown menyembunyikan
                 dua di antaranya di balik satu ketukan tambahan, dan di
                 gerbang yang sibuk ketukan itu berarti. --}}
            <fieldset>
                <legend class="mb-1.5 text-sm font-medium text-brand-ink dark:text-white">
                    Status <span class="text-brand-danger-text">*</span>
                </legend>

                <div class="grid grid-cols-3 gap-2">
                    @foreach ($jenisIzin as $jenis)
                        @php $idRadio = 'izin-' . $jenis->value; @endphp
                        <label for="{{ $idRadio }}" class="cursor-pointer select-none">
                            {{-- wire:model.live — BUKAN wire:model biasa.

                                 Tampilan terpilihnya digambar SERVER lewat
                                 peer-checked pada radio yang sr-only. Dengan
                                 wire:model yang deferred, menekan tombol tidak
                                 memberi umpan balik apa pun sampai ada aksi
                                 lain — persis bug yang pernah dilaporkan guru
                                 di halaman Jurnal & Absen Kelas. --}}
                            <input type="radio" id="{{ $idRadio }}" value="{{ $jenis->value }}"
                                wire:model.live="status" class="peer sr-only">
                            <span class="flex flex-col items-center gap-1 rounded-xl border border-brand-border px-2 py-3 text-center text-xs font-semibold text-brand-muted transition-colors peer-checked:border-brand-accent peer-checked:bg-brand-accent peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent/40 dark:border-gray-800">
                                <x-icon name="{{ $jenis->ikon() }}" class="h-5 w-5" />
                                {{ $jenis->label() }}
                            </span>
                        </label>
                    @endforeach
                </div>

                {{-- Penjelasan status yang sedang dipilih, berubah real-time.
                     Berguna khusus untuk Dispensasi, yang artinya tidak
                     seragam dipahami setiap petugas. --}}
                @if ($status)
                    <p class="mt-2 text-xs leading-relaxed text-brand-muted">
                        {{ \App\Enums\JenisIzin::from($status)->keterangan() }}
                    </p>
                @endif

                @error('status')
                    <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p>
                @enderror
            </fieldset>

            {{-- ---- Keterangan ---- --}}
            <div>
                <label for="keterangan" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                    Keterangan Tambahan
                </label>
                {{-- wire:model biasa (deferred) DISENGAJA di sini.

                     Ini satu-satunya tempat di form ini yang TIDAK boleh live:
                     .live pada textarea berarti satu request ke server per
                     ketikan. Isinya baru dibutuhkan saat Simpan ditekan. --}}
                <textarea id="keterangan" wire:model="keterangan" rows="3" maxlength="500"
                    placeholder="Mis. demam, kontrol ke puskesmas, lomba LKS tingkat kabupaten…"
                    class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white"></textarea>
                <p class="mt-1 text-xs text-brand-muted">Boleh dikosongkan — akan diisi keterangan bawaan sesuai status.</p>
                @error('keterangan')
                    <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- ---- Bukti surat ---- --}}
            <div>
                <label for="foto_surat" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                    Unggah Bukti Surat
                </label>

                {{-- wire:key memakai jumlah izin hari ini: begitu satu izin
                     tersimpan, nilainya berubah dan Livewire MEMBUAT ULANG
                     elemen ini. Itu satu-satunya cara mengosongkan input file
                     — nilainya dikendalikan browser demi keamanan dan tidak
                     bisa dikosongkan dari sisi server. Tanpa ini, nama berkas
                     yang lama tetap terpampang sesudah form dikosongkan. --}}
                <input id="foto_surat" type="file" wire:model="fotoSurat"
                    wire:key="berkas-{{ $this->izinHariIni->count() }}"
                    accept="image/jpeg,image/png,image/webp"
                    class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-accent-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-accent-text dark:border-gray-800 dark:bg-gray-950 dark:text-white">

                <div wire:loading wire:target="fotoSurat" class="mt-1.5 flex items-center gap-2 text-xs font-medium text-brand-accent-text">
                    <svg class="h-3.5 w-3.5 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    Mengunggah foto…
                </div>

                <p class="mt-1 text-xs leading-relaxed text-brand-muted">
                    Foto surat dokter / surat orang tua. JPG, PNG, atau WEBP, maksimal 4 MB.
                    Berkasnya disimpan tertutup — hanya bisa dibuka lewat aplikasi ini.
                </p>
                @error('fotoSurat')
                    <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p>
                @enderror
            </div>

            {{-- Tombol dimatikan selama proses menyimpan. Tanpa ini, petugas
                 yang menekan dua kali karena layarnya terasa lambat akan
                 mengirim dua request — dan yang kedua hanya ditahan oleh
                 unique index di database, bukan oleh antarmukanya. --}}
            <button type="submit" wire:loading.attr="disabled" wire:target="simpan"
                class="kartu-angkat flex w-full items-center justify-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white shadow-soft disabled:cursor-not-allowed disabled:opacity-60">
                <span wire:loading.remove wire:target="simpan" class="flex items-center gap-2">
                    <x-icon name="check-circle" class="h-5 w-5" />
                    Simpan Data Izin
                </span>
                <span wire:loading wire:target="simpan" class="flex items-center gap-2">
                    <svg class="h-5 w-5 animate-spin motion-reduce:animate-none" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    Menyimpan…
                </span>
            </button>
        </div>
    </form>

    {{-- ============ IZIN HARI INI ============
         Ikut tergambar ulang setiap kali komponen ini di-render, jadi baris
         baru muncul seketika sesudah Simpan — tanpa reload halaman. --}}
    <div class="mt-5 rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">
        <h3 class="flex items-center justify-between gap-2 border-b border-brand-border px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-brand-muted dark:border-gray-800">
            <span class="flex items-center gap-1.5">
                <x-icon name="clipboard-check" class="h-3.5 w-3.5" />
                Izin hari ini
            </span>
            @if ($this->izinHariIni->isNotEmpty())
                <span class="rounded-full bg-brand-accent-soft px-2.5 py-0.5 text-[11px] font-bold normal-case tracking-normal text-brand-accent-text">
                    {{ $this->izinHariIni->count() }} tercatat
                </span>
            @endif
        </h3>

        @if ($this->izinHariIni->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-brand-muted">Belum ada izin yang dicatat hari ini.</p>
        @else
            <ul class="divide-y divide-brand-border dark:divide-gray-800">
                @foreach ($this->izinHariIni as $izin)
                    <li wire:key="izin-{{ $izin->id }}" class="flex min-w-0 items-start gap-3 px-5 py-3">
                        <span class="mt-0.5 inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $izin->status->kelasBadge() }}">
                            <x-icon name="{{ $izin->status->ikon() }}" class="h-3 w-3" />
                            {{ $izin->status->label() }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-brand-ink dark:text-white">{{ $izin->siswa?->nama ?? '—' }}</p>
                            <p class="truncate text-xs text-brand-muted">
                                {{ $izin->siswa?->kelas?->nama_kelas ?? $izin->siswa?->nis }}
                                &middot; oleh {{ $izin->petugas?->name ?? '—' }}
                            </p>
                            @if ($izin->keterangan)
                                <p class="mt-0.5 text-xs leading-relaxed text-brand-muted">{{ $izin->keterangan }}</p>
                            @endif
                        </div>
                        @if ($izin->suratAda())
                            <a href="{{ route($panelPrefix . '.gerbang.surat', $izin) }}" target="_blank" rel="noopener"
                                class="mt-0.5 inline-flex shrink-0 items-center gap-1 rounded-lg border border-brand-border px-2.5 py-1 text-[11px] font-medium text-brand-muted hover:bg-brand-surface-muted dark:border-gray-800">
                                <x-icon name="eye" class="h-3 w-3" />
                                Surat
                            </a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
