@extends('layouts.app')

@section('title', 'Jadwal Pelajaran')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Jadwal Pelajaran</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Jadwal pelajaran per hari. Halaman ini hanya menampilkan data — penyusunan
            jadwal dilakukan oleh Super Admin.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:jadwal-pelajaran />

@endsection
