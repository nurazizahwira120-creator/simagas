@extends('layouts.app')

@section('title', 'Dashboard Wali Kelas')

@section('content')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Dashboard Wali Kelas</h1>
            <p class="mt-1 text-sm text-brand-muted">Validasi kehadiran kelas yang Anda ampu.</p>
        </div>
    </div>

    <div class="mx-auto w-full max-w-4xl">
        {{-- Absensi mandiri --}}
        <div class="muncul mb-5">
            @include('partials.kartu-kehadiran-hari-ini')
        </div>

        {{-- Pintasan ke jadwal pelajaran --}}
        <a href="{{ route($panelPrefix . '.jadwal-pelajaran') }}"
            style="animation-delay: 60ms"
            class="kartu-angkat muncul mb-5 flex items-center justify-between gap-3 rounded-2xl border border-brand-border bg-brand-surface px-5 py-4 shadow-soft hover:bg-brand-surface-muted">
            <span class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-accent-soft text-brand-accent-text">
                    <x-icon name="calendar" class="h-6 w-6" />
                </span>
                <span class="min-w-0">
                    <span class="block text-sm font-semibold">Jadwal Pelajaran</span>
                    <span class="block text-xs text-brand-muted">Lihat jadwal Anda Senin&ndash;Jum'at</span>
                </span>
            </span>
            <x-icon name="arrow-right" class="h-4 w-4 shrink-0 text-brand-muted" />
        </a>

        @unless ($kelas)
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="max-w-sm text-sm text-brand-muted">
                    Anda belum ditugaskan sebagai wali kelas mana pun. Hubungi kepala sekolah/admin untuk menautkan akun Anda ke sebuah kelas.
                </p>
            </div>
        @else
            <div class="muncul mb-5 flex flex-wrap items-end justify-between gap-3" style="animation-delay: 120ms">
                <div>
                    <h1 class="text-xl font-bold">{{ $kelas->nama_kelas }}</h1>
                    <p class="mt-0.5 flex items-center gap-1.5 text-sm text-brand-muted">
                        <x-icon name="calendar" class="h-3.5 w-3.5" />
                        {{ now()->translatedFormat('l, d F Y') }} &middot; {{ $kelas->siswa->count() }} siswa
                    </p>
                </div>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    @foreach ($statusOptions as $opsi)
                        <span class="rounded-full border border-brand-border bg-brand-surface px-2.5 py-1 font-medium text-brand-muted">
                            {{ $opsi->shortLabel() }}: {{ $kelas->siswa->filter(fn ($s) => $s->absensi->first()?->status === $opsi)->count() }}
                        </span>
                    @endforeach
                </div>
            </div>

            @if (session('status'))
                <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-emerald-300 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-800 dark:text-emerald-400">
                    <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 flex items-start gap-2.5 rounded-xl border border-red-300 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-800 dark:text-red-400">
                    <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                    <ul class="list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route($panelPrefix . '.absensi.simpan') }}">
                @csrf

                {{-- ================================================================
                     TABEL DI LAYAR BESAR, DAFTAR BERTUMPUK DI HP

                     Sama persis dengan perbaikan di Jurnal & Absen Kelas.
                     Terukur di layar 360px: tabelnya 640px di dalam wadah
                     326px, dan kolom "Status Kehadiran" mulai pada x=488 —
                     128px di luar layar. Wali kelas harus menggeser tabel ke
                     samping dulu untuk menemukan tombol yang harus ia tekan,
                     dan sesudah digeser tombolnya masih terpotong di tepi.

                     Di bawah md: elemen tabelnya dijadikan block (max-md:*)
                     sehingga tiap siswa jadi satu kartu bertumpuk. SATU
                     susunan DOM, bukan dua yang saling disembunyikan — dua
                     susunan berarti dua <input type="radio"> dengan name yang
                     sama untuk satu siswa, dan radio yang disembunyikan CSS
                     tetap ikut terkirim saat form disubmit.
                     ================================================================ --}}
                {{-- Yang beranimasi adalah WADAH tabelnya, bukan tiap baris.

                     Menganimasikan setiap <tr> memang terlihat lebih hidup,
                     tapi transform pada elemen table-row menciptakan
                     containing block baru — dan tombol Simpan di bawah tabel
                     ini position:sticky, yang akan ikut terpengaruh. Satu
                     kelas bisa berisi 30 siswa; 30 baris beranimasi juga
                     berarti 30 lapisan compositor sekaligus di HP guru. --}}
                <div class="muncul overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft"
                    style="animation-delay: 180ms">
                    <table class="w-full text-left text-sm max-md:block md:min-w-[640px]">
                        <thead class="max-md:hidden">
                            <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted">
                                <th class="px-4 py-3 font-medium">NIS</th>
                                <th class="px-4 py-3 font-medium">Nama</th>
                                <th class="px-4 py-3 font-medium">Status Kehadiran</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border max-md:block">
                            @forelse ($kelas->siswa as $siswa)
                                @php
                                    $absensiHariIni = $siswa->absensi->first();
                                @endphp
                                <tr class="hover:bg-brand-surface-muted/60 max-md:block max-md:px-4 max-md:py-4">
                                    {{-- Di HP kolom NIS disembunyikan dan ditampilkan
                                         ulang di bawah nama, supaya barisnya tidak
                                         menyisakan sel kosong yang aneh. --}}
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-brand-muted max-md:hidden">{{ $siswa->nis }}</td>
                                    <td class="px-4 py-3 font-medium max-md:block max-md:p-0">
                                        {{ $siswa->nama }}
                                        <span class="hidden font-mono text-xs font-normal text-brand-muted max-md:block">{{ $siswa->nis }}</span>
                                    </td>
                                    <td class="px-4 py-3 max-md:block max-md:px-0 max-md:pb-0 max-md:pt-3">
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach ($statusOptions as $opsi)
                                                @php
                                                    $inputId = "status-{$siswa->id}-{$opsi->value}";
                                                @endphp
                                                {{--
                                                    Radio asli disembunyikan (sr-only) tapi tetap ada
                                                    demi aksesibilitas & keyboard. Tampilannya diambil
                                                    alih <span> di sebelahnya lewat peer-checked:.
                                                    Sengaja pakai "peer" (selector sibling "~") dan
                                                    BUKAN "has-[:checked]" — :has() belum didukung
                                                    browser HP lawas, yang bikin pilihan tampak tidak
                                                    ter-highlight sama sekali di HP guru.
                                                --}}
                                                <label for="{{ $inputId }}" class="cursor-pointer select-none">
                                                    <input
                                                        type="radio"
                                                        id="{{ $inputId }}"
                                                        name="absensi[{{ $siswa->id }}]"
                                                        value="{{ $opsi->value }}"
                                                        class="peer sr-only"
                                                        @checked($absensiHariIni?->status === $opsi)
                                                        @if ($loop->first) required @endif
                                                    >
                                                    <span class="block rounded-full border border-brand-border px-3 py-1 text-xs font-medium text-brand-muted transition-colors peer-checked:border-brand-accent peer-checked:bg-brand-accent peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent/40">
                                                        {{ $opsi->shortLabel() }}
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr class="max-md:block">
                                    <td colspan="3" class="px-4 py-8 text-center text-brand-muted max-md:block">
                                        Belum ada siswa terdaftar di kelas ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($kelas->siswa->isNotEmpty())
                    <div class="sticky bottom-0 mt-4 flex justify-end border-t border-brand-border bg-brand-bg/90 py-3 backdrop-blur">
                        <button type="submit" class="kartu-angkat inline-flex items-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white shadow-soft hover:bg-brand-accent-dark">
                            <x-icon name="check-circle" class="h-5 w-5" />
                            Simpan Absensi
                        </button>
                    </div>
                @endif
            </form>
        @endunless

    
    </div>

@endsection
