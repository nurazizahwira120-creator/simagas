@extends('layouts.app')

@section('title', 'Kenaikan Kelas')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Kenaikan Kelas</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Pindahkan siswa ke kelas berikutnya. Siswa yang tidak dicentang tetap di kelas lama.
        </p>
    </div>

    {{-- Pilih kelas asal --}}
    <form method="GET" action="{{ route($panelPrefix . '.kenaikan-kelas') }}"
        class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl bg-brand-surface p-5 ring-1 ring-brand-border">
        <div class="min-w-[240px]">
            <label for="kelas_asal" class="mb-1.5 block text-xs font-semibold text-brand-muted">Kelas Asal</label>
            <div class="relative">
                <select id="kelas_asal" name="kelas_asal" required
                    class="w-full appearance-none rounded-xl border-0 bg-brand-surface-muted px-3.5 py-2.5 pr-10 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">— Pilih kelas —</option>
                    @foreach ($daftarKelas as $k)
                        <option value="{{ $k->id }}" @selected($kelasAsal?->id === $k->id)>
                            {{ $k->nama_kelas }} ({{ $k->siswa_count }} siswa)
                        </option>
                    @endforeach
                </select>
                <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-brand-faint">
                    <x-icon name="chevron-down" class="h-4 w-4" />
                </span>
            </div>
        </div>

        <button type="submit"
            class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600">
            <x-icon name="users" class="h-4 w-4" />
            Tampilkan Siswa
        </button>
    </form>

    @if (! $kelasAsal)
        <div class="flex flex-col items-center gap-3 rounded-2xl bg-brand-surface p-12 text-center ring-1 ring-brand-border">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-faint">
                <x-icon name="academic-cap" class="h-6 w-6" />
            </span>
            <p class="text-sm text-brand-muted">Pilih kelas asal terlebih dahulu.</p>
        </div>
    @elseif ($siswa->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl bg-brand-surface p-12 text-center ring-1 ring-brand-border">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-faint">
                <x-icon name="inbox" class="h-6 w-6" />
            </span>
            <p class="text-sm text-brand-muted">Tidak ada siswa di kelas {{ $kelasAsal->nama_kelas }}.</p>
        </div>
    @else
        <form method="POST" action="{{ route($panelPrefix . '.kenaikan-kelas.proses') }}"
                        data-konfirmasi-judul="Pindahkan Siswa Ini?"
            data-konfirmasi="Semua siswa yang dicentang akan dipindahkan ke kelas tujuan. Tindakan ini mengubah data banyak siswa sekaligus."
            data-konfirmasi-ikon="warning"
            data-konfirmasi-ya="Ya, Pindahkan!"
            data-konfirmasi-batal="Batal">
            @csrf
            <input type="hidden" name="kelas_asal" value="{{ $kelasAsal->id }}">

            <div class="overflow-hidden rounded-2xl bg-brand-surface ring-1 ring-brand-border">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                            <x-icon name="academic-cap" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-brand-ink">{{ $kelasAsal->nama_kelas }}</h2>
                            <p class="text-xs text-brand-muted">{{ $siswa->count() }} siswa — centang yang naik kelas</p>
                        </div>
                    </div>

                    <label class="inline-flex cursor-pointer select-none items-center gap-2 rounded-xl bg-brand-surface-muted px-4 py-2 text-xs font-bold text-brand-muted ring-1 ring-brand-border">
                        <input type="checkbox" id="centang-semua"
                            class="h-4 w-4 rounded border-brand-border text-brand-accent-text dark:text-brand-accent-text focus:ring-brand-500">
                        Centang semua
                    </label>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-brand-border text-[11px] uppercase tracking-wide text-brand-faint">
                                <th class="w-12 px-6 py-3"></th>
                                <th class="px-6 py-3 font-semibold">Nama</th>
                                <th class="px-6 py-3 font-semibold">NIS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            @foreach ($siswa as $s)
                                <tr class="hover:bg-brand-surface-muted/60">
                                    <td class="px-6 py-3.5">
                                        <input type="checkbox" name="siswa[]" value="{{ $s->id }}" checked
                                            class="pilih-siswa h-4 w-4 rounded border-brand-border text-brand-accent-text dark:text-brand-accent-text focus:ring-brand-500">
                                    </td>
                                    <td class="px-6 py-3.5 font-semibold text-brand-ink">{{ $s->nama }}</td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-brand-muted">{{ $s->nis }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-end justify-between gap-4 border-t border-brand-border bg-brand-surface-muted px-6 py-5">
                    <div class="min-w-[240px]">
                        <label for="kelas_tujuan" class="mb-1.5 block text-xs font-semibold text-brand-muted">Kelas Tujuan</label>
                        <div class="relative">
                            <select id="kelas_tujuan" name="kelas_tujuan" required
                                class="w-full appearance-none rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 pr-10 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">— Pilih kelas tujuan —</option>
                                @foreach ($daftarKelas as $k)
                                    @if ($k->id !== $kelasAsal->id)
                                        <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-brand-faint">
                                <x-icon name="chevron-down" class="h-4 w-4" />
                            </span>
                        </div>
                    </div>

                    <button type="submit"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600">
                        <x-icon name="arrow-right" class="h-5 w-5" />
                        Naikkan Siswa Terpilih
                    </button>
                </div>
            </div>
        </form>

        <script>
            (function () {
                var semua = document.getElementById('centang-semua');
                var kotak = document.querySelectorAll('.pilih-siswa');
                if (!semua) return;

                // Semua baris tercentang sejak awal, jadi kotak "centang semua"
                // ikut tercentang supaya keadaannya jujur sejak halaman dibuka.
                semua.checked = true;

                semua.addEventListener('change', function () {
                    kotak.forEach(function (k) { k.checked = semua.checked; });
                });

                kotak.forEach(function (k) {
                    k.addEventListener('change', function () {
                        semua.checked = Array.prototype.every.call(kotak, function (x) { return x.checked; });
                    });
                });
            })();
        </script>
    @endif
@endsection
