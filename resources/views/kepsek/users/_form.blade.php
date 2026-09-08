{{-- Dipakai bersama oleh create.blade.php & edit.blade.php --}}
<div class="max-w-lg space-y-4">
    <div>
        <label for="name" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="identification" class="h-3.5 w-3.5" />
            Nama Lengkap
        </label>
        <input id="name" name="name" type="text" value="{{ old('name', $akun->name ?? '') }}" required
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="email" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="mail" class="h-3.5 w-3.5" />
            Email
        </label>
        <input id="email" name="email" type="email" value="{{ old('email', $akun->email ?? '') }}" required
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="role" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="shield-check" class="h-3.5 w-3.5" />
            Role
        </label>
        <div class="relative">
            <select id="role" name="role" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                @foreach ($roleOptions as $opsi)
                    <option value="{{ $opsi->value }}" @selected(old('role', $akun->role->value ?? '') === $opsi->value)>
                        {{ $opsi->label() }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
    </div>

    <div>
        <label for="password" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="lock" class="h-3.5 w-3.5" />
            Kata Sandi
            @isset($akun)
                <span class="font-normal normal-case text-brand-muted">(kosongkan jika tidak ingin mengganti)</span>
            @endisset
        </label>
        <input id="password" name="password" type="password" autocomplete="new-password"
            {{ isset($akun) ? '' : 'required' }}
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        <p class="mt-1 text-xs text-brand-muted">Minimal 8 karakter.</p>
    </div>
</div>
