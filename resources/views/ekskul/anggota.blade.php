@extends('layouts.app')

@section('title', 'Anggota Ekskul')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Anggota Ekskul</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Pilih siswa yang tergabung dalam kegiatan ekstrakurikuler ini.
        </p>
    </div>

    {{-- Id jadwal dibaca dari parameter rute lewat request()->route(), bukan
         lewat Route::view($uri, $view, $data): Route::view TIDAK meneruskan
         parameter URL ke view-nya. Ditulis begini, bukan sebagai rute closure,
         supaya `php artisan route:cache` tetap bisa dijalankan di server —
         closure membuat perintah itu gagal total. --}}
    <livewire:ekskul.anggota-ekskul :jadwal="(int) request()->route('jadwal')" />

@endsection
