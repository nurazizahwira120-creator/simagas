{{--
    View komponen App\Livewire\Kepsek\PantauanSiswa — gaya TailAdmin.

    PANTANGAN: jangan menulis teks berbentuk tag HTML di dalam blok <script>
    pada view Livewire (termasuk di komentar JavaScript).

    Halaman ini HANYA BACA: tidak ada tombol simpan, edit, atau hapus.
--}}
<div class="space-y-6">

    {{-- ============ KARTU FILTER ============ --}}
    <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4 px-6 py-5">

            <div class="min-w-[190px] flex-1">
                <label for="ps-tanggal" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Tanggal</label>

                {{-- Input tanggal bawaan browser, bukan Flatpickr — lihat
                     alasan lengkapnya di pantauan-pegawai.blade.php. --}}
                <input id="ps-tanggal" type="date" wire:model.live="tanggal"
                    max="{{ today()->toDateString() }}"
                    class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
            </div>

            <div class="min-w-[190px] flex-1">
                <label for="ps-kelas" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Kelas</label>
                <select id="ps-kelas" wire:model.live="kelas_id"
                    class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                    @forelse ($this->daftarKelas as $k)
                        <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                    @empty
                        <option value="">Belum ada kelas</option>
                    @endforelse
                </select>
            </div>

            <div class="flex-1 pb-1 text-xs leading-relaxed text-brand-muted dark:text-brand-faint">
                Kolom Pantauan KBM diisi guru mata pelajaran lewat menu
                Jurnal &amp; Absen Kelas.
            </div>
        </div>
    </div>

    @if (! $this->kelasTerpilih)

        <div class="rounded-sm border border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="font-semibold text-brand-ink dark:text-white">Belum ada kelas terdaftar</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                Tambahkan kelas lebih dulu lewat menu Data Kelas.
            </p>
        </div>

    @else

        {{-- ============ 4 KARTU METRIK ============ --}}
        @php $m = $this->metrik; @endphp

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach ([
                ['label' => 'Total Siswa', 'nilai' => $m['total'], 'ikon' => 'users', 'w' => 'text-brand-500', 'bg' => 'bg-brand-500/10'],
                ['label' => 'Hadir (Gerbang)', 'nilai' => $m['hadir'], 'ikon' => 'check-circle', 'w' => 'text-success-500', 'bg' => 'bg-success-500/10'],
                ['label' => 'Tidak Hadir', 'nilai' => $m['tidakHadir'], 'ikon' => 'x-circle', 'w' => 'text-brand-muted', 'bg' => 'bg-gray-200'],
                ['label' => 'Bolos KBM', 'nilai' => $m['bolos'], 'ikon' => 'exclamation-triangle', 'w' => 'text-error-500', 'bg' => 'bg-error-500/10'],
            ] as $k)
                <div class="rounded-sm border border-gray-200 bg-brand-surface px-5 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $k['bg'] }} {{ $k['w'] }}">
                        <x-icon name="{{ $k['ikon'] }}" class="h-5 w-5" />
                    </span>
                    <p class="mt-3 text-2xl font-bold text-brand-ink dark:text-white">{{ $k['nilai'] }}</p>
                    <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $k['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ============ TABEL ============ --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="font-semibold text-brand-ink dark:text-white">
                        Kelas {{ $this->kelasTerpilih->nama_kelas }}
                    </h3>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                        {{ $this->tanggalDipakai->translatedFormat('l, d F Y') }}
                        &middot; {{ $this->totalJamHariItu }} jam pelajaran terjadwal
                    </p>
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-3 py-1 text-xs font-medium text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                    <x-icon name="eye" class="h-3.5 w-3.5" />
                    Hanya baca
                </span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] table-auto" wire:loading.class="opacity-50" wire:target="tanggal,kelas_id">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-800">
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">NIS</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Nama Siswa</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Waktu Scan Gerbang</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Status Kedatangan</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Pantauan KBM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->baris as $b)
                            <tr wire:key="siswa-{{ $b['siswa']->id }}">
                                <td class="border-b border-gray-200 px-4 py-4 font-mono text-sm text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                                    {{ $b['siswa']->nis }}
                                </td>

                                <td class="border-b border-gray-200 px-4 py-4 font-medium text-brand-ink dark:border-gray-800 dark:text-white">
                                    {{ $b['siswa']->nama }}
                                </td>

                                <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                    @if ($b['jam'])
                                        <span class="font-mono text-sm text-brand-ink dark:text-white">{{ $b['jam'] }}</span>
                                    @else
                                        <span class="text-sm text-brand-muted dark:text-brand-faint">&mdash;</span>
                                    @endif
                                </td>

                                <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                    @php
                                        $s = $b['statusGerbang'];
                                        $badge = match (true) {
                                            $s === \App\Enums\AbsensiStatus::Hadir => ['kelas' => 'bg-success-500/10 text-success-500', 'teks' => 'Hadir'],
                                            $s === \App\Enums\AbsensiStatus::Sakit => ['kelas' => 'bg-warning-500/20 text-[#9D5425]', 'teks' => 'Sakit'],
                                            $s === \App\Enums\AbsensiStatus::Izin => ['kelas' => 'bg-brand-500/10 text-brand-500', 'teks' => 'Izin'],
                                            $s === \App\Enums\AbsensiStatus::Alpha => ['kelas' => 'bg-error-500/10 text-error-500', 'teks' => 'Alpa'],
                                            default => ['kelas' => 'bg-gray-200 text-brand-muted dark:bg-gray-800 dark:text-brand-faint', 'teks' => 'Belum Tercatat'],
                                        };
                                    @endphp

                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $badge['kelas'] }}">
                                        {{ $badge['teks'] }}
                                    </span>
                                </td>

                                <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                    @php $total = $this->totalJamHariItu; @endphp

                                    @if ($total === 0)
                                        <span class="text-sm text-brand-muted dark:text-brand-faint">Tidak ada jadwal</span>
                                    @elseif ($b['terisiKbm'] === 0)
                                        {{-- Belum ada satu pun jurnal yang diisi guru.
                                             Ini BUKAN berarti anaknya tidak hadir —
                                             membedakan keduanya penting supaya kepsek
                                             tidak menegur anak yang gurunya belum
                                             sempat mengabsen. --}}
                                        <span class="text-sm text-brand-muted dark:text-brand-faint">Belum diisi guru</span>
                                    @else
                                        @php $persen = (int) round($b['hadirKbm'] / $total * 100); @endphp

                                        <p class="text-sm font-medium text-brand-ink dark:text-white">
                                            {{ $b['hadirKbm'] }}/{{ $total }} Jam Pelajaran Diikuti
                                        </p>

                                        <div class="mt-1.5 h-1.5 w-32 overflow-hidden rounded-full bg-gray-50 dark:bg-gray-800">
                                            <div class="h-full rounded-full {{ $b['bermasalah']->isNotEmpty() ? 'bg-error-500' : 'bg-success-500' }}"
                                                style="width: {{ min($persen, 100) }}%"></div>
                                        </div>

                                        @if ($b['bermasalah']->isNotEmpty())
                                            <span class="mt-2 inline-flex items-center gap-1.5 rounded-full bg-error-500/10 px-3 py-1 text-xs font-semibold text-error-500">
                                                <x-icon name="exclamation-triangle" class="h-3.5 w-3.5" />
                                                {{ $b['adaBolos'] ? 'Bolos' : 'Alpa' }}
                                                {{ $b['bermasalah']->count() }} jam
                                            </span>
                                        @endif

                                        @if ($b['terisiKbm'] < $total)
                                            <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                                                {{ $total - $b['terisiKbm'] }} jam belum diisi guru
                                            </p>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center">
                                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                                        <x-icon name="inbox" class="h-7 w-7" />
                                    </span>
                                    <p class="mt-3 text-sm font-semibold text-brand-ink dark:text-white">
                                        Belum ada siswa di kelas ini
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    @endif
</div>
