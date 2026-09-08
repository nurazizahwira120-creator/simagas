@extends('layouts.app')

@section('title', 'Scan Absensi Siswa')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Scan Absensi Siswa</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Arahkan kamera ke QR/barcode NIS siswa. Kehadiran langsung tercatat tanpa memuat ulang halaman.
        </p>
    </div>

    <div class="max-w-md">
        {{-- Komponen Livewire. Kalau halaman ini error "Unable to find
             component", berarti Livewire belum terpasang — jalankan dulu:
             composer require livewire/livewire --}}
        <livewire:scanner-kamera-siswa />
    </div>

@endsection
