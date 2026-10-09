{{--
    Kartu EKSKUL di Live Monitoring — digabung di grid yang sama dengan
    kartu kelas, diberi lencana "Ekskul". Statusnya dari sesi Mulai/Akhiri
    pembina (MonitoringController::ekskulBerlangsung), bukan jurnal kelas.
    Tidak bisa diklik: detailnya cukup ditulis langsung di kartu.
--}}
@php
    $e = $b['ekskul'];
    $g = $b['gaya'];
    $s = $b['sesi'];
@endphp
<article class="kartu-angkat relative min-w-0 overflow-hidden rounded-2xl border-2 bg-brand-surface shadow-soft dark:bg-gray-900 {{ $g['kartu'] }}">
    <div class="h-1.5 w-full {{ $g['pita'] }}"></div>

    <div class="p-5">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <span class="inline-block rounded bg-brand-500/10 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-brand-accent-text">Ekskul</span>
                <h3 class="mt-1 truncate text-lg font-bold text-brand-ink dark:text-white">{{ $e->nama_ekskul }}</h3>
            </div>

            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $g['chip'] }}">
                <x-icon name="{{ $g['ikon'] }}" class="h-3 w-3" />
                {{ $g['label'] }}
            </span>
        </div>

        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex min-w-0 items-center gap-2">
                <dt class="sr-only">Pembina</dt>
                <x-icon name="briefcase" class="h-4 w-4 shrink-0 text-brand-muted" />
                <dd class="truncate text-brand-ink dark:text-gray-200">{{ $e->namaPembina() }}</dd>
            </div>
            <div class="flex min-w-0 items-center gap-2">
                <dt class="sr-only">Waktu</dt>
                <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-muted" />
                <dd class="tabular-nums text-brand-muted">
                    {{ $e->rentangJam() }}
                    <span class="text-xs">&middot; berjalan {{ $b['menit_berjalan'] }} menit</span>
                </dd>
            </div>
            @if ($s)
                <div class="flex min-w-0 items-center gap-2">
                    <dt class="sr-only">Sesi</dt>
                    <x-icon name="qr-code" class="h-4 w-4 shrink-0 text-brand-muted" />
                    <dd class="text-brand-muted">
                        Scan QR {{ $s->waktu_mulai->format('H:i') }}
                        &middot; absensi {{ $b['absen_jumlah'] ? $b['absen_hadir'] . '/' . $b['absen_jumlah'] . ' hadir' : 'belum diisi' }}
                        &middot; foto {{ $s->adaBukti() ? 'ada' : 'belum' }}
                    </dd>
                </div>
            @endif
        </dl>

        @if ($b['status'] === 'kosong')
            <p class="mt-4 rounded-lg bg-red-500/10 px-3 py-2 text-xs leading-relaxed text-brand-danger-text">
                @if (! $b['pembina_tertaut'])
                    Ekskul ini belum punya pembina yang tertaut ke akun, jadi sesinya tidak bisa dimulai.
                @else
                    Sudah {{ $b['menit_berjalan'] }} menit berjalan dan pembina belum men-scan QR ekskul.
                @endif
            </p>
        @elseif ($b['status'] === 'perhatian')
            <p class="mt-4 rounded-lg bg-amber-500/10 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:text-amber-400">
                Sesi berjalan &mdash; ada {{ $b['jumlah_bolos'] }} anggota berstatus alpa.
            </p>
        @elseif ($b['status'] === 'menunggu')
            <p class="mt-4 rounded-lg bg-brand-surface-muted px-3 py-2 text-xs leading-relaxed text-brand-muted">
                Baru dimulai. Belum dihitung terlambat sebelum {{ $toleransi }} menit.
            </p>
        @endif
    </div>
</article>
