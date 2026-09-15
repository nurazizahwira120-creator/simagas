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

    {{-- ============ STATUS HARI — dibaca PALING DULU ============
         Angka kehadiran tanpa keterangan hari adalah angka yang menyesatkan.
         Pada hari Jumat (libur mingguan di sekolah ini), seluruh siswa memang
         tidak tercatat — dan tanpa blok ini pembacanya akan menyimpulkan satu
         sekolah penuh membolos. --}}
    @php $h = $this->statusHari; @endphp

    @if ($h['hariKbm'])
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-emerald-300 bg-emerald-500/5 px-5 py-4 dark:border-emerald-500/30">
            <span class="flex items-center gap-2 text-sm font-bold text-emerald-800 dark:text-emerald-400">
                <x-icon name="check-circle" class="h-5 w-5 shrink-0" />
                {{ $h['hari'] }} — Hari KBM
            </span>

            <span class="text-xs text-brand-muted">
                Jam pulang <strong>{{ $h['jamPulang'] }}</strong>
            </span>

            {{-- Kalimat inilah yang mengubah arti kolom "Belum Tercatat". --}}
            @if ($h['sudahLewatJamPulang'])
                @php $sapu = $this->penyapuanAlpa; @endphp

                @if ($sapu['status'] === 'sudah_jalan')
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-400">
                        <x-icon name="clock" class="h-3.5 w-3.5" />
                        Jam pulang lewat — {{ $sapu['jumlah'] }} siswa sudah ditandai alpa otomatis
                    </span>
                @elseif ($sapu['status'] === 'belum_jalan')
                    {{-- ============ PERINGATAN YANG PALING PENTING DI HALAMAN INI ============
                         Jam pulang sudah lewat, masih ada yang belum tercatat, tapi TIDAK
                         ADA satu pun baris bertanda otomatis. Artinya penyapunya tidak
                         berjalan — hampir selalu karena cron di cPanel belum dipasang.

                         Tanpa peringatan ini, layar akan terus berkata "ditandai otomatis"
                         sementara kolom Alpa tetap nol selamanya, dan pembacanya
                         menyimpulkan tidak ada yang membolos. Kesimpulan itu persis
                         terbalik dari kenyataannya. --}}
                    <span class="inline-flex items-start gap-1.5 rounded-full border border-red-300 bg-red-500/10 px-3 py-1 text-xs font-bold text-brand-danger-text">
                        <x-icon name="exclamation-triangle" class="mt-px h-3.5 w-3.5 shrink-0" />
                        <span class="min-w-0">
                            Penandaan alpa otomatis BELUM berjalan hari ini —
                            {{ $sapu['jumlah'] }} siswa masih "belum tercatat". Periksa cron di cPanel.
                        </span>
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-400">
                        <x-icon name="check-circle" class="h-3.5 w-3.5" />
                        Jam pulang lewat — seluruh siswa sudah tercatat
                    </span>
                @endif
            @else
                <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-300 bg-sky-500/10 px-3 py-1 text-xs font-semibold text-sky-800 dark:text-sky-400">
                    <x-icon name="clock" class="h-3.5 w-3.5" />
                    Masih jam sekolah — "belum tercatat" bisa berarti masih di perjalanan
                </span>
            @endif
        </div>
    @else
        <div class="rounded-2xl border border-red-300 bg-red-500/5 px-5 py-4 dark:border-red-500/30">
            <p class="flex items-start gap-2 text-sm font-bold text-brand-danger-text">
                <x-icon name="x-circle" class="mt-0.5 h-5 w-5 shrink-0" />
                <span class="min-w-0">{{ $h['hari'] }} — LIBUR: {{ $h['alasan'] }}</span>
            </p>
            <p class="mt-1.5 text-xs leading-relaxed text-brand-muted">
                Tidak ada KBM pada tanggal ini, jadi wajar bila tidak ada kehadiran yang tercatat.
                Hari ini <strong>tidak</strong> dihitung sebagai hari sekolah di laporan, dan
                <strong>tidak ada</strong> siswa yang ditandai alpa.
            </p>
        </div>
    @endif

    {{-- Agenda kalender pada tanggal ini — konteks tambahan yang menjelaskan
         kenapa kehadirannya tidak seperti hari biasa (mis. pekan asesmen). --}}
    @if ($h['agenda']->isNotEmpty())
        <div class="flex flex-wrap gap-2">
            @foreach ($h['agenda'] as $a)
                <span class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold {{ $a->jenis->kelasBadge() }}">
                    <x-icon name="{{ $a->jenis->ikon() }}" class="h-3.5 w-3.5 shrink-0" />
                    {{ $a->judul }}
                </span>
            @endforeach
        </div>
    @endif

    {{-- ============ REKAP SELURUH SEKOLAH ============ --}}
    @php $t = $this->totalSekolah; @endphp

    <div class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-6 py-4 dark:border-gray-800">
            <div class="min-w-0">
                <h3 class="font-semibold text-brand-ink dark:text-white">Rekap Seluruh Sekolah</h3>
                <p class="mt-0.5 text-sm text-brand-muted">{{ $this->tanggalDipakai->translatedFormat('l, d F Y') }}</p>
            </div>

            @if ($t['persen'] !== null)
                <div class="shrink-0 text-right">
                    <p class="text-3xl font-extrabold text-brand-ink dark:text-white">{{ $t['persen'] }}%</p>
                    <p class="text-xs text-brand-muted">{{ $t['hadir'] }} dari {{ $t['total'] }} siswa hadir</p>
                </div>
            @endif
        </div>

        {{-- Lima angka, masing-masing dengan kalimat yang menjelaskan APA YANG
             HARUS DILAKUKAN — bukan hanya labelnya. Angka tanpa tindakan yang
             menyertainya hanya jadi hiasan di layar. --}}
        <div class="grid grid-cols-2 gap-px bg-brand-border sm:grid-cols-3 lg:grid-cols-5 dark:bg-gray-800">
            @foreach ([
                ['Hadir',         $t['hadir'], 'text-emerald-600', 'Tercatat scan di gerbang'],
                ['Izin',          $t['izin'],  'text-sky-600',     'Sudah diurus petugas piket'],
                ['Sakit',         $t['sakit'], 'text-amber-600',   'Ada catatan dari orang tua'],
                ['Alpa',          $t['alpa'],  'text-brand-danger-text', 'Tanpa keterangan — perlu ditindak'],
                ['Belum Tercatat',$t['belum'], 'text-brand-muted', 'Belum scan & belum ada izin'],
            ] as [$label, $nilai, $warna, $bantu])
                <div class="min-w-0 bg-brand-surface px-4 py-3.5 dark:bg-gray-900">
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-brand-muted">{{ $label }}</p>
                    <p class="angka-naik mt-1 text-2xl font-extrabold {{ $warna }}">{{ $nilai }}</p>
                    <p class="mt-0.5 text-xs leading-snug text-brand-muted">{{ $bantu }}</p>
                </div>
            @endforeach
        </div>

        {{-- Tabel per kelas: pertanyaan "kelas mana yang bermasalah hari ini". --}}
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="border-y border-brand-border bg-brand-surface-muted text-left text-xs uppercase tracking-wide text-brand-muted dark:border-gray-800 dark:bg-gray-800/50">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Kelas</th>
                        <th class="px-3 py-3 text-center font-semibold">Siswa</th>
                        <th class="px-3 py-3 text-center font-semibold">Hadir</th>
                        <th class="px-3 py-3 text-center font-semibold">Izin</th>
                        <th class="px-3 py-3 text-center font-semibold">Sakit</th>
                        <th class="px-3 py-3 text-center font-semibold">Alpa</th>
                        <th class="px-3 py-3 text-center font-semibold">Belum</th>
                        <th class="px-6 py-3 font-semibold">Kehadiran</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-border dark:divide-gray-800">
                    @foreach ($this->rekapSekolah as $r)
                        <tr @class(['bg-red-500/5' => $r['alpa'] > 0])>
                            <td class="px-6 py-3 font-medium text-brand-ink dark:text-white">{{ $r['kelas']->nama_kelas }}</td>
                            <td class="px-3 py-3 text-center text-brand-muted">{{ $r['total'] }}</td>
                            <td class="px-3 py-3 text-center font-semibold text-emerald-600">{{ $r['hadir'] }}</td>
                            <td class="px-3 py-3 text-center text-sky-600">{{ $r['izin'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-center text-amber-600">{{ $r['sakit'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-center font-bold text-brand-danger-text">{{ $r['alpa'] ?: '—' }}</td>
                            <td class="px-3 py-3 text-center text-brand-muted">{{ $r['belum'] ?: '—' }}</td>
                            <td class="px-6 py-3">
                                @if ($r['persen'] === null)
                                    <span class="text-xs text-brand-muted">Belum ada siswa</span>
                                @else
                                    <div class="flex items-center gap-2">
                                        {{-- Bilah memakai transform: scaleX, BUKAN width.
                                             scaleX dikerjakan compositor tanpa menghitung
                                             ulang tata letak; width memaksa layout
                                             recalculation setiap frame. --}}
                                        <span class="h-2 w-20 shrink-0 overflow-hidden rounded-full bg-brand-surface-muted dark:bg-gray-800">
                                            <span class="bilah-isi block h-full rounded-full {{ $r['persen'] >= 90 ? 'bg-emerald-500' : ($r['persen'] >= 75 ? 'bg-amber-500' : 'bg-brand-danger') }}"
                                                style="--isi: {{ $r['persen'] / 100 }}"></span>
                                        </span>
                                        <span class="shrink-0 text-xs font-semibold text-brand-ink dark:text-white">{{ $r['persen'] }}%</span>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
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

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['label' => 'Hadir di Gerbang', 'nilai' => $m['hadir'], 'ikon' => 'check-circle', 'w' => 'text-success-500', 'bg' => 'bg-success-500/10'],
                ['label' => 'Terlambat (dari yang hadir)', 'nilai' => $m['terlambat'], 'ikon' => 'clock', 'w' => 'text-amber-600', 'bg' => 'bg-amber-500/10'],
                ['label' => 'Izin / Sakit', 'nilai' => $m['izin'] + $m['sakit'], 'ikon' => 'clipboard-check', 'w' => 'text-sky-600', 'bg' => 'bg-sky-500/10'],
                ['label' => 'Alpa (tercatat)', 'nilai' => $m['alpa'], 'ikon' => 'x-circle', 'w' => 'text-error-500', 'bg' => 'bg-error-500/10'],
                ['label' => 'Belum Tercatat', 'nilai' => $m['belum'], 'ikon' => 'inbox', 'w' => 'text-brand-muted', 'bg' => 'bg-gray-200'],
                ['label' => 'Bolos KBM', 'nilai' => $m['bolos'], 'ikon' => 'exclamation-triangle', 'w' => 'text-error-500', 'bg' => 'bg-error-500/10'],
            ] as $k)
                <div class="min-w-0 rounded-sm border border-gray-200 bg-brand-surface px-5 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
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
