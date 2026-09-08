@extends('layouts.app')

@section('title', 'Absensi Ekskul')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Absensi Ekskul</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Kehadiran siswa per pertemuan ekstrakurikuler.
        </p>
    </div>

    {{-- Lihat catatan di ekskul/anggota.blade.php soal request()->route(). --}}
    <livewire:ekskul.absensi-ekskul :jadwal="(int) request()->route('jadwal')" />

@endsection
