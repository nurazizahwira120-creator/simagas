{{--
    Daftar Izin Pegawai (Kepala Sekolah) — data table TailAdmin, HANYA BACA.

    Soal nama class: brief menyebut `bg-gray-2 dark:bg-meta-4` untuk header
    tabel dan `border-stroke dark:bg-boxdark` untuk kartunya. Nama-nama itu
    milik TailAdmin v1 dan tidak ada di TailAdmin Pro 2.2.0 (Tailwind v4)
    yang dipakai project ini. Padanan yang dipakai di bawah:
        bg-gray-2 dark:bg-meta-4 -> bg-gray-50 dark:bg-gray-800
        border-stroke            -> border-gray-200 dark:border-gray-800
        bg-boxdark               -> dark:bg-white/[0.03]
--}}
@php
    $kelasInput = 'w-full rounded-lg border-[1.5px] border-gray-300 bg-transparent px-4 py-2.5 text-sm font-medium text-gray-800 outline-none transition focus:border-brand-500 active:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:focus:border-brand-500';
@endphp

<div>
    {{-- ================= FILTER ================= --}}
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

            <div>
                <label for="f-bulan" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Bulan</label>
                <div class="relative">
                    <select id="f-bulan" wire:model.live="bulan" class="{{ $kelasInput }} appearance-none pr-10">
                        @foreach ($daftarBulan as $nomor => $nama)
                            <option value="{{ $nomor }}">{{ $nama }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                        <x-icon name="chevron-down" class="h-4 w-4" />
                    </span>
                </div>
            </div>

            <div>
                <label for="f-tahun" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Tahun</label>
                <div class="relative">
                    <select id="f-tahun" wire:model.live="tahun" class="{{ $kelasInput }} appearance-none pr-10">
                        @foreach ($this->pilihanTahun as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-400">
                        <x-icon name="chevron-down" class="h-4 w-4" />
                    </span>
                </div>
            </div>

            <div class="lg:col-span-2">
                <label for="f-cari" class="mb-1.5 block text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Cari Nama Pegawai</label>
                {{-- .live.debounce, bukan .live polos: mengetik 12 huruf akan
                     mengirim 12 request ke server dan tabelnya berkedip di
                     setiap ketukan. --}}
                <input id="f-cari" type="search" wire:model.live.debounce.400ms="cari"
                    placeholder="Ketik sebagian nama…" class="{{ $kelasInput }}">
            </div>
        </div>

        {{-- Ringkasan bulan terpilih. --}}
        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $this->namaBulan }} —</span>
            <x-badge warna="warning" ikon="clipboard-check">{{ $this->ringkasan['izin'] }} Izin</x-badge>
            <x-badge warna="orange" ikon="exclamation-triangle">{{ $this->ringkasan['sakit'] }} Sakit</x-badge>

            <span wire:loading class="text-sm text-brand-600 dark:text-brand-400">Memuat…</span>
        </div>
    </div>

    {{-- ================= TABEL ================= --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">

        @if ($this->daftar->isEmpty())
            <div class="flex flex-col items-center gap-3 p-10 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="font-medium text-gray-700 dark:text-gray-300">
                    Tidak ada pegawai yang izin atau sakit di {{ $this->namaBulan }}.
                </p>
                <p class="max-w-sm text-sm text-gray-500 dark:text-gray-400">
                    Kalau Anda menduga seharusnya ada, periksa filter bulan &amp; tahun di atas —
                    atau kolom pencarian nama yang mungkin masih terisi.
                </p>
            </div>
        @else
            {{-- max-w-full overflow-x-auto: di HP tabel empat kolom ini lebih
                 lebar dari layar, dan tanpa pembungkus ini seluruh HALAMAN
                 ikut bisa digeser ke samping — bukan cuma tabelnya. --}}
            <div class="max-w-full overflow-x-auto">
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-400">
                            <th class="px-5 py-4 font-medium">Tanggal</th>
                            <th class="px-5 py-4 font-medium">Nama Pegawai</th>
                            <th class="px-5 py-4 font-medium">Status</th>
                            <th class="w-2/5 px-5 py-4 font-medium">Alasan</th>
                            <th class="px-5 py-4 font-medium">Bukti</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @foreach ($this->daftar as $baris)
                            <tr class="transition-colors hover:bg-gray-50 dark:hover:bg-white/[0.03]">

                                <td class="px-5 py-4 align-top whitespace-nowrap">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">
                                        {{ $baris->tanggal->translatedFormat('d M Y') }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $baris->tanggal->translatedFormat('l') }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    <p class="font-semibold text-gray-800 dark:text-gray-100">
                                        {{ $baris->pegawai?->nama ?? 'Pegawai terhapus' }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $baris->pegawai?->jabatan ?: '—' }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    {{-- Sakit dibuat ORANYE, bukan kuning seperti Izin.
                                         Di tabel yang isinya hanya dua status itu, dua
                                         nuansa kuning tidak bisa dibedakan sekilas. --}}
                                    <x-badge :warna="$baris->status === \App\Enums\AbsensiStatus::Sakit ? 'orange' : 'warning'">
                                        {{ $baris->status->shortLabel() }}
                                    </x-badge>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    {{-- Alasannya ditampilkan UTUH, bukan dipotong.
                                         Kalimat yang terpotong jadi "Mengantar orang
                                         tua kontrol ke…" memaksa Kepala Sekolah
                                         mengklik untuk tahu isinya — padahal itulah
                                         satu-satunya informasi yang ia cari di
                                         halaman ini. --}}
                                    <p class="text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                                        {{ $baris->alasan ?: '—' }}
                                    </p>
                                </td>

                                <td class="px-5 py-4 align-top">
                                    @if ($baris->bukti_url)
                                        <a href="{{ $baris->bukti_url }}" target="_blank" rel="noopener"
                                            title="Lihat lampiran"
                                            class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-100 hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-800">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            <span class="sr-only">Lihat lampiran {{ $baris->pegawai?->nama }}</span>
                                        </a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($this->daftar->hasPages())
                <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-800">
                    {{ $this->daftar->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
