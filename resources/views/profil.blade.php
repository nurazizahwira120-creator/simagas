@extends('layouts.app')

@section('title', 'Profil Saya')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Profil Saya</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Perbarui foto, data diri, dan kata sandi akun Anda.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:profile.update-profile />

@endsection
