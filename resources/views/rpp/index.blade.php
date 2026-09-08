@extends('layouts.app')

@section('title', 'RPP Saya')

@section('content')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">RPP Saya</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Rencana Pelaksanaan Pembelajaran yang Anda unggah. Hanya Anda dan Kepala Sekolah yang bisa membukanya.
            </p>
        </div>

        <a href="{{ route($panelPrefix . '.rpp.create') }}"
            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <x-icon name="upload" class="h-4 w-4" />
            Upload RPP Baru
        </a>
    </div>

    @if (session('sukses'))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-success-200 bg-success-500/10 p-4 dark:border-success-500/30" role="status">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-700 dark:text-success-400" />
            <p class="text-sm font-semibold text-success-700 dark:text-success-400">{{ session('sukses') }}</p>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $daftar->total() }} dokumen tersimpan
            </p>

            {{-- GET, bukan POST: hasil pencarian jadi bisa di-bookmark dan
                 tombol "kembali" browser bekerja seperti yang diharapkan. --}}
            <form method="GET" action="{{ route($panelPrefix . '.rpp.index') }}" class="relative w-full sm:w-72">
                <label for="rpp-cari" class="sr-only">Cari RPP</label>
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input id="rpp-cari" type="search" name="cari" value="{{ $cari }}"
                    placeholder="Cari judul atau mapel…"
                    class="w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent py-2.5 pl-10 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            </form>
        </div>

        @if ($daftar->isEmpty())
            <div class="flex flex-col items-center gap-3 p-12 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $cari !== ''
                        ? 'Tidak ada RPP yang cocok dengan pencarian "' . $cari . '".'
                        : 'Belum ada RPP yang diunggah.' }}
                </p>
                @if ($cari === '')
                    <a href="{{ route($panelPrefix . '.rpp.create') }}"
                        class="mt-1 inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                        <x-icon name="upload" class="h-4 w-4" />
                        Upload RPP Pertama
                    </a>
                @endif
            </div>
        @else
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                            <th class="px-5 py-4 font-medium">Judul RPP</th>
                            <th class="px-5 py-4 font-medium">Mata Pelajaran</th>
                            <th class="px-5 py-4 font-medium">Diunggah</th>
                            <th class="px-5 py-4 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($daftar as $rpp)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                <td class="px-5 py-4">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-error-500/10 text-error-600 dark:text-error-400">
                                            <x-icon name="document-report" class="h-4 w-4" />
                                        </span>
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $rpp->judul_rpp }}</p>
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    <x-badge warna="info">{{ $rpp->mata_pelajaran }}</x-badge>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $rpp->created_at->translatedFormat('d M Y') }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $rpp->created_at->format('H:i') }}
                                    </p>
                                </td>

                                <td class="px-5 py-4">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route($panelPrefix . '.rpp.show', $rpp) }}"
                                            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-500/40 px-3 text-xs font-semibold text-brand-accent-text transition hover:bg-brand-500/10">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            Lihat
                                        </a>

                                        {{-- Konfirmasi SweetAlert2 lewat data-konfirmasi
                                             (resources/views/partials/sweetalert.blade.php).
                                             Aksinya tetap form POST + @method DELETE supaya
                                             token CSRF-nya ikut dan tombol tetap berfungsi
                                             walau JavaScript gagal dimuat. --}}
                                        <form method="POST" action="{{ route($panelPrefix . '.rpp.destroy', $rpp) }}"
                                            data-konfirmasi-judul="Hapus RPP Ini?"
                                            data-konfirmasi="&quot;{{ $rpp->judul_rpp }}&quot; beserta berkas PDF-nya akan dihapus permanen dan tidak bisa dikembalikan."
                                            data-konfirmasi-ikon="warning"
                                            data-konfirmasi-ya="Ya, Hapus!"
                                            data-konfirmasi-batal="Batal">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-error-500/40 text-error-600 transition hover:bg-error-500/10 dark:text-error-400"
                                                aria-label="Hapus {{ $rpp->judul_rpp }}" title="Hapus">
                                                <x-icon name="trash" class="h-4 w-4" />
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($daftar->hasPages())
                <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    {{ $daftar->links() }}
                </div>
            @endif
        @endif
    </div>

@endsection
