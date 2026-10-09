@extends('layouts.app')

@section('title', 'Rekap Absensi Ekskul')

{{--
    Rekap Absensi Ekskul per bulan — App\Http\Controllers\RekapEkskulController.
    Dua tampilan: RINGKASAN (semua ekskul, Kepsek/Admin) dan RINCIAN (satu
    ekskul: per anggota + matriks tanggal). Angkanya dari
    App\Services\RekapEkskulBulanan, sama persis dengan PDF.
--}}
@php
    use App\Enums\AbsensiStatus;

    $kodeStatus = [
        AbsensiStatus::Hadir->value => ['H', 'bg-success-500/10 text-success-700 dark:text-success-400'],
        AbsensiStatus::Izin->value => ['I', 'bg-warning-500/10 text-warning-700 dark:text-warning-400'],
        AbsensiStatus::Sakit->value => ['S', 'bg-brand-500/10 text-brand-accent-text'],
        AbsensiStatus::Alpha->value => ['A', 'bg-error-500/10 text-error-700 dark:text-error-400'],
    ];
    $rute = $panelPrefix . '.rekap-ekskul';
    $param = array_filter(['bulan' => $bulan->format('Y-m'), 'ekskul' => $ekskulTerpilih?->id]);
    $kartu = 'rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900';
@endphp

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Rekap Absensi Ekskul</h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted line-clamp-2">
            Kehadiran anggota dan keterlaksanaan sesi ekskul selama satu bulan. Bisa diunduh PDF.
        </p>
    </div>

    {{-- ============ SARINGAN ============ --}}
    <form method="GET" action="{{ route($rute) }}" class="mb-6 flex flex-wrap items-end gap-3 {{ $kartu }} p-4">
        <label class="block">
            <span class="mb-1.5 block text-xs font-medium text-brand-muted">Bulan</span>
            <input type="month" name="bulan" value="{{ $bulan->format('Y-m') }}" max="{{ now()->format('Y-m') }}"
                class="h-10 rounded-lg border border-brand-border bg-transparent px-3 text-sm focus:border-brand-accent focus:outline-none dark:text-white">
        </label>
        <label class="block w-full sm:w-auto">
            <span class="mb-1.5 block text-xs font-medium text-brand-muted">Ekskul</span>
            <select name="ekskul" class="h-10 w-full rounded-lg border border-brand-border bg-transparent px-3 text-sm focus:border-brand-accent focus:outline-none sm:w-64 dark:bg-gray-900 dark:text-white">
                @if ($semua || $daftarEkskul->count() > 1)
                    <option value="">Semua ekskul (ringkasan)</option>
                @endif
                @foreach ($daftarEkskul as $e)
                    <option value="{{ $e->id }}" @selected($ekskulTerpilih?->id === $e->id)>{{ $e->nama_ekskul }}</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="inline-flex h-10 items-center gap-1.5 rounded-lg bg-brand-accent px-4 text-sm font-semibold text-white hover:bg-brand-accent-dark">
            <x-icon name="funnel" class="h-4 w-4" />
            Tampilkan
        </button>
        <div class="flex flex-wrap gap-2 sm:ml-auto">
            <a href="{{ route($rute . '.unduh', $param + ['lihat' => 1]) }}" target="_blank" rel="noopener"
                class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-brand-border px-4 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted dark:text-white">
                <x-icon name="eye" class="h-4 w-4" /> Lihat PDF
            </a>
            <a href="{{ route($rute . '.unduh', $param) }}"
                class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-brand-border px-4 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted dark:text-white">
                <x-icon name="download" class="h-4 w-4" /> Unduh PDF
            </a>
        </div>
    </form>

    @if ($ringkasan)

        {{-- ============ RINGKASAN SEMUA EKSKUL ============ --}}
        <div class="mb-4 grid grid-cols-3 gap-3">
            @foreach ([['Ekskul', $ringkasan['total']['ekskul']], ['Pertemuan terjadwal', $ringkasan['total']['terjadwal']], ['Sesi terlaksana', $ringkasan['total']['terlaksana']]] as [$judul, $nilai])
                <div class="{{ $kartu }} p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-muted sm:text-xs">{{ $judul }}</p>
                    <p class="mt-1 text-2xl font-extrabold text-brand-ink dark:text-white">{{ $nilai }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-x-auto {{ $kartu }}">
            <table class="w-full min-w-[720px] text-left text-sm">
                <thead class="bg-brand-surface-muted text-xs uppercase text-brand-muted">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Ekskul</th>
                        <th class="px-3 py-3 font-semibold">Pembina</th>
                        <th class="px-3 py-3 text-center font-semibold">Anggota</th>
                        <th class="px-3 py-3 text-center font-semibold">Terjadwal</th>
                        <th class="px-3 py-3 text-center font-semibold">Terlaksana</th>
                        <th class="px-3 py-3 text-center font-semibold">% Hadir</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border dark:divide-gray-800">
                    @forelse ($ringkasan['baris'] as $b)
                        @php $e = $b['ekskul']; @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <p class="font-semibold text-brand-ink dark:text-white">{{ $e->nama_ekskul }}</p>
                                <p class="text-xs text-brand-muted">{{ $e->hari }} &middot; {{ $e->rentangJam() }}</p>
                            </td>
                            <td class="px-3 py-3 text-brand-muted">{{ $e->namaPembina() }}</td>
                            <td class="px-3 py-3 text-center">{{ $b['anggota'] }}</td>
                            <td class="px-3 py-3 text-center">{{ $b['terjadwal'] }}</td>
                            <td class="px-3 py-3 text-center">
                                <span class="font-semibold {{ $b['terjadwal'] > 0 && $b['terlaksana'] < $b['terjadwal'] ? 'text-error-600 dark:text-error-400' : 'text-brand-ink dark:text-white' }}">{{ $b['terlaksana'] }}</span>
                                @if ($b['persen_terlaksana'] !== null)
                                    <span class="block text-[11px] text-brand-muted">{{ $b['persen_terlaksana'] }}%</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-center">{{ $b['persen_hadir'] !== null ? $b['persen_hadir'] . '%' : '—' }}</td>
                            <td class="px-5 py-2 text-right">
                                <a href="{{ route($rute, ['bulan' => $bulan->format('Y-m'), 'ekskul' => $e->id]) }}"
                                    class="inline-flex items-center rounded-lg border border-brand-border px-3 py-1.5 text-xs font-semibold text-brand-ink hover:bg-brand-surface-muted dark:text-white">Rincian</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-6 py-10 text-center text-sm text-brand-muted">Belum ada jadwal ekskul.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-brand-muted">
            <strong>Terjadwal</strong> = hari ekskul di bulan ini sampai hari ini, tanpa hari libur Kalender Pendidikan.
            <strong>Terlaksana</strong> = sesi yang dimulai dengan scan QR dan diakhiri pembina.
        </p>

    @elseif ($rincian)

        {{-- ============ RINCIAN SATU EKSKUL ============ --}}
        @php $e = $rincian['ekskul']; $t = $rincian['total']; @endphp

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 {{ $kartu }} px-5 py-4">
            <div class="min-w-0">
                <p class="text-lg font-bold text-brand-ink dark:text-white">{{ $e->nama_ekskul }}</p>
                <p class="text-sm text-brand-muted">{{ $e->hari }} &middot; {{ $e->rentangJam() }} &middot; Pembina: {{ $e->namaPembina() }} &middot; {{ $rincian['label'] }}</p>
            </div>
            @if ($semua || $daftarEkskul->count() > 1)
                <a href="{{ route($rute, ['bulan' => $bulan->format('Y-m')]) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted dark:text-white">
                    <x-icon name="arrow-left" class="h-4 w-4" /> Semua ekskul
                </a>
            @endif
        </div>

        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ([['Terjadwal', $t['terjadwal']], ['Sesi terlaksana', $t['terlaksana']], ['Pertemuan diisi', $t['pertemuan']], ['% Hadir', $t['persen_hadir'] !== null ? $t['persen_hadir'] . '%' : '—']] as [$judul, $nilai])
                <div class="{{ $kartu }} p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-brand-muted sm:text-xs">{{ $judul }}</p>
                    <p class="mt-1 text-2xl font-extrabold text-brand-ink dark:text-white">{{ $nilai }}</p>
                </div>
            @endforeach
        </div>

        @if ($rincian['kosong'])
            <div class="mb-4 flex border-l-4 border-error-500 bg-error-500/10 px-5 py-3 text-sm text-error-700 dark:text-error-400" role="status">
                Terjadwal tetapi tidak ada sesi maupun absensi:
                {{ collect($rincian['kosong'])->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'))->join(', ') }}.
            </div>
        @endif

        {{-- Per anggota --}}
        <div class="mb-6 overflow-x-auto {{ $kartu }}">
            <table class="w-full min-w-[560px] text-left text-sm">
                <thead class="bg-brand-surface-muted text-xs uppercase text-brand-muted">
                    <tr>
                        <th class="px-5 py-3 font-semibold">No</th>
                        <th class="px-3 py-3 font-semibold">Nama</th>
                        <th class="px-3 py-3 font-semibold">Kelas</th>
                        @foreach (\App\Services\RekapEkskulBulanan::STATUS as $s)
                            <th class="px-3 py-3 text-center font-semibold">{{ $s->shortLabel() }}</th>
                        @endforeach
                        <th class="px-3 py-3 text-center font-semibold">% Hadir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border dark:divide-gray-800">
                    @forelse ($rincian['siswa'] as $b)
                        <tr>
                            <td class="px-5 py-2.5 text-brand-muted">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2.5 font-medium text-brand-ink dark:text-white">
                                {{ $b['siswa']->nama }}
                                @unless ($b['anggota_aktif']) <span class="ml-1 text-[11px] font-normal text-brand-muted">(sudah keluar)</span> @endunless
                            </td>
                            <td class="px-3 py-2.5 text-brand-muted">{{ $b['siswa']->kelas?->nama_kelas ?? '-' }}</td>
                            @foreach (\App\Services\RekapEkskulBulanan::STATUS as $s)
                                @php $n = $b['jumlah'][$s->value]; @endphp
                                <td class="px-3 py-2.5 text-center {{ $n === 0 ? 'text-brand-faint' : ($s === AbsensiStatus::Alpha ? 'font-semibold text-error-600 dark:text-error-400' : '') }}">{{ $n }}</td>
                            @endforeach
                            <td class="px-3 py-2.5 text-center font-semibold">{{ $b['persen'] !== null ? $b['persen'] . '%' : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-6 py-10 text-center text-sm text-brand-muted">Ekskul ini belum punya anggota.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Matriks per tanggal --}}
        @if ($rincian['pertemuan']->isNotEmpty() && $rincian['siswa']->isNotEmpty())
            <h3 class="mb-2 font-semibold text-brand-ink dark:text-white">Daftar hadir per pertemuan</h3>
            <div class="overflow-x-auto {{ $kartu }}">
                <table class="w-full text-left text-sm">
                    <thead class="bg-brand-surface-muted text-xs text-brand-muted">
                        <tr>
                            <th class="sticky left-0 bg-brand-surface-muted px-4 py-3 font-semibold uppercase">Nama</th>
                            @foreach ($rincian['pertemuan'] as $p)
                                <th class="px-2 py-3 text-center font-semibold" title="{{ $p['sesi']?->sudahSelesai() ? 'Sesi terlaksana' : ($p['sesi'] ? 'Sesi belum diakhiri' : 'Diisi tanpa sesi') }}">
                                    {{ $p['tanggal']->format('d/m') }}
                                    <span class="block text-[10px] font-bold {{ $p['sesi']?->sudahSelesai() ? 'text-success-600' : 'text-warning-600' }}">
                                        {{ $p['sesi']?->sudahSelesai() ? 'Sesi ✓' : ($p['sesi'] ? 'Terbuka' : 'Susulan') }}
                                    </span>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border dark:divide-gray-800">
                        @foreach ($rincian['siswa'] as $b)
                            <tr>
                                <td class="sticky left-0 whitespace-nowrap bg-brand-surface px-4 py-2 font-medium text-brand-ink dark:bg-gray-900 dark:text-white">{{ $b['siswa']->nama }}</td>
                                @foreach ($rincian['pertemuan'] as $p)
                                    @php $st = $b['per_tanggal'][$p['kunci']] ?? null; @endphp
                                    <td class="px-2 py-2 text-center">
                                        @if ($st)
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded text-xs font-bold {{ $kodeStatus[$st->value][1] }}">{{ $kodeStatus[$st->value][0] }}</span>
                                        @else
                                            <span class="text-brand-faint">·</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-brand-muted">
                H = Hadir, I = Izin, S = Sakit, A = Alpa. <strong>Sesi ✓</strong> = dimulai dengan scan QR dan diakhiri pembina;
                <strong>Susulan</strong> = absensi diisi tanpa sesi (koreksi), tidak dihitung sebagai sesi terlaksana.
            </p>
        @endif

    @endif

@endsection
