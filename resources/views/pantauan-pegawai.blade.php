@extends('layouts.app')

@section('title', 'Pantauan Kehadiran Pegawai')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pantauan Kehadiran Pegawai</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Kehadiran Radius dan status mengajar seluruh guru &amp; staff. Halaman ini
            hanya menampilkan data — koreksi absensi dilakukan Super Admin.
        </p>
    </div>

    <livewire:kepsek.pantauan-pegawai />

@endsection
