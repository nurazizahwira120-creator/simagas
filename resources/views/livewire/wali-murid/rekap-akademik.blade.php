{{-- View komponen App\Livewire\WaliMurid\RekapAkademik — gaya TailAdmin. --}}
<div class="mx-auto w-full max-w-4xl">

    {{-- ============ PENYARING ============ --}}
    <div class="mb-6 flex flex-wrap gap-4 rounded-sm border border-gray-200 bg-brand-surface px-6 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        @if ($this->daftarAnak->count() > 1)
            <div class="min-w-[200px] flex-1">
                <label for="rk-anak" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Anak</label>
                <select id="rk-anak" wire:model.live="anak_id"
                    class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                    @foreach ($this->daftarAnak as $a)
                        <option value="{{ $a->id }}">{{ $a->nama }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="min-w-[200px] flex-1">
            <label for="rk-bulan" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Bulan</label>
            <select id="rk-bulan" wire:model.live="bulan"
                class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                @foreach ($this->pilihanBulan as $nilai => $label)
                    <option value="{{ $nilai }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if (! $this->anak)

        <div class="rounded-sm border border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="font-semibold text-brand-ink dark:text-white">Belum ada anak yang tertaut ke akun Anda</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Hubungi Admin TU sekolah untuk menautkannya.</p>
        </div>

    @else

        @php $g = $this->rekapGerbang; @endphp

        {{-- ============ KEHADIRAN DI GERBANG ============ --}}
        <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Kehadiran di Sekolah (Absen Gerbang)</h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    {{ $this->anak->nama }} &middot; {{ $this->awalBulan->translatedFormat('F Y') }}
                </p>
            </div>

            <div class="px-6 py-6">
                <div class="flex flex-wrap items-end gap-6">
                    <div>
                        <p class="text-4xl font-bold text-brand-ink dark:text-white">{{ $g['persen'] }}%</p>
                        <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                            {{ $g['hadir'] }} hadir dari {{ $g['total'] }} hari tercatat
                        </p>
                    </div>

                    <div class="flex flex-1 flex-wrap gap-2">
                        <span class="inline-flex rounded-full bg-success-500/10 px-3 py-1 text-xs font-medium text-success-500">Hadir {{ $g['hadir'] }}</span>
                        <span class="inline-flex rounded-full bg-warning-500/20 px-3 py-1 text-xs font-medium text-[#9D5425]">Sakit {{ $g['sakit'] }}</span>
                        <span class="inline-flex rounded-full bg-brand-500/10 px-3 py-1 text-xs font-medium text-brand-500">Izin {{ $g['izin'] }}</span>
                        <span class="inline-flex rounded-full bg-error-500/10 px-3 py-1 text-xs font-medium text-error-500">Alpa {{ $g['alpa'] }}</span>
                    </div>
                </div>

                {{-- Bar persentase --}}
                <div class="mt-5 h-2.5 w-full overflow-hidden rounded-full bg-gray-50 dark:bg-gray-800">
                    <div class="h-full rounded-full bg-success-500" style="width: {{ $g['persen'] }}%"></div>
                </div>

                @if ($g['total'] === 0)
                    <p class="mt-4 text-sm text-brand-muted dark:text-brand-faint">
                        Belum ada catatan kehadiran di bulan ini.
                    </p>
                @endif
            </div>
        </div>

        {{-- ============ KEHADIRAN PER MATA PELAJARAN ============ --}}
        <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Kehadiran per Mata Pelajaran</h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    Dihitung dari jurnal kelas yang diisi guru, bukan dari absen gerbang.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[620px] table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-800">
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Mata Pelajaran</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Jam Tercatat</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Hadir</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Tidak Hadir</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Persentase</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->rekapMapel as $baris)
                            <tr>
                                <td class="border-b border-gray-200 px-4 py-4 font-medium text-brand-ink dark:border-gray-800 dark:text-white">
                                    {{ $baris['mapel'] }}
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 text-sm text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                                    {{ $baris['total'] }}
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 text-sm text-success-500 dark:border-gray-800">
                                    {{ $baris['hadir'] }}
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 text-sm text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                                    @if ($baris['sakit'] || $baris['izin'] || $baris['alpa'] || $baris['bolos'])
                                        <span class="flex flex-wrap gap-1.5">
                                            @if ($baris['sakit'])<span class="text-[#9D5425]">{{ $baris['sakit'] }} sakit</span>@endif
                                            @if ($baris['izin'])<span class="text-brand-500">{{ $baris['izin'] }} izin</span>@endif
                                            @if ($baris['alpa'])<span class="text-error-500">{{ $baris['alpa'] }} alpa</span>@endif
                                            @if ($baris['bolos'])<span class="font-semibold text-error-500">{{ $baris['bolos'] }} bolos</span>@endif
                                        </span>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium
                                        {{ $baris['persen'] >= 80 ? 'bg-success-500/10 text-success-500' : ($baris['persen'] >= 60 ? 'bg-warning-500/20 text-[#9D5425]' : 'bg-error-500/10 text-error-500') }}">
                                        {{ $baris['persen'] }}%
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-10 text-center">
                                    <p class="text-sm font-semibold text-brand-ink dark:text-white">Belum ada jurnal kelas di bulan ini</p>
                                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                                        Data ini terisi setelah guru menyimpan absensi KBM di kelas anak Anda.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ============ DAFTAR ALPA / BOLOS ============ --}}
        @if ($this->daftarBolos->isNotEmpty())
            <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                    <h3 class="font-semibold text-brand-ink dark:text-white">
                        Jam Pelajaran yang Terlewat
                        <span class="ml-1 text-sm font-normal text-brand-muted dark:text-brand-faint">
                            ({{ $this->daftarBolos->count() }})
                        </span>
                    </h3>
                </div>

                <div class="px-6 py-2">
                    @foreach ($this->daftarBolos as $b)
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 py-3 last:border-0 dark:border-gray-800">
                            <div class="min-w-0">
                                <p class="font-medium text-brand-ink dark:text-white">
                                    {{ $b->jadwal?->mata_pelajaran ?? '(jadwal dihapus)' }}
                                    @if ($b->jadwal?->guru?->nama)
                                        <span class="font-normal text-brand-muted dark:text-brand-faint">&mdash; {{ $b->jadwal->guru->nama }}</span>
                                    @endif
                                </p>
                                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                                    {{ $b->tanggal->translatedFormat('l, d F Y') }}
                                    @if ($b->jadwal)
                                        &middot; <span class="font-mono">{{ $b->jadwal->rentangJam() }}</span>
                                    @endif
                                    @if ($b->keterangan)
                                        &middot; {{ $b->keterangan }}
                                    @endif
                                </p>
                            </div>

                            <span class="shrink-0 rounded-full px-3 py-1 text-xs font-medium {{ $b->status->kelasBadge() }}">
                                {{ $b->status->label() }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ============ BAGIAN NILAI — BELUM TERSEDIA ============
             Ditulis terus terang, bukan disembunyikan: menu ini bernama
             "Rekap & Laporan Akademik", jadi orang tua wajar mencari nilai di
             sini. Lebih baik ia tahu kenapa belum ada daripada menyangka
             halamannya rusak. --}}
        <div class="rounded-sm border border-dashed border-gray-200 bg-brand-surface px-6 py-6 text-center dark:border-gray-800 dark:bg-gray-900">
            <span class="mx-auto flex h-11 w-11 items-center justify-center rounded-full bg-gray-50 text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                <x-icon name="document-report" class="h-5 w-5" />
            </span>
            <p class="mt-3 font-semibold text-brand-ink dark:text-white">Nilai &amp; rapor belum tersedia</p>
            <p class="mx-auto mt-1 max-w-lg text-sm leading-relaxed text-brand-muted dark:text-brand-faint">
                Sistem ini baru mencatat kehadiran. Modul nilai membutuhkan data yang
                belum ada di sekolah ini — daftar KKM, bobot tugas/UTS/UAS, dan form
                input nilai untuk guru. Setelah modul itu dibuat, rekapnya akan muncul
                di halaman ini juga.
            </p>
        </div>

    @endif
</div>
