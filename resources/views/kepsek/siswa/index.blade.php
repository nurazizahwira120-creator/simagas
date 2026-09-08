@extends('layouts.app')

@section('title', 'Kelola Siswa')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kelola Siswa</h1>
            <p class="text-sm text-brand-muted">{{ $daftarSiswa->total() }} siswa terdaftar.</p>
        </div>
        <div class="flex gap-2">
            @if ($kelasFilter)
                <a href="{{ route($panelPrefix . '.siswa.qr-kelas', $kelasFilter) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-brand-ink px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted">
                    <x-icon name="qr-code" class="h-4 w-4" />
                    Cetak QR 1 Kelas
                </a>
            @endif
            <a href="{{ route($panelPrefix . '.siswa.create') }}"
                class="inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Siswa
            </a>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <span class="mr-0.5 text-brand-muted"><x-icon name="funnel" class="h-4 w-4" /></span>
        <a href="{{ route($panelPrefix . '.siswa.index') }}"
            class="rounded-full border px-3 py-1 text-xs font-medium {{ ! $kelasFilter ? 'border-brand-accent bg-brand-accent text-white' : 'border-brand-border text-brand-muted' }}">
            Semua Kelas
        </a>
        @foreach ($daftarKelas as $k)
            <a href="{{ route($panelPrefix . '.siswa.index', ['kelas_id' => $k->id]) }}"
                class="rounded-full border px-3 py-1 text-xs font-medium {{ $kelasFilter === $k->id ? 'border-brand-accent bg-brand-accent text-white' : 'border-brand-border text-brand-muted' }}">
                {{ $k->nama_kelas }}
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft">
        <table class="w-full min-w-[680px] text-left text-sm">
            <thead>
                <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted">
                    <th class="px-4 py-3 font-medium">NIS</th>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Kelas</th>
                    <th class="px-4 py-3 font-medium">Wali Murid</th>
                    <th class="px-4 py-3 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border">
                @forelse ($daftarSiswa as $siswa)
                    <tr class="hover:bg-brand-surface-muted/60">
                        <td class="px-4 py-3 font-mono text-brand-muted">{{ $siswa->nis }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent-soft text-brand-accent-text">
                                    <x-icon name="identification" class="h-4 w-4" />
                                </span>
                                <span class="font-medium">{{ $siswa->nama }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-brand-muted">{{ $siswa->kelas?->nama_kelas ?? '-' }}</td>
                        <td class="px-4 py-3 text-brand-muted">{{ $siswa->waliMurid?->name ?? '— Belum ditautkan —' }}</td>
                        <td class="px-4 py-3">
                            {{-- Empat aksi dalam satu baris rapat: hanya IKON
                                 dengan tooltip, bukan ikon+teks. Dengan teks,
                                 kolom Aksi terpecah jadi tiga baris dan tabelnya
                                 terlihat berantakan — itu benar-benar terjadi
                                 setelah tombol Kartu ditambahkan. --}}
                            <div class="flex items-center justify-end gap-1">
                                {{-- Livewire.dispatch dipakai (bukan wire:click)
                                     karena tombol ini berada di luar komponen
                                     Livewire mana pun — ia cuma baris tabel Blade. --}}
                                <button type="button" title="Kartu Identitas" aria-label="Kartu Identitas"
                                    onclick="Livewire.dispatch('buka-kartu', { jenis: 'siswa', id: {{ $siswa->id }} })"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border text-brand-accent-text transition hover:border-brand-accent hover:bg-brand-accent-soft">
                                    <x-icon name="identification" class="h-4 w-4" />
                                </button>

                                <a href="{{ route($panelPrefix . '.siswa.qr', $siswa) }}"
                                    title="Cetak QR" aria-label="Cetak QR"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border text-brand-muted transition hover:bg-brand-surface-muted hover:text-brand-ink">
                                    <x-icon name="qr-code" class="h-4 w-4" />
                                </a>

                                <button type="button" title="Edit Data Lengkap" aria-label="Edit Data Lengkap"
                                    onclick="Livewire.dispatch('edit-data', { jenis: 'siswa', id: {{ $siswa->id }} })"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border text-brand-muted transition hover:bg-brand-surface-muted hover:text-brand-ink">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>

                                <form method="POST" action="{{ route($panelPrefix . '.siswa.destroy', $siswa) }}"
                                                                        data-konfirmasi-judul="Hapus Data Siswa?"
                                    data-konfirmasi="Data {{ $siswa->nama }} akan dihapus permanen — seluruh riwayat absensinya ikut terhapus dan tidak bisa dikembalikan."
                                    data-konfirmasi-ikon="warning"
                                    data-konfirmasi-ya="Ya, Hapus!"
                                    data-konfirmasi-batal="Batal">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus" aria-label="Hapus"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border text-brand-danger-text transition hover:border-brand-danger hover:bg-brand-danger-soft">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10">
                            <div class="flex flex-col items-center gap-2 text-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                                    <x-icon name="inbox" class="h-6 w-6" />
                                </span>
                                <p class="text-sm text-brand-muted">Belum ada siswa.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarSiswa->links() }}</div>

    {{-- Modal Kartu Identitas — dipasang sekali untuk seluruh tabel. --}}
    <livewire:kartu-identitas />

    {{-- Modal Edit Data Lengkap — dipasang sekali untuk seluruh tabel. --}}
    <livewire:super-admin.edit-data-lengkap />

@endsection
