{{-- Dipakai bersama oleh create.blade.php & edit.blade.php --}}
<div class="max-w-lg space-y-4">
    <div>
        <label for="nis" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="hashtag" class="h-3.5 w-3.5" />
            NIS
        </label>
        <input id="nis" name="nis" type="text" value="{{ old('nis', $siswa->nis ?? '') }}" required
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm font-mono focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="nama" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="identification" class="h-3.5 w-3.5" />
            Nama Lengkap
        </label>
        <input id="nama" name="nama" type="text" value="{{ old('nama', $siswa->nama ?? '') }}" required
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

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
                    <option value="{{ $k->id }}" @selected((string) old('kelas_id', $siswa->kelas_id ?? '') === (string) $k->id)>
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
        <label for="wali_murid_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="users" class="h-3.5 w-3.5" />
            Akun Wali Murid
        </label>
        <div class="relative">
            <select id="wali_murid_id" name="wali_murid_id"
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Belum ditautkan —</option>
                @foreach ($daftarWaliMurid as $wali)
                    <option value="{{ $wali->id }}" @selected((string) old('wali_murid_id', $siswa->wali_murid_id ?? '') === (string) $wali->id)>
                        {{ $wali->name }} ({{ $wali->email }})
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
        @if ($daftarWaliMurid->isEmpty())
            <p class="mt-1 text-xs text-brand-muted">
                Belum ada akun dengan role Wali Murid.
                <a href="{{ route($panelPrefix . '.users.create') }}" class="text-brand-accent-text hover:underline">Buat akunnya dulu</a>.
            </p>
        @endif
    </div>

    <div>
        <label for="no_hp_wali" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="phone" class="h-3.5 w-3.5" />
            No. HP Wali (untuk notifikasi WhatsApp)
        </label>
        <input id="no_hp_wali" name="no_hp_wali" type="text" value="{{ old('no_hp_wali', $siswa->no_hp_wali ?? '') }}"
            placeholder="mis. 6281234567890"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>
</div>
