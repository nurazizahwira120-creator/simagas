@extends('layouts.app')

@section('title', 'Rekap & Laporan Akademik')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Rekap &amp; Laporan Akademik</h1>
        <p class="mt-1 text-sm text-brand-muted">
            Persentase kehadiran anak Anda per bulan — di gerbang maupun per mata pelajaran.
        </p>
    </div>

    <livewire:wali-murid.rekap-akademik />

@endsection
