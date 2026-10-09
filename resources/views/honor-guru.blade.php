@extends('layouts.app')

@section('title', 'Honor Guru')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">
            Honor Guru
            <span class="ml-1 align-middle rounded-full bg-warning-500/10 px-2.5 py-0.5 text-xs font-semibold text-warning-700 dark:text-warning-400">Uji coba</span>
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted line-clamp-2">
            Atur tarif honor per JP dan bagian guru inval, lalu pantau honor semua guru per bulan.
        </p>
    </div>

    <livewire:honor.honor-guru />

@endsection
