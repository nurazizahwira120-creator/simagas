@extends('layouts.app')

@section('title', 'Tambah Pegawai')

@section('content')
    <div class="mb-5">
        <a href="{{ route($panelPrefix . '.pegawai.index') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-brand-muted hover:text-brand-ink">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" />
            Kelola Pegawai
        </a>
        <h1 class="mt-1.5 text-xl font-bold">Tambah Pegawai</h1>
    </div>

    <form method="POST" action="{{ route($panelPrefix . '.pegawai.store') }}" class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
        @csrf
        @include('super-admin.pegawai._form')

        <button type="submit" class="mt-6 inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
            <x-icon name="check-circle" class="h-4 w-4" />
            Simpan Pegawai
        </button>
    </form>
@endsection
