@extends('layouts.app')

@section('title', 'Rapor Anak')

@section('content')

    {{-- ============ BANNER APRESIASI EMAS ============
         Diletakkan PALING ATAS, sebelum judul halaman: kabar baik tidak
         seharusnya perlu di-scroll. Komponennya tidak menggambar apa pun
         kalau $penghargaan null, jadi tidak perlu dibungkus @if di sini. --}}
    <x-banner-apresiasi :penghargaan="$penghargaan" />

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Rapor Anak</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Nilai ditampilkan setelah rapor kelas disetujui dan diterbitkan Kepala Sekolah.
        </p>
    </div>

    @if ($daftarAnak->isEmpty())
        <div class="rounded-2xl border border-warning-200 bg-warning-500/10 p-5 dark:border-warning-500/30" role="alert">
            <p class="text-sm font-bold text-warning-700 dark:text-warning-400">Belum ada anak yang tertaut ke akun ini.</p>
            <p class="mt-1 text-sm text-warning-700 dark:text-warning-400">Hubungi Admin TU sekolah untuk menautkannya.</p>
        </div>
    @else

        {{-- ============ PEMILIH ANAK & SEMESTER ============ --}}
        <form method="GET" action="{{ route($panelPrefix . '.rapor') }}"
            class="mb-6 rounded-2xl border border-gray-200 bg-brand-surface p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

            <div class="flex flex-wrap items-end gap-4">
                @if ($daftarAnak->count() > 1)
                    <div class="min-w-0 flex-1 sm:max-w-xs">
                        <label for="f-anak" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Anak</label>
                        <select id="f-anak" name="anak" onchange="this.form.submit()"
                            class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                            @foreach ($daftarAnak as $a)
                                <option value="{{ $a->id }}" @selected($anak && $anak->id === $a->id)>
                                    {{ $a->nama }} — {{ $a->kelas?->nama_kelas ?? 'tanpa kelas' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <input type="hidden" name="anak" value="{{ $anak?->id }}">
                @endif

                <div class="min-w-0 flex-1 sm:max-w-xs">
                    <label for="f-periode" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Semester</label>
                    <select id="f-periode" name="periode" onchange="this.form.submit()"
                        class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        @forelse ($daftarPeriode as $p)
                            <option value="{{ $p->id }}" @selected($periode && $periode->id === $p->id)>{{ $p->label() }}</option>
                        @empty
                            <option value="">— belum ada tahun ajaran —</option>
                        @endforelse
                    </select>
                </div>

                @if ($anak)
                    <div class="text-xs text-brand-muted dark:text-brand-faint">
                        Kelas<br>
                        <span class="text-sm font-semibold text-brand-ink dark:text-white">{{ $anak->kelas?->nama_kelas ?? '—' }}</span>
                    </div>
                @endif
            </div>

            <noscript>
                <button type="submit" class="mt-3 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
            </noscript>
        </form>

        @if (! $periode || ! $rapor)
            <div class="rounded-2xl border border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <p class="text-sm font-semibold text-brand-ink dark:text-white">Belum ada periode yang bisa ditampilkan.</p>
            </div>

        @elseif (! $rapor->status->terbitKeWaliMurid())
            {{-- Rapor belum terbit. Statusnya disebut apa adanya supaya orang
                 tua tahu ini menunggu, bukan rusak. --}}
            <div class="rounded-2xl border border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-warning-500/10 text-warning-600">
                    <x-icon name="clock" class="h-7 w-7" />
                </span>
                <p class="mt-4 text-base font-bold text-brand-ink dark:text-white">Rapor belum diterbitkan</p>
                <p class="mx-auto mt-2 max-w-md text-sm text-brand-muted dark:text-brand-faint">
                    Rapor {{ $periode->label() }} untuk kelas {{ $anak->kelas?->nama_kelas ?? '—' }}
                    berstatus <strong>{{ $rapor->status->label() }}</strong>. Nilainya akan muncul di sini
                    begitu Kepala Sekolah menyetujuinya.
                </p>
            </div>

        @else

            {{-- ============ RINGKASAN ============ --}}
            <div class="mb-6 grid gap-5 sm:grid-cols-3">
                <div class="rounded-2xl border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
                        <x-icon name="academic-cap" class="h-5 w-5" />
                    </span>
                    <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">{{ $baris->count() }}</p>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Mata pelajaran dinilai</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                        <x-icon name="chart-bar" class="h-5 w-5" />
                    </span>
                    <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">
                        {{ $rataKeseluruhan === null ? '—' : number_format($rataKeseluruhan, 2, ',', '.') }}
                    </p>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Rata-rata keseluruhan</p>
                </div>

                <div class="rounded-2xl border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                        <x-icon name="check-circle" class="h-5 w-5" />
                    </span>
                    <p class="mt-4 text-sm font-semibold text-brand-ink dark:text-white">Rapor terbit</p>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                        {{ $rapor->tanggal_persetujuan?->translatedFormat('d F Y') ?? '—' }}
                    </p>
                </div>
            </div>

            {{-- ============ TABEL NILAI ============ --}}
            <div class="rounded-2xl border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <h3 class="font-semibold text-brand-ink dark:text-white">
                        Nilai {{ $anak->nama }} &middot; {{ $periode->label() }}
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-auto max-md:block md:min-w-[640px]">
                        <thead class="max-md:hidden">
                            <tr class="bg-gray-50 text-left dark:bg-gray-800">
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Mata Pelajaran</th>
                                @foreach ($jenisPenilaian as $jenis)
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">
                                        {{ $jenis->label() }}
                                    </th>
                                @endforeach
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Rata-rata</th>
                            </tr>
                        </thead>

                        <tbody class="max-md:block">
                            @forelse ($baris as $b)
                                <tr class="border-b border-gray-200 last:border-0 dark:border-gray-800 max-md:block max-md:px-5 max-md:py-4">
                                    <td class="px-5 py-4 font-medium text-brand-ink max-md:block max-md:p-0 dark:text-white">
                                        {{ $b['mapel'] }}
                                    </td>

                                    @foreach ($jenisPenilaian as $jenis)
                                        <td class="px-4 py-4 text-center max-md:block max-md:px-0 max-md:pb-0 max-md:pt-2 max-md:text-left">
                                            <span class="hidden text-xs text-brand-muted max-md:inline dark:text-brand-faint">
                                                {{ $jenis->label() }}:
                                            </span>
                                            <span class="text-sm font-semibold text-brand-ink dark:text-white">
                                                @isset($b['skor'][$jenis->value])
                                                    {{ number_format($b['skor'][$jenis->value], 2, ',', '.') }}
                                                @else
                                                    <span class="font-normal text-brand-muted dark:text-brand-faint">—</span>
                                                @endisset
                                            </span>
                                        </td>
                                    @endforeach

                                    <td class="px-5 py-4 text-right max-md:block max-md:px-0 max-md:pt-2 max-md:text-left">
                                        <span class="hidden text-xs text-brand-muted max-md:inline dark:text-brand-faint">Rata-rata:</span>
                                        <span class="text-sm font-bold {{ $b['rata'] >= 75 ? 'text-success-600' : 'text-warning-700 dark:text-warning-400' }}">
                                            {{ number_format($b['rata'], 2, ',', '.') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr class="max-md:block">
                                    <td colspan="{{ count($jenisPenilaian) + 2 }}" class="px-5 py-12 text-center max-md:block">
                                        <p class="text-sm font-semibold text-brand-ink dark:text-white">Belum ada nilai pada semester ini</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-gray-200 px-5 py-4 text-xs leading-relaxed text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                    <p>Tanda <strong>—</strong> berarti jenis penilaian itu belum dinilai untuk mata pelajaran tersebut, bukan bernilai nol.</p>
                </div>
            </div>
        @endif
    @endif

@endsection
