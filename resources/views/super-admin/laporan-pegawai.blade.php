@extends('layouts.app')

@section('title', 'Laporan Bulanan Kehadiran Pegawai')

@section('content')

    <div class="mb-6 print:hidden">
        <h1 class="text-xl font-bold">Laporan Bulanan Kehadiran Pegawai</h1>
        <p class="text-sm text-brand-muted">Rekap kehadiran per pegawai, bisa difilter per bulan dan per jabatan.</p>
    </div>

    {{-- Kop khusus cetak — hanya tampil saat print:block, disembunyikan di layar --}}
    <div class="hidden print:block print:mb-4">
        <p class="text-base font-semibold">SMK Islam Assya'roniyyah</p>
        <p class="text-sm">Laporan Bulanan Kehadiran Pegawai</p>
        <p class="text-sm">
            Periode: {{ $bulan->translatedFormat('F Y') }}
            &middot; Jabatan: {{ $jabatanTerpilih ?? 'Semua Jabatan' }}
        </p>
        <p class="text-xs text-brand-muted">Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }}</p>
        <hr class="mt-3 border-brand-border">
    </div>

    {{-- Form filter --}}
    <form method="GET" action="{{ route($panelPrefix . '.laporan-pegawai') }}"
        class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-soft print:hidden">
        <div>
            <label for="bulan" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="calendar" class="h-3.5 w-3.5" />
                Pilih Bulan
            </label>
            <input type="month" id="bulan" name="bulan" value="{{ $bulan->format('Y-m') }}"
                class="rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
        </div>

        <div>
            <label for="jabatan" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="briefcase" class="h-3.5 w-3.5" />
                Pilih Jabatan
            </label>
            <select id="jabatan" name="jabatan"
                class="rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
                <option value="">Semua Jabatan</option>
                @foreach ($daftarJabatan as $jabatan)
                    <option value="{{ $jabatan }}" @selected($jabatanTerpilih === $jabatan)>
                        {{ $jabatan }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white hover:bg-brand-accent-dark">
            <x-icon name="funnel" class="h-4 w-4" />
            Terapkan Filter
        </button>
    </form>

    {{-- Tabel rekap --}}
    <div class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft print:rounded-none print:border-0 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-4 py-3 print:hidden">
            <div>
                <p class="text-sm font-semibold">
                    {{ $bulan->translatedFormat('F Y') }} &middot; {{ $jabatanTerpilih ?? 'Semua Jabatan' }}
                </p>
                <p class="text-xs text-brand-muted">{{ $rekap->count() }} pegawai</p>
            </div>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-1.5 rounded-lg border border-brand-ink px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted">
                <x-icon name="printer" class="h-4 w-4" />
                Cetak / Ekspor
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead>
                    <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted print:bg-transparent">
                        <th class="px-4 py-3 font-medium">Nama Pegawai</th>
                        <th class="px-4 py-3 text-center font-medium">Total Hadir</th>
                        <th class="px-4 py-3 text-center font-medium">Total Izin</th>
                        <th class="px-4 py-3 text-center font-medium">Total Sakit</th>
                        <th class="px-4 py-3 text-center font-medium">Total Alpa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    @forelse ($rekap as $pegawai)
                        <tr class="hover:bg-brand-surface-muted/60 print:hover:bg-transparent">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $pegawai->nama }}</p>
                                <p class="text-xs text-brand-muted">
                                    {{ $pegawai->nip }}
                                    @unless ($jabatanTerpilih)
                                        &middot; {{ $pegawai->jabatan }}
                                    @endunless
                                </p>
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums font-semibold text-brand-accent-text">{{ $pegawai->hadir_count }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ $pegawai->izin_count }}</td>
                            <td class="px-4 py-3 text-center tabular-nums">{{ $pegawai->sakit_count }}</td>
                            <td class="px-4 py-3 text-center tabular-nums font-semibold text-brand-danger-text">{{ $pegawai->alpha_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-brand-muted">
                                Tidak ada pegawai yang cocok dengan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

@endsection
