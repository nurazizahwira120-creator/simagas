{{--
    View komponen App\Livewire\WaliMurid\PantauanKbm — gaya TailAdmin.

    PANTANGAN: jangan menulis teks berbentuk tag HTML di dalam blok <script>
    pada view Livewire (termasuk di komentar JavaScript) — parser HTML akan
    keluar dari <script> dan Livewire melempar
    MultipleRootElementsDetectedException.
--}}
<div class="mx-auto w-full max-w-3xl">

    {{-- ============ PEMILIH ANAK ============
         Hanya muncul kalau anaknya lebih dari satu; dropdown berisi satu
         nama saja hanya menambah langkah tanpa memberi pilihan. --}}
    @if ($this->daftarAnak->count() > 1)
        <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface px-6 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <label for="anak_id" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Pilih Anak</label>
            <select id="anak_id" wire:model.live="anak_id"
                class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white sm:max-w-xs">
                @foreach ($this->daftarAnak as $a)
                    <option value="{{ $a->id }}">{{ $a->nama }} — {{ $a->kelas?->nama_kelas ?? 'tanpa kelas' }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if (! $this->anak)

        <div class="rounded-sm border border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <p class="font-semibold text-brand-ink dark:text-white">Belum ada anak yang tertaut ke akun Anda</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                Hubungi Admin TU sekolah untuk menautkan data anak Anda.
            </p>
        </div>

    @else

        {{-- ============ RINGKASAN HARI INI ============ --}}
        @php $r = $this->ringkasan; @endphp

        <div class="mb-6 grid grid-cols-3 gap-4">
            @foreach ([
                ['label' => 'Total Hadir', 'nilai' => $r['hadir'], 'ikon' => 'check-circle', 'warna' => 'text-success-500', 'latar' => 'bg-success-500/10'],
                ['label' => 'Sakit / Izin', 'nilai' => $r['sakitIzin'], 'ikon' => 'inbox', 'warna' => 'text-warning-500', 'latar' => 'bg-warning-500/10'],
                ['label' => 'Alpa', 'nilai' => $r['alpa'], 'ikon' => 'x-circle', 'warna' => 'text-error-500', 'latar' => 'bg-error-500/10'],
            ] as $kotak)
                <div class="rounded-sm border border-gray-200 bg-brand-surface px-4 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full {{ $kotak['latar'] }} {{ $kotak['warna'] }}">
                        <x-icon name="{{ $kotak['ikon'] }}" class="h-4 w-4" />
                    </span>
                    <p class="mt-3 text-2xl font-bold text-brand-ink dark:text-white">{{ $kotak['nilai'] }}</p>
                    <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $kotak['label'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- ============ TIMELINE ============ --}}
        <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">
                    Kegiatan Belajar {{ $this->anak->nama }}
                </h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    {{ now()->translatedFormat('l, d F Y') }}
                    &middot; Kelas {{ $this->anak->kelas?->nama_kelas ?? '—' }}
                </p>
            </div>

            <div class="px-6 py-6">
                @forelse ($this->timeline as $baris)
                    @php
                        $jadwal = $baris['jadwal'];
                        $absensi = $baris['absensi'];
                        $status = $absensi?->status;
                        $terakhir = $loop->last;
                    @endphp

                    {{-- Satu titik timeline. Garis vertikalnya digambar sebagai
                         border kiri pada pembungkus, bukan elemen absolut,
                         supaya tingginya otomatis mengikuti isi tiap butir. --}}
                    <div class="relative flex gap-4 {{ $terakhir ? '' : 'pb-6' }}">
                        {{-- Kolom titik + garis --}}
                        <div class="relative flex w-4 shrink-0 justify-center">
                            @unless ($terakhir)
                                <span class="absolute top-5 h-full w-px bg-gray-200 dark:bg-gray-800"></span>
                            @endunless

                            <span class="relative z-10 mt-1.5 flex h-4 w-4 items-center justify-center rounded-full border-2 bg-brand-surface dark:bg-gray-900
                                {{ $status
                                    ? match ($status) {
                                        \App\Enums\StatusKbm::Hadir => 'border-success-500',
                                        \App\Enums\StatusKbm::Sakit => 'border-warning-500',
                                        \App\Enums\StatusKbm::Izin => 'border-brand-500',
                                        default => 'border-error-500',
                                    }
                                    : 'border-gray-200 dark:border-gray-800' }}">
                                <span class="h-1.5 w-1.5 rounded-full
                                    {{ $status
                                        ? match ($status) {
                                            \App\Enums\StatusKbm::Hadir => 'bg-success-500',
                                            \App\Enums\StatusKbm::Sakit => 'bg-warning-500',
                                            \App\Enums\StatusKbm::Izin => 'bg-brand-500',
                                            default => 'bg-error-500',
                                        }
                                        : 'bg-gray-200 dark:bg-gray-800' }}"></span>
                            </span>
                        </div>

                        {{-- Isi butir --}}
                        <div class="min-w-0 flex-1">
                            <p class="font-mono text-sm text-brand-muted dark:text-brand-faint">
                                {{ $jadwal->rentangJam() }}
                            </p>

                            <p class="mt-0.5 font-bold text-brand-ink dark:text-white">
                                {{ $jadwal->mata_pelajaran }}
                                @if ($jadwal->guru?->nama)
                                    <span class="font-normal text-brand-muted dark:text-brand-faint">&mdash; {{ $jadwal->guru->nama }}</span>
                                @endif
                            </p>

                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                @if ($status)
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $status->kelasBadge() }}">
                                        {{ $status->labelWali() }}
                                    </span>
                                @else
                                    {{-- Data kosong TIDAK berarti anaknya tidak hadir —
                                         berarti gurunya belum mengisi jurnal jam itu.
                                         Membedakan keduanya penting: badge merah untuk
                                         guru yang belum sempat mengabsen akan membuat
                                         orang tua panik tanpa sebab. --}}
                                    <span class="inline-flex rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                                        Belum Mulai / Berlangsung
                                    </span>
                                @endif

                                @if ($jadwal->ruangan)
                                    <span class="text-xs text-brand-muted dark:text-brand-faint">{{ $jadwal->ruangan }}</span>
                                @endif
                            </div>

                            @if ($absensi?->keterangan)
                                <p class="mt-2 rounded-md bg-gray-50 px-3 py-2 text-xs leading-relaxed text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                                    Catatan guru: {{ $absensi->keterangan }}
                                </p>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex flex-col items-center gap-3 py-8 text-center">
                        <span class="flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                            <x-icon name="calendar" class="h-7 w-7" />
                        </span>
                        <div>
                            <p class="font-semibold text-brand-ink dark:text-white">
                                Tidak ada kegiatan belajar mengajar hari ini
                            </p>
                            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                                {{ now()->translatedFormat('l, d F Y') }} tidak ada jadwal untuk
                                kelas {{ $this->anak->kelas?->nama_kelas ?? '—' }}.
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($r['belum'] > 0)
            <p class="mt-4 text-center text-xs text-brand-muted dark:text-brand-faint">
                {{ $r['belum'] }} jam pelajaran belum diisi gurunya. Status akan muncul
                setelah guru menyimpan jurnal kelasnya.
            </p>
        @endif

    @endif
</div>
