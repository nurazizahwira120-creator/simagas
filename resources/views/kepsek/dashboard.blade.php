@extends('layouts.app')

@section('title', 'Dashboard Kepala Sekolah')

@section('content')

    {{-- Hero --}}
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold">Ringkasan Kehadiran Hari Ini</h1>
            <p class="mt-0.5 flex items-center gap-1.5 text-sm text-brand-muted">
                <x-icon name="calendar" class="h-3.5 w-3.5" />
                {{ now()->translatedFormat('l, d F Y') }}
            </p>
        </div>
        <a href="{{ route($panelPrefix . '.laporan') }}"
            class="inline-flex items-center gap-1.5 rounded-lg border border-brand-border bg-brand-surface px-3.5 py-2 text-sm font-medium text-brand-ink hover:bg-brand-surface-muted">
            <x-icon name="document-report" class="h-4 w-4" />
            Laporan Bulanan
            <x-icon name="arrow-right" class="h-3.5 w-3.5" />
        </a>
    </div>

    {{-- Absensi mandiri — halaman ini dipakai bersama oleh Kepsek dan Super
         Admin, tapi Super Admin tidak punya fitur absensi. Pakai Route::has()
         alih-alih mengecek role: kondisinya jadi mengikuti pendaftaran rute
         itu sendiri, sehingga tidak bisa jatuh ke RouteNotFoundException. --}}
    @if (Route::has($panelPrefix . '.absen-lokasi'))
        <div class="mb-6">
            @include('partials.kartu-kehadiran-hari-ini')
        </div>
    @endif

    {{-- 3 kartu statistik.

         tampil-berurutan: ketiganya masuk menyusul dengan jarak 60ms —
         mata dituntun dari kiri ke kanan mengikuti urutan membaca, bukan
         disodori tiga kartu sekaligus. Gayanya ada di app.css. --}}
    <div class="tampil-berurutan grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="kartu-angkat rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-surface-muted text-brand-ink">
                <x-icon name="users" class="h-5 w-5" />
            </span>
            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-brand-muted">Total Siswa</p>
            <x-angka-naik :nilai="$totalSiswa" class="mt-1 block text-3xl font-bold text-brand-ink" />
            <p class="mt-1 text-xs text-brand-muted">terdaftar di seluruh kelas</p>
        </div>

        <div class="kartu-angkat rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-accent-soft text-brand-accent-text">
                <x-icon name="check-circle" class="h-5 w-5" />
            </span>
            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-brand-muted">Hadir Hari Ini</p>
            <x-angka-naik :nilai="$totalHadirHariIni" class="mt-1 block text-3xl font-bold text-brand-accent-text" />
            <p class="mt-1 text-xs text-brand-muted">
                @if ($totalSiswa === 0)
                    Belum ada siswa terdaftar
                @else
                    dari {{ number_format($totalSiswa, 0, ',', '.') }} siswa
                @endif
            </p>
        </div>

        @php
            /*
             | Sekolah yang belum punya satu pun siswa BUKAN sekolah dengan
             | kehadiran 0%. Tanpa cabang $belumAdaData di bawah, layar
             | pertama instalasi baru menyambut Super Admin dengan angka
             | merah besar "0.0%" — terbaca sebagai kabar buruk, padahal
             | artinya cuma "datanya belum diisi".
             */
            $belumAdaData = $totalSiswa === 0;

            $warnaPersen = match (true) {
                $belumAdaData => 'text-brand-faint',
                $persentaseKehadiran >= 90 => 'text-brand-accent-text',
                $persentaseKehadiran >= 75 => 'text-amber-600 dark:text-amber-400',
                default => 'text-brand-danger-text',
            };
            $chipPersen = match (true) {
                $belumAdaData => 'bg-brand-surface-muted text-brand-muted',
                $persentaseKehadiran >= 90 => 'bg-brand-accent-soft text-brand-accent-text',
                $persentaseKehadiran >= 75 => 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
                default => 'bg-brand-danger-soft text-brand-danger-text',
            };

            /*
             | Denyut riak dipasang HANYA saat kehadiran benar-benar jatuh di
             | bawah 75%. Gerak yang muncul di setiap keadaan berhenti berarti
             | apa-apa — justru karena ikon ini diam pada hari-hari biasa,
             | denyutnya terbaca sebagai "hari ini ada yang perlu dilihat".
             |
             | Lihat .denyut di app.css: berdenyut tiga kali lalu berhenti,
             | bukan tanpa henti.
             */
            $denyutPersen = (! $belumAdaData && $persentaseKehadiran < 75) ? 'denyut' : '';
        @endphp
        <div class="kartu-angkat rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
            <span class="{{ $denyutPersen }} flex h-10 w-10 items-center justify-center rounded-xl {{ $chipPersen }}">
                <x-icon name="chart-bar" class="h-5 w-5" />
            </span>
            <p class="mt-3 text-xs font-medium uppercase tracking-wide text-brand-muted">Persentase Kehadiran</p>
            <x-angka-naik
                :nilai="$belumAdaData ? null : $persentaseKehadiran"
                :desimal="1"
                akhiran="%"
                class="mt-1 block text-3xl font-bold {{ $warnaPersen }}" />

            {{-- Bilahnya TUMBUH dari nol lewat scaleX, bukan lewat width.
                 Alasannya ada di app.css: width memaksa perhitungan tata
                 letak tiap frame, scaleX dikerjakan compositor. --}}
            <div class="mt-2.5 h-1.5 w-full overflow-hidden rounded-full bg-brand-surface-muted">
                <div class="bilah-isi h-full w-full rounded-full bg-current {{ $warnaPersen }}"
                    style="--isi: {{ round(min(100, max(0, $persentaseKehadiran)) / 100, 4) }}"></div>
            </div>
        </div>
    </div>

    {{-- 3 kelas terendah --}}
    <div class="muncul mt-8" style="animation-delay: 220ms">
        <h2 class="mb-3 flex items-center gap-1.5 text-sm font-semibold uppercase tracking-wide text-brand-muted">
            <x-icon name="exclamation-triangle" class="h-4 w-4" />
            3 Kelas dengan Kehadiran Terendah Hari Ini
        </h2>

        @if ($kelasTerendah->isEmpty())
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-dashed border-brand-border bg-brand-surface p-8 text-center">
                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-surface-muted text-brand-muted">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-brand-muted">Belum ada data absensi hari ini.</p>
            </div>
        @else
            <div class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft">
                <table class="w-full min-w-[520px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-brand-border bg-brand-surface-muted text-xs uppercase tracking-wide text-brand-muted">
                            <th class="px-4 py-3 font-medium">Kelas</th>
                            <th class="px-4 py-3 font-medium">Hadir / Total</th>
                            <th class="px-4 py-3 font-medium">Persentase</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border">
                        @foreach ($kelasTerendah as $kelas)
                            @php
                                $urgen = match (true) {
                                    $kelas->persentase_hadir < 75 => ['label' => 'Perlu Tindakan', 'chip' => 'bg-red-500/10 text-brand-danger-text border-red-300', 'icon' => 'exclamation-triangle'],
                                    $kelas->persentase_hadir < 90 => ['label' => 'Perlu Dipantau', 'chip' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-300', 'icon' => 'clock'],
                                    default => ['label' => 'Baik', 'chip' => 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-300', 'icon' => 'check-circle'],
                                };
                            @endphp
                            <tr class="hover:bg-brand-surface-muted/60">
                                <td class="px-4 py-3 font-medium">{{ $kelas->nama_kelas }}</td>
                                <td class="whitespace-nowrap px-4 py-3 tabular-nums text-brand-muted">
                                    {{ $kelas->hadir_count }} / {{ $kelas->siswa_count }}
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-12 tabular-nums font-semibold">{{ number_format($kelas->persentase_hadir, 1) }}%</span>
                                        <div class="h-1.5 w-24 overflow-hidden rounded-full bg-brand-surface-muted">
                                            <div class="bilah-isi h-full w-full rounded-full {{ $kelas->persentase_hadir < 75 ? 'bg-brand-danger' : ($kelas->persentase_hadir < 90 ? 'bg-amber-500' : 'bg-brand-accent') }}"
                                                style="--isi: {{ round(min(100, max(0, $kelas->persentase_hadir)) / 100, 4) }}"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium {{ $urgen['chip'] }}">
                                        <x-icon name="{{ $urgen['icon'] }}" class="h-3.5 w-3.5" />
                                        {{ $urgen['label'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

@endsection
