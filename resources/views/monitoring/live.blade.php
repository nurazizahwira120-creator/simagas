@extends('layouts.app')

@section('title', 'Monitoring KBM Hari Ini')

{{--
    Monitoring KBM HARI INI — App\Http\Controllers\MonitoringController.

    Seluruh jadwal kelas & ekskul hari ini (bukan hanya jam yang sedang
    berjalan), plus "Bantu Akhiri Sesi" untuk sesi yang lupa diakhiri.
    Status & warnanya dari App\Services\MonitoringKbmHarian.

    Saringan (Semua / Berjalan / Belum diakhiri / ...) bekerja di browser
    dengan Alpine — tanpa memuat ulang halaman, tanpa query tambahan.
--}}

@section('content')

    {{-- ============ KEPALA HALAMAN ============ --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">Monitoring KBM Hari Ini</h1>
                {{-- Titik merahnya saja yang berdenyut, bukan seluruh lencana;
                     motion-reduce menghentikannya bagi yang sensitif gerak. --}}
                <span class="inline-flex items-center gap-2 rounded-full border border-red-300 bg-red-500/10 px-3 py-1 text-xs font-bold uppercase tracking-wide text-brand-danger-text">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-500 opacity-75 motion-reduce:animate-none"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                    </span>
                    Live
                </span>
            </div>
            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-brand-muted dark:text-brand-faint">
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="calendar" class="h-3.5 w-3.5" />
                    {{ $hariIni->label() }}, {{ $sekarang->translatedFormat('d F Y') }}
                </span>
                <span aria-hidden="true">&middot;</span>
                <span class="inline-flex items-center gap-1.5">
                    <x-icon name="clock" class="h-3.5 w-3.5" />
                    Keadaan pukul {{ $sekarang->format('H:i') }} WIB
                </span>
            </p>
        </div>

        {{-- Muat ulang manual (bukan polling): halaman ini sering dibiarkan
             terbuka berjam-jam di hosting bersama. --}}
        <a href="{{ url()->current() }}"
            class="kartu-angkat inline-flex items-center gap-2 rounded-lg border border-brand-border bg-brand-surface px-4 py-2 text-sm font-medium text-brand-ink dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <x-icon name="swap" class="h-4 w-4" />
            Perbarui
        </a>
    </div>

    {{-- ============ NOTIFIKASI HASIL "BANTU AKHIRI SESI" ============ --}}
    @if ($notif = session('notif_monitoring'))
        <div class="mb-5 flex w-full border-l-4 px-5 py-4 shadow-md {{ $notif['tipe'] === 'ok' ? 'border-success-500 bg-success-500/10' : 'border-warning-500 bg-warning-500/10' }}" role="status">
            <x-icon name="{{ $notif['tipe'] === 'ok' ? 'check-circle' : 'exclamation-triangle' }}" class="mr-4 mt-0.5 h-5 w-5 shrink-0 {{ $notif['tipe'] === 'ok' ? 'text-success-500' : 'text-warning-500' }}" />
            <div class="min-w-0">
                <h5 class="mb-1 font-semibold text-brand-ink dark:text-white">{{ $notif['judul'] }}</h5>
                <p class="text-sm leading-relaxed text-brand-muted">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 border-l-4 border-error-500 bg-error-500/10 px-5 py-4 text-sm text-error-700 dark:text-error-400" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- ============ SESI MENGGANTUNG HARI-HARI SEBELUMNYA ============ --}}
    @if ($menggantung->isNotEmpty())
        <div class="mb-6 rounded-2xl border-2 border-orange-300 bg-brand-surface shadow-soft dark:border-orange-500/50 dark:bg-gray-900">
            <div class="border-b border-orange-200 px-5 py-4 dark:border-orange-500/30">
                <h2 class="font-semibold text-orange-800 dark:text-orange-300">
                    Sesi belum diakhiri dari hari sebelumnya ({{ $menggantung->count() }})
                </h2>
                <p class="mt-0.5 text-xs text-brand-muted">7 hari terakhir. Honor sesi ini belum tercatat sampai sesinya diakhiri.</p>
            </div>
            <ul class="divide-y divide-brand-border dark:divide-gray-800">
                @foreach ($menggantung as $m)
                    <li class="grid gap-3 px-5 py-4 sm:grid-cols-[1fr_20rem] sm:items-start">
                        <div class="min-w-0">
                            <p class="font-semibold text-brand-ink dark:text-white">{{ $m['judul'] }}</p>
                            <p class="mt-0.5 text-xs text-brand-muted">
                                {{ $m['tanggal']->translatedFormat('l, d M') }} &middot; {{ $m['jam'] }} &middot; {{ $m['orang'] }} &middot; scan {{ $m['scan'] }}
                            </p>
                        </div>
                        @if ($bolehAkhiri)
                            <div class="-mt-4">
                                @include('monitoring.partials.form-akhiri', ['jenis' => $m['jenis'], 'idSesi' => $m['id'], 'nama' => $m['judul']])
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (empty($baris))

        <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-12 text-center dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                <x-icon name="calendar" class="h-6 w-6" />
            </span>
            <p class="font-semibold text-brand-ink dark:text-white">Tidak ada jadwal kelas maupun ekskul hari ini</p>
            <p class="max-w-md text-sm leading-relaxed text-brand-muted">Hari {{ $hariIni->label() }} tidak punya jadwal pelajaran atau ekskul.</p>
        </div>

    @else

        @php
            $saringan = [
                'semua' => ['Semua', $ringkas['total'], 'text-brand-ink dark:text-white'],
                'berjalan' => ['Sedang berjalan', $ringkas['berjalan'], 'text-brand-accent-text'],
                'belum_diakhiri' => ['Belum diakhiri', $ringkas['belum_diakhiri'], 'text-orange-600 dark:text-orange-400'],
                'selesai' => ['Selesai', $ringkas['selesai'], 'text-emerald-600 dark:text-emerald-400'],
                'belum_mulai' => ['Belum mulai', $ringkas['belum_mulai'], 'text-brand-muted'],
                'bermasalah' => ['Bermasalah', $ringkas['bermasalah'], 'text-brand-danger-text'],
            ];
        @endphp

        <div x-data="{ saring: '{{ $ringkas['belum_diakhiri'] > 0 ? 'belum_diakhiri' : 'semua' }}', jumlah: @js(collect($saringan)->map(fn ($s) => $s[1])) }">

            {{-- ============ RINGKASAN = SARINGAN ============
                 Setiap kotak angka sekaligus tombol saringan: satu ketukan
                 langsung menampilkan kelompok itu saja. --}}
            <div class="mb-6 grid grid-cols-3 gap-2 sm:grid-cols-6 sm:gap-3">
                @foreach ($saringan as $kunci => [$label, $nilai, $warna])
                    <button type="button" x-on:click="saring = '{{ $kunci }}'"
                        x-bind:class="saring === '{{ $kunci }}' ? 'ring-2 ring-brand-500' : ''"
                        x-bind:aria-pressed="saring === '{{ $kunci }}'"
                        class="flex min-w-0 flex-col justify-between rounded-2xl border border-brand-border bg-brand-surface p-3 text-left shadow-soft transition hover:bg-brand-surface-muted dark:border-gray-800 dark:bg-gray-900">
                        {{-- Label boleh dua baris di HP (bukan dipotong "…"). --}}
                        <span class="block text-[11px] font-medium uppercase leading-tight tracking-wide text-brand-muted">{{ $label }}</span>
                        <span class="mt-1 block text-2xl font-bold {{ $warna }}">{{ $nilai }}</span>
                    </button>
                @endforeach
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($baris as $b)
                    @include('monitoring.partials.kartu', ['b' => $b])
                @endforeach
            </div>

            <p x-show="jumlah[saring] === 0" style="display: none"
                class="rounded-2xl border border-dashed border-brand-border bg-brand-surface p-8 text-center text-sm text-brand-muted dark:border-gray-800 dark:bg-gray-900">
                Tidak ada jadwal dengan status ini.
            </p>
        </div>

        {{-- ============ KETERANGAN ============ --}}
        <div class="mt-6 rounded-2xl border border-brand-border bg-brand-surface px-5 py-4 text-xs leading-relaxed text-brand-muted shadow-soft dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-2 font-semibold uppercase tracking-wide">Arti status</p>
            <ul class="grid gap-1.5 sm:grid-cols-2">
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-brand-500"></span><strong>Sedang berjalan</strong> &mdash; guru/pembina sudah scan QR, jadwal belum selesai.</li>
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-orange-500"></span><strong>Belum diakhiri</strong> &mdash; jadwal selesai, tombol Akhiri Sesi belum ditekan; bisa dibantu dengan tombol oranye.</li>
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-emerald-500"></span><strong>Selesai</strong> &mdash; sesi diakhiri; honor JP sudah dihitung.</li>
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-red-500"></span><strong>Bermasalah</strong> &mdash; lewat {{ $toleransi }} menit tanpa scan QR, atau jam sudah habis tanpa sesi.</li>
            </ul>
            <p class="mt-2">"Bantu Akhiri Sesi" mencatat waktu selesai sesuai jam selesai jadwal (atau jam sekarang bila jadwal belum selesai), menghitung JP dari panjang jadwal seperti saat guru mengakhiri sendiri, dan mencatat nama Anda beserta alasannya.</p>
        </div>

        {{-- Panel detail kartu kelas (dibuka lewat klik kartu). --}}
        @livewire('monitoring.detail-sesi')
    @endif

@endsection
