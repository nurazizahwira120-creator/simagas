{{--
 | PAGINATION SIMAGAS — satu tampilan untuk halaman biasa & Livewire.
 |
 | Dipanggil oleh:
 |   - vendor/pagination/simagas.blade.php   ($mode = 'tautan'   -> <a href>)
 |   - vendor/livewire/tailwind.blade.php    ($mode = 'livewire' -> wire:click)
 |
 | ============ KENAPA TIDAK MEMAKAI TEMPLATE BAWAAN ============
 | Template bawaan Laravel/Livewire tinggal di folder vendor/, yang TIDAK
 | dipindai Tailwind v4 (@source hanya resources/views & app). Kelas
 | responsifnya (sm:flex, sm:hidden) tidak pernah masuk ke simagas.css,
 | sehingga nomor halaman tidak pernah tampil di layar mana pun — yang
 | terlihat hanya "« Previous / Next »" berbahasa Inggris.
 | ===============================================================
 |
 | Tata letak:
 |   HP      :          Halaman 2 dari 5
 |             [‹ Sebelumnya        ] [        Berikutnya ›]   (tinggi 44 px)
 |   Desktop : Menampilkan 16–30 dari 72 data   [‹] [1] … [4] [5] [6] … [9] [›]
--}}
@php
    $livewire = ($mode ?? 'tautan') === 'livewire';
    $namaHalaman = $paginator->getPageName();

    // Livewire: gulir ke atas daftar sesudah pindah halaman (perilaku bawaan Livewire).
    $gulir = '';
    if ($livewire && ($scrollTo ?? 'body') !== false) {
        $sasaran = $scrollTo ?? 'body';
        $gulir = "(\$el.closest('{$sasaran}') || document.querySelector('{$sasaran}')).scrollIntoView()";
    }

    $dasarTombol = 'inline-flex items-center justify-center gap-1.5 rounded-xl border text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40';
    $aktifPanah = 'border-brand-border bg-brand-surface text-brand-ink hover:border-brand-300 hover:bg-brand-50 hover:text-brand-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/[0.05]';
    $matiPanah = 'cursor-not-allowed border-brand-border/60 bg-brand-surface-muted text-brand-faint dark:border-gray-800 dark:bg-gray-900/40 dark:text-gray-600';
    // HP: dua tombol berbagi lebar (flex-1). Desktop: tombol ikon persegi 40 px.
    $ukuranPanah = 'h-11 flex-1 px-3.5 sm:h-10 sm:min-w-10 sm:flex-none sm:px-2.5';
    $ukuranNomor = 'h-10 min-w-10 px-3 tabular-nums';
@endphp

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman"
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">

        {{-- HP: posisi halaman di baris sendiri, supaya dua tombol bisa selebar
             layar (setengah-setengah) tanpa teks yang patah dua baris. --}}
        <p class="text-center text-sm text-brand-muted dark:text-gray-400 sm:hidden" aria-live="polite">
            Halaman <span class="font-semibold tabular-nums text-brand-ink dark:text-white">{{ $paginator->currentPage() }}</span>
            dari <span class="font-semibold tabular-nums text-brand-ink dark:text-white">{{ $paginator->lastPage() }}</span>
        </p>

        {{-- Desktop: keterangan jumlah data. --}}
        <p class="hidden text-sm text-brand-muted dark:text-gray-400 sm:block">
            Menampilkan
            <span class="font-semibold tabular-nums text-brand-ink dark:text-white">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</span>
            dari
            <span class="font-semibold tabular-nums text-brand-ink dark:text-white">{{ $paginator->total() }}</span>
            data
        </p>

        <div class="flex items-center justify-between gap-2 sm:justify-end">

            {{-- ===== Sebelumnya ===== --}}
            @if ($paginator->onFirstPage())
                <span aria-disabled="true" class="{{ $dasarTombol }} {{ $ukuranPanah }} {{ $matiPanah }}">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    <span class="sm:sr-only">Sebelumnya</span>
                </span>
            @elseif ($livewire)
                <button type="button" wire:click="previousPage('{{ $namaHalaman }}')" x-on:click="{{ $gulir }}" wire:loading.attr="disabled"
                    rel="prev" aria-label="Halaman sebelumnya" class="{{ $dasarTombol }} {{ $ukuranPanah }} {{ $aktifPanah }}">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    <span class="sm:sr-only">Sebelumnya</span>
                </button>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya"
                    class="{{ $dasarTombol }} {{ $ukuranPanah }} {{ $aktifPanah }}">
                    <x-icon name="chevron-left" class="h-4 w-4" />
                    <span class="sm:sr-only">Sebelumnya</span>
                </a>
            @endif

            {{-- ===== Desktop: nomor halaman ===== --}}
            <div class="hidden items-center gap-1 sm:flex">
                @foreach ($elements as $elemen)
                    {{-- "..." pemisah rentang halaman --}}
                    @if (is_string($elemen))
                        <span aria-hidden="true" class="inline-flex h-10 min-w-8 items-center justify-center text-sm text-brand-faint">…</span>
                    @endif

                    @if (is_array($elemen))
                        @foreach ($elemen as $halaman => $url)
                            @if ($halaman == $paginator->currentPage())
                                <span aria-current="page"
                                    class="{{ $dasarTombol }} {{ $ukuranNomor }} border-brand-500 bg-brand-500 text-white shadow-soft">{{ $halaman }}</span>
                            @elseif ($livewire)
                                <button type="button" wire:key="halaman-{{ $namaHalaman }}-{{ $halaman }}"
                                    wire:click="gotoPage({{ $halaman }}, '{{ $namaHalaman }}')" x-on:click="{{ $gulir }}"
                                    aria-label="Ke halaman {{ $halaman }}"
                                    class="{{ $dasarTombol }} {{ $ukuranNomor }} {{ $aktifPanah }}">{{ $halaman }}</button>
                            @else
                                <a href="{{ $url }}" aria-label="Ke halaman {{ $halaman }}"
                                    class="{{ $dasarTombol }} {{ $ukuranNomor }} {{ $aktifPanah }}">{{ $halaman }}</a>
                            @endif
                        @endforeach
                    @endif
                @endforeach
            </div>

            {{-- ===== Berikutnya ===== --}}
            @if (! $paginator->hasMorePages())
                <span aria-disabled="true" class="{{ $dasarTombol }} {{ $ukuranPanah }} {{ $matiPanah }}">
                    <span class="sm:sr-only">Berikutnya</span>
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </span>
            @elseif ($livewire)
                <button type="button" wire:click="nextPage('{{ $namaHalaman }}')" x-on:click="{{ $gulir }}" wire:loading.attr="disabled"
                    rel="next" aria-label="Halaman berikutnya" class="{{ $dasarTombol }} {{ $ukuranPanah }} {{ $aktifPanah }}">
                    <span class="sm:sr-only">Berikutnya</span>
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </button>
            @else
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya"
                    class="{{ $dasarTombol }} {{ $ukuranPanah }} {{ $aktifPanah }}">
                    <span class="sm:sr-only">Berikutnya</span>
                    <x-icon name="chevron-right" class="h-4 w-4" />
                </a>
            @endif
        </div>
    </nav>
@endif
