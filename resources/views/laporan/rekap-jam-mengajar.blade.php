{{--
    Halaman Rekap Jam Mengajar Guru (berbasis JP).

    Dipakai BERSAMA oleh /kepsek/rekap-jam-mengajar dan
    /super-admin/rekap-jam-mengajar. Formnya GET biasa: URL yang membawa
    ?dari=...&sampai=...&guru=... diteruskan apa adanya ke tombol unduh,
    jadi layar dan PDF memakai saringan yang sama.
--}}
@extends('layouts.app')

@section('title', 'Rekap Jam Mengajar Guru')

@section('content')

    @php
        $param = array_filter(['dari' => $dari->format('Y-m-d'), 'sampai' => $sampai->format('Y-m-d'), 'guru' => $guruId]);
        $kelasInput = 'rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">
            Rekap Jam Mengajar Guru
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Beban mengajar dihitung dalam <strong>jam pelajaran (JP)</strong>, bukan jumlah sesi. Setiap jadwal
            bernilai panjangnya dibagi durasi 1 JP
            @if ($data)
                (saat ini <strong>{{ $data['durasi_jp'] }} menit</strong>, diatur di Pengaturan Sistem)
            @endif
            &mdash; jadi blok 4 JP dihitung 4, bukan 1.
        </p>
    </div>

    {{-- ============ SARINGAN ============ --}}
    <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route($panelPrefix . '.rekap-jam-mengajar') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <label for="f-dari" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Dari tanggal</label>
                <input id="f-dari" type="date" name="dari" value="{{ $dari->format('Y-m-d') }}" class="{{ $kelasInput }}">
            </div>
            <div>
                <label for="f-sampai" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Sampai tanggal</label>
                <input id="f-sampai" type="date" name="sampai" value="{{ $sampai->format('Y-m-d') }}" class="{{ $kelasInput }}">
            </div>
            <div>
                <label for="f-guru" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Guru</label>
                <select id="f-guru" name="guru" class="{{ $kelasInput }} pr-10">
                    <option value="">Semua guru</option>
                    @foreach ($daftarGuru as $g)
                        <option value="{{ $g->id }}" @selected($guruId === $g->id)>{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                Tampilkan
            </button>

            @if ($data)
                <a href="{{ route($panelPrefix . '.rekap-jam-mengajar.unduh', $param) }}"
                    class="ms-auto inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                    <x-icon name="download" class="h-4 w-4" />
                    Unduh PDF
                </a>
            @endif
        </form>
    </div>

    @if ($galat)
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-500/10 p-5 dark:border-warning-500/30" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
            <p class="text-sm font-semibold text-warning-700 dark:text-warning-400">{{ $galat }}</p>
        </div>
    @endif

    @if ($data)
        @php $r = $data['ringkas']; @endphp

        {{-- ============ KARTU RINGKASAN ============ --}}
        <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['JP terjadwal', $r['jp_terjadwal'], 'calendar', 'bg-brand-500/10 text-brand-500', $data['hari_kbm'] . ' hari KBM · ' . $data['label_periode']],
                ['JP terlaksana', $r['jp_terlaksana'], 'check-circle', 'bg-success-500/10 text-success-500', $r['persen'] === null ? 'belum ada jadwal' : $r['persen'] . '% keterlaksanaan'],
                ['JP berhalangan', $r['jp_berhalangan'], 'clipboard-check', 'bg-warning-500/10 text-warning-500', 'guru izin / sakit / alpa'],
                ['JP tidak terlaksana', $r['jp_tidak_terlaksana'], 'x-circle', 'bg-error-500/10 text-error-500', 'tanpa sesi tuntas & tanpa izin'],
            ] as [$label, $nilai, $ikon, $warna, $sub])
                <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full {{ $warna }}">
                        <x-icon name="{{ $ikon }}" class="h-5 w-5" />
                    </span>
                    <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">{{ number_format($nilai, 0, ',', '.') }} <span class="text-sm font-semibold text-brand-muted">JP</span></p>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">{{ $label }}</p>
                    <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $sub }}</p>
                </div>
            @endforeach
        </div>

        {{-- ============ TABEL PER GURU ============ --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Rekap per Guru &middot; {{ $data['label_periode'] }}</h3>
                <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                    JP terlaksana hanya dihitung dari sesi yang tuntas: scan QR ruangan, foto bukti, dan diakhiri.
                    @if ($r['jp_pengganti'] > 0)
                        Total {{ $r['jp_pengganti'] }} JP diisi guru pengganti.
                    @endif
                    @if ($r['jp_akan_datang'] > 0)
                        {{ $r['jp_akan_datang'] }} JP lagi di periode ini belum selesai, jadi belum dihitung.
                    @endif
                </p>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-100 text-left dark:bg-gray-800">
                            <th class="px-6 py-4 text-sm font-semibold text-brand-ink dark:text-white">Guru</th>
                            <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">JP/minggu</th>
                            <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Terjadwal</th>
                            <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Terlaksana</th>
                            <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Berhalangan</th>
                            <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Tidak terlaksana</th>
                            <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Pengganti</th>
                            <th class="px-6 py-4 text-right text-sm font-semibold text-brand-ink dark:text-white">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['baris'] as $b)
                            <tr class="border-b border-gray-200 last:border-0 dark:border-gray-800">
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-brand-ink dark:text-white">{{ $b['nama'] }}</p>
                                    @if ($b['nip'])
                                        <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">NIP {{ $b['nip'] }}</p>
                                    @endif
                                    @if ($b['sesi_luar_jadwal'] > 0)
                                        <p class="mt-0.5 text-xs text-warning-500" title="Sesi tuntas yang ruangannya tidak cocok dengan jadwal mana pun, atau di hari libur. Tidak diberi JP.">
                                            {{ $b['sesi_luar_jadwal'] }} sesi di luar jadwal
                                        </p>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $b['jp_per_minggu'] }}</td>
                                <td class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">{{ $b['jp_terjadwal'] }}</td>
                                <td class="px-3 py-4 text-center text-sm font-semibold text-success-500">{{ $b['jp_terlaksana'] }}</td>
                                <td class="px-3 py-4 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $b['jp_berhalangan'] }}</td>
                                <td class="px-3 py-4 text-center text-sm {{ $b['jp_tidak_terlaksana'] > 0 ? 'font-semibold text-error-600' : 'text-brand-muted dark:text-brand-faint' }}">{{ $b['jp_tidak_terlaksana'] }}</td>
                                <td class="px-3 py-4 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $b['jp_pengganti'] ?: '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if ($b['persen'] === null)
                                        <span class="text-sm text-brand-muted dark:text-brand-faint">—</span>
                                    @else
                                        <span class="text-sm font-bold {{ $b['persen'] >= 85 ? 'text-success-500' : ($b['persen'] >= 70 ? 'text-warning-500' : 'text-error-600') }}">{{ $b['persen'] }}%</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-brand-muted dark:text-brand-faint">
                                    Belum ada jadwal pelajaran yang cocok dengan saringan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

@endsection
