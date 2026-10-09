{{--
    Daftar rincian honor satu orang satu bulan — dipakai halaman guru
    (Rincian Pendapatan) dan halaman Kepsek/Admin (Honor Guru > Rincian).

    Variabel: $rincian dari App\Services\PencatatHonor::rincian().
--}}
@php
    use App\Models\HonorMengajar;
    use App\Services\AturanHonor;

    $gayaPeran = [
        HonorMengajar::PERAN_MENGAJAR => 'bg-success-500/10 text-success-700 dark:text-success-400',
        HonorMengajar::PERAN_INVAL => 'bg-warning-500/10 text-warning-700 dark:text-warning-400',
        HonorMengajar::PERAN_GURU_ASLI => 'bg-gray-500/10 text-gray-600 dark:text-gray-300',
        HonorMengajar::PERAN_EKSKUL => 'bg-brand-500/10 text-brand-accent-text',
    ];
@endphp

@if ($rincian['daftar']->isEmpty())
    <div class="px-6 py-10 text-center">
        <x-icon name="cash" class="mx-auto h-10 w-10 text-brand-faint" />
        <p class="mt-3 text-sm font-semibold text-brand-ink dark:text-white">Belum ada honor di bulan ini</p>
        <p class="mt-1 text-xs text-brand-muted">Honor bertambah setiap kali sesi mengajar atau sesi ekskul diakhiri, atau absensi kelas inval disimpan.</p>
    </div>
@else
    <ul class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($rincian['daftar']->groupBy(fn ($h) => $h->tanggal->toDateString()) as $tanggal => $hariItu)
            <li class="bg-gray-50 px-5 py-2 text-xs font-semibold uppercase tracking-wide text-brand-muted dark:bg-white/[0.03]">
                {{ \Illuminate\Support\Carbon::parse($tanggal)->translatedFormat('l, d F Y') }}
                <span class="float-right normal-case tracking-normal">{{ AturanHonor::rupiah((int) $hariItu->sum('nominal')) }}</span>
            </li>
            @foreach ($hariItu as $h)
                <li class="flex items-start gap-3 px-5 py-3.5" wire:key="honor-{{ $h->id }}">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-brand-ink dark:text-white">{{ $h->rincian }}</p>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-brand-muted">
                            <span class="rounded-full px-2 py-0.5 font-semibold {{ $gayaPeran[$h->peran] ?? $gayaPeran[HonorMengajar::PERAN_MENGAJAR] }}">{{ $h->labelPeran() }}</span>
                            <span class="font-mono">
                                {{ $h->jp }} JP × {{ AturanHonor::rupiah($h->tarif_per_jp) }}@if ($h->persen !== 100) × {{ $h->persen }}%@endif
                            </span>
                        </p>
                    </div>
                    <p class="shrink-0 text-sm font-bold text-success-600 dark:text-success-400">+{{ AturanHonor::rupiah($h->nominal) }}</p>
                </li>
            @endforeach
        @endforeach
    </ul>
@endif
