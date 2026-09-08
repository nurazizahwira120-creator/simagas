{{--
    View untuk komponen App\Livewire\SearchData.

    Komponen Livewire WAJIB punya tepat satu elemen akar. Kalau ada dua
    elemen sejajar di tingkat teratas, Livewire tidak bisa menentukan bagian
    mana yang harus diperbarui dan akan melempar error.
--}}
<div>

    {{-- Kotak pencarian --}}
    <div class="relative max-w-xl">
        <span class="pointer-events-none absolute inset-y-0 left-4 flex items-center text-brand-faint">
            <x-icon name="search" class="h-5 w-5" />
        </span>

        {{-- wire:model.live = kirim ke server setiap kali isinya berubah.
             .debounce.300ms = tunggu 300 ms setelah berhenti mengetik, supaya
             tidak mengirim satu request per huruf. --}}
        <input
            type="search"
            wire:model.live.debounce.300ms="cari"
            placeholder="Ketik nama atau NIS siswa…"
            autocomplete="off"
            class="w-full rounded-2xl border-0 bg-brand-surface py-3.5 pl-12 pr-12 text-sm text-brand-ink ring-1 ring-brand-border placeholder:text-brand-faint focus:outline-none focus:ring-2 focus:ring-brand-500">

        {{-- Indikator loading: hanya tampil selama request Livewire berjalan. --}}
        <span wire:loading class="absolute inset-y-0 right-4 flex items-center text-brand-accent-text">
            <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-20" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
            </svg>
        </span>
    </div>

    {{-- Hasil --}}
    <div class="mt-5">
        @if ($kunci === '')
            <div class="flex flex-col items-center gap-3 rounded-2xl bg-brand-surface p-12 text-center ring-1 ring-brand-border">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-faint">
                    <x-icon name="search" class="h-6 w-6" />
                </span>
                <p class="text-sm text-brand-muted">Mulai mengetik untuk mencari siswa.</p>
                <p class="text-xs text-brand-faint">Hasil muncul otomatis — tidak perlu menekan tombol apa pun.</p>
            </div>
        @elseif ($hasil->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl bg-brand-surface p-12 text-center ring-1 ring-brand-border">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-faint">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-brand-muted">Tidak ada siswa yang cocok dengan &ldquo;{{ $kunci }}&rdquo;.</p>
            </div>
        @else
            <p class="mb-3 text-xs font-semibold text-brand-muted">
                {{ $hasil->count() }} hasil{{ $hasil->count() >= $batas ? ' (dibatasi ' . $batas . ' teratas)' : '' }}
            </p>

            <div class="overflow-hidden rounded-2xl bg-brand-surface ring-1 ring-brand-border">
                <ul class="divide-y divide-brand-border">
                    @foreach ($hasil as $siswa)
                        <li class="flex items-center gap-4 px-5 py-3.5 hover:bg-brand-surface-muted/60">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                                <x-icon name="identification" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-brand-ink">{{ $siswa->nama }}</p>
                                <p class="truncate font-mono text-xs text-brand-faint">{{ $siswa->nis }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-brand-surface-muted px-3 py-1 text-xs font-semibold text-brand-muted ring-1 ring-brand-border">
                                {{ $siswa->kelas?->nama_kelas ?? '—' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

</div>
