@extends('layouts.app')

@section('title', 'Absen Radius')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Absen Radius</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Catat kehadiran Anda dengan verifikasi lokasi. {{ now()->translatedFormat('l, d F Y') }}.
        </p>
    </div>

    <div class="max-w-2xl">
        {{-- Komponen Livewire. Kalau halaman ini error "Unable to find
             component", berarti Livewire belum terpasang — jalankan dulu:
             composer require livewire/livewire --}}
        <livewire:absen-guru />

        <div class="mt-4 flex items-start gap-2.5 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-xs text-brand-muted">
            <x-icon name="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-brand-accent-text" />
            <p>
                Lokasi hanya dibaca sekali, tepat saat Anda menekan tombol, dan yang disimpan ke
                database hanyalah jarak dalam meter — bukan koordinat Anda. Sistem tidak melacak
                posisi Anda di luar momen itu.
            </p>
        </div>
    </div>

@endsection
