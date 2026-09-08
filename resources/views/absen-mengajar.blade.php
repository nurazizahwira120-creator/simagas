@extends('layouts.app')

@section('title', 'Absen Mengajar')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Absen Mengajar (QR)</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Scan stiker QR ruangan di meja guru setiap kali Anda masuk kelas.
            Wajib sudah Absen Kehadiran pagi ini terlebih dahulu.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:guru.absen-mengajar-qr />

@endsection
