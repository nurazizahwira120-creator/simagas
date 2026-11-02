@extends('layouts.app')

@section('title', 'Absensi Kedatangan')

@section('content')

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 print:hidden">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Absensi Kedatangan</h1>
            <p class="mt-1 text-sm text-brand-muted line-clamp-2">
                {{ now()->translatedFormat('l, d F Y') }} &middot; hasil scan anak Anda di gerbang sekolah.
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
                {{-- Pemilih anak. Tautannya ke halaman INI (dulu ke Dashboard, sehingga
                     memilih anak lain malah memindahkan orang tua ke halaman lain).
                     Nama panjang dibatasi 14rem + title, dan barisnya bisa digeser. --}}
                <div class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1">
                    @foreach ($anakAnak as $a)
                        <a href="{{ route($panelPrefix . '.absensi-kedatangan', ['anak' => $a->id]) }}"
                            title="{{ $a->nama }}" @if ($a->id === $anak->id) aria-current="page" @endif
                            class="inline-flex min-h-11 max-w-[14rem] shrink-0 items-center rounded-full border px-4 py-2 text-sm font-medium transition-colors {{ $a->id === $anak->id ? 'border-brand-accent bg-brand-accent text-white shadow-soft' : 'border-brand-border bg-brand-surface text-brand-muted' }}">
                            <span class="truncate">{{ $a->nama }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            {{-- Kartu status yang SAMA dengan Beranda (components/kartu-status-anak).
                 Dulu kartu ini merah "Belum Absen" setiap kali belum ada catatan —
                 juga tengah malam dan di hari libur — dengan warna yang sama
                 seperti Alpa. Aturan barunya ada di App\Services\StatusKedatanganAnak. --}}
            <x-kartu-status-anak :status="$status" :anak="$anak" :kontak="$kontak" />

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
                                        {{-- Keterangan (alasan izin/sakit) dibungkus, BUKAN truncate:
                                             satu baris terpotong "Sakit demam, surat dokter..." membuat
                                             alasannya tidak pernah bisa dibaca utuh di HP. --}}
                                        <p class="break-words text-xs text-brand-muted [overflow-wrap:anywhere]">{{ $item->keterangan }}</p>
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
