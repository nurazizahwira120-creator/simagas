@extends('layouts.app')

@section('title', 'Laporan Bulanan Kehadiran')

@section('content')

    <div class="mb-6 print:hidden">
        <h1 class="text-xl font-bold">Laporan Bulanan Kehadiran</h1>
        <p class="text-sm text-brand-muted line-clamp-2">Rekap absensi gerbang &amp; di kelas per siswa selama satu bulan, bisa dicetak PDF.</p>
    </div>

    {{-- Kop khusus cetak — hanya tampil saat print:block, disembunyikan di layar --}}
    <div class="hidden print:block print:mb-4">
        <p class="text-base font-semibold">{{ \App\Models\PengaturanSistem::namaSekolah() }}</p>
        <p class="text-sm">Laporan Bulanan Kehadiran Siswa (Gerbang &amp; Kelas)</p>
        <p class="text-sm">
            Periode: {{ $bulan->translatedFormat('F Y') }}
            &middot; Kelas: {{ $kelasTerpilih->nama_kelas ?? 'Semua Kelas' }}
        </p>
        <p class="text-xs text-brand-muted">Dicetak pada {{ now()->translatedFormat('d F Y, H:i') }}</p>
        <hr class="mt-3 border-brand-border">
    </div>

    {{-- Form filter --}}
    <form method="GET" action="{{ route($panelPrefix . '.laporan') }}"
        class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-soft print:hidden">
        <div>
            <label for="bulan" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="calendar" class="h-3.5 w-3.5" />
                Pilih Bulan
            </label>
            <input type="month" id="bulan" name="bulan" value="{{ $bulan->format('Y-m') }}"
                class="rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
        </div>

        <div>
            <label for="kelas_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="academic-cap" class="h-3.5 w-3.5" />
                Pilih Kelas
            </label>
            <select id="kelas_id" name="kelas_id"
                class="rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
                <option value="">Semua Kelas</option>
                @foreach ($daftarKelas as $kelas)
                    <option value="{{ $kelas->id }}" @selected($kelasTerpilih?->id === $kelas->id)>
                        {{ $kelas->nama_kelas }}
                    </option>
                @endforeach
            </select>
        </div>

        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white hover:bg-brand-accent-dark">
            <x-icon name="funnel" class="h-4 w-4" />
            Terapkan Filter
        </button>
    </form>

    {{-- Tabel rekap: gerbang (hari) + kelas (jam pelajaran) --}}
    @php
        $kolomGerbang = ['hadir' => 'Hadir', 'terlambat' => 'Terlambat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
        $kolomKelas = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'bolos' => 'Bolos', 'alpa' => 'Alpa'];
        $warna = ['terlambat' => 'text-warning-600', 'alpa' => 'text-error-600', 'bolos' => 'text-error-600'];
        $saringan = array_filter(['bulan' => $bulan->format('Y-m'), 'kelas_id' => $kelasTerpilih?->id]);
    @endphp

    <div class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft print:rounded-none print:border-0 print:shadow-none">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-4 py-3 print:hidden">
            <div>
                <p class="text-sm font-semibold">
                    {{ $data['label'] }} &middot; {{ $kelasTerpilih->nama_kelas ?? 'Semua Kelas' }}
                </p>
                <p class="text-xs text-brand-muted">{{ $data['baris']->count() }} siswa</p>
            </div>

            <div class="flex flex-wrap gap-2">
                <a href="{{ route($panelPrefix . '.laporan.unduh', $saringan + ['lihat' => 1]) }}" target="_blank" rel="noopener"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-brand-ink px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-brand-surface-muted">
                    <x-icon name="eye" class="h-4 w-4" />
                    Lihat PDF
                </a>
                <a href="{{ route($panelPrefix . '.laporan.unduh', $saringan) }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-brand-accent px-4 py-2 text-sm font-semibold text-white hover:bg-brand-accent-dark">
                    <x-icon name="download" class="h-4 w-4" />
                    Unduh PDF
                </a>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[960px] text-left text-sm" data-rekap="siswa">
                <thead class="text-xs uppercase tracking-wide text-brand-muted">
                    <tr class="border-b border-brand-border bg-brand-surface-muted print:bg-transparent">
                        <th rowspan="2" class="px-3 py-2 font-medium">No</th>
                        <th rowspan="2" class="px-3 py-2 font-medium">Nama Siswa</th>
                        <th colspan="6" class="border-l border-brand-border px-3 py-2 text-center font-semibold text-brand-ink">Absensi Gerbang <span class="font-normal normal-case">(hari)</span></th>
                        <th colspan="6" class="border-l border-brand-border px-3 py-2 text-center font-semibold text-brand-ink">Absensi di Kelas <span class="font-normal normal-case">(jam pelajaran)</span></th>
                    </tr>
                    <tr class="border-b border-brand-border bg-brand-surface-muted print:bg-transparent">
                        @foreach ($kolomGerbang as $kunci => $label)
                            <th class="{{ $loop->first ? 'border-l border-brand-border' : '' }} px-2 py-2 text-center font-medium">{{ $label }}</th>
                        @endforeach
                        <th class="px-2 py-2 text-center font-medium">Jml</th>
                        @foreach ($kolomKelas as $kunci => $label)
                            <th class="{{ $loop->first ? 'border-l border-brand-border' : '' }} px-2 py-2 text-center font-medium">{{ $label }}</th>
                        @endforeach
                        <th class="px-2 py-2 text-center font-medium">Jml</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border">
                    @forelse ($data['baris'] as $b)
                        <tr class="hover:bg-brand-surface-muted/60 print:hover:bg-transparent">
                            <td class="px-3 py-2.5 text-xs text-brand-muted tabular-nums">{{ $loop->iteration }}</td>
                            <td class="px-3 py-2.5">
                                <p class="font-medium">{{ $b['siswa']->nama }}</p>
                                <p class="text-xs text-brand-muted">
                                    {{ $b['siswa']->nis }}
                                    @unless ($kelasTerpilih)
                                        &middot; {{ $b['siswa']->kelas?->nama_kelas ?? '-' }}
                                    @endunless
                                </p>
                            </td>
                            @foreach ($kolomGerbang as $kunci => $label)
                                <td class="{{ $loop->first ? 'border-l border-brand-border' : '' }} px-2 py-2.5 text-center tabular-nums {{ $b['gerbang'][$kunci] > 0 ? ($warna[$kunci] ?? '') . ' font-semibold' : 'text-brand-faint' }}">{{ $b['gerbang'][$kunci] }}</td>
                            @endforeach
                            <td class="px-2 py-2.5 text-center tabular-nums text-brand-muted">{{ $b['gerbang']['total'] }}</td>
                            @foreach ($kolomKelas as $kunci => $label)
                                <td class="{{ $loop->first ? 'border-l border-brand-border' : '' }} px-2 py-2.5 text-center tabular-nums {{ $b['kelas'][$kunci] > 0 ? ($warna[$kunci] ?? '') . ' font-semibold' : 'text-brand-faint' }}">{{ $b['kelas'][$kunci] }}</td>
                            @endforeach
                            <td class="px-2 py-2.5 text-center tabular-nums text-brand-muted">{{ $b['kelas']['total'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="14" class="px-4 py-8 text-center text-brand-muted">
                                Tidak ada siswa yang cocok dengan filter ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($data['baris']->isNotEmpty())
                    <tfoot>
                        <tr class="border-t-2 border-brand-border bg-brand-surface-muted font-semibold print:bg-transparent">
                            <td colspan="2" class="px-3 py-2.5">Total</td>
                            @foreach ($kolomGerbang as $kunci => $label)
                                <td class="{{ $loop->first ? 'border-l border-brand-border' : '' }} px-2 py-2.5 text-center tabular-nums">{{ $data['total']['gerbang'][$kunci] }}</td>
                            @endforeach
                            <td class="px-2 py-2.5 text-center tabular-nums">{{ $data['total']['gerbang']['total'] }}</td>
                            @foreach ($kolomKelas as $kunci => $label)
                                <td class="{{ $loop->first ? 'border-l border-brand-border' : '' }} px-2 py-2.5 text-center tabular-nums">{{ $data['total']['kelas'][$kunci] }}</td>
                            @endforeach
                            <td class="px-2 py-2.5 text-center tabular-nums">{{ $data['total']['kelas']['total'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="border-t border-brand-border px-4 py-3 text-xs leading-relaxed text-brand-muted">
            <strong>Gerbang</strong> dihitung per hari: Hadir = tepat waktu, Terlambat = scan setelah pukul {{ $data['batas_terlambat'] }}.
            <strong>Kelas</strong> dihitung per jam pelajaran (1 JP = {{ $data['durasi_jp'] }} menit); Bolos = masuk gerbang tapi tidak ada di kelas.
        </p>
    </div>

@endsection
