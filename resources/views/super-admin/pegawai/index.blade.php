@extends('layouts.app')

@section('title', 'Kelola Pegawai')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kelola Pegawai</h1>
            <p class="text-sm text-brand-muted line-clamp-2">{{ $daftarPegawai->total() }} pegawai terdaftar.</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route($panelPrefix . '.pegawai.qr-semua') }}"
                class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border border-brand-ink px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted">
                <x-icon name="qr-code" class="h-4 w-4" />
                Cetak QR Semua
            </a>
            <a href="{{ route($panelPrefix . '.pegawai.create') }}"
                class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
                <x-icon name="plus" class="h-4 w-4" />
                Tambah Pegawai
            </a>
        </div>
    </div>

    <form method="GET" action="{{ route($panelPrefix . '.pegawai.index') }}" class="mb-4">
        <div class="relative w-full sm:max-w-xs">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand-muted">
                <x-icon name="search" class="h-4 w-4" />
            </span>
            <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari nama, NIP, atau jabatan…"
                class="w-full rounded-lg border border-brand-border py-2.5 pl-10 pr-3 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        </div>
    </form>

    <div class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft">
        {{-- .tabel-kartu: kartu per pegawai di bawah 768 px (app.css bagian 6b). --}}
        <table class="tabel-kartu w-full text-left text-sm md:min-w-[680px]">
            <thead>
                <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted">
                    <th class="px-4 py-3 font-medium">NIP</th>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Jabatan</th>
                    <th class="px-4 py-3 font-medium">Akun Login</th>
                    <th class="px-4 py-3 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border">
                @forelse ($daftarPegawai as $pegawai)
                    <tr class="hover:bg-brand-surface-muted/60">
                        <td data-label="NIP" class="px-4 py-3 font-mono text-brand-muted">{{ $pegawai->nip ?: '—' }}</td>
                        <td data-utama class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent-soft text-brand-accent-text">
                                    <x-icon name="briefcase" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 break-words font-medium">{{ $pegawai->nama }}</span>
                            </div>
                        </td>
                        <td data-label="Jabatan" class="px-4 py-3 text-brand-muted">{{ $pegawai->jabatan }}</td>
                        <td data-label="Akun login" class="px-4 py-3 text-brand-muted [overflow-wrap:anywhere]">{{ $pegawai->user?->email ?? '— Belum ditautkan —' }}</td>
                        <td data-aksi class="px-4 py-3">
                            {{-- Empat aksi dalam satu baris rapat: hanya IKON
                                 dengan tooltip, bukan ikon+teks. Dengan teks,
                                 kolom Aksi terpecah jadi tiga baris dan tabelnya
                                 terlihat berantakan — itu benar-benar terjadi
                                 setelah tombol Kartu ditambahkan. --}}
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" title="Kartu Identitas" aria-label="Kartu Identitas"
                                    onclick="Livewire.dispatch('buka-kartu', { jenis: 'pegawai', id: {{ $pegawai->id }} })"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg border md:h-8 md:w-8 border-brand-border text-brand-accent-text transition hover:border-brand-accent hover:bg-brand-accent-soft">
                                    <x-icon name="identification" class="h-4 w-4" />
                                </button>

                                <a href="{{ route($panelPrefix . '.pegawai.qr', $pegawai) }}"
                                    title="Cetak QR" aria-label="Cetak QR"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg border md:h-8 md:w-8 border-brand-border text-brand-muted transition hover:bg-brand-surface-muted hover:text-brand-ink">
                                    <x-icon name="qr-code" class="h-4 w-4" />
                                </a>

                                <button type="button" title="Edit Data Lengkap" aria-label="Edit Data Lengkap"
                                    onclick="Livewire.dispatch('edit-data', { jenis: 'pegawai', id: {{ $pegawai->id }} })"
                                    class="flex h-10 w-10 items-center justify-center rounded-lg border md:h-8 md:w-8 border-brand-border text-brand-muted transition hover:bg-brand-surface-muted hover:text-brand-ink">
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </button>

                                <form method="POST" action="{{ route($panelPrefix . '.pegawai.destroy', $pegawai) }}"
                                    data-konfirmasi-judul="Hapus Data Pegawai?"
                                    data-konfirmasi="Data pegawai {{ $pegawai->nama }} akan dihapus permanen dan tidak bisa dikembalikan."
                                    data-konfirmasi-ikon="warning"
                                    data-konfirmasi-ya="Ya, Hapus!"
                                    data-konfirmasi-batal="Batal">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Hapus" aria-label="Hapus"
                                        class="flex h-10 w-10 items-center justify-center rounded-lg border md:h-8 md:w-8 border-brand-border text-brand-danger-text transition hover:border-brand-danger hover:bg-brand-danger-soft">
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
                                <p class="text-sm text-brand-muted">
                                    @if ($cari)
                                        Tidak ada pegawai yang cocok dengan pencarian "{{ $cari }}".
                                    @else
                                        Belum ada pegawai.
                                    @endif
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarPegawai->links() }}</div>

    {{-- Modal Kartu Identitas — dipasang sekali untuk seluruh tabel. --}}
    <livewire:kartu-identitas />

    {{-- Modal Edit Data Lengkap — dipasang sekali untuk seluruh tabel. --}}
    <livewire:super-admin.edit-data-lengkap />

@endsection
