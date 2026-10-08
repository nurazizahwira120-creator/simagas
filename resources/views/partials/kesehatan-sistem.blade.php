{{--
    Kesehatan penjadwal (cron) — hanya di dashboard Super Admin.
    Lihat App\Services\DenyutPenjadwal untuk alasannya.

    Saat semuanya normal, yang tampil hanya SATU baris tipis — dashboard tidak
    perlu dibebani kartu besar untuk kabar "tidak ada masalah".
--}}
@php
    /** @var array{terakhir: ?\Illuminate\Support\Carbon, sehat: bool, antrean_gagal: int, antrean_tertahan: int} $kesehatan */
    $masalahAntrean = $kesehatan['antrean_gagal'] > 0 || $kesehatan['antrean_tertahan'] > 0;
@endphp

@if ($kesehatan['sehat'] && ! $masalahAntrean)
    <p class="mb-6 flex items-center gap-2 text-xs text-brand-muted" data-kesehatan="sehat">
        <span class="inline-block h-2 w-2 rounded-full bg-success-500"></span>
        Penjadwal otomatis berjalan normal &middot; terakhir {{ $kesehatan['terakhir']->format('H:i') }}
    </p>
@else
    <div class="mb-6 rounded-2xl border border-warning-500/40 bg-warning-500/10 p-4 text-sm text-gray-800 dark:text-gray-100" data-kesehatan="masalah" role="status">
        <p class="flex items-center gap-2 font-semibold text-warning-600 dark:text-warning-400">
            <x-icon name="exclamation-triangle" class="h-4 w-4" />
            Perlu dicek: proses otomatis sistem
        </p>

        <ul class="mt-2 ml-2 list-disc space-y-1 pl-5">
            @if (! $kesehatan['sehat'])
                <li>
                    @if ($kesehatan['terakhir'])
                        Penjadwal terakhir berjalan <strong>{{ $kesehatan['terakhir']->diffForHumans() }}</strong>
                        ({{ $kesehatan['terakhir']->translatedFormat('d M, H:i') }}).
                    @else
                        Penjadwal <strong>belum pernah tercatat berjalan</strong>.
                    @endif
                    WhatsApp, pengingat Akhiri Sesi, penandaan alpa, dan laporan bulanan ikut berhenti.
                    Periksa Cron Jobs cPanel: harus ada perintah <code class="rounded bg-white/60 px-1 dark:bg-black/30">schedule:run</code> setiap menit.
                </li>
            @endif
            @if ($kesehatan['antrean_tertahan'] > 0)
                <li><strong>{{ $kesehatan['antrean_tertahan'] }}</strong> kiriman masih menunggu lebih dari 10 menit di antrean.</li>
            @endif
            @if ($kesehatan['antrean_gagal'] > 0)
                <li><strong>{{ $kesehatan['antrean_gagal'] }}</strong> kiriman (WA/notifikasi HP) gagal dalam 7 hari terakhir — cek <code class="rounded bg-white/60 px-1 dark:bg-black/30">storage/logs</code>.</li>
            @endif
        </ul>
    </div>
@endif
