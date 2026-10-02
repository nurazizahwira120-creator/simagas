@extends('layouts.app')

@section('title', 'Kalender Pendidikan')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">Kalender Pendidikan</h1>
        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-brand-muted dark:text-brand-faint line-clamp-2">
            Hari libur tidak dihitung alpa dan tidak masuk persentase kehadiran.
        </p>
    </div>

    @livewire('kalender-pendidikan')

@endsection
