@php
    $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-5 py-3 font-medium text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 active:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:border-brand-500';
    $sedangUbah = $ekskulId !== null;
@endphp

<div class="space-y-6">

    {{-- ================= NOTIFIKASI ================= --}}
    @if ($notif)
        @php
            $gaya = match ($notif['tipe']) {
                'ok' => ['kotak' => 'border-success-200 bg-success-500/10 dark:border-success-500/30', 'teks' => 'text-success-700 dark:text-success-400', 'ikon' => 'check-circle'],
                'warn' => ['kotak' => 'border-warning-200 bg-warning-500/10 dark:border-warning-500/30', 'teks' => 'text-warning-700 dark:text-warning-400', 'ikon' => 'exclamation-triangle'],
                default => ['kotak' => 'border-error-200 bg-error-500/10 dark:border-error-500/30', 'teks' => 'text-error-700 dark:text-error-400', 'ikon' => 'x-circle'],
            };
        @endphp

        <div id="ek-notif" class="flex items-start gap-3 rounded-2xl border p-4 {{ $gaya['kotak'] }}" role="status">
            <x-icon name="{{ $gaya['ikon'] }}" class="mt-0.5 h-5 w-5 shrink-0 {{ $gaya['teks'] }}" />
            <div class="min-w-0">
                <p class="text-sm font-bold {{ $gaya['teks'] }}">{{ $notif['judul'] }}</p>
                <p class="mt-0.5 text-sm {{ $gaya['teks'] }}">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    {{-- ================= FORM (tambah / ubah) =================
         Hanya Super Admin & Kepala Sekolah. Peran lain tetap boleh MEMBUKA
         halaman ini — brief memang meminta menunya terbuka untuk semua — tapi
         yang mereka lihat cuma tabelnya. Menyembunyikan form saja tidak cukup
         sebagai pengaman; setiap method di komponennya memeriksa ulang. --}}
    @if ($this->bisaKelola)
    <div id="ek-form" class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">
                    {{ $sedangUbah ? 'Ubah Jadwal Ekskul' : 'Tambah Jadwal Ekskul' }}
                </h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    Nama kegiatan, hari, jam, dan pembinanya. Keterangan boleh dikosongkan.
                </p>
            </div>

            @if ($sedangUbah)
                <button type="button" wire:click="batal"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                    <x-icon name="x-mark" class="h-4 w-4" />
                    Batal Ubah
                </button>
            @endif
        </div>

        <form wire:submit="simpan" class="space-y-5 p-6">

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="ek-nama" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Nama Ekskul <span class="text-error-500">*</span>
                    </label>
                    <input id="ek-nama" type="text" wire:model="nama_ekskul" maxlength="100"
                        placeholder="Contoh: Pramuka" class="{{ $kelasInput }}">
                    @error('nama_ekskul')
                        <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ek-pembina" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Pembina <span class="text-error-500">*</span>
                    </label>
                    <div class="relative">
                        <select id="ek-pembina" wire:model="pembina_id" class="{{ $kelasInput }} appearance-none pr-12">
                            <option value="">— Pilih pembina —</option>
                            @foreach ($this->daftarPembina as $guru)
                                <option value="{{ $guru->id }}">
                                    {{ $guru->nama }}@if ($guru->jabatan) &middot; {{ $guru->jabatan }} @endif
                                </option>
                            @endforeach
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-400">
                            <x-icon name="chevron-down" class="h-5 w-5" />
                        </span>
                    </div>
                    @error('pembina_id')
                        <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Diambil dari Data Pegawai yang akunnya berperan Guru atau Wali Kelas.
                        Pembina yang dipilih di sini yang nanti bisa mengisi absensi ekskulnya.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-3">
                <div>
                    <label for="ek-hari" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Hari <span class="text-error-500">*</span>
                    </label>
                    <div class="relative">
                        {{-- list="ek-daftar-hari" membuat kotak ini bisa DIPILIH dari
                             daftar sekaligus DIKETIK bebas. Dropdown murni akan
                             menolak "Jumat & Sabtu" atau "Menyesuaikan", padahal
                             kolomnya string justru supaya itu boleh. --}}
                        <input id="ek-hari" type="text" wire:model="hari" list="ek-daftar-hari"
                            maxlength="40" placeholder="Senin" class="{{ $kelasInput }} pr-12">
                        <datalist id="ek-daftar-hari">
                            @foreach ($pilihanHari as $h)
                                <option value="{{ $h }}"></option>
                            @endforeach
                        </datalist>
                        <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-400">
                            <x-icon name="chevron-down" class="h-5 w-5" />
                        </span>
                    </div>
                    @error('hari')
                        <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ek-mulai" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Jam Mulai <span class="text-error-500">*</span>
                    </label>
                    <input id="ek-mulai" type="time" wire:model="jam_mulai" class="{{ $kelasInput }}">
                    @error('jam_mulai')
                        <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ek-selesai" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                        Jam Selesai <span class="text-error-500">*</span>
                    </label>
                    <input id="ek-selesai" type="time" wire:model="jam_selesai" class="{{ $kelasInput }}">
                    @error('jam_selesai')
                        <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="ek-keterangan" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Keterangan <span class="text-xs font-normal text-gray-400">(opsional)</span>
                </label>
                <textarea id="ek-keterangan" rows="2" wire:model="keterangan" maxlength="255"
                    placeholder="Contoh: Latihan di lapangan belakang, bawa seragam olahraga."
                    class="{{ $kelasInput }} resize-y"></textarea>
                @error('keterangan')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap items-center gap-3 pt-1">
                <button type="submit" wire:loading.attr="disabled" wire:target="simpan"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">

                    <svg wire:loading wire:target="simpan" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>

                    <span wire:loading.remove wire:target="simpan">
                        {{ $sedangUbah ? 'Simpan Perubahan' : 'Tambah Jadwal' }}
                    </span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>
            </div>
        </form>
    </div>
    @else
        <div class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-white/[0.03]">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-brand-accent-text" />
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Jadwal ekstrakurikuler disusun oleh Super Admin atau Kepala Sekolah.
                @if (! empty($this->idEkskulSayaBina))
                    Anda tercatat sebagai pembina — buka tombol <span class="font-semibold">Absensi</span>
                    pada ekskul Anda untuk mengisi kehadiran.
                @endif
            </p>
        </div>
    @endif

    {{-- ================= TABEL ================= --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div>
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">Daftar Jadwal Ekstrakurikuler</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->daftar->count() }} jadwal &middot; {{ $this->totalEkskul }} jenis ekskul
                </p>
            </div>

            <div class="relative w-full sm:w-72">
                <span class="pointer-events-none absolute inset-y-0 left-3.5 flex items-center text-gray-400">
                    <x-icon name="search" class="h-4 w-4" />
                </span>
                <label for="ek-cari" class="sr-only">Cari jadwal ekskul</label>
                <input id="ek-cari" type="search" wire:model.live.debounce.300ms="cari"
                    placeholder="Cari ekskul, pembina, atau hari…"
                    class="w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent py-2.5 pl-10 pr-4 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            </div>
        </div>

        @if ($this->daftar->isEmpty())
            <div class="flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {{ trim($cari) !== ''
                        ? 'Tidak ada jadwal yang cocok dengan pencarian "' . $cari . '".'
                        : 'Belum ada jadwal ekstrakurikuler. Tambahkan lewat form di atas.' }}
                </p>
            </div>
        @else
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                            <th class="px-5 py-4 font-medium">Ekskul</th>
                            <th class="px-5 py-4 font-medium">Hari</th>
                            <th class="px-5 py-4 font-medium">Waktu</th>
                            <th class="px-5 py-4 font-medium">Pembina</th>
                            <th class="px-5 py-4 font-medium">Anggota</th>
                            <th class="w-1/5 px-5 py-4 font-medium">Keterangan</th>
                            <th class="px-5 py-4 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->daftar as $item)
                            <tr wire:key="ekskul-{{ $item->id }}"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03] {{ $ekskulId === $item->id ? 'bg-brand-500/5' : '' }}">

                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $item->nama_ekskul }}</p>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 align-top">
                                    <x-badge warna="info">{{ $item->hari }}</x-badge>
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 align-top">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $item->rentangJam() }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item->durasiMenit() }} menit
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top text-gray-600 dark:text-gray-300">
                                    {{ $item->namaPembina() }}

                                    {{-- Penanda untuk jadwal lama yang namanya tidak
                                         cocok saat migrasi ke pembina_id. Tanpa ini
                                         admin tidak punya cara tahu mana yang perlu
                                         dipilih ulang lewat dropdown. --}}
                                    @if ($item->pembinaBelumTertaut())
                                        <span class="mt-1 block text-[11px] font-semibold text-warning-600 dark:text-warning-400">
                                            belum tertaut ke data pegawai
                                        </span>
                                    @endif
                                </td>

                                <td class="whitespace-nowrap px-5 py-4 align-top">
                                    <span class="font-medium text-gray-800 dark:text-gray-200">{{ $item->anggota_count }}</span>
                                    <span class="text-gray-500 dark:text-gray-400">siswa</span>
                                </td>

                                <td class="px-5 py-4 align-top text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                    {{ $item->keterangan ?: '—' }}
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        @php
                                            $bolehKelolaBaris = $this->bisaKelola || in_array($item->id, $this->idEkskulSayaBina, true);
                                        @endphp

                                        @if ($bolehKelolaBaris)
                                            <a href="{{ route($panelPrefix . '.ekskul.anggota', $item->id) }}" wire:navigate
                                                class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-600 transition hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                                                title="Kelola anggota {{ $item->nama_ekskul }}">
                                                <x-icon name="users" class="h-4 w-4" />
                                                Anggota
                                            </a>
                                        @endif

                                        <a href="{{ route($panelPrefix . '.ekskul.absensi', $item->id) }}" wire:navigate
                                            class="flex h-9 items-center justify-center gap-1.5 rounded-lg border border-brand-500/40 px-3 text-xs font-semibold text-brand-accent-text transition hover:bg-brand-500/10"
                                            title="Absensi {{ $item->nama_ekskul }}">
                                            <x-icon name="clipboard-check" class="h-4 w-4" />
                                            Absensi
                                        </a>

                                        @if ($this->bisaKelola)
                                        <button type="button" wire:click="edit({{ $item->id }})"
                                            wire:loading.attr="disabled" wire:target="edit({{ $item->id }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                                            aria-label="Ubah {{ $item->nama_ekskul }}" title="Ubah">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                        </button>

                                        {{-- Konfirmasi SweetAlert2 lewat atribut data-konfirmasi
                                             (lihat resources/views/partials/sweetalert.blade.php).
                                             Bukan wire:confirm: atribut Livewire itu memanggil
                                             confirm() bawaan browser dan tidak bisa diarahkan
                                             ke SweetAlert2. --}}
                                        <button type="button" wire:click="hapus({{ $item->id }})"
                                            wire:loading.attr="disabled" wire:target="hapus({{ $item->id }})"
                                            data-konfirmasi-judul="Hapus Jadwal Ini?"
                                            data-konfirmasi="Jadwal {{ $item->nama_ekskul }} hari {{ $item->hari }} ({{ $item->rentangJam() }}) akan dihapus permanen."
                                            data-konfirmasi-ikon="warning"
                                            data-konfirmasi-ya="Ya, Hapus!"
                                            data-konfirmasi-batal="Batal"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-error-500/40 text-error-600 transition hover:bg-error-500/10 disabled:opacity-50 dark:text-error-400"
                                            aria-label="Hapus {{ $item->nama_ekskul }}" title="Hapus">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Menggulir ke form saat tombol "Ubah" di baris paling bawah ditekan.
         Tanpa ini form-nya memang terisi, tapi berada di luar layar dan
         terlihat seperti tombol yang tidak melakukan apa-apa.

         Livewire mengirim event browser ke WINDOW, bukan document — jadi
         pendengarnya harus di window. --}}
    <script>
        window.addEventListener('gulir-ke-form-ekskul', function () {
            var f = document.getElementById('ek-form');

            if (f) {
                f.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    </script>
</div>
