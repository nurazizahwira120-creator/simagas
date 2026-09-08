@extends('layouts.app')

@section('title', 'Pencarian Cepat')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pencarian Cepat</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Cari siswa berdasarkan nama atau NIS. Hasil muncul seketika tanpa memuat ulang halaman.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require livewire/livewire --}}
    <livewire:search-data />
@endsection
