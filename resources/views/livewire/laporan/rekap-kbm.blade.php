{{-- View komponen App\Livewire\Laporan\RekapKbm — gaya TailAdmin. --}}
<div>

    @php
        $r = $this->rekap;
        $s = $r['ringkas'];
    @endphp

    {{-- ============ KARTU RINGKASAN ============ --}}
    <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">

        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
                <x-icon name="calendar" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">{{ $s['jumlah_jadwal'] }}</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Jadwal dalam periode ini</p>
        </div>

        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                <x-icon name="clipboard-check" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">
                {{ $s['persen'] === null ? '—' : $s['persen'] . '%' }}
            </p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                Kehadiran di kelas
                @if ($s['total_catatan'] > 0)
                    <span class="text-xs">({{ number_format($s['total_catatan'], 0, ',', '.') }} catatan)</span>
                @endif
            </p>
        </div>

        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-error-500/10 text-error-500">
                <x-icon name="x-circle" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">
                {{ number_format($s['alpa'] + $s['bolos'], 0, ',', '.') }}
            </p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                Alpa &amp; bolos
                <span class="text-xs">({{ number_format($s['bolos'], 0, ',', '.') }} di antaranya bolos)</span>
            </p>
        </div>

        {{-- Kartu ini yang paling sering menjelaskan angka aneh di kartu lain:
             kehadiran 100% pada jadwal yang jurnalnya cuma diisi sekali bukan
             kabar baik. --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-warning-500/10 text-warning-500">
                <x-icon name="exclamation-triangle" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">{{ $s['belum_terisi'] }}</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                Jadwal belum pernah diabsen
                <span class="text-xs">
                    ({{ number_format($s['pertemuan'], 0, ',', '.') }} dari
                    {{ number_format($s['pertemuan_mungkin'], 0, ',', '.') }} pertemuan tercatat)
                </span>
            </p>
        </div>
    </div>

    {{-- ============ SARINGAN ============ --}}
    <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

        <div class="flex flex-wrap items-end gap-4">

            <div>
                <label for="f-dari" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Dari tanggal</label>
                <input id="f-dari" type="date" wire:model.live="dari" max="{{ $sampai }}"
                    class="rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>

            <div>
                <label for="f-sampai" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Sampai tanggal</label>
                <input id="f-sampai" type="date" wire:model.live="sampai" min="{{ $dari }}"
                    class="rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
            </div>

            <div>
                <label for="f-kelas" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Kelas</label>
                <select id="f-kelas" wire:model.live="kelasId"
                    class="rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua kelas</option>
                    @foreach ($this->daftarKelas as $k)
                        <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="f-mapel" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Mata pelajaran</label>
                <select id="f-mapel" wire:model.live="mapel"
                    class="rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua mapel</option>
                    @foreach ($this->daftarMapel as $m)
                        <option value="{{ $m }}">{{ $m }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="f-guru" class="mb-1.5 block text-xs font-medium text-brand-muted dark:text-brand-faint">Guru</label>
                <select id="f-guru" wire:model.live="guruId"
                    class="rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                    <option value="">Semua guru</option>
                    @foreach ($this->daftarGuru as $g)
                        <option value="{{ $g->id }}">{{ $g->nama }}</option>
                    @endforeach
                </select>
            </div>

            <div class="ms-auto flex flex-wrap items-center gap-2">
                <button type="button" wire:click="bersihkanSaringan"
                    class="rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                    Reset saringan
                </button>

                <a href="{{ route($panelPrefix . '.rekap-kbm.unduh', $this->paramUnduh) }}"
                    class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                    <x-icon name="download" class="h-4 w-4" />
                    Unduh PDF
                </a>
            </div>
        </div>

        {{-- Pintasan periode. Mengetik dua tanggal untuk pertanyaan sesederhana
             "bulan lalu bagaimana" adalah gesekan yang tidak perlu. --}}
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-gray-200 pt-4 dark:border-gray-800">
            <span class="text-xs font-medium text-brand-muted dark:text-brand-faint">Pintasan:</span>
            @foreach (['pekan-ini' => 'Pekan ini', 'bulan-ini' => 'Bulan ini', 'bulan-lalu' => 'Bulan lalu', 'semester' => '6 bulan terakhir'] as $kode => $label)
                <button type="button" wire:click="aturCepat('{{ $kode }}')"
                    class="rounded-full border border-gray-300 px-3 py-1 text-xs font-medium text-gray-600 transition hover:border-brand-500 hover:text-brand-600 dark:border-gray-700 dark:text-gray-300">
                    {{ $label }}
                </button>
            @endforeach

            <span class="ms-auto text-xs text-brand-muted dark:text-brand-faint">
                Periode: <span class="font-semibold text-brand-ink dark:text-white">{{ $r['label_periode'] }}</span>
            </span>
        </div>
    </div>

    @if ($galat)
        <div class="mb-6 flex items-start gap-3 rounded-2xl border border-warning-200 bg-warning-500/10 p-5 dark:border-warning-500/30" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-warning-700 dark:text-warning-400" />
            <p class="text-sm font-semibold text-warning-700 dark:text-warning-400">{{ $galat }}</p>
        </div>
    @endif

    {{-- ============ TABEL PER JADWAL ============ --}}
    <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h3 class="font-semibold text-brand-ink dark:text-white">Rekap per Jadwal Pelajaran</h3>
            <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                Klik satu baris untuk melihat rincian per siswa pada jadwal tersebut.
            </p>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-100 text-left dark:bg-gray-800">
                        <th class="px-6 py-4 text-sm font-semibold text-brand-ink dark:text-white">Jadwal</th>
                        <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Kelas &amp; Guru</th>
                        <th class="px-4 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Pertemuan</th>
                        <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Hadir</th>
                        <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">S</th>
                        <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">I</th>
                        <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Alpa</th>
                        <th class="px-3 py-4 text-center text-sm font-semibold text-brand-ink dark:text-white">Bolos</th>
                        <th class="px-6 py-4 text-right text-sm font-semibold text-brand-ink dark:text-white">% Hadir</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($r['baris'] as $b)
                        <tr wire:key="jadwal-{{ $b['jadwal_id'] }}"
                            wire:click="bukaRincian({{ $b['jadwal_id'] }})"
                            class="cursor-pointer border-b border-gray-200 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03] {{ $jadwalDibuka === $b['jadwal_id'] ? 'bg-brand-500/5' : '' }}">

                            <td class="px-6 py-4">
                                <p class="font-semibold text-brand-ink dark:text-white">{{ $b['mapel'] }}</p>
                                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                                    {{ $b['hari'] }} &middot; {{ $b['jam'] }}
                                    @if ($b['ruangan'] !== '—')
                                        &middot; {{ $b['ruangan'] }}
                                    @endif
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm text-brand-ink dark:text-white">{{ $b['kelas'] }}</p>
                                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $b['guru'] }}</p>
                            </td>

                            <td class="px-4 py-4 text-center">
                                @if ($b['pertemuan'] === 0)
                                    <span class="inline-flex rounded-full bg-error-500/10 px-2.5 py-1 text-xs font-semibold text-error-600">
                                        belum diisi
                                    </span>
                                    <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                                        dari {{ $b['pertemuan_mungkin'] }} pertemuan
                                    </p>
                                @else
                                    <p class="text-sm font-semibold text-brand-ink dark:text-white">
                                        {{ $b['pertemuan'] }} / {{ $b['pertemuan_mungkin'] }}
                                    </p>
                                    <p class="mt-0.5 text-xs {{ $b['persen_terisi'] !== null && $b['persen_terisi'] >= 80 ? 'text-success-500' : 'text-warning-500' }}">
                                        {{ $b['persen_terisi'] }}% terisi
                                    </p>
                                @endif
                            </td>

                            <td class="px-3 py-4 text-center text-sm text-brand-ink dark:text-white">{{ $b['hadir'] }}</td>
                            <td class="px-3 py-4 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $b['sakit'] }}</td>
                            <td class="px-3 py-4 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $b['izin'] }}</td>
                            <td class="px-3 py-4 text-center text-sm {{ $b['alpa'] > 0 ? 'font-semibold text-error-600' : 'text-brand-muted dark:text-brand-faint' }}">{{ $b['alpa'] }}</td>
                            <td class="px-3 py-4 text-center text-sm {{ $b['bolos'] > 0 ? 'font-semibold text-error-600' : 'text-brand-muted dark:text-brand-faint' }}">{{ $b['bolos'] }}</td>

                            <td class="px-6 py-4 text-right">
                                @if ($b['persen'] === null)
                                    <span class="text-sm text-brand-muted dark:text-brand-faint">—</span>
                                @else
                                    <span class="text-sm font-bold {{ $b['persen'] >= 85 ? 'text-success-500' : ($b['persen'] >= 70 ? 'text-warning-500' : 'text-error-600') }}">
                                        {{ $b['persen'] }}%
                                    </span>
                                @endif
                            </td>
                        </tr>

                        {{-- Panel rincian per siswa, langsung di bawah barisnya. --}}
                        @if ($jadwalDibuka === $b['jadwal_id'])
                            <tr wire:key="rincian-{{ $b['jadwal_id'] }}" class="border-b border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-white/[0.02]">
                                <td colspan="9" class="px-6 py-5">
                                    <p class="mb-3 text-sm font-semibold text-brand-ink dark:text-white">
                                        Rincian per siswa — {{ $b['mapel'] }}, {{ $b['kelas'] }}
                                        <span class="font-normal text-brand-muted dark:text-brand-faint">
                                            ({{ $b['hari'] }} {{ $b['jam'] }}, {{ $r['label_periode'] }})
                                        </span>
                                    </p>

                                    @if (empty($this->rincian))
                                        <p class="py-4 text-center text-sm text-brand-muted dark:text-brand-faint">
                                            Belum ada catatan absensi KBM untuk jadwal ini pada periode tersebut.
                                        </p>
                                    @else
                                        <div class="max-w-full overflow-x-auto rounded-md border border-gray-200 dark:border-gray-800">
                                            <table class="w-full table-auto bg-brand-surface dark:bg-gray-900">
                                                <thead>
                                                    <tr class="border-b border-gray-200 text-left dark:border-gray-800">
                                                        <th class="px-4 py-2.5 text-xs font-semibold text-brand-ink dark:text-white">Nama</th>
                                                        <th class="px-3 py-2.5 text-xs font-semibold text-brand-ink dark:text-white">NIS</th>
                                                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-brand-ink dark:text-white">Hadir</th>
                                                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-brand-ink dark:text-white">S</th>
                                                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-brand-ink dark:text-white">I</th>
                                                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-brand-ink dark:text-white">Alpa</th>
                                                        <th class="px-3 py-2.5 text-center text-xs font-semibold text-brand-ink dark:text-white">Bolos</th>
                                                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-brand-ink dark:text-white">% Hadir</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach ($this->rincian as $sis)
                                                        <tr class="border-b border-gray-200 last:border-0 dark:border-gray-800">
                                                            <td class="px-4 py-2.5 text-sm text-brand-ink dark:text-white">{{ $sis['nama'] }}</td>
                                                            <td class="px-3 py-2.5 text-xs text-brand-muted dark:text-brand-faint">{{ $sis['nis'] }}</td>
                                                            <td class="px-3 py-2.5 text-center text-sm text-brand-ink dark:text-white">{{ $sis['hadir'] }}</td>
                                                            <td class="px-3 py-2.5 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $sis['sakit'] }}</td>
                                                            <td class="px-3 py-2.5 text-center text-sm text-brand-muted dark:text-brand-faint">{{ $sis['izin'] }}</td>
                                                            <td class="px-3 py-2.5 text-center text-sm {{ $sis['alpa'] > 0 ? 'font-semibold text-error-600' : 'text-brand-muted dark:text-brand-faint' }}">{{ $sis['alpa'] }}</td>
                                                            <td class="px-3 py-2.5 text-center text-sm {{ $sis['bolos'] > 0 ? 'font-semibold text-error-600' : 'text-brand-muted dark:text-brand-faint' }}">{{ $sis['bolos'] }}</td>
                                                            <td class="px-4 py-2.5 text-right text-sm font-semibold {{ $sis['persen'] !== null && $sis['persen'] >= 85 ? 'text-success-500' : 'text-warning-500' }}">
                                                                {{ $sis['persen'] === null ? '—' : $sis['persen'] . '%' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-14 text-center">
                                <p class="text-sm font-semibold text-brand-ink dark:text-white">
                                    Tidak ada jadwal yang cocok pada periode ini.
                                </p>
                                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                                    Coba lebarkan rentang tanggalnya, atau kosongkan saringan kelas / mata pelajaran / guru.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Keterangan cara membacanya. Ditulis di halaman, bukan diserahkan
             ke ingatan: "% Hadir" dan "% terisi" mengukur dua hal berbeda dan
             sangat mudah tertukar. --}}
        <div class="border-t border-gray-200 px-6 py-4 text-xs leading-relaxed text-brand-muted dark:border-gray-800 dark:text-brand-faint">
            <p><span class="font-semibold">% Hadir</span> = perbandingan status "hadir" terhadap seluruh catatan yang ada pada jadwal itu — mengukur <em>siswanya</em>.</p>
            <p class="mt-1"><span class="font-semibold">Pertemuan &amp; % terisi</span> = berapa kali jurnal benar-benar diisi dibanding berapa kali pelajaran itu seharusnya berlangsung dalam periode ini — mengukur <em>pengisian jurnalnya</em>.</p>
            <p class="mt-1"><span class="font-semibold">Bolos</span> berbeda dari <span class="font-semibold">Alpa</span>: bolos berarti siswa tercatat masuk gerbang pagi itu tetapi tidak ada di kelas.</p>
        </div>
    </div>
</div>
