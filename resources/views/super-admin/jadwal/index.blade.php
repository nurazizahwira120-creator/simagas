@extends('layouts.app')

@section('title', 'Jadwal Pelajaran')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Jadwal Pelajaran</h1>
            <p class="text-sm text-brand-muted">{{ $totalJadwal }} slot jadwal ditampilkan.</p>
        </div>
        <a href="{{ route($panelPrefix . '.jadwal.create') }}"
            class="inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Tambah Jadwal
        </a>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route($panelPrefix . '.jadwal.index') }}"
        class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-soft">
        <div>
            <label for="kelas_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="academic-cap" class="h-3.5 w-3.5" />
                Kelas
            </label>
            <select id="kelas_id" name="kelas_id"
                class="rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
                <option value="">Semua Kelas</option>
                @foreach ($daftarKelas as $k)
                    <option value="{{ $k->id }}" @selected($kelasFilter === $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="hari" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="calendar" class="h-3.5 w-3.5" />
                Hari
            </label>
            <select id="hari" name="hari"
                class="rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
                <option value="">Semua Hari</option>
                @foreach ($daftarHari as $h)
                    <option value="{{ $h->value }}" @selected($hariFilter === $h)>{{ $h->label() }}</option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white hover:bg-brand-accent-dark">
            <x-icon name="funnel" class="h-4 w-4" />
            Terapkan Filter
        </button>

        @if ($kelasFilter || $hariFilter)
            <a href="{{ route($panelPrefix . '.jadwal.index') }}"
                class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border px-4 py-2 text-sm font-medium text-brand-muted hover:bg-brand-surface-muted">
                <x-icon name="x-circle" class="h-4 w-4" />
                Reset
            </a>
        @endif
    </form>

    {{-- Daftar jadwal, dikelompokkan per hari --}}
    @if ($jadwalPerHari->isEmpty())
        <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                <x-icon name="inbox" class="h-6 w-6" />
            </span>
            <p class="text-sm text-brand-muted">
                @if ($kelasFilter || $hariFilter)
                    Tidak ada jadwal yang cocok dengan filter ini.
                @else
                    Belum ada jadwal pelajaran. Klik "Tambah Jadwal" untuk mulai menyusun.
                @endif
            </p>
        </div>
    @else
        <div class="space-y-5">
            @foreach ($jadwalPerHari as $kodeHari => $daftar)
                @php
                    $hari = \App\Enums\Hari::from($kodeHari);
                @endphp
                <section class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-soft">
                    <div class="flex items-center justify-between gap-3 border-b border-brand-border bg-brand-surface-muted px-4 py-2.5">
                        <p class="flex items-center gap-1.5 text-sm font-semibold">
                            <x-icon name="calendar" class="h-4 w-4 text-brand-accent-text" />
                            {{ $hari->label() }}
                        </p>
                        <span class="text-xs text-brand-muted">{{ $daftar->count() }} pelajaran</span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[680px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-brand-border text-xs uppercase tracking-wide text-brand-muted">
                                    <th class="px-4 py-2.5 font-medium">Jam</th>
                                    <th class="px-4 py-2.5 font-medium">Mata Pelajaran</th>
                                    <th class="px-4 py-2.5 font-medium">Kelas</th>
                                    <th class="px-4 py-2.5 font-medium">Guru</th>
                                    <th class="px-4 py-2.5 text-right font-medium">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-border">
                                @foreach ($daftar as $item)
                                    <tr class="hover:bg-brand-surface-muted/60">
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-brand-muted">
                                            {{ $item->rentangJam() }}
                                        </td>
                                        <td class="px-4 py-3 font-medium">{{ $item->mata_pelajaran }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center rounded-full bg-brand-accent-soft px-2.5 py-0.5 text-xs font-medium text-brand-accent-text">
                                                {{ $item->kelas?->nama_kelas ?? '-' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-brand-muted">{{ $item->guru?->nama ?? '-' }}</td>
                                        <td class="px-4 py-3">
                                            <div class="flex flex-wrap justify-end gap-1.5">
                                                <a href="{{ route($panelPrefix . '.jadwal.edit', $item) }}"
                                                    class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border px-3 py-1.5 text-xs font-medium hover:bg-brand-surface-muted">
                                                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                                                    Edit
                                                </a>
                                                <form method="POST" action="{{ route($panelPrefix . '.jadwal.destroy', $item) }}"
                                                                                                        data-konfirmasi-judul="Hapus Jadwal Ini?"
                                                    data-konfirmasi="Jadwal {{ $item->mata_pelajaran }} pada {{ $hari->label() }} ({{ $item->rentangJam() }}) akan dihapus permanen."
                                                    data-konfirmasi-ikon="warning"
                                                    data-konfirmasi-ya="Ya, Hapus!"
                                                    data-konfirmasi-batal="Batal">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex items-center gap-1.5 rounded-lg border border-brand-danger px-3 py-1.5 text-xs font-medium text-brand-danger-text hover:bg-brand-danger-soft">
                                                        <x-icon name="trash" class="h-3.5 w-3.5" />
                                                        Hapus
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
