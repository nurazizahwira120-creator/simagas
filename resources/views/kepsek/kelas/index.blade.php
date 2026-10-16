@extends('layouts.app')

@section('title', 'Kelola Kelas')

@section('content')
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Kelola Kelas</h1>
            <p class="text-sm text-brand-muted line-clamp-2">Daftar kelas beserta wali kelas dan jumlah siswanya.</p>
        </div>
        <a href="{{ route($panelPrefix . '.kelas.create') }}"
            class="inline-flex min-h-11 items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
            <x-icon name="plus" class="h-4 w-4" />
            Tambah Kelas
        </a>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft">
        {{-- .tabel-kartu: kartu per kelas di bawah 768 px (app.css bagian 6b). --}}
        <table class="tabel-kartu w-full text-left text-sm md:min-w-[560px]">
            <thead>
                <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted">
                    <th class="px-4 py-3 font-medium">Kelas</th>
                    <th class="px-4 py-3 font-medium">Wali Kelas</th>
                    <th class="px-4 py-3 font-medium text-center">Jumlah Siswa</th>
                    <th class="px-4 py-3 font-medium text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-brand-border">
                @forelse ($daftarKelas as $kelas)
                    <tr class="hover:bg-brand-surface-muted/60">
                        <td data-utama class="px-4 py-3">
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-accent-soft text-brand-accent-text">
                                    <x-icon name="academic-cap" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0 break-words font-medium">{{ $kelas->nama_kelas }}</span>
                            </div>
                        </td>
                        <td data-label="Wali kelas" class="px-4 py-3 text-brand-muted">{{ $kelas->waliKelas?->name ?? '— Belum ditentukan —' }}</td>
                        <td data-label="Jumlah siswa" class="px-4 py-3 text-center tabular-nums">{{ $kelas->siswa_count }}</td>
                        <td data-aksi class="px-4 py-3">
                            <div class="flex flex-wrap justify-end gap-1.5">
                                <a href="{{ route($panelPrefix . '.siswa.index', ['kelas_id' => $kelas->id]) }}"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-border px-3.5 py-2 text-xs md:min-h-0 md:px-3 md:py-1.5 font-medium hover:bg-brand-surface-muted">
                                    <x-icon name="identification" class="h-3.5 w-3.5" />
                                    Siswa
                                </a>
                                <a href="{{ route($panelPrefix . '.siswa.qr-kelas', $kelas) }}"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-border px-3.5 py-2 text-xs md:min-h-0 md:px-3 md:py-1.5 font-medium hover:bg-brand-surface-muted">
                                    <x-icon name="qr-code" class="h-3.5 w-3.5" />
                                    QR
                                </a>
                                <a href="{{ route($panelPrefix . '.kelas.edit', $kelas) }}"
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-border px-3.5 py-2 text-xs md:min-h-0 md:px-3 md:py-1.5 font-medium hover:bg-brand-surface-muted">
                                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                                    Edit
                                </a>
                                <form method="POST" action="{{ route($panelPrefix . '.kelas.destroy', $kelas) }}"
                                    data-konfirmasi-judul="Hapus Kelas Ini?"
                                    data-konfirmasi="Kelas {{ $kelas->nama_kelas }} akan dihapus permanen."
                                    data-konfirmasi-ikon="warning"
                                    data-konfirmasi-ya="Ya, Hapus!"
                                    data-konfirmasi-batal="Batal">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-brand-danger px-3.5 py-2 text-xs md:min-h-0 md:px-3 md:py-1.5 font-medium text-brand-danger-text hover:bg-brand-danger-soft">
                                        <x-icon name="trash" class="h-3.5 w-3.5" />
                                        Hapus
                                    </button>
                                </form>
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
                                <p class="text-sm text-brand-muted">Belum ada kelas.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $daftarKelas->links() }}</div>
@endsection
