@extends('layouts.app')

@section('title', 'Kelas Pengganti')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Kelas Pengganti</h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted">
            Kelas hari ini yang gurunya berhalangan hadir (izin, sakit, atau alpa). Absensi siswanya
            tetap bisa diisi oleh guru pengganti, wali kelas, kepala sekolah, guru piket, atau super admin &mdash;
            tanpa scan QR ruangan. Nama pengisi tercatat otomatis.
        </p>
    </div>

    <livewire:guru.kelas-pengganti />

@endsection
