@extends('layouts.app')

@section('title', 'Jurnal & Absen Kelas')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Jurnal &amp; Absen Kelas</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Daftar hadir siswa untuk jam pelajaran yang sedang berlangsung. Terbuka
            setelah Anda men-scan QR ruangan lewat menu Absen Mengajar.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:guru.jurnal-absen-kelas />

@endsection
