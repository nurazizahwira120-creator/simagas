@extends('layouts.app')

@section('title', 'Lihat RPP')

@section('content')

    @php
        // Satu URL untuk keduanya: <embed> dan <iframe> menunjuk rute yang
        // sama, yang memeriksa login + RppPolicy sebelum mengalirkan berkas.
        $urlBerkas = route($panelPrefix . '.rpp.berkas', $rpp);
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">
                {{ $rpp->judul_rpp }}
            </h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ $rpp->mata_pelajaran }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $rpp->user?->name ?? 'Akun terhapus' }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $rpp->created_at->translatedFormat('d F Y, H:i') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($adaBerkas)
                <a href="{{ $urlBerkas }}?unduh=1"
                    class="inline-flex items-center gap-2 rounded-lg border border-brand-500/40 px-4 py-2.5 text-sm font-semibold text-brand-accent-text transition hover:bg-brand-500/10">
                    <x-icon name="download" class="h-4 w-4" />
                    Unduh
                </a>
            @endif

            <a href="{{ route($panelPrefix . '.rpp.index') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Kembali
            </a>
        </div>
    </div>

    @if (! $adaBerkas)
        {{-- Baris database ada, berkasnya tidak. Ini bisa terjadi kalau folder
             storage/ ikut terhapus saat deploy. Ditampilkan terang-terangan —
             viewer PDF kosong tanpa penjelasan justru membuat orang mengira
             seluruh fiturnya rusak. --}}
        <div class="flex items-start gap-3 rounded-2xl border border-error-200 bg-error-500/10 p-5 dark:border-error-500/30" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-error-700 dark:text-error-400" />
            <div>
                <p class="text-sm font-bold text-error-700 dark:text-error-400">Berkas PDF-nya tidak ditemukan di server.</p>
                <p class="mt-1 text-sm text-error-700 dark:text-error-400">
                    Datanya masih tercatat, tetapi berkas
                    <code class="font-mono text-xs">storage/app/public/{{ $rpp->file_path }}</code>
                    sudah tidak ada. Unggah ulang dokumennya, lalu hapus entri yang lama.
                </p>
            </div>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">

            {{-- ============================================================
                 PENAMPIL PDF — berlapis tiga, dari yang paling baik.

                 ============ KENAPA <object>, BUKAN <embed> ============
                 Bentuk yang biasa disarankan —

                     <embed src="..."> <iframe src="..."></iframe> </embed>

                 — TIDAK bekerja, dan gagalnya kelihatan jelas: PDF-nya tampil
                 DUA KALI, bertumpuk ke bawah. Sebabnya <embed> itu elemen
                 VOID (seperti <img> dan <br>): ia tidak boleh punya anak dan
                 tidak punya penutup. Browser mengabaikan </embed>, lalu
                 <iframe> yang dimaksudkan sebagai cadangan berubah jadi
                 SAUDARA-nya — dua penampil aktif sekaligus, bukan satu
                 dengan cadangannya.

                 <object> memang dirancang untuk ini: isinya baru dirender
                 kalau berkasnya gagal ditampilkan. Cara menampilkan PDF-nya
                 identik dengan <embed> (jalur plugin yang sama), jadi tidak
                 ada yang dikorbankan.
                 =======================================================

                 Lapis 3 adalah tautan di bawah kotak ini yang SELALU terlihat:
                 di banyak browser HP (Safari iOS, sebagian Android) kotaknya
                 tampil kosong TANPA memicu konten cadangan apa pun, jadi jalan
                 keluarnya harus ada di halaman — bukan di dalam elemen yang
                 gagal itu.
                 ============================================================ --}}
            <object data="{{ $urlBerkas }}" type="application/pdf"
                aria-label="Penampil RPP: {{ $rpp->judul_rpp }}"
                class="block w-full bg-gray-100 dark:bg-gray-900" style="height: 600px;">

                <iframe src="{{ $urlBerkas }}" title="Penampil RPP: {{ $rpp->judul_rpp }}"
                    width="100%" height="600px" class="block w-full border-0 bg-gray-100 dark:bg-gray-900">

                    <div class="flex flex-col items-center gap-3 p-10 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Browser Anda tidak bisa menampilkan PDF langsung di halaman ini.
                        </p>
                        <a href="{{ $urlBerkas }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                            Buka PDF di Tab Baru
                        </a>
                    </div>
                </iframe>
            </object>
        </div>

        {{-- Tautan cadangan yang SELALU terlihat, di luar embed/iframe.
             Di HP, kotak penampilnya bisa tampil kosong tanpa memicu konten
             cadangan apa pun — jadi jalan keluarnya harus ada di halaman,
             bukan hanya di dalam elemen yang gagal itu. --}}
        <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
            PDF tidak tampil?
            <a href="{{ $urlBerkas }}" target="_blank" rel="noopener"
                class="font-semibold text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">
                Buka di tab baru
            </a>
            atau
            <a href="{{ $urlBerkas }}?unduh=1"
                class="font-semibold text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">
                unduh berkasnya
            </a>.
        </p>
    @endif

@endsection
