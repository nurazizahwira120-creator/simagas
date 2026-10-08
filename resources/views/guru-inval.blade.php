@extends('layouts.app')

@php $penunjukInval = \App\Services\GuruInval::bolehMenunjuk(auth()->user()); @endphp

@section('title', $penunjukInval ? 'Guru Inval' : 'Tugas Inval')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">{{ $penunjukInval ? 'Guru Inval' : 'Tugas Inval' }}</h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted line-clamp-2">
            @if ($penunjukInval)
                Tunjuk guru atau staf pengganti untuk jam pelajaran yang gurunya berhalangan.
            @else
                Kelas yang ditugaskan kepada Anda sebagai guru inval. Absensinya diisi tanpa scan QR.
            @endif
        </p>
    </div>

    <livewire:guru.guru-inval />

@endsection
