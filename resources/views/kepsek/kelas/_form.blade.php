{{-- Dipakai bersama oleh create.blade.php & edit.blade.php --}}
<div class="max-w-lg space-y-4">
    <div>
        <label for="nama_kelas" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="academic-cap" class="h-3.5 w-3.5" />
            Nama Kelas
        </label>
        <input id="nama_kelas" name="nama_kelas" type="text" value="{{ old('nama_kelas', $kelas->nama_kelas ?? '') }}"
            placeholder="mis. XII RPL 1" required
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="wali_kelas_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="users" class="h-3.5 w-3.5" />
            Wali Kelas
        </label>
        <div class="relative">
            <select id="wali_kelas_id" name="wali_kelas_id"
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Belum ditentukan —</option>
                @foreach ($daftarWaliKelas as $guru)
                    <option value="{{ $guru->id }}" @selected((string) old('wali_kelas_id', $kelas->wali_kelas_id ?? '') === (string) $guru->id)>
                        {{ $guru->name }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
        @if ($daftarWaliKelas->isEmpty())
            <p class="mt-1 text-xs text-brand-muted">
                Belum ada akun dengan role Wali Kelas.
                <a href="{{ route($panelPrefix . '.users.create') }}" class="text-brand-accent-text hover:underline">Buat akunnya dulu</a>.
            </p>
        @endif
    </div>
</div>
