{{--
    Pembungkus halaman untuk komponen App\Livewire\Laporan\LaporanBulanan.

    Dipakai BERSAMA oleh dua rute: /kepsek/laporan-bulanan dan
    /super-admin/laporan-bulanan. Satu berkas, bukan dua salinan — isinya
    identik dan $panelPrefix di dalam komponen sudah membedakan tujuan
    tautannya sendiri.
--}}
@extends('layouts.app')

@section('title', 'Laporan Bulanan')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Laporan Bulanan</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Arsip rekapitulasi kehadiran pegawai dan siswa yang dihasilkan sistem secara otomatis
            setiap awal bulan. Berkasnya siap cetak dan tidak memerlukan tanda tangan basah.
        </p>
    </div>

    @livewire('laporan.laporan-bulanan')

@endsection
