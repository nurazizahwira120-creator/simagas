{{-- Dipakai bersama oleh create.blade.php & edit.blade.php --}}
<div class="max-w-lg space-y-4">
    <div>
        <label for="nip" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="hashtag" class="h-3.5 w-3.5" />
            NIP
            <span class="font-normal text-brand-faint">(Opsional)</span>
        </label>
        {{-- `required` DILEPAS, bukan cuma labelnya yang diubah. Kalau atribut
             itu tertinggal, browser menolak submit sebelum permintaan sampai
             ke server, dan validasi 'nullable' di controller tidak pernah
             kebagian bicara — pengguna hanya melihat form yang tidak mau
             dikirim tanpa penjelasan. --}}
        <input id="nip" name="nip" type="text" value="{{ old('nip', $pegawai->nip ?? '') }}"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm font-mono focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="nama" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="identification" class="h-3.5 w-3.5" />
            Nama Lengkap
        </label>
        <input id="nama" name="nama" type="text" value="{{ old('nama', $pegawai->nama ?? '') }}" required
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="jabatan" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="briefcase" class="h-3.5 w-3.5" />
            Jabatan
        </label>
        <input id="jabatan" name="jabatan" type="text" value="{{ old('jabatan', $pegawai->jabatan ?? '') }}" required
            placeholder="mis. Guru Matematika, Staf Tata Usaha"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="no_hp" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="phone" class="h-3.5 w-3.5" />
            No. HP
        </label>
        <input id="no_hp" name="no_hp" type="text" value="{{ old('no_hp', $pegawai->no_hp ?? '') }}"
            placeholder="mis. 6281234567890"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="user_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="users" class="h-3.5 w-3.5" />
            Akun Login
        </label>
        <div class="relative">
            <select id="user_id" name="user_id"
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Belum ditautkan —</option>
                @foreach ($daftarUser as $akun)
                    <option value="{{ $akun->id }}" @selected((string) old('user_id', $pegawai->user_id ?? '') === (string) $akun->id)>
                        {{ $akun->name }} ({{ $akun->email }})
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
        @if ($daftarUser->isEmpty())
            <p class="mt-1 text-xs text-brand-muted">
                Tidak ada akun yang belum ditautkan.
                <a href="{{ route($panelPrefix . '.users.create') }}" class="text-brand-accent-text hover:underline">Buat akunnya dulu</a>.
            </p>
        @endif
    </div>
</div>
