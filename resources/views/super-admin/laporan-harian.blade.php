@extends('layouts.app')

@section('title', 'Laporan Absensi Harian')

@section('content')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 print:hidden">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Laporan Absensi Harian</h1>
            <p class="mt-1 text-sm text-brand-muted">Kehadiran siswa dan pegawai untuk satu tanggal.</p>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <form method="GET" action="{{ route($panelPrefix . '.laporan-harian') }}" class="flex items-end gap-2">
                <div>
                    <label for="tanggal" class="mb-1.5 block text-xs font-semibold text-brand-muted">Tanggal</label>
                    <input id="tanggal" name="tanggal" type="date" value="{{ $tanggal->format('Y-m-d') }}"
                        class="rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600">
                    <x-icon name="funnel" class="h-4 w-4" />
                    Tampilkan
                </button>
            </form>

            <button type="button" onclick="window.print()"
                class="inline-flex items-center gap-2 rounded-xl bg-brand-surface px-4 py-2.5 text-sm font-semibold text-brand-muted ring-1 ring-brand-border hover:text-brand-ink">
                <x-icon name="printer" class="h-4 w-4" />
                Cetak
            </button>
        </div>
    </div>

    {{-- Kop cetak --}}
    <div class="hidden print:mb-4 print:block">
        <p class="text-base font-bold">SMK Islam Assya'roniyyah</p>
        <p class="text-sm">Laporan Absensi Harian &middot; {{ $tanggal->translatedFormat('l, d F Y') }}</p>
        <hr class="mt-3">
    </div>

    <p class="mb-5 text-sm font-semibold text-brand-muted">{{ $tanggal->translatedFormat('l, d F Y') }}</p>

    @php
        $panel = [
            ['judul' => 'Siswa', 'icon' => 'identification', 'data' => $siswa, 'batas' => $batasSiswa, 'kolomNomor' => 'NIS', 'kolomGrup' => 'Kelas'],
            ['judul' => 'Pegawai', 'icon' => 'briefcase', 'data' => $pegawai, 'batas' => $batasPegawai, 'kolomNomor' => 'NIP', 'kolomGrup' => 'Jabatan'],
        ];
    @endphp

    <div class="space-y-6">
        @foreach ($panel as $bagian)
            @php
                $d = $bagian['data'];
            @endphp
            <section class="overflow-hidden rounded-2xl bg-brand-surface ring-1 ring-brand-border">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                            <x-icon name="{{ $bagian['icon'] }}" class="h-5 w-5" />
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-brand-ink">{{ $bagian['judul'] }}</h2>
                            <p class="text-xs text-brand-muted">Batas terlambat pukul {{ $bagian['batas'] }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200">{{ $d['hadir'] }} hadir</span>
                        <span class="rounded-full bg-brand-surface-muted px-3 py-1 text-brand-muted ring-1 ring-brand-border">{{ $d['belum'] }} belum absen</span>
                        <span class="rounded-full bg-brand-500/10 px-3 py-1 text-brand-accent-text dark:text-brand-accent-text ring-1 ring-brand-200">{{ $d['total'] }} total</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[680px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-brand-border text-[11px] uppercase tracking-wide text-brand-faint">
                                <th class="px-6 py-3 font-semibold">Nama</th>
                                <th class="px-6 py-3 font-semibold">{{ $bagian['kolomNomor'] }}</th>
                                <th class="px-6 py-3 font-semibold">{{ $bagian['kolomGrup'] }}</th>
                                <th class="px-6 py-3 font-semibold">Jam Masuk</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            @forelse ($d['baris'] as $baris)
                                @php
                                    $a = $baris['absensi'];
                                    $jam = $a?->jam_masuk?->format('H:i');
                                    // Terlambat dihitung dari batas jam di menu Pengaturan.
                                    $terlambat = $a
                                        && $a->status === \App\Enums\AbsensiStatus::Hadir
                                        && $jam !== null
                                        && $jam > $bagian['batas'];

                                    $chip = match (true) {
                                        $a === null => ['Belum Absen', 'bg-brand-surface-muted text-brand-muted ring-brand-border'],
                                        $terlambat => ['Terlambat', 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-amber-200'],
                                        $a->status === \App\Enums\AbsensiStatus::Hadir => ['Hadir', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 ring-emerald-200'],
                                        $a->status === \App\Enums\AbsensiStatus::Izin => ['Izin', 'bg-sky-500/10 text-sky-700 dark:text-sky-400 ring-sky-200'],
                                        $a->status === \App\Enums\AbsensiStatus::Sakit => ['Sakit', 'bg-violet-50 text-violet-700 ring-violet-200'],
                                        default => ['Alpa', 'bg-rose-500/10 text-rose-700 dark:text-rose-400 ring-rose-200'],
                                    };
                                @endphp
                                <tr class="hover:bg-brand-surface-muted/60 print:hover:bg-transparent">
                                    <td class="px-6 py-3.5 font-semibold text-brand-ink">{{ $baris['nama'] }}</td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-brand-muted">{{ $baris['nomor'] }}</td>
                                    <td class="px-6 py-3.5 text-brand-muted">{{ $baris['grup'] }}</td>
                                    <td class="px-6 py-3.5 font-mono text-xs text-brand-muted">{{ $jam ?? '—' }}</td>
                                    <td class="px-6 py-3.5">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $chip[1] }}">
                                            {{ $chip[0] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-brand-faint">
                                        Belum ada data {{ Str::lower($bagian['judul']) }}.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
