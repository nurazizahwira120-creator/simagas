@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    {{-- ============ BANNER APRESIASI EMAS ============
         Paling atas, sebelum judul: kabar baik tidak seharusnya perlu
         di-scroll. Komponennya tidak menggambar apa pun kalau tidak ada
         penghargaan, jadi tidak perlu dibungkus @if.

         Halaman ini dipakai bersama oleh guru, staff, dan admin TU. Variabel
         $penghargaan selalu dikirim controller (null kalau tidak ada), tapi
         "?? null" tetap dipasang supaya halaman tidak pecah kalau suatu saat
         ada pemanggil lain yang lupa mengirimnya. --}}
    <x-banner-apresiasi :penghargaan="$penghargaan ?? null" />

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Dashboard</h1>
            <p class="mt-1 text-sm text-brand-muted">Kehadiran Anda hari ini dan riwayat terakhir.</p>
        </div>
    </div>

    {{-- ============ SUSUNAN DUA KOLOM MULAI DARI LAYAR BESAR ============
         Sebelumnya seluruh isi halaman ini dikurung max-w-md — satu kolom
         selebar 448px di tengah layar. Di HP itu tepat; di laptop 1280px
         hasilnya satu pita sempit dengan ruang kosong 800px di kanannya,
         sementara daftar riwayat di bawahnya harus di-scroll.

         Mulai lg: kolom kiri memuat yang perlu DITINDAK (kartu absensi,
         status hari ini, pintasan jadwal), kolom kanan memuat yang perlu
         DIBACA (riwayat). Pembagiannya mengikuti tugas, bukan sekadar
         memenuhi ruang — kolom kiri lebih sempit karena isinya ringkas,
         kolom kanan lebih lebar karena berisi daftar tanggal panjang.

         Di bawah lg keduanya kembali menumpuk persis seperti semula. --}}
    <div class="mx-auto grid w-full gap-4 lg:items-start lg:gap-6 {{ $pegawai ? 'max-w-md lg:max-w-5xl lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]' : 'max-w-md' }}">

        {{-- ---------- KOLOM KIRI: yang perlu ditindak ---------- --}}
        <div class="tampil-berurutan flex flex-col gap-4">
        {{-- Absensi mandiri --}}
        @include('partials.kartu-kehadiran-hari-ini')

        {{-- Pintasan ke Jadwal Pelajaran — halaman dashboard ini dipakai
             bersama oleh guru, staff, dan admin TU, tapi rute
             'jadwal-pelajaran' TIDAK didaftarkan untuk admin_tu. Pakai
             Route::has() alih-alih mengecek enum role secara manual:
             kondisinya jadi mengikuti pendaftaran rute itu sendiri, sehingga
             tidak bisa jatuh ke RouteNotFoundException kalau daftar role yang
             punya menu ini berubah nanti. --}}
        @if (Route::has($panelPrefix . '.jadwal-pelajaran'))
            <a href="{{ route($panelPrefix . '.jadwal-pelajaran') }}"
                class="kartu-angkat flex items-center justify-between gap-3 rounded-2xl border border-brand-border bg-brand-surface px-5 py-4 shadow-soft active:bg-brand-surface-muted">
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
        @endif

        @if (! $pegawai)
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-brand-muted">
                    Akun Anda belum ditautkan ke data pegawai manapun. Hubungi admin sekolah untuk menautkan akun ini ke data kepegawaian Anda.
                </p>
            </div>
        @else

            @php
                $status = $absensiHariIni?->status;
                $kartu = match (true) {
                    $status === \App\Enums\AbsensiStatus::Hadir => [
                        'bg' => 'bg-emerald-600',
                        'icon' => 'check-circle',
                        'label' => 'Hadir',
                        // jam_masuk bisa NULL kalau kehadiran diinput manual
                        // (bukan hasil scan QR di pos piket).
                        'sub' => $absensiHariIni->jam_masuk
                            ? 'Tercatat masuk pukul ' . $absensiHariIni->jam_masuk->format('H:i')
                            : 'Ditandai hadir secara manual',
                    ],
                    $status === \App\Enums\AbsensiStatus::Izin => [
                        'bg' => 'bg-amber-500',
                        'icon' => 'exclamation-triangle',
                        'label' => 'Izin',
                        'sub' => $absensiHariIni->keterangan ?: 'Tidak masuk dengan izin',
                    ],
                    $status === \App\Enums\AbsensiStatus::Sakit => [
                        'bg' => 'bg-sky-600',
                        'icon' => 'exclamation-triangle',
                        'label' => 'Sakit',
                        'sub' => $absensiHariIni->keterangan ?: 'Tidak masuk karena sakit',
                    ],
                    $status === \App\Enums\AbsensiStatus::Alpha => [
                        'bg' => 'bg-brand-danger',
                        'icon' => 'x-circle',
                        'label' => 'Alpa',
                        'sub' => 'Tidak ada keterangan kehadiran hari ini',
                    ],
                    default => [
                        'bg' => 'bg-brand-danger',
                        'icon' => 'x-circle',
                        'label' => 'Belum Absen',
                        'sub' => 'Belum ada catatan kehadiran hari ini',
                    ],
                };
            @endphp

            @php
                // Denyut riak HANYA saat kehadiran hari ini belum tercatat —
                // satu-satunya keadaan di kartu ini yang menuntut tindakan.
                // Kalau dipasang juga pada status 'Hadir', gerakannya berhenti
                // berarti apa-apa dan tinggal menjadi hiasan yang berkedip.
                $perluTindakan = $status === null;
            @endphp

            {{-- kilau: satu sapuan cahaya melintas 0,55 detik setelah halaman
                 siap. Sekali saja — lihat alasannya di .kilau pada app.css. --}}
            <div class="kilau rounded-3xl {{ $kartu['bg'] }} p-6 text-white shadow-soft">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ $pegawai->nama }} &middot; {{ $pegawai->jabatan }}</p>
                        <p class="mt-1 text-xs text-white/70">{{ now()->translatedFormat('l, d F Y') }}</p>
                    </div>
                    <span class="{{ $perluTindakan ? 'denyut' : '' }} flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="{{ $kartu['icon'] }}" class="h-6 w-6" />
                    </span>
                </div>
                <p class="mt-5 text-4xl font-bold">{{ $kartu['label'] }}</p>
                <p class="mt-1 text-sm text-white/85">{{ $kartu['sub'] }}</p>
            </div>
        @endif
        </div>

        {{-- ---------- KOLOM KANAN: yang perlu dibaca ----------
             Hanya dirender kalau akunnya tertaut ke data pegawai. Tanpa
             tautan itu tidak ada riwayat kehadiran yang bisa ditampilkan,
             dan kolom kosong di sebelah pemberitahuan hanya membuat
             halamannya terlihat rusak. --}}
        @if ($pegawai)
        <div class="muncul" style="animation-delay: 160ms">
            <section>
                <h2 class="mb-2 flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-brand-muted">
                    <x-icon name="clock" class="h-3.5 w-3.5" />
                    Riwayat {{ now()->translatedFormat('F Y') }}
                </h2>

                @if ($riwayat->isEmpty())
                    <div class="rounded-2xl border border-dashed border-brand-border bg-brand-surface px-4 py-6 text-center text-sm text-brand-muted">
                        Belum ada riwayat kehadiran bulan ini.
                    </div>
                @else
                    <ul class="divide-y divide-brand-border rounded-2xl border border-brand-border bg-brand-surface px-4 shadow-soft">
                        @foreach ($riwayat as $item)
                            @php
                                $badge = match ($item->status) {
                                    \App\Enums\AbsensiStatus::Hadir => ['chip' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300', 'icon' => 'check-circle'],
                                    \App\Enums\AbsensiStatus::Izin => ['chip' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-300', 'icon' => 'exclamation-triangle'],
                                    \App\Enums\AbsensiStatus::Sakit => ['chip' => 'bg-sky-500/10 text-sky-700 dark:text-sky-400 border-sky-300', 'icon' => 'exclamation-triangle'],
                                    \App\Enums\AbsensiStatus::Alpha => ['chip' => 'bg-red-500/10 text-brand-danger-text border-red-300', 'icon' => 'x-circle'],
                                };
                            @endphp
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">{{ $item->tanggal->translatedFormat('l, d F Y') }}</p>
                                    @if ($item->status === \App\Enums\AbsensiStatus::Hadir && $item->jam_masuk)
                                        <p class="text-xs text-brand-muted">Masuk pukul {{ $item->jam_masuk->format('H:i') }}</p>
                                    @elseif ($item->keterangan)
                                        <p class="truncate text-xs text-brand-muted">{{ $item->keterangan }}</p>
                                    @endif
                                </div>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-3 py-1 text-xs font-medium {{ $badge['chip'] }}">
                                    <x-icon name="{{ $badge['icon'] }}" class="h-3.5 w-3.5" />
                                    {{ $item->status->shortLabel() }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
        @endif

    </div>

@endsection
