@extends('layouts.app')

@section('title', 'Jadwal Ekskul')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Jadwal Ekskul</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Jadwal kegiatan ekstrakurikuler: hari, jam, dan pembinanya.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:ekskul.kelola-ekskul />

@endsection
