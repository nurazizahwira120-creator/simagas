@extends('layouts.app')

@section('title', 'Pantauan Kehadiran Pegawai')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pantauan Kehadiran Pegawai</h1>
        <p class="mt-1 text-sm text-brand-muted line-clamp-2">
            Kehadiran &amp; status mengajar guru dan staf. Koreksi dilakukan Super Admin.
        </p>
    </div>

    <livewire:kepsek.pantauan-pegawai />

@endsection
