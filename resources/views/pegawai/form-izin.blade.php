@extends('layouts.app')

@section('title', 'Pengajuan Izin')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Pengajuan Izin</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Ajukan izin atau sakit untuk tanggal tertentu. Pengajuan langsung tercatat
            di absensi Anda dan terbaca oleh Kepala Sekolah.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:pegawai.form-izin />

@endsection
