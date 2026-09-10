@php
    $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-4 py-2.5 text-sm font-medium text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:border-brand-500';
    $jadwal = $this->jadwal;
    $peran = $this->peranSaya;

    $warnaStatus = [
        'hadir' => 'bg-success-500/10 text-success-700 dark:text-success-400 ring-success-200',
        'izin' => 'bg-warning-500/10 text-warning-700 dark:text-warning-400 ring-warning-200',
        'sakit' => 'bg-brand-500/10 text-brand-accent-text ring-brand-500/30',
        'alpha' => 'bg-error-500/10 text-error-700 dark:text-error-400 ring-error-200',
    ];
@endphp

<div class="space-y-6">

    @if (! $jadwal)
        <div class="rounded-2xl border border-gray-200 bg-white p-10 text-center dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-sm text-gray-500 dark:text-gray-400">Jadwal ekskul ini sudah tidak ada.</p>
        </div>
    @else

    {{-- ================= NOTIFIKASI ================= --}}
    @if ($notif)
        @php
            $gaya = match ($notif['tipe']) {
                'ok' => ['kotak' => 'border-success-200 bg-success-500/10 dark:border-success-500/30', 'teks' => 'text-success-700 dark:text-success-400', 'ikon' => 'check-circle'],
                'warn' => ['kotak' => 'border-warning-200 bg-warning-500/10 dark:border-warning-500/30', 'teks' => 'text-warning-700 dark:text-warning-400', 'ikon' => 'exclamation-triangle'],
                default => ['kotak' => 'border-error-200 bg-error-500/10 dark:border-error-500/30', 'teks' => 'text-error-700 dark:text-error-400', 'ikon' => 'x-circle'],
            };
        @endphp

        <div id="ab-notif" class="flex items-start gap-3 rounded-2xl border p-4 {{ $gaya['kotak'] }}" role="status">
            <x-icon name="{{ $gaya['ikon'] }}" class="mt-0.5 h-5 w-5 shrink-0 {{ $gaya['teks'] }}" />
            <div class="min-w-0">
                <p class="text-sm font-bold {{ $gaya['teks'] }}">{{ $notif['judul'] }}</p>
                <p class="mt-0.5 text-sm {{ $gaya['teks'] }}">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    {{-- ================= KARTU IDENTITAS EKSKUL ================= --}}
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex items-center gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-brand-500/10 text-brand-accent-text">
                <x-icon name="clipboard-check" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ $jadwal->nama_ekskul }}</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $jadwal->hari }} &middot; {{ $jadwal->rentangJam() }} &middot; Pembina: {{ $jadwal->namaPembina() }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if ($peran === 'isi')
                <a href="{{ route($panelPrefix . '.ekskul.anggota', $jadwal->id) }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                    <x-icon name="users" class="h-4 w-4" />
                    Anggota
                </a>
            @endif
            <a href="{{ route($panelPrefix . '.ekskul') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Kembali
            </a>
        </div>
    </div>

    {{-- ============================================================
         MODE PEMBINA / ADMIN — form isi kehadiran
         ============================================================ --}}
    @if ($peran === 'isi')
        <div id="ab-form" class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex flex-wrap items-end justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <div>
                    <h3 class="font-semibold text-gray-800 dark:text-gray-100">Isi Kehadiran</h3>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        Bawaan setiap siswa <span class="font-semibold">Hadir</span> — ubah hanya yang tidak datang.
                    </p>
                </div>

                <div class="w-full sm:w-56">
                    <label for="ab-tanggal" class="mb-1.5 block text-xs font-semibold text-gray-600 dark:text-gray-400">
                        Tanggal Pertemuan
                    </label>
                    <input id="ab-tanggal" type="date" wire:model.live="tanggal"
                        max="{{ now()->toDateString() }}" class="{{ $kelasInput }}">
                </div>
            </div>

            @if ($this->anggota->isEmpty())
                <div class="flex flex-col items-center gap-3 p-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                        <x-icon name="users" class="h-6 w-6" />
                    </span>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Ekskul ini belum punya anggota, jadi belum ada yang bisa diabsen.
                    </p>
                    <a href="{{ route($panelPrefix . '.ekskul.anggota', $jadwal->id) }}" wire:navigate
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                        <x-icon name="plus" class="h-4 w-4" />
                        Tambah Anggota
                    </a>
                </div>
            @else
                @if ($this->sudahTerisi)
                    <div class="mx-6 mt-5 flex items-start gap-2.5 rounded-xl bg-brand-500/10 p-3">
                        <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0 text-brand-accent-text" />
                        <p class="text-xs text-brand-accent-text">
                            Pertemuan tanggal ini <strong>sudah pernah diisi</strong>. Menyimpan lagi akan memperbarui isian sebelumnya, bukan menambah baris baru.
                        </p>
                    </div>
                @endif

                <form wire:submit="simpan">
                    <div class="max-w-full overflow-x-auto">
                        <table class="w-full min-w-[820px] text-left text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                                    <th class="px-5 py-4 font-medium">Siswa</th>
                                    <th class="px-5 py-4 font-medium">Kelas</th>
                                    <th class="px-5 py-4 font-medium">Status Kehadiran</th>
                                    <th class="w-1/4 px-5 py-4 font-medium">Keterangan</th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                @foreach ($this->anggota as $siswa)
                                    <tr wire:key="absen-{{ $siswa->id }}" class="align-top">
                                        <td class="px-5 py-4">
                                            <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $siswa->nama }}</p>
                                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $siswa->nis ?: 'tanpa NIS' }}</p>
                                        </td>

                                        <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                            {{ $siswa->kelas?->nama_kelas ?? '—' }}
                                        </td>

                                        <td class="px-5 py-4">
                                            {{-- Radio, bukan dropdown: empat pilihan yang
                                                 semuanya terlihat sekaligus jauh lebih cepat
                                                 diisi untuk dua puluh baris berturut-turut,
                                                 dan tidak menyembunyikan status yang sedang
                                                 dipilih di balik satu klik. --}}
                                            <div class="flex flex-wrap gap-1.5">
                                                @foreach ($pilihanStatus as $s)
                                                    @php $aktif = ($status[$siswa->id] ?? '') === $s->value; @endphp
                                                    <label class="cursor-pointer">
                                                        {{-- ============ WAJIB .live ============
                                                             Sama persis dengan kasus di Jurnal &
                                                             Absen Kelas: radio aslinya sr-only, dan
                                                             warna terpilihnya digambar SERVER dari
                                                             $aktif. Dengan `wire:model` biasa
                                                             (deferred sejak Livewire v3), server
                                                             tidak pernah merender ulang saat
                                                             diklik — pembina mengklik status dan
                                                             tampilannya tidak berubah sama sekali.

                                                             `peer` di sini hanya dipakai untuk
                                                             cincin fokus, BUKAN untuk warna
                                                             terpilih: warnanya berbeda per status
                                                             dan dirangkai saat runtime, sehingga
                                                             tidak bisa dipakai dengan varian
                                                             peer-checked: (Tailwind hanya membuat
                                                             class yang terlihat saat build).
                                                             ====================================== --}}
                                                        <input type="radio" class="peer sr-only"
                                                            wire:model.live="status.{{ $siswa->id }}" value="{{ $s->value }}">
                                                        <span class="inline-flex items-center rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 transition peer-focus-visible:outline-none peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-focus-visible:ring-offset-2
                                                            {{ $aktif
                                                                ? $warnaStatus[$s->value]
                                                                : 'bg-transparent text-gray-500 ring-gray-300 hover:bg-gray-100 dark:text-gray-400 dark:ring-gray-700 dark:hover:bg-white/[0.05]' }}">
                                                            {{ $s->shortLabel() }}
                                                        </span>
                                                    </label>
                                                @endforeach
                                            </div>
                                        </td>

                                        <td class="px-5 py-4">
                                            <input type="text" wire:model="keterangan.{{ $siswa->id }}"
                                                maxlength="255" placeholder="opsional"
                                                class="{{ $kelasInput }} py-2 text-xs">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                        <button type="submit" id="ab-simpan" wire:loading.attr="disabled" wire:target="simpan"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60">
                            <svg wire:loading wire:target="simpan" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            <span wire:loading.remove wire:target="simpan">Simpan Absensi</span>
                            <span wire:loading wire:target="simpan">Menyimpan…</span>
                        </button>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $this->anggota->count() }} siswa akan tercatat untuk tanggal yang dipilih.
                        </p>
                    </div>
                </form>
            @endif
        </div>

        {{-- Pertemuan yang sudah pernah diisi — pintasan, bukan sekadar hiasan:
             tanpa ini pembina harus menebak tanggal berapa saja yang sudah ada. --}}
        @if ($this->tanggalTerisi->isNotEmpty())
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="mb-3 text-sm font-semibold text-gray-800 dark:text-gray-100">Pertemuan yang sudah diisi</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->tanggalTerisi as $t)
                        @php $tgl = \Illuminate\Support\Carbon::parse($t)->toDateString(); @endphp
                        <button type="button" wire:click="$set('tanggal', '{{ $tgl }}')"
                            class="rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 transition
                                {{ $tanggal === $tgl
                                    ? 'bg-brand-500 text-white ring-brand-500'
                                    : 'text-gray-600 ring-gray-300 hover:bg-gray-100 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-white/[0.05]' }}">
                            {{ \Illuminate\Support\Carbon::parse($t)->translatedFormat('d M Y') }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    {{-- ============================================================
         MODE WALI MURID — hanya riwayat anaknya
         ============================================================ --}}
    @if ($peran === 'wali')
        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Riwayat Kehadiran Anak Anda</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Hanya menampilkan anak Anda yang terdaftar di ekskul ini.
                </p>
            </div>

            @if ($this->anakSaya->isEmpty())
                <div class="flex flex-col items-center gap-3 p-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                        <x-icon name="inbox" class="h-6 w-6" />
                    </span>
                    <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                        Anak Anda belum terdaftar sebagai anggota ekskul ini. Hubungi pembina
                        ({{ $jadwal->namaPembina() }}) bila seharusnya ikut.
                    </p>
                </div>
            @elseif ($this->riwayatAnak->isEmpty())
                <div class="flex flex-col items-center gap-3 p-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                        <x-icon name="calendar" class="h-6 w-6" />
                    </span>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Belum ada pertemuan yang tercatat untuk
                        {{ $this->anakSaya->pluck('nama')->join(', ', ' dan ') }}.
                    </p>
                </div>
            @else
                <div class="max-w-full overflow-x-auto">
                    <table id="ab-riwayat" class="w-full min-w-[620px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                                <th class="px-5 py-4 font-medium">Tanggal</th>
                                <th class="px-5 py-4 font-medium">Nama</th>
                                <th class="px-5 py-4 font-medium">Status</th>
                                <th class="px-5 py-4 font-medium">Keterangan</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($this->riwayatAnak as $baris)
                                <tr wire:key="riwayat-{{ $baris->id }}" class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <p class="font-medium text-gray-800 dark:text-gray-200">
                                            {{ $baris->tanggal->translatedFormat('d M Y') }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $baris->tanggal->translatedFormat('l') }}
                                        </p>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">{{ $baris->siswa?->nama ?? '—' }}</td>
                                    <td class="px-5 py-4">
                                        <x-badge status="{{ $baris->status_kehadiran->value }}" />
                                    </td>
                                    <td class="px-5 py-4 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $baris->keterangan ?: '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    {{-- ============================================================
         MODE BACA-SAJA — peran lain (guru non-pembina, staff, dll.)
         ============================================================ --}}
    @if ($peran === 'lihat')
        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Rekap Kehadiran</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Pengisian absensi hanya bisa dilakukan pembina ekskul ini ({{ $jadwal->namaPembina() }}).
                </p>
            </div>

            <div class="grid grid-cols-2 gap-4 p-6 sm:grid-cols-4">
                @foreach ($pilihanStatus as $s)
                    <div class="rounded-xl p-4 ring-1 {{ $warnaStatus[$s->value] }}">
                        <p class="text-2xl font-extrabold">{{ $this->rekap[$s->value] }}</p>
                        <p class="mt-0.5 text-xs font-semibold uppercase tracking-wide">{{ $s->shortLabel() }}</p>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->anggota->count() }} siswa terdaftar sebagai anggota.
                </p>
            </div>
        </div>
    @endif

    @endif
</div>
