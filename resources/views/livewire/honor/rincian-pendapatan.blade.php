{{--
    View App\Livewire\Honor\RincianPendapatan — honor milik guru/staf yang login.

    Bulan berjalan disegarkan tiap 60 detik (wire:poll) supaya honor yang
    baru tercatat dari HP lain / tab lain ikut muncul tanpa muat ulang.
--}}
@php
    use App\Models\HonorMengajar;
    use App\Services\AturanHonor;

    $aturan = $this->aturan;
    $rincian = $this->rincian;
    $perPeran = $rincian['per_peran'];
@endphp
<div class="mx-auto w-full max-w-4xl" @if ($this->bulanIni()) wire:poll.60s.visible @endif>

    @if (! $aturan['aktif'])
        <div class="mb-6 flex w-full border-l-4 border-warning-500 bg-warning-500/10 px-5 py-4 shadow-md" role="status">
            <x-icon name="clock" class="mr-4 mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
            <div class="min-w-0">
                <h5 class="mb-1 font-semibold text-brand-ink dark:text-white">Pencatatan honor sedang tidak aktif</h5>
                <p class="text-sm leading-relaxed text-brand-muted">Kepala Sekolah belum menyalakan fitur ini. Honor yang sudah tercatat tetap bisa dilihat di bawah.</p>
            </div>
        </div>
    @endif

    {{-- ============ PILIH BULAN ============ --}}
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <label class="block w-full sm:w-64">
            <span class="mb-1 block text-xs font-semibold text-brand-muted">Bulan</span>
            <select wire:model.live="bulan"
                class="h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                @foreach ($this->pilihanBulan() as $nilai => $label)
                    <option value="{{ $nilai }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>

    {{-- ============ RINGKASAN ============ --}}
    {{-- Empat kartu rincian: 2 kolom di HP, 4 kolom di layar lebar. --}}
    <div class="mb-6 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-4">
        <div class="col-span-2 rounded-2xl border border-success-500/30 bg-success-500/10 p-5 sm:col-span-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-success-700 dark:text-success-400">Total pendapatan {{ $this->bulanDipakai()->translatedFormat('F Y') }}</p>
            <p class="mt-1 text-3xl font-extrabold tracking-tight text-brand-ink dark:text-white">{{ AturanHonor::rupiah($rincian['total']) }}</p>
            <p class="mt-1 text-xs text-brand-muted">
                {{ $rincian['jp'] }} JP diajar &middot; tarif Anda {{ AturanHonor::rupiah($this->tarifSaya) }} per JP
            </p>
        </div>

        @foreach ([
            HonorMengajar::PERAN_MENGAJAR => ['Mengajar', 'sesi diakhiri'],
            HonorMengajar::PERAN_INVAL => ['Inval', 'kelas digantikan'],
            HonorMengajar::PERAN_GURU_ASLI => ['Bagian saat diinval', 'jam diajar inval'],
            HonorMengajar::PERAN_EKSKUL => ['Ekskul', 'sesi ekskul'],
        ] as $peran => [$judul, $satuan])
            <div class="min-w-0 rounded-2xl border border-gray-200 bg-brand-surface p-3 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                <p class="text-[11px] font-semibold leading-tight text-brand-muted sm:text-xs">{{ $judul }}</p>
                <p class="mt-1 truncate text-sm font-bold text-brand-ink dark:text-white sm:text-lg">{{ AturanHonor::rupiah($perPeran[$peran]['nominal']) }}</p>
                <p class="text-[11px] leading-tight text-brand-muted sm:text-xs">{{ $perPeran[$peran]['jumlah'] }} {{ $satuan }}</p>
            </div>
        @endforeach
    </div>

    {{-- ============ DAFTAR ============ --}}
    <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
            <h3 class="font-semibold text-brand-ink dark:text-white">Rincian per jam pelajaran</h3>
        </div>
        @include('livewire.honor.partials.daftar-rincian', ['rincian' => $rincian])
    </div>

    {{-- ============ CARA MENGHITUNG ============ --}}
    <details class="mt-6 rounded-lg border border-gray-200 bg-brand-surface px-5 py-4 text-sm dark:border-gray-800 dark:bg-gray-900">
        <summary class="cursor-pointer font-semibold text-brand-ink dark:text-white">Bagaimana honor dihitung?</summary>
        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-brand-muted">
            <li><strong>Mengajar:</strong> setelah Anda menekan <em>Akhiri Sesi</em>, honor = jumlah JP jadwal × tarif per JP Anda.</li>
            <li><strong>Inval:</strong> setelah Anda menyimpan absensi kelas yang Anda gantikan, Anda mendapat {{ $aturan['persen_inval'] }}% dari honor jam tersebut.</li>
            <li><strong>Ekskul:</strong> setelah Anda menekan <em>Akhiri Sesi Ekskul</em>, honor = JP ekskul × {{ $aturan['tarif_ekskul'] !== null ? 'tarif ekskul ' . AturanHonor::rupiah($aturan['tarif_ekskul']) : 'tarif per JP Anda' }}.</li>
            <li><strong>Bagian saat diinval:</strong> kalau kelas Anda diajar guru inval, Anda tetap mendapat sisa {{ 100 - $aturan['persen_inval'] }}%.</li>
            <li>Tarif yang dipakai adalah tarif pada saat mengajar. Perubahan tarif tidak mengubah honor yang sudah tercatat.</li>
        </ul>
    </details>
</div>
