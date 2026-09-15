<div class="min-w-0">

    @if (session('kalender-sukses'))
        <div class="muncul mb-5 flex items-start gap-3 rounded-2xl border border-emerald-300 bg-emerald-500/10 p-4" role="status">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <p class="text-sm font-semibold leading-relaxed text-emerald-800 dark:text-emerald-400">{{ session('kalender-sukses') }}</p>
        </div>
    @endif

    {{-- ================= RINGKASAN BULAN ================= --}}
    <div class="tampil-berurutan mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        @php
            $kartu = [
                ['Hari KBM',      $this->ringkasBulan['kbm'],    'academic-cap', 'text-emerald-600', 'Hari yang dihitung sebagai hari sekolah'],
                ['Hari Libur',    $this->ringkasBulan['libur'],  'x-circle',     'text-brand-danger-text', 'Libur mingguan + libur kalender'],
                ['Agenda Bulan Ini', $this->ringkasBulan['agenda'], 'calendar',  'text-sky-600', 'Jumlah agenda yang menyentuh bulan ini'],
                ['Total Hari',    $this->ringkasBulan['total'],  'clock',        'text-brand-muted', 'Jumlah hari kalender'],
            ];
        @endphp

        @foreach ($kartu as [$label, $nilai, $ikon, $warna, $bantu])
            <div class="kartu-angkat min-w-0 rounded-2xl border border-brand-border bg-brand-surface p-4 shadow-soft dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center gap-2">
                    <x-icon name="{{ $ikon }}" class="h-4 w-4 shrink-0 {{ $warna }}" />
                    <span class="truncate text-xs font-semibold uppercase tracking-wide text-brand-muted">{{ $label }}</span>
                </div>
                <p class="angka-naik mt-1.5 text-2xl font-extrabold text-brand-ink dark:text-white">{{ $nilai }}</p>
                <p class="mt-0.5 text-xs leading-snug text-brand-muted">{{ $bantu }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid min-w-0 gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] xl:items-start">

        {{-- ================= GRID KALENDER ================= --}}
        <div class="min-w-0 rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">

            {{-- Kepala: navigasi bulan --}}
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-4 py-3.5 dark:border-gray-800">
                <div class="min-w-0">
                    <h2 class="truncate text-lg font-bold text-brand-ink dark:text-white">
                        {{ $this->bulanDipakai->translatedFormat('F Y') }}
                    </h2>
                    <p class="mt-0.5 text-xs text-brand-muted">
                        Libur setiap minggu:
                        <strong>{{ collect($this->hariLiburMingguan)->map(fn ($h) => $h->label())->implode(', ') ?: 'tidak ada' }}</strong>
                    </p>
                </div>

                <div class="flex shrink-0 items-center gap-1.5">
                    <button type="button" wire:click="bulanSebelumnya"
                        class="rounded-lg border border-brand-border p-2 text-brand-muted hover:bg-brand-surface-muted dark:border-gray-800"
                        aria-label="Bulan sebelumnya">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                    </button>

                    <button type="button" wire:click="keBulanIni"
                        class="rounded-lg border border-brand-border px-3 py-2 text-xs font-semibold text-brand-muted hover:bg-brand-surface-muted dark:border-gray-800">
                        Hari Ini
                    </button>

                    <button type="button" wire:click="bulanBerikutnya"
                        class="rounded-lg border border-brand-border p-2 text-brand-muted hover:bg-brand-surface-muted dark:border-gray-800"
                        aria-label="Bulan berikutnya">
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </button>
                </div>
            </div>

            {{-- Grid: lebar minimum dijaga, kalau sempit halaman ini yang
                 menggulir mendatar — bukan seluruh badan halaman. --}}
            <div class="overflow-x-auto p-3">
                <div class="min-w-[20rem]">

                    {{-- Nama hari --}}
                    <div class="grid grid-cols-7 gap-1 pb-1.5">
                        @foreach (['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $nama)
                            <div class="py-1 text-center text-[11px] font-bold uppercase tracking-wide text-brand-muted">{{ $nama }}</div>
                        @endforeach
                    </div>

                    {{-- 42 kotak --}}
                    <div class="grid grid-cols-7 gap-1">
                        @foreach ($this->kotakKalender as $kotak)
                            @php
                                $dipilih = $tanggalDipilih === $kotak['kunci'];
                            @endphp

                            <button type="button"
                                wire:click="pilihTanggal('{{ $kotak['kunci'] }}')"
                                wire:key="kotak-{{ $kotak['kunci'] }}"
                                @class([
                                    'relative flex aspect-square min-w-0 flex-col items-center justify-start rounded-lg border p-1 text-left transition-colors',
                                    // Hari KBM: latar netral. Libur: merah samar.
                                    'border-brand-border bg-brand-surface hover:bg-brand-surface-muted dark:border-gray-800' => $kotak['hariKbm'],
                                    'border-red-200 bg-red-500/5 hover:bg-red-500/10 dark:border-red-500/20' => ! $kotak['hariKbm'],
                                    // Di luar bulan ini dipudarkan, bukan disembunyikan:
                                    // menghilangkannya membuat baris pertama dan terakhir
                                    // tampak bolong dan sulit dibaca sebagai kalender.
                                    'opacity-40' => ! $kotak['bulanIni'],
                                    'ring-2 ring-brand-accent' => $dipilih,
                                ])
                                aria-label="{{ $kotak['tanggal']->translatedFormat('l, d F Y') }}{{ $kotak['hariKbm'] ? ' — hari KBM' : ' — libur: ' . $kotak['alasan'] }}"
                                @if ($dipilih) aria-current="date" @endif>

                                <span @class([
                                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                                    'bg-brand-accent text-white' => $kotak['hariIni'],
                                    'text-brand-danger-text' => ! $kotak['hariIni'] && ! $kotak['hariKbm'],
                                    'text-brand-ink dark:text-gray-200' => ! $kotak['hariIni'] && $kotak['hariKbm'],
                                ])>{{ $kotak['angka'] }}</span>

                                {{-- Titik penanda agenda. Maksimal tiga — kotak
                                     tanggal di HP hanya selebar ±40px, dan titik
                                     keempat sudah membuatnya meluber. --}}
                                @if ($kotak['agenda']->isNotEmpty())
                                    <span class="mt-auto flex flex-wrap items-center justify-center gap-0.5 pb-0.5">
                                        @foreach ($kotak['agenda']->take(3) as $a)
                                            <span class="h-1.5 w-1.5 rounded-full {{ $a->jenis->kelasTitik() }}"></span>
                                        @endforeach
                                    </span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Keterangan warna --}}
            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-brand-border px-4 py-3 text-xs text-brand-muted dark:border-gray-800">
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded border border-brand-border bg-brand-surface dark:border-gray-700"></span>
                    Hari KBM
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded border border-red-200 bg-red-500/10"></span>
                    Libur
                </span>
                @foreach (\App\Enums\JenisAgenda::semua() as $j)
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $j->kelasTitik() }}"></span>
                        {{ $j->label() }}
                    </span>
                @endforeach
            </div>
        </div>

        {{-- ================= PANEL KANAN ================= --}}
        <div class="min-w-0 space-y-4">

            {{-- ---- Detail tanggal terpilih ---- --}}
            @if ($this->detailTanggal)
                @php $d = $this->detailTanggal; @endphp

                <div class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-brand-border px-5 py-4 dark:border-gray-800">
                        <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">Tanggal Terpilih</p>
                        <p class="mt-0.5 text-base font-bold text-brand-ink dark:text-white">
                            {{ $d['tanggal']->translatedFormat('l, d F Y') }}
                        </p>

                        {{-- Status hari ditulis sebagai KALIMAT, bukan hanya
                             warna. Warna saja tidak terbaca pembaca layar, dan
                             tidak menjelaskan KENAPA harinya libur. --}}
                        @if ($d['hariKbm'])
                            <p class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-emerald-300 bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                                <x-icon name="check-circle" class="h-3.5 w-3.5" />
                                Hari KBM — sekolah masuk
                            </p>
                        @else
                            <p class="mt-2 inline-flex items-start gap-1.5 rounded-full border border-red-300 bg-red-500/10 px-3 py-1 text-xs font-bold text-brand-danger-text">
                                <x-icon name="x-circle" class="mt-px h-3.5 w-3.5 shrink-0" />
                                <span class="min-w-0">Libur — {{ $d['alasan'] }}</span>
                            </p>
                        @endif
                    </div>

                    <div class="px-5 py-4">
                        @if ($d['agenda']->isEmpty())
                            <p class="text-sm text-brand-muted">Tidak ada agenda pada tanggal ini.</p>
                        @else
                            <ul class="space-y-3">
                                @foreach ($d['agenda'] as $a)
                                    <li class="min-w-0" wire:key="detail-{{ $a->id }}">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $a->jenis->kelasBadge() }}">
                                                <x-icon name="{{ $a->jenis->ikon() }}" class="h-3 w-3" />
                                                {{ $a->jenis->label() }}
                                            </span>
                                            @if ($a->dariKaldik())
                                                <span class="shrink-0 rounded-full bg-brand-surface-muted px-2 py-0.5 text-[11px] font-medium text-brand-muted">Kaldik Dinas</span>
                                            @endif
                                        </div>

                                        <p class="mt-1 text-sm font-semibold text-brand-ink dark:text-white">{{ $a->judul }}</p>
                                        <p class="text-xs text-brand-muted">{{ $a->rentangTanggal() }}@if (! $a->satuHari()) · {{ $a->jumlahHari() }} hari @endif</p>

                                        @if ($a->keterangan)
                                            <p class="mt-1 text-xs leading-relaxed text-brand-muted">{{ $a->keterangan }}</p>
                                        @endif

                                        @if ($this->bolehKelola && ! $a->dariKaldik())
                                            <div class="mt-1.5 flex gap-2">
                                                <button type="button" wire:click="ubah({{ $a->id }})"
                                                    class="inline-flex items-center gap-1 rounded-lg border border-brand-border px-2.5 py-1 text-[11px] font-medium text-brand-muted hover:bg-brand-surface-muted dark:border-gray-800">
                                                    <x-icon name="pencil" class="h-3 w-3" /> Ubah
                                                </button>
                                                <button type="button" wire:click="hapus({{ $a->id }})"
                                                    wire:confirm="Hapus agenda '{{ $a->judul }}'? Hari yang tadinya libur akan kembali dihitung sebagai hari KBM."
                                                    class="inline-flex items-center gap-1 rounded-lg border border-red-300 px-2.5 py-1 text-[11px] font-medium text-brand-danger-text hover:bg-red-500/10">
                                                    <x-icon name="trash" class="h-3 w-3" /> Hapus
                                                </button>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @if ($this->bolehKelola)
                            <button type="button" wire:click="bukaForm('{{ $d['tanggal']->toDateString() }}')"
                                class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border-2 border-dashed border-brand-border px-4 py-2.5 text-sm font-semibold text-brand-muted hover:border-brand-accent hover:text-brand-accent-text dark:border-gray-800">
                                <x-icon name="plus" class="h-4 w-4" />
                                Tambah Agenda / Libur
                            </button>
                        @endif
                    </div>
                </div>
            @endif

            {{-- ---- Daftar agenda bulan ini ---- --}}
            <div class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">
                <h2 class="flex items-center gap-1.5 border-b border-brand-border px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-brand-muted dark:border-gray-800">
                    <x-icon name="calendar" class="h-3.5 w-3.5" />
                    Agenda {{ $this->bulanDipakai->translatedFormat('F Y') }}
                </h2>

                @if ($this->agendaBulanIni->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-brand-muted">Tidak ada agenda bulan ini.</p>
                @else
                    <ul class="divide-y divide-brand-border dark:divide-gray-800">
                        @foreach ($this->agendaBulanIni as $a)
                            <li class="min-w-0 px-5 py-3" wire:key="bulan-{{ $a->id }}">
                                <div class="flex items-start gap-2">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $a->jenis->kelasTitik() }}"></span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-brand-ink dark:text-white">{{ $a->judul }}</p>
                                        <p class="text-xs text-brand-muted">{{ $a->rentangTanggal() }}</p>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>

    {{-- ================= FORM TAMBAH / UBAH ================= --}}
    @if ($formTerbuka && $this->bolehKelola)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-4"
            role="dialog" aria-modal="true" aria-labelledby="judul-form-agenda">

            <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-t-2xl bg-brand-surface shadow-xl sm:rounded-2xl dark:bg-gray-900">
                <div class="flex items-center justify-between border-b border-brand-border px-5 py-4 dark:border-gray-800">
                    <h2 id="judul-form-agenda" class="text-base font-bold text-brand-ink dark:text-white">
                        {{ $agendaId ? 'Ubah Agenda' : 'Tambah Agenda / Libur' }}
                    </h2>
                    <button type="button" wire:click="tutupForm" class="rounded-lg p-1.5 text-brand-muted hover:bg-brand-surface-muted" aria-label="Tutup">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="simpan" class="space-y-4 px-5 py-4">
                    <div>
                        <label for="ag-judul" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                            Judul <span class="text-brand-danger-text">*</span>
                        </label>
                        {{-- wire:model biasa (deferred), BUKAN .live: setiap
                             ketikan pada .live berarti satu permintaan ke
                             server, dan isian ini tidak mengubah apa pun di
                             layar sampai disimpan. --}}
                        <input id="ag-judul" type="text" wire:model="judul" maxlength="150"
                            placeholder="Mis. Libur Haul Pendiri Pesantren"
                            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        @error('judul') <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="min-w-0">
                            <label for="ag-mulai" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                                Tanggal Mulai <span class="text-brand-danger-text">*</span>
                            </label>
                            <input id="ag-mulai" type="date" wire:model="tanggalMulai"
                                class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                            @error('tanggalMulai') <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p> @enderror
                        </div>

                        <div class="min-w-0">
                            <label for="ag-selesai" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                                Tanggal Selesai <span class="text-brand-danger-text">*</span>
                            </label>
                            <input id="ag-selesai" type="date" wire:model="tanggalSelesai"
                                class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                            @error('tanggalSelesai') <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p> @enderror
                            <p class="mt-1 text-xs text-brand-muted">Untuk satu hari, isi sama dengan tanggal mulai.</p>
                        </div>
                    </div>

                    <fieldset>
                        <legend class="mb-1.5 text-sm font-medium text-brand-ink dark:text-white">
                            Jenis <span class="text-brand-danger-text">*</span>
                        </legend>

                        <div class="grid gap-2 sm:grid-cols-3">
                            @foreach (\App\Enums\JenisAgenda::semua() as $j)
                                @php $idJ = 'ag-jenis-' . $j->value; @endphp
                                <label for="{{ $idJ }}" class="cursor-pointer select-none">
                                    {{-- .live WAJIB pada radio sr-only: tanpa itu
                                         nilainya baru sampai ke server saat form
                                         dikirim, sementara tampilan peer-checked
                                         sudah berubah — dan kalau ada bagian lain
                                         yang bergantung padanya, layar dan data
                                         berbeda tanpa ada yang tahu.
                                         Penjaganya: tests/Feature/BindingRadioTersembunyiTest. --}}
                                    <input type="radio" id="{{ $idJ }}" value="{{ $j->value }}"
                                        wire:model.live="jenis" class="peer sr-only">

                                    <span class="flex h-full items-center gap-1.5 rounded-xl border-2 border-brand-border px-3 py-2 text-xs font-semibold text-brand-muted transition-colors peer-checked:border-brand-accent peer-checked:bg-brand-accent-soft peer-checked:text-brand-accent-text peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent/40 dark:border-gray-800">
                                        <x-icon name="{{ $j->ikon() }}" class="h-3.5 w-3.5 shrink-0" />
                                        {{ $j->label() }}
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <p class="mt-2 flex items-start gap-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs leading-relaxed text-amber-800 dark:text-amber-400">
                            <x-icon name="exclamation-triangle" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            <span>
                                Hanya <strong>Libur</strong> yang membuat hari itu berhenti dihitung sebagai hari sekolah.
                                Kegiatan dan Ujian tetap hari masuk — siswa tetap wajib hadir dan tetap ditandai alpa bila tidak datang.
                            </span>
                        </p>
                    </fieldset>

                    <div>
                        <label for="ag-ket" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                            Keterangan <span class="text-xs font-normal text-brand-muted">(opsional)</span>
                        </label>
                        <textarea id="ag-ket" wire:model="keterangan" rows="2" maxlength="1000"
                            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white"></textarea>
                        @error('keterangan') <p class="mt-1 text-xs font-medium text-brand-danger-text">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex gap-2 pt-1">
                        <button type="submit" wire:loading.attr="disabled"
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white shadow-soft disabled:opacity-60">
                            <x-icon name="check-circle" class="h-4 w-4" />
                            <span wire:loading.remove wire:target="simpan">Simpan</span>
                            <span wire:loading wire:target="simpan">Menyimpan…</span>
                        </button>
                        <button type="button" wire:click="tutupForm"
                            class="rounded-xl border border-brand-border px-5 py-2.5 text-sm font-medium text-brand-muted dark:border-gray-800">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
