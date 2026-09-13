@extends('layouts.app')

@section('title', 'Live Monitoring KBM')

@section('content')

    {{-- ============ KEPALA HALAMAN ============ --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">Live Monitoring KBM</h1>

                {{-- Lencana LIVE.

                     Titik merahnya yang berdenyut (animate-ping), BUKAN
                     seluruh lencananya. Tulisan yang ikut berkedip lebih
                     sulit dibaca, dan pada halaman yang dibiarkan terbuka di
                     layar ruang kepala sekolah seharian, kedipan sebesar itu
                     berubah menjadi gangguan.

                     motion-reduce:animate-none — untuk sebagian orang gerak
                     berulang memicu pusing; titik merahnya tetap ada, hanya
                     tidak bergerak. --}}
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

        {{-- Muat ulang manual, bukan polling otomatis.

             Halaman ini SENGAJA tidak menyegarkan dirinya sendiri. Auto-refresh
             tiap beberapa detik berarti satu query penuh ke database setiap
             kali, dari halaman yang biasanya dibiarkan terbuka berjam-jam di
             hosting bersama. Kepala sekolah membukanya saat ingin tahu, dan
             saat itulah datanya diambil. --}}
        <a href="{{ url()->current() }}"
            class="kartu-angkat inline-flex items-center gap-2 rounded-lg border border-brand-border bg-brand-surface px-4 py-2 text-sm font-medium text-brand-ink dark:border-gray-800 dark:bg-gray-900 dark:text-white">
            <x-icon name="swap" class="h-4 w-4" />
            Perbarui
        </a>
    </div>

    {{-- ============ RINGKASAN ============ --}}
    @if ($ringkas['total'] > 0)
        <div class="tampil-berurutan mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @php
                $kartuRingkas = [
                    ['label' => 'Kelas berlangsung', 'nilai' => $ringkas['total'],      'warna' => 'text-brand-ink dark:text-white', 'ikon' => 'academic-cap'],
                    ['label' => 'Berjalan normal',   'nilai' => $ringkas['aman'],       'warna' => 'text-emerald-600 dark:text-emerald-400', 'ikon' => 'check-circle'],
                    ['label' => 'Perlu dilihat',     'nilai' => $ringkas['perhatian'],  'warna' => 'text-amber-600 dark:text-amber-400', 'ikon' => 'exclamation-triangle'],
                    ['label' => 'Belum ada jurnal',  'nilai' => $ringkas['kosong'],     'warna' => 'text-brand-danger-text', 'ikon' => 'x-circle'],
                ];
            @endphp

            @foreach ($kartuRingkas as $k)
                <div class="min-w-0 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-soft dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-brand-muted">
                        <x-icon name="{{ $k['ikon'] }}" class="h-3.5 w-3.5 shrink-0" />
                        <span class="truncate">{{ $k['label'] }}</span>
                    </span>
                    <x-angka-naik :nilai="$k['nilai']" class="mt-1.5 block text-3xl font-bold {{ $k['warna'] }}" />
                </div>
            @endforeach
        </div>
    @endif

    {{-- ============ GRID KARTU KELAS ============ --}}
    @if (empty($baris))

        <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-12 text-center dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                <x-icon name="clock" class="h-6 w-6" />
            </span>
            <p class="font-semibold text-brand-ink dark:text-white">Tidak ada kelas yang sedang berlangsung</p>
            <p class="max-w-md text-sm leading-relaxed text-brand-muted">
                Pukul {{ $sekarang->format('H:i') }} hari {{ $hariIni->label() }} tidak ada jam pelajaran
                yang jatuh pada rentang ini. Halaman akan terisi sendiri begitu jam berikutnya dimulai.
            </p>
        </div>

    @else

        <div class="tampil-berurutan grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($baris as $b)
                @php
                    $j = $b['jadwal'];
                    $g = $b['gaya'];
                @endphp

                {{-- Warna status disampaikan lewat TIGA hal sekaligus: warna
                     bingkai, pita di tepi atas, DAN lencana bertuliskan
                     artinya. Mengandalkan warna saja menutup halaman ini bagi
                     pembaca yang buta warna — dan merah vs hijau justru
                     pasangan yang paling sering tidak terbedakan. --}}
                <article class="kartu-angkat relative min-w-0 overflow-hidden rounded-2xl border-2 bg-brand-surface shadow-soft dark:bg-gray-900 {{ $g['kartu'] }}">

                    <div class="h-1.5 w-full {{ $g['pita'] }}"></div>

                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate text-lg font-bold text-brand-ink dark:text-white">{{ $j->kelas?->nama_kelas ?? 'Kelas —' }}</h3>
                                <p class="mt-0.5 truncate text-sm font-medium text-brand-accent-text">{{ $j->mata_pelajaran }}</p>
                            </div>

                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $g['chip'] }}">
                                <x-icon name="{{ $g['ikon'] }}" class="h-3 w-3" />
                                {{ $g['label'] }}
                            </span>
                        </div>

                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex min-w-0 items-center gap-2">
                                <dt class="sr-only">Guru pengampu</dt>
                                <x-icon name="briefcase" class="h-4 w-4 shrink-0 text-brand-muted" />
                                <dd class="truncate text-brand-ink dark:text-gray-200">{{ $j->guru?->nama ?? '— belum ditetapkan —' }}</dd>
                            </div>

                            <div class="flex min-w-0 items-center gap-2">
                                <dt class="sr-only">Waktu</dt>
                                <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-muted" />
                                {{-- rentangJam() dari model, BUKAN memotong string sendiri.

                                     Kolom jam_mulai di-cast ke Carbon (lihat
                                     JadwalPelajaran::casts), jadi memperlakukannya
                                     sebagai teks '07:00:00' dan memotong 5 huruf
                                     pertama menghasilkan '2026-' — tanggalnya,
                                     bukan jamnya. Itu sudah sempat terjadi dan
                                     hanya ketahuan saat halamannya dilihat. --}}
                                <dd class="tabular-nums text-brand-muted">
                                    {{ $j->rentangJam() }}
                                    <span class="text-xs">&middot; berjalan {{ $b['menit_berjalan'] }} menit</span>
                                </dd>
                            </div>

                            @if ($j->ruangan)
                                <div class="flex min-w-0 items-center gap-2">
                                    <dt class="sr-only">Ruangan</dt>
                                    <x-icon name="building" class="h-4 w-4 shrink-0 text-brand-muted" />
                                    <dd class="truncate text-brand-muted">{{ $j->ruangan }}</dd>
                                </div>
                            @endif
                        </dl>

                        @if ($b['status'] === 'kosong')
                            <p class="mt-4 rounded-lg bg-red-500/10 px-3 py-2 text-xs leading-relaxed text-brand-danger-text">
                                Sudah {{ $b['menit_berjalan'] }} menit berjalan dan belum ada jurnal yang tersimpan
                                untuk jam ini.
                            </p>
                        @elseif ($b['status'] === 'perhatian')
                            <p class="mt-4 rounded-lg bg-amber-500/10 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:text-amber-400">
                                Jurnal sudah diisi &mdash; ada {{ $b['jumlah_bolos'] }} siswa berstatus alpa atau bolos.
                            </p>
                        @elseif ($b['status'] === 'menunggu')
                            <p class="mt-4 rounded-lg bg-brand-surface-muted px-3 py-2 text-xs leading-relaxed text-brand-muted">
                                Baru dimulai. Belum dihitung terlambat sebelum {{ $toleransi }} menit.
                            </p>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ============ KETERANGAN WARNA ============
             Ditaruh di bawah, bukan di atas: pembaca yang sudah terbiasa tidak
             perlu melewatinya setiap kali membuka halaman, sementara yang baru
             tetap menemukannya tanpa mencari. --}}
        <div class="mt-6 rounded-2xl border border-brand-border bg-brand-surface px-5 py-4 text-xs leading-relaxed text-brand-muted shadow-soft dark:border-gray-800 dark:bg-gray-900">
            <p class="mb-2 font-semibold uppercase tracking-wide">Arti warna</p>
            <ul class="grid gap-1.5 sm:grid-cols-2">
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-emerald-500"></span><strong>Hijau</strong> &mdash; guru sudah mengisi jurnal &amp; absensi jam ini.</li>
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-amber-500"></span><strong>Kuning</strong> &mdash; jurnal terisi, tapi ada siswa alpa/bolos.</li>
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-red-500"></span><strong>Merah</strong> &mdash; lewat {{ $toleransi }} menit dan jurnalnya masih kosong.</li>
                <li><span class="mr-1.5 inline-block h-2 w-2 rounded-full bg-gray-400"></span><strong>Abu</strong> &mdash; jam baru dimulai, masih dalam masa toleransi.</li>
            </ul>
        </div>

    @endif

@endsection
