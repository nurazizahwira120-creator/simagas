{{--
    BERANDA wali murid.

    Dirancang untuk HP orang tua: satu layar menjawab empat pertanyaan —
    anak sudah sampai? hari ini belajar apa? bulan ini rajin? ada kabar dari
    sekolah? — tanpa membuka menu. Datanya disusun App\Services\BerandaWaliMurid.

    Pindah halaman lewat bilah menu bawah (components/menu-bawah); anak yang
    dipilih di sini DIINGAT di halaman lain (App\Support\AnakTerpilih).
--}}
@extends('layouts.app')

@section('title', 'Beranda')

@section('content')

    <div class="mx-auto w-full max-w-3xl">

        <div class="mb-4">
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-white">
                Halo, {{ auth()->user()->name }}
            </h1>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">{{ now()->translatedFormat('l, d F Y') }}</p>
        </div>

        @if (! $anak)

            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-50 text-brand-muted dark:bg-white/[0.04]">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="font-semibold text-brand-ink dark:text-white">Belum ada anak yang tertaut ke akun Anda</p>
                <p class="max-w-sm text-sm text-brand-muted dark:text-brand-faint">
                    Hubungi Admin TU sekolah agar data anak Anda dihubungkan ke akun ini.
                    Sesudah itu, seluruh menu akan terisi.
                </p>
            </div>

        @else

            {{-- ============ PEMILIH ANAK ============
                 Hanya bila anaknya lebih dari satu. Bisa digeser ke samping
                 kalau namanya banyak; tinggi 44px supaya mudah diketuk. --}}
            @if ($anakAnak->count() > 1)
                <nav class="-mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Pilih anak">
                    @foreach ($anakAnak as $a)
                        @php
                            $aktif = $a->id === $anak->id;
                            $kata = preg_split('/\s+/', trim($a->nama)) ?: [$a->nama];
                            $inisial = mb_strtoupper(collect($kata)->take(2)->map(fn ($k) => mb_substr($k, 0, 1))->join(''));
                        @endphp
                        <a href="{{ route($panelPrefix . '.dashboard', ['anak' => $a->id]) }}"
                            title="{{ $a->nama }}" @if ($aktif) aria-current="page" @endif
                            class="inline-flex min-h-11 max-w-[15rem] shrink-0 items-center gap-2 rounded-full border py-1.5 pl-1.5 pr-4 text-sm font-semibold transition-colors {{ $aktif ? 'border-brand-500 bg-brand-500 text-white shadow-theme-sm' : 'border-gray-200 bg-brand-surface text-brand-muted hover:border-brand-300 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300' }}">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-extrabold {{ $aktif ? 'bg-white/25 text-white' : 'bg-gray-100 text-brand-muted dark:bg-white/[0.06] dark:text-gray-300' }}">{{ $inisial }}</span>
                            <span class="truncate">{{ $kata[0] }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif

            {{-- ============ STATUS HARI INI ============ --}}
            <x-kartu-status-anak :status="$status" :anak="$anak" :kontak="$kontak" />

            <div class="mt-4 grid gap-4 lg:grid-cols-2">

                {{-- ============ PELAJARAN HARI INI ============ --}}
                <section class="rounded-2xl border border-gray-200 bg-brand-surface p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex min-h-11 items-center justify-between gap-2">
                        <h2 class="font-bold text-brand-ink dark:text-white">Pelajaran hari ini</h2>
                        @if (Route::has($panelPrefix . '.pantauan-kbm') && $pelajaran !== [])
                            <a href="{{ route($panelPrefix . '.pantauan-kbm') }}" class="inline-flex min-h-11 items-center px-1 text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Rinci</a>
                        @endif
                    </div>

                    @if ($pelajaran === [])
                        <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                            {{ $libur ? 'Tidak ada pelajaran — hari ini libur.' : 'Tidak ada jadwal pelajaran untuk kelas ini hari ini.' }}
                        </p>
                    @else
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($pelajaran as $p)
                                <li class="flex items-center gap-3 py-2.5">
                                    <span class="w-12 shrink-0 text-sm font-bold tabular-nums text-brand-muted dark:text-brand-faint">{{ $p['jam'] }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-semibold text-brand-ink [overflow-wrap:anywhere] dark:text-white">{{ $p['mapel'] }}</span>
                                        @if ($p['guru'])
                                            <span class="block text-xs text-brand-muted dark:text-brand-faint">{{ $p['guru'] }}</span>
                                        @endif
                                    </span>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold {{ $p['kelas_status'] }}">{{ $p['status'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                {{-- ============ KEHADIRAN BULAN INI ============ --}}
                <section class="rounded-2xl border border-gray-200 bg-brand-surface p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex min-h-11 items-center justify-between gap-2">
                        <h2 class="font-bold text-brand-ink dark:text-white">Kehadiran {{ $bulan['bulan'] }}</h2>
                        @if (Route::has($panelPrefix . '.rekap-akademik'))
                            <a href="{{ route($panelPrefix . '.rekap-akademik') }}" class="inline-flex min-h-11 items-center px-1 text-sm font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400">Rinci</a>
                        @endif
                    </div>

                    @if ($bulan['persen'] === null)
                        <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Belum ada catatan kehadiran bulan ini.</p>
                    @else
                        <div class="mt-1 flex items-center gap-4">
                            {{-- Cincin persentase: conic-gradient memakai variabel warna
                                 tema, jadi ikut berganti di mode gelap. --}}
                            <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full"
                                style="background: conic-gradient(var(--color-brand-500) 0 {{ $bulan['persen'] }}%, var(--color-gray-200) {{ $bulan['persen'] }}% 100%)"
                                role="img" aria-label="Kehadiran {{ $bulan['persen'] }} persen">
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface text-sm font-extrabold text-brand-ink dark:bg-gray-900 dark:text-white">{{ $bulan['persen'] }}%</span>
                            </div>
                            <div class="text-sm text-brand-muted dark:text-brand-faint">
                                <p><strong class="text-brand-ink dark:text-white">{{ $bulan['hadir'] }} hadir</strong> dari {{ $bulan['total'] }} hari tercatat</p>
                                <p class="mt-0.5">{{ $bulan['sakit'] }} sakit &middot; {{ $bulan['izin'] }} izin &middot; {{ $bulan['alpa'] }} alpa</p>
                            </div>
                        </div>
                    @endif
                </section>
            </div>
        @endif

        {{-- ============ PENGUMUMAN TERBARU ============
             Isi LENGKAP dari tabel pengumuman, dilipat 3 baris dan dibuka di
             tempat — bukan lewat lonceng, karena lonceng hanya menampilkan
             notifikasi yang belum dibaca. --}}
        @if ($pengumuman)
            <section class="mt-4 rounded-2xl border border-gray-200 bg-brand-surface p-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900"
                x-data="{
                    lengkap: false,
                    adaLanjutan: false,
                    ukur() {
                        if (this.lengkap || ! this.$refs.teks) return;
                        this.adaLanjutan = this.$refs.teks.scrollHeight > this.$refs.teks.clientHeight + 2;
                    },
                }"
                x-init="ukur(); new ResizeObserver(() => ukur()).observe($refs.teks)">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-500/10 text-brand-600 dark:text-brand-400">
                        <x-icon name="bell" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold text-brand-muted dark:text-brand-faint">
                            Pengumuman sekolah &middot; {{ $pengumuman['waktu']->diffForHumans() }}
                            @if ($pengumuman['baru'])
                                <span class="ml-1 rounded-full bg-error-500 px-1.5 py-0.5 text-[10px] font-bold text-white">Baru</span>
                            @endif
                        </p>
                        <h2 class="mt-0.5 font-bold text-brand-ink [overflow-wrap:anywhere] dark:text-white">{{ $pengumuman['judul'] }}</h2>
                        <p x-ref="teks" :class="lengkap ? '' : 'line-clamp-3'"
                            class="mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-600 [overflow-wrap:anywhere] dark:text-gray-300">{{ \App\Support\TeksBertaut::html($pengumuman['isi'], 'font-medium text-brand-600 underline dark:text-brand-400') }}</p>
                        <button type="button" x-show="adaLanjutan || lengkap" x-cloak
                            x-on:click="lengkap = ! lengkap"
                            x-bind:aria-expanded="lengkap ? 'true' : 'false'"
                            x-text="lengkap ? 'Sembunyikan' : 'Baca selengkapnya'"
                            class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-brand-600 hover:text-brand-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-brand-400">
                            Baca selengkapnya
                        </button>
                    </div>
                </div>
            </section>
        @endif

    </div>

@endsection
