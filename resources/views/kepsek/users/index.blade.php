@extends('layouts.app')

@section('title', 'Kelola Pengguna')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kelola Pengguna</h1>
            <p class="text-sm text-brand-muted line-clamp-2">Akun kepala sekolah, wali kelas, wali murid, dan guru piket.</p>
        </div>
        <a href="{{ route($panelPrefix . '.users.create') }}"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Tambah Akun
        </a>
    </div>

    {{-- Chip saringan: tinggi 36 px di HP (dulu 26 px — terlalu kecil untuk jari). --}}
    <div class="mb-4 flex flex-wrap items-center gap-1.5">
        <span class="mr-0.5 text-brand-muted"><x-icon name="funnel" class="h-4 w-4" /></span>
        <a href="{{ route($panelPrefix . '.users.index') }}"
            class="inline-flex min-h-9 items-center rounded-full border px-3.5 py-1.5 text-xs font-medium sm:min-h-0 sm:px-3 sm:py-1 {{ ! $roleFilter ? 'border-brand-accent bg-brand-accent text-white' : 'border-brand-border text-brand-muted' }}">
            Semua
        </a>
        @foreach ($roleOptions as $opsi)
            <a href="{{ route($panelPrefix . '.users.index', ['role' => $opsi->value]) }}"
                class="inline-flex min-h-9 items-center rounded-full border px-3.5 py-1.5 text-xs font-medium sm:min-h-0 sm:px-3 sm:py-1 {{ $roleFilter === $opsi->value ? 'border-brand-accent bg-brand-accent text-white' : 'border-brand-border text-brand-muted' }}">
                {{ $opsi->label() }}
            </a>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft">
        {{-- .tabel-kartu: di bawah 768 px tiap baris tampil sebagai kartu
             (lihat app.css bagian 6b), di desktop tetap tabel. --}}
        <table class="tabel-kartu w-full text-left text-sm md:min-w-[560px]">
            <thead>
                <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted">
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Email</th>
                    <th class="px-4 py-3 font-medium">Role</th>
                    <th class="px-4 py-3 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border">
                @forelse ($users as $akun)
                    <tr class="hover:bg-brand-surface-muted/60">
                        <td data-utama class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-accent-soft text-[11px] font-bold text-brand-accent-text">
                                    {{ Str::of($akun->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                </span>
                                <span class="min-w-0 break-words font-medium">{{ $akun->name }}</span>
                            </div>
                        </td>
                        <td data-label="Email" class="px-4 py-3 text-brand-muted [overflow-wrap:anywhere]">{{ $akun->email }}</td>
                        <td data-label="Role" class="px-4 py-3">
                            <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full border border-brand-border px-2.5 py-1 text-xs text-brand-muted">
                                <x-icon name="shield-check" class="h-3.5 w-3.5" />
                                {{ $akun->role->label() }}
                            </span>
                        </td>
                        <td data-aksi class="px-4 py-3">
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route($panelPrefix . '.users.edit', $akun) }}"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-border px-3.5 py-2 text-xs font-medium hover:bg-brand-surface-muted md:min-h-0 md:px-3 md:py-1.5">
                                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                                    Edit
                                </a>
                                @if ($akun->id !== auth()->id())
                                    <form method="POST" action="{{ route($panelPrefix . '.users.destroy', $akun) }}"
                                        data-konfirmasi-judul="Hapus Akun Ini?"
                                        data-konfirmasi="Akun {{ $akun->name }} akan dihapus permanen dan tidak bisa dipakai login lagi."
                                        data-konfirmasi-ikon="warning"
                                        data-konfirmasi-ya="Ya, Hapus!"
                                        data-konfirmasi-batal="Batal">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-danger px-3.5 py-2 text-xs font-medium text-brand-danger-text hover:bg-brand-danger-soft md:min-h-0 md:px-3 md:py-1.5">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                            Hapus
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10">
                            <div class="flex flex-col items-center gap-2 text-center">
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                                    <x-icon name="inbox" class="h-6 w-6" />
                                </span>
                                <p class="text-sm text-brand-muted">Belum ada akun.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
