{{--
    Halaman pratinjau Lembar Paraf Guru Mengajar.

    Dipakai BERSAMA oleh /kepsek/lembar-paraf-mengajar dan
    /super-admin/lembar-paraf-mengajar. Formnya GET biasa, bukan Livewire:
    halamannya hanya membaca, dan URL yang membawa ?tanggal=...&urut=...
    bisa langsung diteruskan ke tombol cetak — layar dan kertas dijamin
    memakai saringan yang sama.
--}}
@extends('layouts.app')

@section('title', 'Lembar Paraf Guru Mengajar')

@section('content')

    @php
        $param = ['tanggal' => $tanggal->format('Y-m-d'), 'urut' => $urut];
        $kelasInput = 'rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white';
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">
            Lembar Paraf Guru Mengajar
        </h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Cetakan A4 harian untuk paraf manual guru, sebagai cadangan laporan mengajar bila catatan
            di sistem tidak sesuai. Guru yang izinnya sudah disetujui otomatis tertulis
            <strong>GURU IZIN</strong> pada kolom parafnya. Tanggal dan jam cetak tercantum di setiap halaman.
        </p>
    </div>

    {{-- ============ SARINGAN & TOMBOL CETAK ============ --}}
    <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" action="{{ route($panelPrefix . '.lembar-paraf') }}" class="flex flex-wrap items-end gap-4">

            <div>
                <label for="f-tanggal" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Tanggal mengajar</label>
                <input id="f-tanggal" type="date" name="tanggal" value="{{ $tanggal->format('Y-m-d') }}" class="{{ $kelasInput }}">
            </div>

            <div>
                <label for="f-urut" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Urutan baris</label>
                {{-- pr-10: ruang untuk panah bawaan select, supaya teks
                     pilihannya tidak tertimpa panah. --}}
                <select id="f-urut" name="urut" class="{{ $kelasInput }} pr-10">
                    @foreach ($pilihanUrut as $kode => $label)
                        <option value="{{ $kode }}" @selected($urut === $kode)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <button type="submit"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                Tampilkan
            </button>

            @if ($data)
                <div class="ms-auto flex flex-wrap items-center gap-2">
                    {{-- target=_blank + lihat=1: PDF terbuka di tab baru dan
                         langsung bisa dicetak dengan Ctrl+P, tanpa membuka
                         folder unduhan lebih dulu. --}}
                    <a href="{{ route($panelPrefix . '.lembar-paraf.cetak', $param + ['lihat' => 1]) }}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                        <x-icon name="printer" class="h-4 w-4" />
                        Cetak (A4)
                    </a>
                    <a href="{{ route($panelPrefix . '.lembar-paraf.cetak', $param) }}"
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                        <x-icon name="download" class="h-4 w-4" />
                        Unduh PDF
                    </a>
                </div>
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

        @if ($data['libur'])
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-500/10 p-5 dark:border-warning-500/30" role="status">
                <x-icon name="calendar" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
                <p class="text-sm text-warning-700 dark:text-warning-400">
                    Menurut Kalender Pendidikan, tanggal ini <strong>bukan hari KBM</strong> ({{ $data['libur'] }}).
                    Jadwal tetap bisa dicetak bila memang dibutuhkan.
                </p>
            </div>
        @endif

        {{-- ============ PRATINJAU ============ --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">
                    {{ $data['hari'] }}, {{ $data['tanggal']->translatedFormat('d F Y') }}
                </h3>
                <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                    {{ $data['ringkas']['jadwal'] }} jam pelajaran &middot; {{ $data['ringkas']['guru'] }} guru
                    &middot; {{ $data['ringkas']['guru_izin'] }} guru izin/sakit
                    &middot; {{ $data['ringkas']['tercatat'] }} sudah tercatat scan QR
                </p>
            </div>

            <div class="max-w-full overflow-x-auto">
                <table class="w-full table-auto">
                    <thead>
                        <tr class="bg-gray-100 text-left dark:bg-gray-800">
                            <th class="px-4 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">No</th>
                            <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Jam</th>
                            <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Kelas &amp; Mapel</th>
                            <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Guru</th>
                            <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Catatan Sistem</th>
                            <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Paraf</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($data['baris'] as $b)
                            <tr class="border-b border-gray-200 last:border-0 dark:border-gray-800">
                                <td class="px-4 py-3 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $b['no'] }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-brand-ink dark:text-white">{{ $b['jam'] }}</td>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-brand-ink dark:text-white">{{ $b['kelas'] }}</p>
                                    <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $b['mapel'] }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-brand-ink dark:text-white">{{ $b['guru'] }}</td>
                                <td class="px-4 py-3 text-sm {{ match ($b['sistem']['nada']) {
                                    'ok' => 'text-success-500',
                                    'kurang' => 'text-warning-500',
                                    'nihil' => 'font-semibold text-error-600',
                                    default => 'text-brand-muted dark:text-brand-faint',
                                } }}">{{ $b['sistem']['teks'] }}</td>
                                <td class="px-4 py-3">
                                    @if ($b['izin'])
                                        <span class="inline-flex rounded-full bg-error-500/10 px-2.5 py-1 text-xs font-semibold text-error-600">
                                            {{ $b['izin']['label'] }}
                                        </span>
                                        @if ($b['izin']['rinci'])
                                            <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">{{ $b['izin']['rinci'] }}</p>
                                        @endif
                                    @elseif ($b['izin_menunggu'])
                                        <span class="text-xs text-warning-500">Izin diajukan, belum disetujui</span>
                                    @else
                                        <span class="text-xs text-brand-muted dark:text-brand-faint">— diparaf di kertas —</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-brand-muted dark:text-brand-faint">
                                    Tidak ada jadwal pelajaran pada hari {{ $data['hari'] }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

@endsection
