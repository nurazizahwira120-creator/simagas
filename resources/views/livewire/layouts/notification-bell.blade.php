{{--
    Lonceng notifikasi — gaya dropdown TailAdmin.

    Buka/tutupnya dipegang ALPINE (x-data), bukan JavaScript biasa seperti
    dropdown lain di topbar. Alasannya: panel ini ikut dirender ulang setiap
    kali Livewire memperbarui komponen, dan listener JavaScript yang dipasang
    sekali saat halaman dimuat akan menempel pada elemen yang sudah dibuang —
    panelnya berhenti bisa dibuka setelah notifikasi pertama diklik. State
    Alpine di elemen akar bertahan melewati pembaruan Livewire.

    Supaya tetap rukun dengan dropdown lain di topbar, keduanya saling
    memberi tahu lewat satu event: 'simagas-tutup-dropdown'.
--}}
<div class="relative"
    x-data="{ buka: false }"
    x-on:simagas-tutup-dropdown.window="buka = false"
    x-on:click.outside="buka = false"
    x-on:keydown.escape.window="buka = false">

    <button type="button"
        x-on:click="buka = ! buka; if (buka) window.dispatchEvent(new CustomEvent('simagas-tutup-lain'))"
        class="relative flex h-10 w-10 items-center justify-center rounded-full border border-gray-200 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 motion-reduce:transition-none lg:h-11 lg:w-11 dark:border-gray-800 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
        x-bind:aria-expanded="buka ? 'true' : 'false'"
        aria-haspopup="true"
        aria-label="Notifikasi{{ $this->jumlah ? ' (' . $this->jumlah . ' belum dibaca)' : '' }}">

        <x-icon name="bell" class="h-5 w-5" />

        @if ($this->jumlah > 0)
            {{-- Badge merah. Angkanya dipotong di 9+ supaya lingkarannya tidak
                 melar dan mendorong tombol di sebelahnya begitu notifikasi
                 menumpuk. --}}
            <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-error-500 px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-gray-900">
                {{ $this->jumlah > 9 ? '9+' : $this->jumlah }}
            </span>
        @endif
    </button>

    <div x-show="buka"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        x-cloak
        class="shadow-theme-lg absolute right-0 z-50 mt-2 w-80 rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900 sm:w-96">

        <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-800">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">Notifikasi</p>

            @if ($this->jumlah > 0)
                <button type="button" wire:click="tandaiSemua"
                    class="text-xs font-medium text-brand-600 transition-colors hover:text-brand-700 dark:text-brand-400">
                    Tandai semua terbaca
                </button>
            @endif
        </div>

        @if ($this->belumDibaca->isEmpty())
            <div class="flex flex-col items-center gap-2 px-4 py-8 text-center">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <x-icon name="inbox" class="h-5 w-5" />
                </span>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tidak ada notifikasi baru</p>
            </div>
        @else
            {{-- max-h + overflow: dengan 8 notifikasi panel ini bisa lebih
                 tinggi dari layar HP, dan panel yang menjulur ke luar layar
                 tidak bisa digulung sendiri. --}}
            <ul class="custom-scrollbar max-h-96 divide-y divide-gray-200 overflow-y-auto dark:divide-gray-800">
                @foreach ($this->belumDibaca as $n)
                    @php
                        $isi = $n->data;
                        $warna = match ($isi['warna'] ?? 'info') {
                            'warning' => 'bg-warning-500/10 text-warning-600 dark:text-warning-400',
                            'danger' => 'bg-error-500/10 text-error-600 dark:text-error-400',
                            'success' => 'bg-success-500/10 text-success-600 dark:text-success-400',
                            default => 'bg-brand-500/10 text-brand-600 dark:text-brand-400',
                        };
                    @endphp

                    <li>
                        <button type="button" wire:click="markAsRead('{{ $n->id }}')"
                            wire:key="notif-{{ $n->id }}"
                            class="flex w-full items-start gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 dark:hover:bg-white/[0.03]">

                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $warna }}">
                                <x-icon name="{{ $isi['ikon'] ?? 'bell' }}" class="h-4 w-4" />
                            </span>

                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-800 dark:text-gray-100">
                                    {{ $isi['judul'] ?? 'Notifikasi' }}
                                </span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    {{ $isi['pesan'] ?? '' }}
                                </span>
                                @if (! empty($isi['cuplikan']))
                                    <span class="mt-1 block text-xs italic leading-relaxed text-gray-400 dark:text-gray-500">
                                        &ldquo;{{ $isi['cuplikan'] }}&rdquo;
                                    </span>
                                @endif
                                <span class="mt-1 block text-[11px] text-gray-400 dark:text-gray-500">
                                    {{ $n->created_at->diffForHumans() }}
                                </span>
                            </span>
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
