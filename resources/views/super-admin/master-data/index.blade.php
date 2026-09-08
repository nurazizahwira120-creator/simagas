@extends('layouts.app')

@section('title', 'Master Data')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl font-bold">Master Data</h1>
        <p class="text-sm text-brand-muted">
            Import massal dari Excel/CSV atau export data yang sudah ada, untuk Siswa, Pegawai, dan Kelas.
        </p>
    </div>

    @if (session('importGagal') !== null && collect(session('importGagal'))->isNotEmpty())
        <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-500/10 p-4 text-sm text-amber-900">
            <p class="mb-2 flex items-center gap-1.5 font-semibold">
                <x-icon name="exclamation-triangle" class="h-4 w-4" />
                {{ collect(session('importGagal'))->count() }} baris dilewati saat import "{{ session('importGagalEntitas') }}":
            </p>
            <ul class="ml-1 max-h-64 list-inside list-disc space-y-1 overflow-y-auto">
                @foreach (session('importGagal') as $item)
                    <li>
                        @if (! empty($item['baris']))
                            <span class="font-mono font-medium">Baris {{ $item['baris'] }}:</span>
                        @endif
                        {{ $item['pesan'] }}
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        @foreach ($daftarEntitas as $entitas)
            <div class="flex flex-col rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
                <div class="mb-1 flex items-center gap-2.5">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-accent-soft text-brand-accent-text">
                        <x-icon name="{{ $entitas['icon'] }}" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="font-semibold leading-tight">{{ $entitas['label'] }}</p>
                        <p class="text-xs text-brand-muted">{{ $entitas['total'] }} data tersimpan</p>
                    </div>
                </div>

                <p class="mt-3 text-xs text-brand-muted">
                    Kolom file: <span class="font-mono">{{ $entitas['kolom'] }}</span>
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route($panelPrefix . '.master-data.' . $entitas['kunci'] . '.template') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border px-3 py-1.5 text-xs font-medium hover:bg-brand-surface-muted">
                        <x-icon name="download" class="h-3.5 w-3.5" />
                        Unduh Template
                    </a>
                    <a href="{{ route($panelPrefix . '.master-data.' . $entitas['kunci'] . '.export') }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border px-3 py-1.5 text-xs font-medium hover:bg-brand-surface-muted">
                        <x-icon name="download" class="h-3.5 w-3.5" />
                        Export Data
                    </a>
                </div>

                <form method="POST"
                    action="{{ route($panelPrefix . '.master-data.' . $entitas['kunci'] . '.import') }}"
                    enctype="multipart/form-data"
                    class="mt-4 border-t border-brand-border pt-4">
                    @csrf

                    <label for="file-{{ $entitas['kunci'] }}" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                        <x-icon name="upload" class="h-3.5 w-3.5" />
                        Import dari file (.xlsx, .xls, .csv — maks. 10 MB)
                    </label>
                    <input id="file-{{ $entitas['kunci'] }}" type="file" name="file" accept=".xlsx,.xls,.csv" required
                        class="block w-full text-xs text-brand-muted file:mr-3 file:rounded-lg file:border-0 file:bg-brand-surface-muted file:px-3 file:py-2 file:text-xs file:font-medium file:text-brand-ink hover:file:bg-brand-border/60">

                    <button type="submit"
                        class="mt-3 inline-flex w-full items-center justify-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
                        <x-icon name="upload" class="h-4 w-4" />
                        Import {{ $entitas['label'] }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>
@endsection
