{{-- Dipakai bersama oleh create.blade.php & edit.blade.php --}}
<div class="max-w-lg space-y-4">
    <div>
        <label for="kelas_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="academic-cap" class="h-3.5 w-3.5" />
            Kelas
        </label>
        <div class="relative">
            <select id="kelas_id" name="kelas_id" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Pilih kelas —</option>
                @foreach ($daftarKelas as $k)
                    <option value="{{ $k->id }}" @selected((string) old('kelas_id', $jadwal->kelas_id ?? '') === (string) $k->id)>
                        {{ $k->nama_kelas }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
    </div>

    <div>
        <label for="guru_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="briefcase" class="h-3.5 w-3.5" />
            Guru Pengajar
        </label>
        <div class="relative">
            <select id="guru_id" name="guru_id" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Pilih guru —</option>

                @if ($guruPengajar->isNotEmpty())
                    <optgroup label="Guru & Wali Kelas">
                        @foreach ($guruPengajar as $g)
                            <option value="{{ $g->id }}" @selected((string) old('guru_id', $jadwal->guru_id ?? '') === (string) $g->id)>
                                {{ $g->nama }} — {{ $g->jabatan }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif

                @if ($pegawaiLain->isNotEmpty())
                    <optgroup label="Pegawai Lain">
                        @foreach ($pegawaiLain as $g)
                            <option value="{{ $g->id }}" @selected((string) old('guru_id', $jadwal->guru_id ?? '') === (string) $g->id)>
                                {{ $g->nama }} — {{ $g->jabatan }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
        @if ($guruPengajar->isEmpty() && $pegawaiLain->isEmpty())
            <p class="mt-1 text-xs text-brand-muted">
                Belum ada data pegawai.
                <a href="{{ route($panelPrefix . '.pegawai.create') }}" class="text-brand-accent-text hover:underline">Tambahkan dulu</a>.
            </p>
        @endif
    </div>

    <div>
        <label for="mata_pelajaran" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="document-report" class="h-3.5 w-3.5" />
            Mata Pelajaran
        </label>
        <input id="mata_pelajaran" name="mata_pelajaran" type="text"
            value="{{ old('mata_pelajaran', $jadwal->mata_pelajaran ?? '') }}" required
            placeholder="mis. Pemrograman Web"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="ruangan" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="building" class="h-3.5 w-3.5" />
            Ruangan <span class="font-normal text-brand-muted/70">(opsional)</span>
        </label>
        <input id="ruangan" name="ruangan" type="text"
            value="{{ old('ruangan', $jadwal->ruangan ?? '') }}"
            placeholder="mis. Lab RPL 1"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="hari" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="calendar" class="h-3.5 w-3.5" />
            Hari
        </label>
        <div class="relative">
            <select id="hari" name="hari" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Pilih hari —</option>
                @foreach ($daftarHari as $h)
                    <option value="{{ $h->value }}" @selected(old('hari', $jadwal->hari->value ?? '') === $h->value)>
                        {{ $h->label() }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="jam_mulai" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="clock" class="h-3.5 w-3.5" />
                Jam Mulai
            </label>
            <input id="jam_mulai" name="jam_mulai" type="time" required
                value="{{ old('jam_mulai', isset($jadwal) ? $jadwal->jam_mulai->format('H:i') : '') }}"
                class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        </div>
        <div>
            <label for="jam_selesai" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="clock" class="h-3.5 w-3.5" />
                Jam Selesai
            </label>
            <input id="jam_selesai" name="jam_selesai" type="time" required
                value="{{ old('jam_selesai', isset($jadwal) ? $jadwal->jam_selesai->format('H:i') : '') }}"
                class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        </div>
    </div>

    <p class="flex items-start gap-1.5 rounded-lg bg-brand-surface-muted px-3 py-2 text-xs text-brand-muted">
        <x-icon name="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
        Sistem otomatis menolak jadwal yang bentrok — baik guru yang sama mengajar
        dua kelas sekaligus, maupun satu kelas kebagian dua pelajaran di jam yang sama.
    </p>
</div>
