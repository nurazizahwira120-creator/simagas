@php
    $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-5 py-3 font-medium text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 active:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:border-brand-500';
    $sedangUbah = $editId !== null;
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

        <div id="pg-notif" class="flex items-start gap-3 rounded-2xl border p-4 {{ $gaya['kotak'] }}" role="status">
            <x-icon name="{{ $gaya['ikon'] }}" class="mt-0.5 h-5 w-5 shrink-0 {{ $gaya['teks'] }}" />
            <div class="min-w-0">
                <p class="text-sm font-bold {{ $gaya['teks'] }}">{{ $notif['judul'] }}</p>
                <p class="mt-0.5 text-sm {{ $gaya['teks'] }}">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    {{-- ================= FORM (buat / ubah) ================= --}}
    <div id="pg-form" class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div class="min-w-0">
                <h2 class="font-semibold text-gray-800 dark:text-gray-100">
                    {{ $sedangUbah ? 'Ubah Pengumuman' : 'Tulis Pengumuman' }}
                </h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $sedangUbah
                        ? 'Perubahan hanya memperbaiki riwayat. Notifikasi yang sudah terkirim tidak ikut berubah.'
                        : 'Pengumuman langsung berbunyi di lonceng penerima. Pengiriman WhatsApp opsional.' }}
                </p>
            </div>

            @if ($sedangUbah)
                <button type="button" wire:click="batalEdit"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                    <x-icon name="x-mark" class="h-4 w-4" />
                    Batal Ubah
                </button>
            @endif
        </div>

        <form wire:submit="simpan" class="space-y-5 p-6">

            <div>
                <label for="pg-judul" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Judul Pengumuman <span class="text-error-500">*</span>
                </label>
                <input id="pg-judul" type="text" wire:model="judul" maxlength="150"
                    placeholder="Contoh: Rapat Dewan Guru Sabtu Pagi"
                    class="{{ $kelasInput }}">
                @error('judul')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="pg-target" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Target Penerima <span class="text-error-500">*</span>
                </label>
                <div class="relative">
                    <select id="pg-target" wire:model.live="target_role" class="{{ $kelasInput }} appearance-none pr-12">
                        <option value="pegawai">Seluruh Guru &amp; Staff</option>
                        <option value="wali_murid">Seluruh Wali Murid</option>
                        <option value="semua">Semua Pengguna</option>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-4 flex items-center text-gray-400">
                        <x-icon name="chevron-down" class="h-5 w-5" />
                    </span>
                </div>
                @error('target_role')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror

                {{-- Jumlah penerima ditampilkan SEBELUM tombol ditekan.
                     "Kirim ke 312 nomor" adalah hal yang perlu dilihat lebih
                     dulu, bukan diketahui setelah pesannya terlanjur terkirim. --}}
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Akan diterima <strong class="text-gray-700 dark:text-gray-200">{{ $this->ringkasanPenerima['total'] }} pengguna aktif</strong>
                    &middot; {{ $this->ringkasanPenerima['ber_nomor'] }} di antaranya punya nomor HP tersimpan.
                </p>
            </div>

            <div>
                <label for="pg-isi" class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                    Isi Pesan Pengumuman <span class="text-error-500">*</span>
                </label>
                <textarea id="pg-isi" rows="5" wire:model="isi_pesan" maxlength="2000"
                    placeholder="Tulis isi pengumuman selengkapnya di sini."
                    class="{{ $kelasInput }} resize-y"></textarea>
                @error('isi_pesan')
                    <p class="mt-1.5 text-sm text-error-600 dark:text-error-400">{{ $message }}</p>
                @enderror
            </div>

            {{-- ---- Opsi WhatsApp (hanya saat membuat baru) --------------- --}}
            @unless ($sedangUbah)
                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-800">
                    @if ($this->gatewayAktif)
                        <label for="pg-wa" class="flex cursor-pointer items-start gap-3">
                            <input id="pg-wa" type="checkbox" wire:model.live="kirim_wa"
                                class="mt-0.5 h-5 w-5 shrink-0 rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-900">
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Kirim juga lewat WhatsApp (Fonnte)
                                </span>
                                <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    Pesan dikirim satu per satu dengan jeda supaya nomor sekolah tidak diblokir.
                                    Butuh <span class="font-mono">php artisan queue:work</span> berjalan.
                                </span>
                            </span>
                        </label>

                        @if ($kirim_wa)
                            <div class="mt-3 flex items-start gap-2.5 rounded-lg bg-warning-500/10 p-3">
                                <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400" />
                                <p class="text-xs text-warning-700 dark:text-warning-400">
                                    <strong>{{ $this->ringkasanPenerima['ber_nomor'] }} pesan WhatsApp</strong> akan dikirim
                                    dan setiap pesan berbiaya di Fonnte. Pengguna tanpa nomor HP tersimpan tetap menerima
                                    pengumuman lewat lonceng notifikasi.
                                </p>
                            </div>
                        @endif
                    @else
                        <div class="flex items-start gap-2.5">
                            <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Gateway WhatsApp sedang <strong>nonaktif</strong>, jadi pilihan kirim WA disembunyikan.
                                Pengumuman tetap masuk ke lonceng notifikasi semua penerima.
                                Nyalakan di <em>Pengaturan Sistem &rsaquo; Identitas &amp; WhatsApp</em> bila perlu.
                            </p>
                        </div>
                    @endif
                </div>
            @endunless

            <div class="flex flex-wrap items-center gap-3 pt-1">
                {{-- Konfirmasi hanya dipasang saat WhatsApp dicentang.
                     Kotak yang muncul setiap kali, termasuk untuk kiriman yang
                     tidak berisiko, cepat berubah jadi kotak yang ditekan-OK
                     tanpa dibaca. --}}
                <button type="submit" wire:loading.attr="disabled" wire:target="simpan"
                    @if (! $sedangUbah && $kirim_wa && $this->gatewayAktif)
                        data-konfirmasi-judul="Kirim Sekaligus ke WhatsApp?"
                        data-konfirmasi="{{ $this->ringkasanPenerima['ber_nomor'] }} pesan WhatsApp akan diantrekan, dan setiap pesan berbiaya. Pesan yang sudah terkirim tidak bisa ditarik kembali."
                        data-konfirmasi-ikon="warning"
                        data-konfirmasi-ya="Ya, Kirim Sekarang!"
                        data-konfirmasi-batal="Batal"
                    @endif
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60">

                    <svg wire:loading wire:target="simpan" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>

                    <span wire:loading.remove wire:target="simpan">
                        {{ $sedangUbah ? 'Simpan Perubahan' : 'Kirim Pengumuman' }}
                    </span>
                    <span wire:loading wire:target="simpan">Menyimpan…</span>
                </button>

                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $sedangUbah
                        ? 'Untuk mengirimkannya lagi ke penerima, pakai tombol "Kirim Ulang" di riwayat.'
                        : 'Pesan WhatsApp tidak bisa ditarik kembali setelah terkirim.' }}
                </p>
            </div>
        </form>
    </div>

    {{-- ================= RIWAYAT ================= --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <h2 class="font-semibold text-gray-800 dark:text-gray-100">Riwayat Pengumuman</h2>
        </div>

        @if ($this->riwayat->isEmpty())
            <div class="flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada pengumuman yang pernah dikirim.</p>
            </div>
        @else
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                            <th class="px-5 py-4 font-medium">Tanggal</th>
                            <th class="w-2/5 px-5 py-4 font-medium">Judul &amp; Isi</th>
                            <th class="px-5 py-4 font-medium">Target</th>
                            <th class="px-5 py-4 font-medium">Pengirim</th>
                            <th class="px-5 py-4 font-medium">WhatsApp</th>
                            <th class="px-5 py-4 text-right font-medium">Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->riwayat as $p)
                            <tr wire:key="pengumuman-{{ $p->id }}"
                                class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03] {{ $editId === $p->id ? 'bg-brand-500/5' : '' }}">
                                <td class="whitespace-nowrap px-5 py-4 align-top">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $p->created_at->translatedFormat('d M Y') }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $p->created_at->format('H:i') }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">{{ $p->judul }}</p>
                                    <p class="mt-0.5 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                                        {{ \Illuminate\Support\Str::limit($p->isi_pesan, 140) }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <x-badge warna="info">{{ $p->labelTarget() }}</x-badge>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $p->jumlah_penerima }} penerima
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top text-gray-600 dark:text-gray-300">
                                    {{ $p->pembuat?->name ?? 'Akun terhapus' }}
                                </td>

                                <td class="px-5 py-4 align-top">
                                    @if ($p->is_sent_wa)
                                        <x-badge warna="success" ikon="phone">{{ $p->jumlah_wa }} pesan</x-badge>
                                    @else
                                        <span class="text-gray-400">&mdash;</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <div class="flex items-center justify-end gap-2">
                                        <button type="button" wire:click="edit({{ $p->id }})"
                                            wire:loading.attr="disabled" wire:target="edit({{ $p->id }})"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                                            aria-label="Ubah {{ $p->judul }}" title="Ubah">
                                            <x-icon name="pencil" class="h-4 w-4" />
                                        </button>

                                        <button type="button" wire:click="kirimUlang({{ $p->id }})"
                                            wire:loading.attr="disabled" wire:target="kirimUlang({{ $p->id }})"
                                            data-konfirmasi-judul="Kirim Ulang Notifikasi?"
                                            data-konfirmasi="Pengumuman ini akan muncul lagi di lonceng {{ $p->labelTarget() }} dan berbunyi di perangkat mereka. WhatsApp TIDAK ikut dikirim ulang."
                                            data-konfirmasi-ikon="question"
                                            data-konfirmasi-ya="Ya, Kirim Ulang!"
                                            data-konfirmasi-batal="Batal"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-brand-500/40 text-brand-accent-text transition hover:bg-brand-500/10 disabled:opacity-50"
                                            aria-label="Kirim ulang {{ $p->judul }}" title="Kirim ulang">
                                            <x-icon name="bell" class="h-4 w-4" />
                                        </button>

                                        <button type="button" wire:click="hapus({{ $p->id }})"
                                            wire:loading.attr="disabled" wire:target="hapus({{ $p->id }})"
                                            data-konfirmasi-judul="Hapus Pengumuman Ini?"
                                            data-konfirmasi="Pengumuman &quot;{{ \Illuminate\Support\Str::limit($p->judul, 60) }}&quot; dihapus dari riwayat dan ditarik dari lonceng semua penerimanya."
                                            data-konfirmasi-ikon="warning"
                                            data-konfirmasi-ya="Ya, Hapus!"
                                            data-konfirmasi-batal="Batal"
                                            class="flex h-9 w-9 items-center justify-center rounded-lg border border-error-500/40 text-error-600 transition hover:bg-error-500/10 disabled:opacity-50 dark:text-error-400"
                                            aria-label="Hapus {{ $p->judul }}" title="Hapus">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->riwayat->hasPages())
                <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    {{ $this->riwayat->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Menggulir ke form saat tombol "Ubah" di baris paling bawah ditekan.
         Tanpa ini form-nya memang terisi, tapi berada di luar layar dan
         terlihat seperti tombol yang tidak melakukan apa-apa.

         Skrip biasa di dalam root komponen, BUKAN direktif @ script Livewire:
         direktif itu pernah menyebabkan error 500 "Attempt to read property
         childNodes on null" di project ini. --}}
    <script>
        window.addEventListener('gulir-ke-form', function () {
            var f = document.getElementById('pg-form');

            if (f) {
                f.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    </script>
</div>
