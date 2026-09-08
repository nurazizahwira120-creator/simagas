@extends('layouts.app')

@section('title', 'Kirim Pengumuman')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Kirim Pengumuman</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Pengumuman masuk ke lonceng notifikasi penerima, dan bisa sekaligus dikirim
            lewat WhatsApp bila gateway-nya aktif.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:kirim-pengumuman />

@endsection
