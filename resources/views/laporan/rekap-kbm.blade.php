{{--
    Pembungkus halaman untuk komponen App\Livewire\Laporan\RekapKbm.

    Dipakai BERSAMA oleh dua rute: /kepsek/rekap-kbm dan
    /super-admin/rekap-kbm. Satu berkas, bukan dua salinan.
--}}
@extends('layouts.app')

@section('title', 'Rekap KBM per Jadwal')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">
            Rekap KBM per Jadwal Pelajaran
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Kehadiran siswa dihitung per slot jadwal — hari, jam, mata pelajaran, kelas, dan gurunya.
            Berbeda dari Rekap Bulanan Siswa yang menghitung kehadiran di gerbang, halaman ini
            menunjukkan apa yang terjadi <em>di dalam kelas</em>: jam mana yang paling banyak
            ditinggalkan, dan jadwal mana yang jurnalnya belum pernah diisi.
        </p>
    </div>

    @livewire('laporan.rekap-kbm')

@endsection
