{{--
    View App\Livewire\Honor\HonorGuru — Kepala Sekolah & Super Admin.

    Dua tab: Rekap Bulanan (semua guru + rincian per orang) dan Pengaturan
    (sakelar fitur, tarif umum, bagian inval, tarif khusus per orang).
--}}
@php
    use App\Services\AturanHonor;

    $aturan = $this->aturan;
    $kelasInput = 'h-11 w-full rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 focus:border-brand-300 focus:outline-none focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $kelasTombol = 'inline-flex items-center justify-center gap-2 rounded-md bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-500/90 disabled:opacity-60';
@endphp
<div class="mx-auto w-full max-w-6xl">

    @if ($notif)
        @php
            $gaya = $notif['tipe'] === 'ok'
                ? ['garis' => 'border-success-500 bg-success-500/10', 'ikon' => 'check-circle', 'warnaIkon' => 'text-success-500']
                : ['garis' => 'border-error-500 bg-error-500/10', 'ikon' => 'x-circle', 'warnaIkon' => 'text-error-500'];
        @endphp
        <div wire:key="notif-honor-{{ md5($notif['pesan']) }}-{{ now()->format('Hisu') }}"
            class="mb-6 flex w-full border-l-4 px-5 py-4 shadow-md {{ $gaya['garis'] }}" role="status">
            <x-icon name="{{ $gaya['ikon'] }}" class="mr-4 mt-0.5 h-5 w-5 shrink-0 {{ $gaya['warnaIkon'] }}" />
            <div class="min-w-0">
                <h5 class="mb-1 font-semibold text-brand-ink dark:text-white">{{ $notif['judul'] }}</h5>
                <p class="text-sm leading-relaxed text-brand-muted">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    {{-- ============ TAB ============ --}}
    <div class="mb-5 flex gap-2 border-b border-gray-200 dark:border-gray-800" role="tablist">
        @foreach (['rekap' => 'Rekap Bulanan', 'pengaturan' => 'Pengaturan Tarif'] as $kunci => $label)
            <button type="button" wire:click="$set('tab', '{{ $kunci }}')" role="tab" aria-selected="{{ $tab === $kunci ? 'true' : 'false' }}"
                class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold transition {{ $tab === $kunci ? 'border-brand-500 text-brand-600 dark:text-brand-400' : 'border-transparent text-brand-muted hover:text-brand-ink' }}">
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if (! $aturan['aktif'])
        <div class="mb-5 flex w-full border-l-4 border-warning-500 bg-warning-500/10 px-5 py-4" role="status">
            <x-icon name="clock" class="mr-4 mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
            <p class="text-sm leading-relaxed text-brand-muted">
                <strong class="text-brand-ink dark:text-white">Fitur honor masih mati.</strong>
                Belum ada honor yang dicatat — Rincian Pendapatan setiap guru masih kosong.
                Nyalakan di tab <button type="button" wire:click="$set('tab', 'pengaturan')" class="font-semibold text-brand-600 underline">Pengaturan Tarif</button>.
            </p>
        </div>
    @endif

    @if ($tab === 'pengaturan')

        {{-- ============ PENGATURAN UMUM ============ --}}
        <form wire:submit="simpanPengaturan"
            class="mb-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Pengaturan umum</h3>
                <p class="mt-1 text-sm text-brand-muted">Berlaku untuk semua guru yang tidak diberi tarif khusus.</p>
            </div>

            <div class="grid gap-5 px-6 py-5 sm:grid-cols-2">
                <label class="flex items-start gap-3 sm:col-span-2">
                    <input type="checkbox" wire:model="aktif" class="mt-1 h-5 w-5 rounded border-gray-300 text-brand-500 focus:ring-brand-500">
                    <span>
                        <span class="block text-sm font-semibold text-brand-ink dark:text-white">Catat honor secara otomatis</span>
                        <span class="block text-xs text-brand-muted">Kalau dimatikan, tidak ada honor baru yang dicatat. Honor yang sudah tercatat tetap terlihat oleh guru.</span>
                    </span>
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-brand-ink dark:text-white">Tarif umum per 1 JP (Rp)</span>
                    <input type="number" inputmode="numeric" min="0" max="{{ AturanHonor::MAKS_TARIF }}" step="500" wire:model.live.debounce.400ms="tarifUmum" class="{{ $kelasInput }}">
                    @error('tarifUmum') <span class="mt-1 block text-xs text-error-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-sm font-semibold text-brand-ink dark:text-white">Bagian guru inval (%)</span>
                    <input type="number" inputmode="numeric" min="0" max="100" step="5" wire:model.live.debounce.400ms="persenInval" class="{{ $kelasInput }}">
                    @error('persenInval') <span class="mt-1 block text-xs text-error-600">{{ $message }}</span> @enderror
                </label>

                <label class="block sm:col-span-2">
                    <span class="mb-1 block text-sm font-semibold text-brand-ink dark:text-white">Tarif ekskul per 1 JP (Rp) <span class="text-xs font-normal text-brand-muted">— opsional</span></span>
                    <input type="number" inputmode="numeric" min="0" max="{{ AturanHonor::MAKS_TARIF }}" step="500" wire:model="tarifEkskul"
                        placeholder="Kosongkan = ikut tarif pembinanya" class="{{ $kelasInput }} sm:w-80">
                    <span class="mt-1 block text-xs text-brand-muted">Dipakai saat pembina menekan Akhiri Sesi Ekskul. JP ekskul diatur di Jadwal Ekskul (kosong = otomatis dari jam).</span>
                    @error('tarifEkskul') <span class="mt-1 block text-xs text-error-600">{{ $message }}</span> @enderror
                </label>

                {{-- Contoh hitungan langsung dari angka yang sedang diketik. --}}
                @php
                    $tarifContoh = max(0, (int) $tarifUmum);
                    $persenContoh = max(0, min(100, (int) $persenInval));
                    $totalContoh = 2 * $tarifContoh;
                    $invalContoh = (int) round($totalContoh * $persenContoh / 100);
                @endphp
                <div class="rounded-lg bg-gray-50 px-4 py-3 text-xs leading-relaxed text-brand-muted dark:bg-white/[0.03] sm:col-span-2">
                    <strong class="text-brand-ink dark:text-white">Contoh:</strong>
                    jadwal 2 JP × {{ AturanHonor::rupiah($tarifContoh) }} = <strong>{{ AturanHonor::rupiah($totalContoh) }}</strong>.
                    Kalau diajar guru inval: inval mendapat <strong>{{ AturanHonor::rupiah($invalContoh) }}</strong> ({{ $persenContoh }}%),
                    guru asli <strong>{{ AturanHonor::rupiah($totalContoh - $invalContoh) }}</strong> ({{ 100 - $persenContoh }}%).
                </div>
            </div>

            <div class="flex justify-end border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                <button type="submit" wire:loading.attr="disabled" wire:target="simpanPengaturan" class="{{ $kelasTombol }}">
                    <x-icon name="check-circle" class="h-5 w-5" />
                    <span wire:loading.remove wire:target="simpanPengaturan">Simpan Pengaturan</span>
                    <span wire:loading wire:target="simpanPengaturan">Menyimpan…</span>
                </button>
            </div>
        </form>

        {{-- ============ TARIF KHUSUS ============ --}}
        <form wire:submit="simpanTarifKhusus"
            class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="font-semibold text-brand-ink dark:text-white">Tarif khusus per orang</h3>
                    <p class="mt-1 text-sm text-brand-muted">Kosongkan untuk memakai tarif umum ({{ AturanHonor::rupiah($aturan['tarif']) }}).</p>
                </div>
                <input type="search" wire:model.live.debounce.300ms="cari" placeholder="Cari nama…" class="{{ $kelasInput }} sm:w-64">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-brand-muted dark:bg-white/[0.03]">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Nama</th>
                            <th class="px-4 py-3 font-semibold">Peran</th>
                            <th class="px-6 py-3 font-semibold">Tarif per JP (Rp)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->daftarOrang as $orang)
                            <tr wire:key="tarif-{{ $orang->id }}">
                                <td class="px-6 py-3 font-medium text-brand-ink dark:text-white">{{ $orang->pegawai?->nama ?? $orang->name }}</td>
                                <td class="px-4 py-3 text-brand-muted">{{ $orang->role->label() }}</td>
                                <td class="px-6 py-2">
                                    <input type="number" inputmode="numeric" min="0" max="{{ AturanHonor::MAKS_TARIF }}" step="500"
                                        wire:model="tarifKhusus.{{ $orang->id }}" placeholder="ikut umum"
                                        class="{{ $kelasInput }} sm:w-48">
                                    @error('tarifKhusus.' . $orang->id) <span class="mt-1 block text-xs text-error-600">{{ $message }}</span> @enderror
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-sm text-brand-muted">Tidak ada nama yang cocok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                <button type="submit" wire:loading.attr="disabled" wire:target="simpanTarifKhusus" class="{{ $kelasTombol }}">
                    <x-icon name="check-circle" class="h-5 w-5" />
                    <span wire:loading.remove wire:target="simpanTarifKhusus">Simpan Tarif Khusus</span>
                    <span wire:loading wire:target="simpanTarifKhusus">Menyimpan…</span>
                </button>
            </div>
        </form>

    @elseif ($this->orangDilihat)

        {{-- ============ RINCIAN SATU ORANG ============ --}}
        @php $orang = $this->orangDilihat; $rincian = $this->rincianDilihat; @endphp
        <div class="mb-5 flex flex-wrap items-center gap-4 rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="min-w-0 flex-1">
                <p class="text-lg font-bold text-brand-ink dark:text-white">{{ $orang['nama'] }}</p>
                <p class="mt-1 text-sm text-brand-muted">
                    {{ $this->bulanDipakai()->translatedFormat('F Y') }} &middot; {{ $rincian['jp'] }} JP diajar &middot;
                    tarif {{ AturanHonor::rupiah($orang['tarif']) }}/JP{{ $orang['tarif_khusus'] ? ' (khusus)' : '' }}
                </p>
            </div>
            <p class="text-2xl font-extrabold text-success-600 dark:text-success-400">{{ AturanHonor::rupiah($rincian['total']) }}</p>
            <button type="button" wire:click="tutupRincian"
                class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                Kembali ke rekap
            </button>
        </div>
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            @include('livewire.honor.partials.daftar-rincian', ['rincian' => $rincian])
        </div>

    @else

        {{-- ============ REKAP BULANAN ============ --}}
        @php $rekap = $this->rekap; @endphp
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <label class="block w-full sm:w-64">
                <span class="mb-1 block text-xs font-semibold text-brand-muted">Bulan</span>
                <select wire:model.live="bulan" class="{{ $kelasInput }}">
                    @foreach ($this->pilihanBulan() as $nilai => $label)
                        <option value="{{ $nilai }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">Total honor bulan ini</p>
                <p class="text-2xl font-extrabold text-brand-ink dark:text-white">{{ AturanHonor::rupiah($rekap['total']) }}</p>
            </div>
        </div>

        <div class="overflow-x-auto rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-brand-muted dark:bg-white/[0.03]">
                    <tr>
                        <th class="px-5 py-3 font-semibold">Nama</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right font-semibold">Tarif/JP</th>
                        <th class="px-3 py-3 text-center font-semibold">JP Ajar</th>
                        <th class="px-3 py-3 text-center font-semibold">JP Inval</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right font-semibold">Mengajar</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right font-semibold">Inval</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right font-semibold">Saat Diinval</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right font-semibold">Ekskul</th>
                        <th class="whitespace-nowrap px-3 py-3 text-right font-semibold">Total</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rekap['baris'] as $b)
                        <tr wire:key="rekap-{{ $b['user']->id }}" class="{{ $b['total'] === 0 ? 'text-brand-faint' : '' }}">
                            <td class="px-5 py-3 font-medium text-brand-ink dark:text-white">{{ $b['nama'] }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-mono text-xs">
                                {{ AturanHonor::rupiah($b['tarif']) }}
                                @if ($b['tarif_khusus']) <span class="ml-1 rounded bg-brand-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-brand-600">khusus</span> @endif
                            </td>
                            <td class="px-3 py-3 text-center">{{ $b['jp_mengajar'] }}</td>
                            <td class="px-3 py-3 text-center">{{ $b['jp_inval'] }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ AturanHonor::rupiah($b['honor_mengajar']) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ AturanHonor::rupiah($b['honor_inval']) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">{{ AturanHonor::rupiah($b['honor_guru_asli']) }}</td>
                            <td class="whitespace-nowrap px-3 py-3 text-right">
                                {{ AturanHonor::rupiah($b['honor_ekskul']) }}
                                @if ($b['jp_ekskul']) <span class="block text-[11px] text-brand-muted">{{ $b['jp_ekskul'] }} JP</span> @endif
                            </td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-bold text-brand-ink dark:text-white">{{ AturanHonor::rupiah($b['total']) }}</td>
                            <td class="px-5 py-2 text-right">
                                <button type="button" wire:click="lihat({{ $b['user']->id }})"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                                    Rincian
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="px-6 py-10 text-center text-sm text-brand-muted">Belum ada guru maupun honor tercatat di bulan ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-brand-muted">
            <strong>Saat Diinval</strong> = bagian guru asli dari jam yang diajar guru inval
            (sekarang {{ 100 - $aturan['persen_inval'] }}% untuk guru asli, {{ $aturan['persen_inval'] }}% untuk inval).
        </p>
    @endif
</div>
