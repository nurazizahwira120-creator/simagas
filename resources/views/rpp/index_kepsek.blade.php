@extends('layouts.app')

@section('title', 'Pantauan RPP Guru')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Pantauan RPP Guru</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Seluruh Rencana Pelaksanaan Pembelajaran yang diunggah guru. Anda dapat membacanya,
            tetapi pengunggahan dan penghapusan tetap wewenang guru pemiliknya.
        </p>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <p class="text-sm text-gray-500 dark:text-gray-400">
                {{ $daftar->total() }} dokumen dari seluruh guru
            </p>

            <form method="GET" action="{{ route($panelPrefix . '.rpp.index') }}" class="relative w-full sm:w-80">
                <label for="rpp-cari" class="sr-only">Cari RPP</label>
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <input id="rpp-cari" type="search" name="cari" value="{{ $cari }}"
                    placeholder="Cari judul, mapel, atau nama guru…"
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
                        : 'Belum ada guru yang mengunggah RPP.' }}
                </p>
            </div>
        @else
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                            <th class="px-5 py-4 font-medium">Judul RPP</th>
                            <th class="px-5 py-4 font-medium">Guru Pengunggah</th>
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

                                {{-- 'Akun terhapus' — bukan baris kosong. Relasi user
                                     memang cascade, jadi normalnya tidak pernah null;
                                     tapi kalau suatu saat ada baris yatim, tabel yang
                                     menampilkan sel kosong terlihat seperti bug. --}}
                                <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                    {{ $rpp->user?->name ?? 'Akun terhapus' }}
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
                                    <div class="flex items-center justify-end">
                                        {{-- Hanya "Lihat". Tidak ada tombol hapus di sini,
                                             dan itu bukan sekadar disembunyikan: rute
                                             hapusnya memang tidak didaftarkan untuk panel
                                             kepsek, dan RppPolicy::delete() menolaknya. --}}
                                        <a href="{{ route($panelPrefix . '.rpp.show', $rpp) }}"
                                            class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-brand-500/40 px-3 text-xs font-semibold text-brand-accent-text transition hover:bg-brand-500/10">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            Lihat
                                        </a>
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
