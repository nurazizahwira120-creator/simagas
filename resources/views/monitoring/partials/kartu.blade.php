{{--
    Satu kartu jadwal di Monitoring KBM Hari Ini — kelas ATAU ekskul.
    Data dari App\Services\MonitoringKbmHarian. Warna status disampaikan
    lewat tiga hal sekaligus (bingkai, pita, lencana bertulisan) supaya tetap
    terbaca oleh pembaca buta warna.
--}}
@php
    $g = $b['gaya'];
    $s = $b['sesi'];
    $kbm = $b['jenis'] === 'kbm';
@endphp
<article x-show="saring === 'semua' || saring === '{{ $b['kelompok'] }}'"
    class="kartu-angkat relative min-w-0 overflow-hidden rounded-2xl border-2 bg-brand-surface shadow-soft dark:bg-gray-900 {{ $g['kartu'] }}">

    @if ($kbm)
        {{-- Seluruh kartu kelas bisa diklik untuk membuka panel detail
             (lihat catatan "tombol membentang" di riwayat berkas ini). --}}
        <button type="button"
            class="absolute inset-0 z-10 h-full w-full cursor-pointer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-accent"
            aria-label="Lihat detail {{ $b['judul'] }} — {{ $b['subjudul'] }}"
            x-data
            @click="Livewire.dispatch('buka-detail-sesi', { jadwal: {{ $b['jadwal']->id }} })"></button>
    @endif

    <div class="h-1.5 w-full {{ $g['pita'] }}"></div>

    <div class="p-4 sm:p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                @unless ($kbm)
                    <span class="inline-block rounded bg-brand-500/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-accent-text">Ekskul</span>
                @endunless
                <h3 class="truncate text-base font-bold text-brand-ink dark:text-white sm:text-lg" title="{{ $b['judul'] }}">{{ $b['judul'] }}</h3>
                @if ($kbm)
                    <p class="mt-0.5 line-clamp-2 break-words text-sm font-medium text-brand-accent-text">{{ $b['subjudul'] }}</p>
                @endif
            </div>
            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $g['chip'] }}">
                <x-icon name="{{ $g['ikon'] }}" class="h-3 w-3" />
                {{ $g['label'] }}
            </span>
        </div>

        <dl class="mt-3 space-y-1.5 text-sm">
            <div class="flex min-w-0 items-center gap-2">
                <dt class="sr-only">{{ $kbm ? 'Guru' : 'Pembina' }}</dt>
                <x-icon name="briefcase" class="h-4 w-4 shrink-0 text-brand-muted" />
                <dd class="truncate text-brand-ink dark:text-gray-200" title="{{ $b['orang'] }}">{{ $b['orang'] }}</dd>
            </div>
            <div class="flex min-w-0 items-center gap-2">
                <dt class="sr-only">Waktu</dt>
                <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-muted" />
                <dd class="tabular-nums text-brand-muted">
                    {{ $b['jam'] }}
                    @if ($b['ruangan']) <span class="text-xs">&middot; {{ $b['ruangan'] }}</span> @endif
                </dd>
            </div>
            @if ($s)
                <div class="flex min-w-0 items-start gap-2">
                    <dt class="sr-only">Sesi</dt>
                    <x-icon name="qr-code" class="mt-0.5 h-4 w-4 shrink-0 text-brand-muted" />
                    <dd class="text-xs leading-relaxed text-brand-muted">
                        Scan {{ $s->waktu_mulai->format('H:i') }}
                        @if ($s->waktu_selesai)
                            &middot; selesai {{ $s->waktu_selesai->format('H:i') }}
                            @if ($s->diakhiri_oleh)
                                <span class="font-semibold text-orange-700 dark:text-orange-400">(dibantu {{ $s->pengakhir?->name ?? 'pimpinan' }})</span>
                            @endif
                        @endif
                        &middot; foto {{ $s->adaBukti() ? 'ada' : 'belum' }}
                        @if ($kbm)
                            &middot; jurnal {{ $b['jurnal_terisi'] ? 'terisi' : 'belum' }}
                        @else
                            &middot; absensi {{ $b['absen_jumlah'] ? $b['absen_hadir'] . '/' . $b['absen_jumlah'] . ' hadir' : 'belum' }}
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        {{-- Keterangan per status --}}
        @if ($b['status'] === 'belum_diakhiri')
            <p class="mt-3 rounded-lg bg-orange-500/10 px-3 py-2 text-xs leading-relaxed text-orange-800 dark:text-orange-300">
                Jadwal sudah selesai pukul {{ \Illuminate\Support\Str::after($b['jam'], '– ') }} tetapi sesi belum diakhiri &mdash; honor belum tercatat.
            </p>
        @elseif ($b['status'] === 'kosong')
            <p class="mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-xs leading-relaxed text-brand-danger-text">
                @if (! $kbm && ! ($b['pembina_tertaut'] ?? true))
                    Ekskul ini belum punya pembina yang tertaut ke akun.
                @else
                    Sudah {{ $b['menit_berjalan'] }} menit berjalan dan {{ $kbm ? 'guru' : 'pembina' }} belum men-scan QR.
                @endif
            </p>
        @elseif ($b['status'] === 'tidak_ada_sesi')
            <p class="mt-3 rounded-lg bg-red-500/10 px-3 py-2 text-xs leading-relaxed text-brand-danger-text">
                Jam ini sudah lewat tanpa scan QR &mdash; tidak ada sesi yang bisa diakhiri.
            </p>
        @elseif ($b['status'] === 'berhalangan')
            <p class="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:text-amber-400">
                {{ $b['berhalangan']['label'] ?? 'Guru berhalangan' }}
                @if (! empty($b['berhalangan']['rinci'])) ({{ $b['berhalangan']['rinci'] }}) @endif
                &mdash; {{ $b['jurnal_terisi'] ? 'absensi siswa sudah diisi guru inval.' : 'absensi siswa belum diisi guru inval.' }}
                @if (! $b['jurnal_terisi'] && Route::has($panelPrefix . '.guru-inval'))
                    <a href="{{ route($panelPrefix . '.guru-inval') }}" class="relative z-20 font-semibold underline">Buka Guru Inval</a>
                @endif
            </p>
        @elseif ($b['status'] === 'menunggu')
            <p class="mt-3 rounded-lg bg-brand-surface-muted px-3 py-2 text-xs leading-relaxed text-brand-muted">
                Baru dimulai. Belum dihitung terlambat sebelum {{ $toleransi }} menit.
            </p>
        @endif

        @if ($b['jumlah_bolos'] > 0)
            <p class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-400">
                {{ $b['jumlah_bolos'] }} {{ $kbm ? 'siswa alpa/bolos' : 'anggota alpa' }}
            </p>
        @endif

        @if ($bolehAkhiri && $b['bisa_diakhiri'])
            @include('monitoring.partials.form-akhiri', ['jenis' => $b['jenis'], 'idSesi' => $s->id, 'nama' => $b['judul'] . ($kbm ? ' — ' . $b['subjudul'] : '')])
        @endif
    </div>
</article>
