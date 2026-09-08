@extends('layouts.app')

@section('title', 'Edit Akun')

@section('content')
    <div class="mb-5">
        <a href="{{ route($panelPrefix . '.users.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-muted hover:text-brand-ink">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" />
            Kelola Pengguna
        </a>
        <h1 class="mt-1.5 text-xl font-bold">Edit Akun — {{ $akun->name }}</h1>
    </div>

    <form method="POST" action="{{ route($panelPrefix . '.users.update', $akun) }}" class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
        @csrf
        @method('PUT')
        @include('kepsek.users._form')

        <button type="submit" class="mt-6 inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
            <x-icon name="check-circle" class="h-4 w-4" />
            Simpan Perubahan
        </button>
    </form>
@endsection
