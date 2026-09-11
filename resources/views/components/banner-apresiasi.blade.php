{{--
    Banner Apresiasi Emas (Hall of Fame).

    Dipakai BERSAMA oleh dashboard guru dan halaman rapor wali murid — satu
    berkas, bukan dua salinan. Dua salinan berarti perubahan kalimat atau warna
    di satu tempat diam-diam tidak ikut di tempat lain, dan yang ketinggalan
    justru yang jarang dibuka.

    Pemakaian:
        <x-banner-apresiasi :penghargaan="$penghargaan" />

    Aman dipanggil dengan null: kalau tidak ada penghargaan, komponen ini tidak
    menggambar apa pun. Jadi pemanggilnya tidak perlu membungkusnya dengan @if.
--}}
@props(['penghargaan' => null])

@if ($penghargaan)
    @php
        $untukGuru = $penghargaan->peran === \App\Models\Penghargaan::PERAN_GURU;
    @endphp

    <div role="status"
        class="relative mb-6 overflow-hidden rounded-2xl border border-amber-300/60 bg-gradient-to-br from-amber-50 via-yellow-50 to-amber-100 p-5 shadow-theme-sm dark:border-amber-500/30 dark:from-amber-950/40 dark:via-yellow-950/30 dark:to-amber-900/30 sm:p-6">

        {{-- Kilau dekoratif. Murni CSS — tidak menambah satu pun permintaan
             gambar, dan pointer-events-none supaya tidak pernah menghalangi
             sentuhan pada teks di bawahnya. --}}
        <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-amber-300/30 blur-3xl dark:bg-amber-500/20"></div>
        <div class="pointer-events-none absolute -bottom-20 -left-12 h-44 w-44 rounded-full bg-yellow-200/40 blur-3xl dark:bg-yellow-600/10"></div>

        <div class="relative flex flex-col gap-4 sm:flex-row sm:items-start">

            {{-- Medali. SVG inline, bukan berkas gambar: ikonnya ikut berubah
                 warna mengikuti tema dan tidak pernah gagal dimuat. --}}
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-amber-400 to-yellow-600 shadow-lg shadow-amber-500/30">
                <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2.5l2.6 5.27 5.82.85-4.21 4.1.99 5.79L12 15.78l-5.2 2.73.99-5.79-4.21-4.1 5.82-.85L12 2.5z" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-amber-700 dark:text-amber-400">
                    Hall of Fame &middot; {{ $penghargaan->labelPeriode() }}
                </p>

                <h2 class="mt-1 text-xl font-extrabold tracking-tight text-amber-900 dark:text-amber-100 sm:text-2xl">
                    {{ $untukGuru ? 'Guru Teladan Bulan Ini' : 'Bintang Kelas!' }}
                </h2>

                <p class="mt-2 text-sm leading-relaxed text-amber-900/80 dark:text-amber-100/80">
                    {{ $penghargaan->pesan_apresiasi }}
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center rounded-full bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-800 ring-1 ring-inset ring-amber-500/30 dark:text-amber-300">
                        {{ $penghargaan->kategori }}
                    </span>

                    @if ($penghargaan->nilai_acuan !== null)
                        <span class="inline-flex items-center rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-500/20 dark:bg-white/10 dark:text-amber-200">
                            {{ $untukGuru ? 'Skor kedisiplinan' : 'Rata-rata' }}
                            {{ number_format((float) $penghargaan->nilai_acuan, $untukGuru ? 0 : 2, ',', '.') }}
                        </span>
                    @endif

                    @if (! $untukGuru && $penghargaan->kelas)
                        <span class="inline-flex items-center rounded-full bg-white/70 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-500/20 dark:bg-white/10 dark:text-amber-200">
                            Kelas {{ $penghargaan->kelas->nama_kelas }}
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
