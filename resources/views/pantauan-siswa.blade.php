@extends('layouts.app')

@section('title', 'Pantauan Kehadiran Siswa')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pantauan Kehadiran Siswa</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Absen gerbang dan kehadiran per jam pelajaran, dilihat per kelas. Halaman ini
            hanya menampilkan data — koreksi absensi dilakukan wali kelas &amp; guru mapel.
        </p>
    </div>

    <livewire:kepsek.pantauan-siswa />

@endsection
