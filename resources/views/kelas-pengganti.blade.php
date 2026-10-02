@extends('layouts.app')

@section('title', 'Kelas Pengganti')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Kelas Pengganti</h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted line-clamp-2">
            Kelas yang gurunya berhalangan hari ini. Absensinya bisa diisi tanpa scan QR.
        </p>
    </div>

    <livewire:guru.kelas-pengganti />

@endsection
