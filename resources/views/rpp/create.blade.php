@extends('layouts.app')

@section('title', 'Upload RPP')

@section('content')

    @php
        $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-5 py-3 font-medium text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:border-brand-500';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Upload RPP Baru</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Berkas PDF, maksimal 5 MB. Dokumen hanya bisa dibuka oleh Anda dan Kepala Sekolah.
        </p>
    </div>

    <div class="max-w-3xl rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">

        {{-- enctype WAJIB. Tanpa itu berkasnya tidak pernah ikut terkirim dan
             validasi menolak dengan "Berkas PDF wajib dipilih" — padahal
             penggunanya sudah memilih berkas. --}}
        <form id="rpp-form" method="POST" action="{{ route($panelPrefix . '.rpp.store') }}"
            enctype="multipart/form-data" class="space-y-5 p-6">
            @csrf

            <div>
                <label for="judul_rpp" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Judul RPP <span class="text-error-500">*</span>
                </label>
                <input id="judul_rpp" name="judul_rpp" type="text" maxlength="150" required
                    value="{{ old('judul_rpp') }}"
                    placeholder="Contoh: RPP Bab 3 — Persamaan Kuadrat"
                    class="{{ $kelasInput }}">
                @error('judul_rpp')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="mata_pelajaran" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Mata Pelajaran <span class="text-error-500">*</span>
                </label>
                <input id="mata_pelajaran" name="mata_pelajaran" type="text" maxlength="100" required
                    value="{{ old('mata_pelajaran') }}"
                    placeholder="Contoh: Matematika"
                    class="{{ $kelasInput }}">
                @error('mata_pelajaran')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="file_rpp" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Berkas RPP (PDF) <span class="text-error-500">*</span>
                </label>

                {{-- accept="application/pdf" hanya menyaring dialog pemilih
                     berkas, BUKAN pengaman: siapa pun bisa mengabaikannya.
                     Pemeriksaan sesungguhnya ada di RppController (aturan
                     mimes:pdf + pembacaan tanda tangan "%PDF-"). --}}
                <input id="file_rpp" name="file_rpp" type="file" accept="application/pdf,.pdf" required
                    class="w-full cursor-pointer rounded-lg border-[1.5px] border-gray-300 bg-transparent text-sm text-gray-600 outline-none transition file:mr-4 file:cursor-pointer file:border-0 file:bg-brand-500 file:px-5 file:py-3 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-600 focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">

                @error('file_rpp')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror

                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Format PDF, maksimal <strong>{{ $batasServer }} MB</strong>.
                    @if ($batasServer < 5)
                        {{-- Batas PHP server lebih kecil dari batas aplikasi. Ditulis
                             terang-terangan karena gejalanya kalau tidak: berkas
                             ditolak dengan pesan "wajib dipilih" — menyesatkan,
                             sebab berkasnya memang tidak pernah sampai ke PHP. --}}
                        <span class="mt-1 block text-warning-700 dark:text-warning-400">
                            Server ini membatasi unggahan di {{ $batasServer }} MB (lebih kecil dari batas aplikasi 5 MB).
                            Untuk menaikkannya, ubah <code>upload_max_filesize</code> dan <code>post_max_size</code>
                            di cPanel &rsaquo; Select PHP Version &rsaquo; Options.
                        </span>
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                    <x-icon name="upload" class="h-4 w-4" />
                    Simpan RPP
                </button>

                <a href="{{ route($panelPrefix . '.rpp.index') }}"
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-5 py-3 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                    <x-icon name="arrow-left" class="h-4 w-4" />
                    Batal
                </a>
            </div>
        </form>
    </div>

@endsection
