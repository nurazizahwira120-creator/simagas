{{--
    Halaman Jadwal Pelajaran — HANYA BACA.

    PANTANGAN: jangan menambahkan tombol Tambah / Edit / Hapus di sini.
    Penyuntingan jadwal satu pintu di Super Admin > Manajemen Akademik.
    Komponen App\Livewire\JadwalPelajaran memang tidak punya satu pun method
    yang menulis ke database, jadi tombol semacam itu tidak akan berfungsi.
--}}
<div class="space-y-5">

    {{-- ============ NOTIFIKASI JADWAL HARI INI (guru & wali kelas) ============
         Sengaja dipisah dari tabel: tabel menampilkan hari yang SEDANG DIPILIH
         di filter, sedangkan kotak ini selalu tentang HARI INI. Kalau guru
         menggeser filter ke hari lain, pengingat "hari ini Anda mengajar jam
         sekian" tidak boleh ikut hilang. --}}
    @if ($this->jadwalHariIni->isNotEmpty())
        <div class="flex gap-3 rounded-2xl border border-teal-200 dark:border-teal-500/30 bg-teal-500/10 p-4">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white">
                <x-icon name="bell" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-teal-900">
                    Anda mengajar {{ $this->jadwalHariIni->count() }} jam pelajaran hari ini
                </p>
                <p class="mt-0.5 text-xs text-teal-700 dark:text-teal-400">
                    {{ now()->translatedFormat('l, d F Y') }}
                </p>

                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($this->jadwalHariIni as $item)
                        <span class="inline-flex items-center gap-2 rounded-lg border border-teal-200 dark:border-teal-500/30 bg-brand-surface px-3 py-1.5 text-xs">
                            <span class="font-mono font-semibold text-teal-700 dark:text-teal-400">{{ $item->rentangJam() }}</span>
                            <span class="font-semibold text-brand-ink">{{ $item->mata_pelajaran }}</span>
                            <span class="text-brand-faint">&middot;</span>
                            <span class="text-brand-muted">{{ $item->kelas?->nama_kelas ?? '—' }}</span>
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ============ KARTU JADWAL ============ --}}
    <div class="rounded-2xl border border-brand-border bg-brand-surface p-6 shadow-sm">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="min-w-0">
                <p class="text-sm font-bold text-brand-ink">{{ $this->judulSumber }}</p>
                <p class="mt-0.5 text-xs text-brand-faint">
                    Data jadwal disusun oleh Super Admin dan tidak dapat diubah dari halaman ini.
                </p>
            </div>

            {{-- Filter hari. wire:model.live supaya tabel berganti begitu
                 pilihan berubah, tanpa tombol "Tampilkan". --}}
            <div class="w-full sm:w-56">
                <label for="hari_terpilih" class="mb-1.5 block text-xs font-semibold text-brand-muted">
                    Pilih Hari
                </label>
                <div class="relative">
                    <select id="hari_terpilih" wire:model.live="hari_terpilih"
                        class="w-full appearance-none rounded-xl border border-brand-border bg-brand-surface py-2.5 pl-3.5 pr-10 text-sm font-medium text-brand-ink focus:border-teal-500 focus:outline-none focus:ring-2 focus:ring-teal-500/20">
                        {{-- "Semua Hari" ditaruh paling atas: itu pilihan yang
                             dipakai orang untuk melihat jadwal sepekan penuh,
                             dan juga nilai default di hari libur. --}}
                        <option value="{{ \App\Livewire\JadwalPelajaran::SEMUA }}">Semua Hari</option>
                        @foreach ($this->pilihanHari as $hari)
                            <option value="{{ $hari->value }}">{{ $hari->label() }}</option>
                        @endforeach
                    </select>
                    <x-icon name="chevron-down" class="pointer-events-none absolute right-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-brand-faint" />
                </div>
            </div>
        </div>

        <div class="mt-5 -mx-6 overflow-x-auto px-6">
            {{-- wire:loading.class meredupkan tabel saat hari diganti, supaya
                 jelas bahwa isinya sedang diperbarui, bukan sedang kosong. --}}
            <table class="min-w-full text-left text-sm"
                wire:loading.class="opacity-50" wire:target="hari_terpilih">
                <thead>
                    <tr class="border-b border-brand-border text-[11px] font-bold uppercase tracking-wide text-brand-faint">
                        @if ($this->semuaHariDipilih())
                            <th class="whitespace-nowrap pb-3 pr-4">Hari</th>
                        @endif
                        <th class="whitespace-nowrap pb-3 pr-4">Jam</th>
                        <th class="whitespace-nowrap pb-3 pr-4">Mata Pelajaran</th>
                        <th class="whitespace-nowrap pb-3 pr-4">Kelas</th>
                        @if ($this->tampilkanGuru)
                            <th class="whitespace-nowrap pb-3 pr-4">Guru</th>
                        @endif
                        <th class="whitespace-nowrap pb-3">Ruangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->jadwalHari as $item)
                        <tr class="border-b border-brand-border last:border-0">
                            @if ($this->semuaHariDipilih())
                                <td class="whitespace-nowrap py-3.5 pr-4 font-semibold text-brand-ink">
                                    {{ $item->hari->label() }}
                                </td>
                            @endif
                            <td class="whitespace-nowrap py-3.5 pr-4 font-mono text-[13px] font-semibold text-brand-ink">
                                {{ $item->rentangJam() }}
                            </td>
                            <td class="py-3.5 pr-4 font-semibold text-brand-ink">
                                {{ $item->mata_pelajaran }}
                            </td>
                            <td class="whitespace-nowrap py-3.5 pr-4">
                                <span class="inline-flex rounded-lg bg-brand-surface-muted px-2.5 py-1 text-xs font-semibold text-brand-muted">
                                    {{ $item->kelas?->nama_kelas ?? '—' }}
                                </span>
                            </td>
                            @if ($this->tampilkanGuru)
                                <td class="py-3.5 pr-4 text-brand-ink">
                                    {{ $item->guru?->nama ?? '—' }}
                                </td>
                            @endif
                            <td class="whitespace-nowrap py-3.5 text-brand-ink">
                                {{ $item->ruangan ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ ($this->tampilkanGuru ? 5 : 4) + ($this->semuaHariDipilih() ? 1 : 0) }}" class="py-12">
                                <div class="flex flex-col items-center gap-3 text-center">
                                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-surface-muted text-brand-faint">
                                        <x-icon name="calendar" class="h-7 w-7" />
                                    </span>
                                    <div>
                                        <p class="text-sm font-semibold text-brand-muted">
                                            Tidak ada jadwal pelajaran di hari ini
                                        </p>
                                        <p class="mt-1 text-xs text-brand-faint">
                                            @if ($this->semuaHariDipilih())
                                                Belum ada jadwal sama sekali untuk Anda sepanjang Senin&ndash;Sabtu.
                                            @else
                                                Hari {{ $this->labelHariTerpilih }} kosong. Coba pilih hari lain di atas.
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
