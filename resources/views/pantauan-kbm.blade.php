@extends('layouts.app')

@section('title', 'Pantauan KBM Harian')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pantauan KBM Harian</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Kehadiran anak Anda per jam pelajaran hari ini, sesuai jurnal yang diisi guru
            di kelas.
        </p>
    </div>

    <livewire:wali-murid.pantauan-kbm />

@endsection
