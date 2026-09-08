@extends('layouts.app')

@section('title', 'Daftar Izin Pegawai')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Daftar Izin Pegawai</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Rekap pengajuan izin &amp; sakit yang dikirim pegawai. Halaman ini hanya
            menampilkan data — tidak ada tombol yang mengubahnya.
        </p>
    </div>

    {{-- Komponen Livewire. Kalau halaman ini error "Unable to find component",
         berarti Livewire belum terpasang — jalankan dulu:
         composer require "livewire/livewire:^3.6" --}}
    <livewire:kepsek.daftar-izin-pegawai />

@endsection
