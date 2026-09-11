@extends('layouts.app')

@section('title', 'Input Nilai')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Input Nilai Siswa</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Pilih kelas dan mata pelajaran, lalu isi nilainya untuk seluruh siswa sekaligus.
            Kolom yang dikosongkan berarti nilainya <em>belum ada</em> — bukan nol.
        </p>
    </div>

    @if (session('sukses'))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-success-200 bg-success-500/10 p-4 dark:border-success-500/30" role="status">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-600" />
            <p class="text-sm font-semibold text-success-700 dark:text-success-400">{{ session('sukses') }}</p>
        </div>
    @endif

    @if (session('gagal'))
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-500/10 p-4 dark:border-warning-500/30" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
            <p class="text-sm font-semibold text-warning-700 dark:text-warning-400">{{ session('gagal') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 rounded-2xl border border-error-200 bg-error-500/10 p-4 dark:border-error-500/30" role="alert">
            <p class="text-sm font-bold text-error-700 dark:text-error-400">Ada isian yang belum benar:</p>
            <ul class="mt-1 list-inside list-disc text-sm text-error-700 dark:text-error-400">
                @foreach ($errors->unique() as $pesan)
                    <li>{{ $pesan }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ============ SARINGAN ============
         Form GET biasa, bukan Livewire: halaman ini hanya perlu berpindah
         kelas/mapel, dan pilihannya ikut di URL sehingga bisa di-bookmark
         serta tidak hilang saat halaman dimuat ulang sesudah menyimpan. --}}
    <form method="GET" action="{{ route($panelPrefix . '.nilai') }}"
        class="mb-6 rounded-2xl border border-gray-200 bg-brand-surface p-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

        <div class="flex flex-wrap items-end gap-4">
            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="f-kelas" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Kelas</label>
                <select id="f-kelas" name="kelas_id" onchange="this.form.submit()"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    @forelse ($daftarKelas as $k)
                        <option value="{{ $k->id }}" @selected($kelas && $kelas->id === $k->id)>{{ $k->nama_kelas }}</option>
                    @empty
                        <option value="">— tidak ada kelas —</option>
                    @endforelse
                </select>
            </div>

            <div class="min-w-0 flex-1 sm:max-w-xs">
                <label for="f-mapel" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Mata Pelajaran</label>
                <select id="f-mapel" name="mapel_id" onchange="this.form.submit()"
                    class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    @forelse ($daftarMapel as $m)
                        <option value="{{ $m->id }}" @selected($mapel && $mapel->id === $m->id)>{{ $m->nama }}</option>
                    @empty
                        <option value="">— tidak ada mata pelajaran —</option>
                    @endforelse
                </select>
            </div>

            @if ($periode)
                <div class="text-xs text-brand-muted dark:text-brand-faint">
                    Periode aktif<br>
                    <span class="text-sm font-semibold text-brand-ink dark:text-white">{{ $periode->label() }}</span>
                </div>
            @endif
        </div>

        {{-- Tombol cadangan untuk browser yang JavaScript-nya dimatikan;
             onchange di atas menanganinya untuk semua orang lain. --}}
        <noscript>
            <button type="submit" class="mt-3 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white">Tampilkan</button>
        </noscript>
    </form>

    @if ($pesanKosong)
        <div class="rounded-2xl border border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="text-sm font-semibold text-brand-ink dark:text-white">{{ $pesanKosong }}</p>
        </div>
    @else

        {{-- Rapor terkunci: form-nya tidak digambar sama sekali, bukan sekadar
             tombolnya disembunyikan. Penjagaan sebenarnya tetap di server
             (lihat NilaiController::simpan). --}}
        @if (! $rapor->status->bolehUbahNilai())
            <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-500/10 p-5 dark:border-warning-500/30" role="alert">
                <x-icon name="lock" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
                <div>
                    <p class="text-sm font-bold text-warning-700 dark:text-warning-400">
                        Nilai kelas ini terkunci — status rapor: {{ $rapor->status->label() }}.
                    </p>
                    <p class="mt-1 text-sm text-warning-700 dark:text-warning-400">
                        Angka di bawah hanya bisa dilihat. Hubungi Wali Kelas atau Kepala Sekolah
                        bila masih ada yang perlu diperbaiki.
                    </p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route($panelPrefix . '.nilai.simpan') }}">
            @csrf
            <input type="hidden" name="kelas_id" value="{{ $kelas->id }}">
            <input type="hidden" name="mapel_id" value="{{ $mapel->id }}">

            <div class="rounded-2xl border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h3 class="font-semibold text-brand-ink dark:text-white">
                            {{ $mapel->nama }} &middot; {{ $kelas->nama_kelas }}
                        </h3>
                        <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                            {{ $siswa->count() }} siswa &middot; skala nilai 0–{{ $skorMaks }}
                        </p>
                    </div>

                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $rapor->status->kelasBadge() }}">
                        {{ $rapor->status->label() }}
                    </span>
                </div>

                {{-- Keterangan jenis penilaian. Ditulis sekali di sini, bukan
                     diulang sebagai tooltip di tiap kolom. --}}
                <div class="grid gap-2 border-b border-gray-200 px-5 py-3 text-xs text-brand-muted dark:border-gray-800 dark:text-brand-faint sm:grid-cols-3">
                    @foreach ($jenisPenilaian as $jenis)
                        <p><span class="font-semibold text-brand-ink dark:text-white">{{ $jenis->label() }}:</span> {{ $jenis->penjelasan() }}</p>
                    @endforeach
                </div>

                {{-- Tabel di layar besar, kartu bertumpuk di HP — pola yang
                     sama dengan Jurnal & Absen Kelas. min-w hanya berlaku di
                     md: ke atas supaya di HP tidak ada yang perlu digeser ke
                     samping. --}}
                <div class="overflow-x-auto">
                    <table class="w-full table-auto max-md:block md:min-w-[720px]">
                        <thead class="max-md:hidden">
                            <tr class="bg-gray-50 text-left dark:bg-gray-800">
                                <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Siswa</th>
                                @foreach ($jenisPenilaian as $jenis)
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">
                                        {{ $jenis->label() }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>

                        <tbody class="max-md:block">
                            @forelse ($siswa as $s)
                                <tr class="border-b border-gray-200 last:border-0 dark:border-gray-800 max-md:block max-md:px-5 max-md:py-4">
                                    <td class="px-5 py-3 max-md:block max-md:border-0 max-md:p-0">
                                        <p class="font-medium text-brand-ink dark:text-white">{{ $s->nama }}</p>
                                        <p class="font-mono text-xs text-brand-muted dark:text-brand-faint">{{ $s->nis }}</p>
                                    </td>

                                    @foreach ($jenisPenilaian as $jenis)
                                        @php
                                            $nilaiLama = $nilaiTersimpan[$s->id][$jenis->value] ?? null;
                                        @endphp
                                        <td class="px-4 py-3 text-center max-md:block max-md:px-0 max-md:pb-0 max-md:pt-2 max-md:text-left">
                                            {{-- Label hanya tampil di HP: di tabel,
                                                 kepala kolom sudah menjelaskannya. --}}
                                            <label class="hidden text-xs font-medium text-brand-muted max-md:mb-1 max-md:block dark:text-brand-faint"
                                                for="n-{{ $s->id }}-{{ $jenis->value }}">
                                                {{ $jenis->label() }}
                                            </label>

                                            <input id="n-{{ $s->id }}-{{ $jenis->value }}"
                                                type="number" inputmode="decimal" step="0.01" min="0" max="{{ $skorMaks }}"
                                                name="nilai[{{ $s->id }}][{{ $jenis->value }}]"
                                                value="{{ old('nilai.' . $s->id . '.' . $jenis->value, $nilaiLama) }}"
                                                @disabled(! $rapor->status->bolehUbahNilai())
                                                placeholder="—"
                                                class="w-full rounded-lg border border-gray-300 bg-transparent px-3 py-2 text-center text-sm text-brand-ink outline-none transition focus:border-brand-500 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:text-white md:w-24 max-md:text-left">
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr class="max-md:block">
                                    <td colspan="{{ count($jenisPenilaian) + 1 }}" class="px-5 py-12 text-center max-md:block">
                                        <p class="text-sm font-semibold text-brand-ink dark:text-white">Kelas ini belum punya siswa</p>
                                        <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                                            Hubungi Admin TU untuk memasukkan data siswanya lebih dulu.
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($rapor->status->bolehUbahNilai() && $siswa->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                        <button type="submit"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-6 py-3 text-sm font-semibold text-white transition hover:bg-brand-600">
                            <x-icon name="check" class="h-4 w-4" />
                            Simpan Nilai
                        </button>
                        <p class="text-xs text-brand-muted dark:text-brand-faint">
                            Bisa disimpan berkali-kali — nilai yang sama tidak akan terhitung dua kali.
                        </p>
                    </div>
                @endif
            </div>
        </form>
    @endif

@endsection
