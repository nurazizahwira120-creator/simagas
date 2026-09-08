@extends('layouts.app')

@section('title', 'Absensi Kedatangan')

@section('content')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Absensi Kedatangan</h1>
            <p class="mt-1 text-sm text-brand-muted">
                Hasil scan QR anak Anda di gerbang sekolah — status hari ini dan riwayat
                bulan berjalan. Kehadiran per mata pelajaran ada di menu Pantauan KBM.
            </p>
        </div>
    </div>

    <div class="mx-auto flex w-full max-w-md flex-col gap-4">
        @if ($anakAnak->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-brand-muted">
                    Akun Anda belum ditautkan ke data siswa manapun. Hubungi wali kelas atau admin sekolah untuk menautkan akun ini ke data anak Anda.
                </p>
            </div>
        @else

            @if ($anakAnak->count() > 1)
                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    @foreach ($anakAnak as $a)
                        <a href="{{ route($panelPrefix . '.dashboard', ['anak' => $a->id]) }}"
                            class="shrink-0 rounded-full border px-4 py-2 text-sm font-medium transition-colors {{ $a->id === $anak->id ? 'border-brand-accent bg-brand-accent text-white shadow-soft' : 'border-brand-border bg-brand-surface text-brand-muted' }}">
                            {{ $a->nama }}
                        </a>
                    @endforeach
                </div>
            @endif

            @php
                $status = $absensiHariIni?->status;
                $kartu = match (true) {
                    $status === \App\Enums\AbsensiStatus::Hadir => [
                        'bg' => 'bg-emerald-600',
                        'icon' => 'check-circle',
                        'label' => 'Hadir',
                        // jam_masuk bisa NULL kalau kehadiran diinput manual oleh
                        // wali kelas (bukan hasil scan QR), jadi wajib dicek dulu.
                        'sub' => $absensiHariIni->jam_masuk
                            ? 'Tercatat masuk pukul ' . $absensiHariIni->jam_masuk->format('H:i')
                            : 'Ditandai hadir oleh wali kelas',
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

            <div class="rounded-3xl {{ $kartu['bg'] }} p-6 text-white shadow-soft">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="text-sm font-medium text-white/80">{{ $anak->nama }} &middot; {{ $anak->kelas?->nama_kelas ?? '-' }}</p>
                        <p class="mt-1 text-xs text-white/70">{{ now()->translatedFormat('l, d F Y') }}</p>
                    </div>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="{{ $kartu['icon'] }}" class="h-6 w-6" />
                    </span>
                </div>
                <p class="mt-5 text-4xl font-bold">{{ $kartu['label'] }}</p>
                <p class="mt-1 text-sm text-white/85">{{ $kartu['sub'] }}</p>
            </div>

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

        @endif

    
    </div>

@endsection
