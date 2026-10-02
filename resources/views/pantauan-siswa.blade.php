@extends('layouts.app')

@section('title', 'Pantauan Kehadiran Siswa')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pantauan Kehadiran Siswa</h1>
        <p class="mt-1 text-sm text-brand-muted line-clamp-2">
            Absen gerbang &amp; per jam pelajaran tiap kelas. Koreksi oleh wali kelas &amp; guru mapel.
        </p>
    </div>

    <livewire:kepsek.pantauan-siswa />

@endsection
