@php
    $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-4 py-2.5 text-sm font-medium text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:border-brand-500';
    $jadwal = $this->jadwal;
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

        <div id="ag-notif" class="flex items-start gap-3 rounded-2xl border p-4 {{ $gaya['kotak'] }}" role="status">
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
                <x-icon name="users" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <h2 class="text-lg font-bold text-gray-800 dark:text-gray-100">{{ $jadwal->nama_ekskul }}</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $jadwal->hari }} &middot; {{ $jadwal->rentangJam() }} &middot; Pembina: {{ $jadwal->namaPembina() }}
                </p>
            </div>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route($panelPrefix . '.ekskul.absensi', $jadwal->id) }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-lg border border-brand-500/40 px-4 py-2.5 text-sm font-semibold text-brand-accent-text transition hover:bg-brand-500/10">
                <x-icon name="clipboard-check" class="h-4 w-4" />
                Absensi
            </a>
            <a href="{{ route($panelPrefix . '.ekskul') }}" wire:navigate
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Kembali
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">

        {{-- ================= CALON ANGGOTA ================= --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Tambah Anggota</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Pilih siswa yang belum tergabung, lalu tekan Tambahkan.
                </p>
            </div>

            <div class="space-y-4 p-6">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label for="ag-cari" class="sr-only">Cari siswa</label>
                        <input id="ag-cari" type="search" wire:model.live.debounce.300ms="cari"
                            placeholder="Cari nama atau NIS…" class="{{ $kelasInput }}">
                    </div>
                    <div class="relative">
                        <label for="ag-kelas" class="sr-only">Saring per kelas</label>
                        <select id="ag-kelas" wire:model.live="kelasId" class="{{ $kelasInput }} appearance-none pr-10">
                            <option value="">Semua kelas</option>
                            @foreach ($this->daftarKelas as $k)
                                <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                            <x-icon name="chevron-down" class="h-4 w-4" />
                        </span>
                    </div>
                </div>

                @if ($this->kandidat->isEmpty())
                    <div class="flex flex-col items-center gap-2 py-10 text-center">
                        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                            <x-icon name="inbox" class="h-5 w-5" />
                        </span>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ trim($cari) !== '' || $kelasId !== ''
                                ? 'Tidak ada siswa yang cocok dengan filter ini.'
                                : 'Semua siswa sudah tergabung di ekskul ini.' }}
                        </p>
                    </div>
                @else
                    <ul id="ag-kandidat" class="custom-scrollbar max-h-96 divide-y divide-gray-200 overflow-y-auto rounded-xl border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                        @foreach ($this->kandidat as $siswa)
                            <li wire:key="kandidat-{{ $siswa->id }}"
                                class="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                <input type="checkbox" id="ag-pilih-{{ $siswa->id }}"
                                    value="{{ $siswa->id }}" wire:model="pilih"
                                    class="h-4 w-4 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900">

                                <label for="ag-pilih-{{ $siswa->id }}" class="min-w-0 flex-1 cursor-pointer">
                                    <span class="block truncate text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $siswa->nama }}</span>
                                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $siswa->nis ?: 'tanpa NIS' }} &middot; {{ $siswa->kelas?->nama_kelas ?? 'belum berkelas' }}
                                    </span>
                                </label>

                                <button type="button" wire:click="tambahSatu({{ $siswa->id }})"
                                    wire:loading.attr="disabled" wire:target="tambahSatu({{ $siswa->id }})"
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-brand-500/40 text-brand-accent-text transition hover:bg-brand-500/10 disabled:opacity-50"
                                    aria-label="Tambahkan {{ $siswa->nama }}" title="Tambahkan langsung">
                                    <x-icon name="plus" class="h-4 w-4" />
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" id="ag-tambah" wire:click="tambahTerpilih"
                            wire:loading.attr="disabled" wire:target="tambahTerpilih"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-60">
                            <x-icon name="plus" class="h-4 w-4" />
                            Tambahkan {{ count(array_filter($pilih)) > 0 ? '(' . count(array_filter($pilih)) . ')' : '' }}
                        </button>

                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Menampilkan {{ $this->jumlahKandidat }} calon teratas — persempit dengan pencarian bila belum ketemu.
                        </p>
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= ANGGOTA SAAT INI ================= --}}
        <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Anggota Ekskul</h3>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->anggota->count() }} siswa tergabung
                </p>
            </div>

            @if ($this->anggota->isEmpty())
                <div class="flex flex-col items-center gap-3 p-10 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                        <x-icon name="users" class="h-6 w-6" />
                    </span>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Belum ada anggota. Pilih siswa di panel sebelah untuk menambahkan.
                    </p>
                </div>
            @else
                <div class="max-w-full overflow-x-auto">
                    <table class="w-full min-w-[420px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                                <th class="px-5 py-4 font-medium">Nama</th>
                                <th class="px-5 py-4 font-medium">Kelas</th>
                                <th class="px-5 py-4 text-right font-medium">Aksi</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                            @foreach ($this->anggota as $siswa)
                                <tr wire:key="anggota-{{ $siswa->id }}"
                                    class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $siswa->nama }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $siswa->nis ?: 'tanpa NIS' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-gray-600 dark:text-gray-300">
                                        {{ $siswa->kelas?->nama_kelas ?? '—' }}
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end">
                                            <button type="button" wire:click="keluarkan({{ $siswa->id }})"
                                                wire:loading.attr="disabled" wire:target="keluarkan({{ $siswa->id }})"
                                                data-konfirmasi-judul="Keluarkan dari Ekskul?"
                                                data-konfirmasi="{{ $siswa->nama }} dikeluarkan dari {{ $jadwal->nama_ekskul }}. Riwayat kehadirannya tetap tersimpan."
                                                data-konfirmasi-ikon="warning"
                                                data-konfirmasi-ya="Ya, Keluarkan!"
                                                data-konfirmasi-batal="Batal"
                                                class="flex h-9 w-9 items-center justify-center rounded-lg border border-error-500/40 text-error-600 transition hover:bg-error-500/10 disabled:opacity-50 dark:text-error-400"
                                                aria-label="Keluarkan {{ $siswa->nama }}" title="Keluarkan dari ekskul">
                                                <x-icon name="x-mark" class="h-4 w-4" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @endif
</div>
