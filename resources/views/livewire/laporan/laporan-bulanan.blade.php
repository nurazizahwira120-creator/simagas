{{-- View komponen App\Livewire\Laporan\LaporanBulanan — gaya TailAdmin. --}}
<div>

    {{-- ============ KARTU RINGKASAN ============ --}}
    <div class="mb-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">

        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
                <x-icon name="document-report" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">{{ $this->ringkas['total'] }}</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Laporan tersimpan</p>
        </div>

        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                <x-icon name="calendar" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-2xl font-bold text-brand-ink dark:text-white">
                {{ $this->ringkas['terbaru']?->labelPeriode() ?? '—' }}
            </p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">Periode terbaru</p>
        </div>

        <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-5 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900 sm:col-span-2 xl:col-span-1">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-warning-500/10 text-warning-500">
                <x-icon name="clock" class="h-5 w-5" />
            </span>
            <p class="mt-4 text-sm font-semibold text-brand-ink dark:text-white">Dibuat otomatis</p>
            <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                Setiap tanggal 1 pukul 01.00 WIB, merekap bulan sebelumnya.
            </p>
        </div>
    </div>

    {{-- ============ TABEL ============ --}}
    <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div>
                <h3 class="font-semibold text-brand-ink dark:text-white">Arsip Laporan Bulanan</h3>
                <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                    Rekap kehadiran pegawai &amp; siswa dalam bentuk PDF siap cetak.
                </p>
            </div>

            @if ($this->daftarTahun)
                <div class="flex items-center gap-2">
                    <label for="filter-tahun" class="text-xs font-medium text-brand-muted dark:text-brand-faint">Tahun</label>
                    <select id="filter-tahun" wire:model.live="tahun"
                        class="rounded-md border border-gray-300 bg-transparent px-3 py-2 text-sm text-brand-ink outline-none transition focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
                        <option value="">Semua tahun</option>
                        @foreach ($this->daftarTahun as $t)
                            <option value="{{ $t }}">{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="w-full table-auto">
                <thead>
                    <tr class="bg-gray-100 text-left dark:bg-gray-800">
                        <th class="px-6 py-4 text-sm font-semibold text-brand-ink dark:text-white">Periode</th>
                        <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Pegawai</th>
                        <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Siswa</th>
                        <th class="px-4 py-4 text-sm font-semibold text-brand-ink dark:text-white">Dibuat</th>
                        <th class="px-6 py-4 text-right text-sm font-semibold text-brand-ink dark:text-white">Aksi</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($this->daftar as $laporan)
                        <tr wire:key="laporan-{{ $laporan->id }}"
                            class="border-b border-gray-200 transition-colors last:border-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-white/[0.03]">

                            <td class="px-6 py-4">
                                <p class="font-semibold text-brand-ink dark:text-white">{{ $laporan->labelPeriode() }}</p>
                                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                                    {{ $laporan->jumlah_hari_kerja }} hari kerja
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm text-brand-ink dark:text-white">
                                    {{ $laporan->jumlah_pegawai }} orang
                                </p>
                                <p class="mt-0.5 text-xs {{ $laporan->persenPegawai() === null ? 'text-brand-muted dark:text-brand-faint' : ($laporan->persenPegawai() >= 85 ? 'text-success-500' : 'text-warning-500') }}">
                                    {{ $laporan->persenPegawai() === null ? 'belum terhitung' : $laporan->persenPegawai() . '% kehadiran' }}
                                </p>
                            </td>

                            <td class="px-4 py-4">
                                <p class="text-sm text-brand-ink dark:text-white">
                                    {{ $laporan->jumlah_siswa }} orang
                                </p>
                                <p class="mt-0.5 text-xs {{ $laporan->persenSiswa() === null ? 'text-brand-muted dark:text-brand-faint' : ($laporan->persenSiswa() >= 85 ? 'text-success-500' : 'text-warning-500') }}">
                                    {{ $laporan->persenSiswa() === null ? 'belum terhitung' : $laporan->persenSiswa() . '% kehadiran' }}
                                </p>
                            </td>

                            <td class="px-4 py-4 text-sm text-brand-muted dark:text-brand-faint">
                                {{ $laporan->dibuat_pada?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex items-center justify-end gap-2">
                                    @if ($laporan->berkasAda())
                                        {{-- target="_blank" pada tombol Lihat, bukan pada Download:
                                             unduhan tidak berpindah halaman, jadi tab baru untuknya
                                             hanya menghasilkan tab kosong yang menutup sendiri. --}}
                                        <a href="{{ route($panelPrefix . '.laporan-bulanan.unduh', ['laporan' => $laporan->id, 'lihat' => 1]) }}"
                                            target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 px-3 py-2 text-xs font-medium text-brand-ink transition hover:bg-gray-100 dark:border-gray-700 dark:text-white dark:hover:bg-gray-800">
                                            <x-icon name="eye" class="h-4 w-4" />
                                            Lihat
                                        </a>

                                        <a href="{{ route($panelPrefix . '.laporan-bulanan.unduh', $laporan) }}"
                                            class="inline-flex items-center gap-1.5 rounded-md bg-brand-500 px-3 py-2 text-xs font-medium text-white transition hover:bg-brand-500/90">
                                            <x-icon name="download" class="h-4 w-4" />
                                            Download PDF
                                        </a>
                                    @else
                                        {{-- Baris ada tapi berkasnya hilang dari disk. Ditampilkan
                                             apa adanya, bukan disembunyikan: menyembunyikannya
                                             membuat periode itu seolah tidak pernah dilaporkan. --}}
                                        <span class="inline-flex items-center gap-1.5 rounded-md bg-error-500/10 px-3 py-2 text-xs font-medium text-error-500">
                                            <x-icon name="exclamation-triangle" class="h-4 w-4" />
                                            Berkas hilang
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-14 text-center">
                                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 text-gray-400 dark:bg-gray-800">
                                    <x-icon name="inbox" class="h-6 w-6" />
                                </span>
                                <p class="mt-4 font-medium text-brand-ink dark:text-white">Belum ada laporan bulanan</p>
                                <p class="mx-auto mt-1 max-w-md text-sm text-brand-muted dark:text-brand-faint">
                                    Laporan pertama akan muncul otomatis pada tanggal 1 bulan depan pukul 01.00 WIB,
                                    berisi rekap bulan ini.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->daftar->hasPages())
            <div class="border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                {{ $this->daftar->links() }}
            </div>
        @endif
    </div>
</div>
