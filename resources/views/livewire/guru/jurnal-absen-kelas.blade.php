{{--
    View komponen App\Livewire\Guru\JurnalAbsenKelas — gaya TailAdmin.

    PANTANGAN: jangan menulis teks berbentuk tag HTML di dalam blok <script>
    pada view Livewire (termasuk di dalam komentar JavaScript). Parser HTML
    akan keluar dari <script> di situ dan membuat elemen nyata di luar root
    div, sehingga Livewire melempar MultipleRootElementsDetectedException.
--}}
<div class="mx-auto w-full max-w-5xl">

    @if (! $this->bolehMengisi)

        {{-- ============================================================
             GERBANG TERTUTUP. Formnya tidak dirender sama sekali — bukan
             sekadar tombol simpannya dinonaktifkan.

             Pesannya dibedakan per sebab, karena tindakan yang harus diambil
             guru berbeda: "belum scan" bisa ia perbaiki sekarang juga,
             sedangkan "tidak ada jam mengajar" tidak.
             ============================================================ --}}
        <div class="flex w-full border-l-4 border-orange-500 bg-orange-500/10 px-7 py-8 shadow-md dark:bg-gray-900/30">
            <div class="mr-5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-500">
                <x-icon name="exclamation-triangle" class="h-5 w-5 text-white" />
            </div>

            <div class="w-full">
                @if (! $this->jadwalAktif)
                    <h5 class="mb-3 text-lg font-bold text-[#9D5425]">
                        Tidak ada jam mengajar Anda yang sedang berlangsung
                    </h5>
                    <p class="leading-relaxed text-[#D0915C]">
                        Jurnal kelas hanya bisa diisi selama jam pelajaran Anda berjalan
                        (dengan toleransi 15 menit sebelum dan sesudah). Sekarang
                        {{ now()->translatedFormat('l, d F Y') }} pukul {{ now()->format('H:i') }}.
                    </p>

                    @if (Route::has($panelPrefix . '.jadwal-pelajaran'))
                        <a href="{{ route($panelPrefix . '.jadwal-pelajaran') }}"
                            class="mt-5 inline-flex items-center gap-2 rounded-md bg-orange-500 px-6 py-3 font-medium text-white transition hover:bg-orange-500/90">
                            <x-icon name="calendar" class="h-5 w-5" />
                            Lihat Jadwal Mengajar
                        </a>
                    @endif
                @else
                    <h5 class="mb-3 text-lg font-bold text-[#9D5425]">
                        Anda belum men-scan QR ruangan untuk jam ini
                    </h5>
                    <p class="leading-relaxed text-[#D0915C]">
                        Jam yang sedang berjalan:
                        <span class="font-semibold">{{ $this->jadwalAktif->mata_pelajaran }}</span>
                        di <span class="font-semibold">{{ $this->jadwalAktif->kelas?->nama_kelas ?? '—' }}</span>
                        ({{ $this->jadwalAktif->rentangJam() }}).
                        Scan dulu stiker QR di meja guru ruangan tersebut, baru daftar hadir
                        siswa bisa diisi.
                    </p>

                    @if (Route::has($panelPrefix . '.absen-mengajar'))
                        <a href="{{ route($panelPrefix . '.absen-mengajar') }}"
                            class="mt-5 inline-flex items-center gap-2 rounded-md bg-orange-500 px-6 py-3 font-medium text-white transition hover:bg-orange-500/90">
                            <x-icon name="qr-code" class="h-5 w-5" />
                            Absen Mengajar (QR)
                        </a>
                    @endif
                @endif
            </div>
        </div>

    @else

        @php $jadwal = $this->jadwalAktif; @endphp

        {{-- ============ KARTU INFORMASI JAM PELAJARAN ============ --}}
        <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-6 px-6 py-5">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
                    <x-icon name="clipboard-check" class="h-6 w-6" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-lg font-bold text-brand-ink dark:text-white">{{ $jadwal->mata_pelajaran }}</p>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                        Kelas <span class="font-semibold">{{ $jadwal->kelas?->nama_kelas ?? '—' }}</span>
                        &middot; <span class="font-mono">{{ $jadwal->rentangJam() }}</span>
                        @if ($jadwal->ruangan)
                            &middot; {{ $jadwal->ruangan }}
                        @endif
                    </p>
                </div>

                <div class="shrink-0 text-right">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-success-500/10 px-3 py-1 text-xs font-semibold text-success-500">
                        <x-icon name="check-circle" class="h-3.5 w-3.5" />
                        QR ruangan terscan {{ $this->scanCocok->waktu_mulai->format('H:i') }}
                    </span>
                    <p class="mt-1.5 text-xs text-brand-muted dark:text-brand-faint">
                        {{ now()->translatedFormat('l, d F Y') }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ============ NOTIFIKASI ============ --}}
        @if ($notif)
            @php
                $gaya = match ($notif['tipe']) {
                    'ok' => ['garis' => 'border-success-500 bg-success-500/10', 'ikon' => 'check-circle', 'warnaIkon' => 'text-success-500', 'judul' => 'text-brand-ink dark:text-white'],
                    'warn' => ['garis' => 'border-warning-500 bg-warning-500/10', 'ikon' => 'clock', 'warnaIkon' => 'text-warning-500', 'judul' => 'text-[#9D5425]'],
                    default => ['garis' => 'border-error-500 bg-error-500/10', 'ikon' => 'x-circle', 'warnaIkon' => 'text-error-500', 'judul' => 'text-[#B45454]'],
                };
            @endphp

            <div wire:key="notif-jurnal-{{ md5($notif['pesan']) }}-{{ now()->format('Hisu') }}"
                class="mb-6 flex w-full border-l-4 px-5 py-4 shadow-md {{ $gaya['garis'] }}">
                <x-icon name="{{ $gaya['ikon'] }}" class="mr-4 mt-0.5 h-5 w-5 shrink-0 {{ $gaya['warnaIkon'] }}" />
                <div class="min-w-0">
                    <h5 class="mb-1 font-semibold {{ $gaya['judul'] }}">{{ $notif['judul'] }}</h5>
                    <p class="text-sm leading-relaxed text-brand-muted dark:text-brand-faint">{{ $notif['pesan'] }}</p>
                </div>
            </div>
        @endif

        {{-- ============ DAFTAR SISWA ============ --}}
        <form wire:submit="simpan"
            class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="font-semibold text-brand-ink dark:text-white">
                        Daftar Hadir Siswa
                        <span class="ml-1 text-sm font-normal text-brand-muted dark:text-brand-faint">
                            ({{ $this->daftarSiswa->count() }} siswa)
                        </span>
                    </h3>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                        Semua siswa sudah terisi <span class="font-semibold">Hadir</span>.
                        Ubah hanya yang tidak masuk.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] table-auto">
                    <thead>
                        <tr class="bg-gray-50 text-left dark:bg-gray-800">
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">No</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">NIS</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Nama Siswa</th>
                            <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->daftarSiswa as $i => $siswa)
                            <tr wire:key="siswa-{{ $siswa->id }}">
                                <td class="border-b border-gray-200 px-4 py-4 text-sm text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                                    {{ $i + 1 }}
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 font-mono text-sm text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                                    {{ $siswa->nis }}
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                    <p class="font-medium text-brand-ink dark:text-white">{{ $siswa->nama }}</p>

                                    {{-- Penanda ini yang membuat "bolos" masuk akal
                                         bagi guru: ia bisa melihat siapa yang tadi
                                         pagi lewat gerbang tapi tidak ada di kelas. --}}
                                    @if ($this->hadirDiGerbang->contains($siswa->id))
                                        <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-success-500">
                                            <x-icon name="qr-code" class="h-3 w-3" />
                                            Masuk gerbang pagi ini
                                        </span>
                                    @endif
                                </td>
                                <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($this->pilihanStatus as $pilihan)
                                            @php $dipilih = ($status[$siswa->id] ?? 'hadir') === $pilihan->value; @endphp

                                            <label
                                                class="flex cursor-pointer select-none items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium transition
                                                       {{ $dipilih
                                                            ? $pilihan->kelasTitik()
                                                            : 'border-gray-200 text-brand-muted hover:border-brand-500 dark:border-gray-800 dark:text-brand-faint' }}">
                                                <input type="radio"
                                                    wire:model="status.{{ $siswa->id }}"
                                                    value="{{ $pilihan->value }}"
                                                    class="sr-only">

                                                <span class="flex h-4 w-4 items-center justify-center rounded-full border
                                                             {{ $dipilih ? $pilihan->kelasTitik() : 'border-gray-200 dark:border-gray-800' }}">
                                                    <span class="h-2 w-2 rounded-full {{ $dipilih ? 'bg-current' : 'bg-transparent' }}"></span>
                                                </span>

                                                {{ $pilihan->label() }}
                                            </label>
                                        @endforeach
                                    </div>

                                    {{-- Keterangan hanya muncul untuk status selain
                                         Hadir — kolom yang selalu tampil membuat
                                         tabel 30 baris jadi dinding input kosong. --}}
                                    @if (($status[$siswa->id] ?? 'hadir') !== 'hadir')
                                        <input type="text" wire:model="keterangan.{{ $siswa->id }}"
                                            maxlength="255" placeholder="Keterangan (opsional)"
                                            class="mt-2 w-full max-w-xs rounded-md border border-gray-200 bg-transparent px-3 py-1.5 text-xs text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-12 text-center">
                                    <p class="text-sm font-semibold text-brand-ink dark:text-white">Kelas ini belum punya siswa</p>
                                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                                        Hubungi Admin TU untuk memasukkan data siswanya lebih dulu.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->daftarSiswa->isNotEmpty())
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                    <p class="text-xs text-brand-muted dark:text-brand-faint">
                        Siswa yang ditandai <span class="font-semibold">Alpa</span> padahal tadi pagi
                        masuk gerbang akan otomatis dicatat sebagai <span class="font-semibold">Bolos</span>.
                    </p>

                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-500/90 disabled:opacity-60">
                        <x-icon name="check-circle" class="h-5 w-5" />
                        <span wire:loading.remove wire:target="simpan">Simpan Absensi KBM</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </button>
                </div>
            @endif
        </form>

    @endif
</div>
