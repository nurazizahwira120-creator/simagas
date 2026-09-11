@extends('layouts.app')

@section('title', 'Persetujuan Rapor')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">Persetujuan Rapor</h1>
        <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">
            Selama berstatus <strong>Draft</strong>, guru masih bisa mengubah nilai. Begitu diajukan,
            nilainya terkunci. Setelah disetujui, rapor terbit dan barulah bisa dilihat wali murid —
            sekaligus menetapkan <strong>Bintang Kelas</strong> secara otomatis.
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

    @if (! $periode)
        <div class="rounded-2xl border border-warning-200 bg-warning-500/10 p-5 dark:border-warning-500/30" role="alert">
            <p class="text-sm font-bold text-warning-700 dark:text-warning-400">Belum ada Tahun Ajaran yang diaktifkan.</p>
            <p class="mt-1 text-sm text-warning-700 dark:text-warning-400">
                Buka menu <strong>Tahun Ajaran</strong> dan aktifkan satu periode lebih dulu — seluruh
                nilai dan rapor menempel pada periode itu.
            </p>
        </div>
    @else

        <div class="rounded-2xl border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                <div>
                    <h3 class="font-semibold text-brand-ink dark:text-white">Status Rapor per Kelas</h3>
                    <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                        Periode aktif: <span class="font-semibold">{{ $periode->label() }}</span>
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-auto max-md:block md:min-w-[820px]">
                    <thead class="max-md:hidden">
                        <tr class="bg-gray-50 text-left dark:bg-gray-800">
                            <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Kelas</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Wali Kelas</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Nilai Masuk</th>
                            <th class="px-4 py-3 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Status</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Tindakan</th>
                        </tr>
                    </thead>

                    <tbody class="max-md:block">
                        @forelse ($baris as $b)
                            @php
                                $kelas = $b['kelas'];
                                $status = $b['status'];
                                $r = $b['rapor'];
                            @endphp
                            <tr class="border-b border-gray-200 last:border-0 dark:border-gray-800 max-md:block max-md:px-5 max-md:py-4">

                                <td class="px-5 py-4 max-md:block max-md:p-0">
                                    <p class="font-semibold text-brand-ink dark:text-white">{{ $kelas->nama_kelas }}</p>
                                    <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $kelas->siswa_count }} siswa</p>
                                </td>

                                <td class="px-4 py-4 text-sm text-brand-muted max-md:block max-md:p-0 max-md:pt-1 dark:text-brand-faint">
                                    {{ $kelas->waliKelas?->name ?? '— belum ditetapkan —' }}
                                </td>

                                <td class="px-4 py-4 text-center max-md:block max-md:p-0 max-md:pt-2 max-md:text-left">
                                    <span class="text-sm font-semibold {{ $b['jumlah_nilai'] > 0 ? 'text-brand-ink dark:text-white' : 'text-error-600' }}">
                                        {{ number_format($b['jumlah_nilai'], 0, ',', '.') }} nilai
                                    </span>
                                    @if ($b['jumlah_nilai'] === 0)
                                        <p class="text-xs text-error-600">belum ada yang bisa disetujui</p>
                                    @endif
                                </td>

                                <td class="px-4 py-4 max-md:block max-md:p-0 max-md:pt-2">
                                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $status->kelasBadge() }}">
                                        {{ $status->label() }}
                                    </span>

                                    @if ($r?->tanggal_persetujuan)
                                        <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                                            oleh {{ $r->penyetuju?->name ?? '—' }},
                                            {{ $r->tanggal_persetujuan->translatedFormat('d M Y H:i') }}
                                        </p>
                                    @elseif ($r?->diajukan_pada)
                                        <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                                            diajukan {{ $r->pengaju?->name ?? '—' }},
                                            {{ $r->diajukan_pada->translatedFormat('d M Y H:i') }}
                                        </p>
                                    @endif

                                    @if ($r?->catatan)
                                        <p class="mt-1 text-xs text-warning-700 dark:text-warning-400">Catatan: {{ $r->catatan }}</p>
                                    @endif
                                </td>

                                <td class="px-5 py-4 max-md:block max-md:p-0 max-md:pt-3">
                                    <div class="flex flex-wrap justify-end gap-2 max-md:justify-start">

                                        @if ($bolehAjukan && $status === \App\Enums\StatusRapor::Draft)
                                            <form method="POST" action="{{ route($panelPrefix . '.rapor.ajukan', $kelas) }}">
                                                @csrf
                                                <button type="submit" @disabled($b['jumlah_nilai'] === 0)
                                                    class="inline-flex items-center gap-2 rounded-lg border border-brand-500/40 px-4 py-2 text-sm font-semibold text-brand-accent-text transition hover:bg-brand-500/10 disabled:cursor-not-allowed disabled:opacity-40">
                                                    <x-icon name="upload" class="h-4 w-4" />
                                                    Ajukan
                                                </button>
                                            </form>
                                        @endif

                                        @if ($bolehSetujui && $status === \App\Enums\StatusRapor::Menunggu)
                                            {{-- onsubmit confirm(): persetujuan menerbitkan rapor ke
                                                 seluruh wali murid kelas itu sekaligus. Satu klik tidak
                                                 sengaja bukan sesuatu yang bisa ditarik kembali diam-diam. --}}
                                            <form method="POST" action="{{ route($panelPrefix . '.rapor.setujui', $kelas) }}"
                                                onsubmit="return confirm('Setujui dan terbitkan rapor kelas {{ $kelas->nama_kelas }}? Nilainya akan langsung terlihat oleh wali murid.');">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex items-center gap-2 rounded-lg bg-success-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-success-700">
                                                    <x-icon name="check-circle" class="h-4 w-4" />
                                                    Setujui &amp; Terbitkan
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route($panelPrefix . '.rapor.kembalikan', $kelas) }}">
                                                @csrf
                                                <button type="submit"
                                                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                                                    <x-icon name="arrow-left" class="h-4 w-4" />
                                                    Kembalikan
                                                </button>
                                            </form>
                                        @endif

                                        @if ($status === \App\Enums\StatusRapor::Disetujui)
                                            <span class="inline-flex items-center gap-1.5 text-sm font-semibold text-success-600">
                                                <x-icon name="check" class="h-4 w-4" />
                                                Sudah terbit
                                            </span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr class="max-md:block">
                                <td colspan="5" class="px-5 py-12 text-center max-md:block">
                                    <p class="text-sm font-semibold text-brand-ink dark:text-white">Belum ada kelas</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-gray-200 px-5 py-4 text-xs leading-relaxed text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                <p>
                    <span class="font-semibold">Bintang Kelas</span> ditetapkan otomatis saat rapor disetujui:
                    siswa dengan nilai rata-rata tertinggi pada periode ini, dengan syarat minimal
                    {{ \App\Services\BintangKelasService::MIN_NILAI }} nilai tercatat. Bila seri, yang menang adalah
                    yang nilainya lebih lengkap.
                </p>
                <p class="mt-1">
                    Apresiasinya dikirim ke akun <span class="font-semibold">wali murid</span> anak tersebut —
                    siswa tidak punya akun login di sistem ini.
                </p>
            </div>
        </div>
    @endif

@endsection
