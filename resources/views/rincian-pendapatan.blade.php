@extends('layouts.app')

@section('title', 'Rincian Pendapatan')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Rincian Pendapatan</h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted line-clamp-2">
            Honor mengajar Anda per bulan. Bertambah otomatis setiap sesi diakhiri atau kelas inval selesai diisi.
        </p>
    </div>

    <livewire:honor.rincian-pendapatan />

@endsection
