{{--
    View komponen App\Livewire\Kepsek\PantauanPegawai — gaya TailAdmin.

    PANTANGAN: jangan menulis teks berbentuk tag HTML di dalam blok <script>
    pada view Livewire (termasuk di komentar JavaScript) — parser HTML akan
    keluar dari <script> dan Livewire melempar
    MultipleRootElementsDetectedException.

    Halaman ini HANYA BACA: tidak boleh ada tombol simpan, edit, atau hapus.
--}}
<div class="space-y-6">

    {{-- ============ KARTU FILTER ============ --}}
    <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-4 px-6 py-5">

            <div class="min-w-[190px] flex-1">
                <label for="pp-tanggal" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Tanggal</label>

                {{-- Memakai input tanggal BAWAAN BROWSER, bukan Flatpickr.
                     Alasannya: Flatpickr harus diambil dari CDN, sehingga
                     tanggal tidak bisa dipilih sama sekali kalau internet
                     sekolah mati — padahal absensi justru paling sering
                     diperiksa saat itu. Input bawaan tidak butuh apa pun,
                     dan di HP memunculkan pemilih tanggal asli yang lebih
                     enak dipakai. Halaman Pengaturan Sistem juga sudah
                     memakai input waktu bawaan, jadi ini konsisten. --}}
                <input id="pp-tanggal" type="date" wire:model.live="tanggal"
                    max="{{ today()->toDateString() }}"
                    class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
            </div>

            <div class="min-w-[190px] flex-1">
                <label for="pp-status" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">Status</label>
                <select id="pp-status" wire:model.live="status"
                    class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                    <option value="semua">Semua</option>
                    <option value="hadir">Hadir</option>
                    <option value="belum">Belum Hadir</option>
                    <option value="terlambat">Terlambat</option>
                </select>
            </div>

            <div class="flex-1 pb-1 text-xs leading-relaxed text-brand-muted dark:text-brand-faint">
                Batas terlambat pegawai: <span class="font-semibold">{{ $this->batasTerlambat }}</span>.
                Diatur di Pengaturan Sistem oleh Super Admin.
            </div>
        </div>
    </div>

    {{-- ============ RINGKASAN ============ --}}
    @php $r = $this->ringkasan; @endphp

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ([
            ['label' => 'Tepat Waktu', 'nilai' => $r['hadir'], 'ikon' => 'check-circle', 'w' => 'text-success-500', 'bg' => 'bg-success-500/10'],
            ['label' => 'Terlambat', 'nilai' => $r['terlambat'], 'ikon' => 'clock', 'w' => 'text-warning-500', 'bg' => 'bg-warning-500/10'],
            ['label' => 'Belum Hadir', 'nilai' => $r['belum'], 'ikon' => 'x-circle', 'w' => 'text-brand-muted', 'bg' => 'bg-gray-200'],
            ['label' => 'Sedang Mengajar', 'nilai' => $r['mengajar'], 'ikon' => 'qr-code', 'w' => 'text-brand-500', 'bg' => 'bg-brand-500/10'],
        ] as $k)
            <div class="rounded-sm border border-gray-200 bg-brand-surface px-5 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="flex h-10 w-10 items-center justify-center rounded-full {{ $k['bg'] }} {{ $k['w'] }}">
                    <x-icon name="{{ $k['ikon'] }}" class="h-5 w-5" />
                </span>
                <p class="mt-3 text-2xl font-bold text-brand-ink dark:text-white">{{ $k['nilai'] }}</p>
                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $k['label'] }}</p>
            </div>
        @endforeach
    </div>

    @if ($r['berhalangan'] > 0)
        <p class="text-xs text-brand-muted dark:text-brand-faint">
            Catatan: {{ $r['berhalangan'] }} pegawai tercatat izin/sakit — mereka masuk
            hitungan "Belum Hadir" pada kartu di atas, tapi statusnya tetap ditampilkan
            apa adanya di tabel.
        </p>
    @endif

    {{-- ============ TABEL ============ --}}
    <div class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
            <div>
                <h3 class="font-semibold text-brand-ink dark:text-white">
                    Kehadiran Guru &amp; Staff
                    <span class="ml-1 text-sm font-normal text-brand-muted dark:text-brand-faint">
                        ({{ $this->barisTersaring->count() }} dari {{ $r['total'] }} orang)
                    </span>
                </h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    {{ $this->tanggalDipakai->translatedFormat('l, d F Y') }}
                </p>
            </div>

            <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 px-3 py-1 text-xs font-medium text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                <x-icon name="eye" class="h-3.5 w-3.5" />
                Hanya baca
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[820px] table-auto" wire:loading.class="opacity-50" wire:target="tanggal,status">
                <thead>
                    <tr class="bg-gray-50 text-left dark:bg-gray-800">
                        <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Nama &amp; Jabatan</th>
                        <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Waktu Hadir (Radius)</th>
                        <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Status Kehadiran</th>
                        <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Status Mengajar Saat Ini</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->barisTersaring as $b)
                        <tr wire:key="pegawai-{{ $b['pegawai']->id }}">
                            <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                <p class="font-medium text-brand-ink dark:text-white">{{ $b['pegawai']->nama }}</p>
                                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $b['pegawai']->jabatan }}</p>
                            </td>

                            <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                @if ($b['jam'])
                                    <span class="font-mono text-sm text-brand-ink dark:text-white">{{ $b['jam'] }}</span>
                                    @if ($b['absen']?->keterangan)
                                        <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $b['absen']->keterangan }}</p>
                                    @endif
                                @else
                                    <span class="text-sm text-brand-muted dark:text-brand-faint">&mdash;</span>
                                @endif
                            </td>

                            <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                @php
                                    $badge = match ($b['kategori']) {
                                        'hadir' => ['kelas' => 'bg-success-500/10 text-success-500', 'teks' => 'Tepat Waktu'],
                                        'terlambat' => ['kelas' => 'bg-warning-500/20 text-[#9D5425]', 'teks' => 'Terlambat'],
                                        'berhalangan' => ['kelas' => 'bg-brand-500/10 text-brand-500', 'teks' => $b['statusAbsen']?->shortLabel() ?? 'Berhalangan'],
                                        default => ['kelas' => 'bg-gray-200 text-brand-muted dark:bg-gray-800 dark:text-brand-faint', 'teks' => 'Belum Hadir'],
                                    };
                                @endphp

                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-medium {{ $badge['kelas'] }}">
                                    {{ $badge['teks'] }}
                                </span>
                            </td>

                            <td class="border-b border-gray-200 px-4 py-4 dark:border-gray-800">
                                @if (! $this->melihatHariIni)
                                    {{-- "Saat ini" tidak punya arti untuk tanggal lampau.
                                         Lebih jujur mengosongkannya daripada menampilkan
                                         scan kemarin seolah sedang berlangsung. --}}
                                    <span class="text-sm text-brand-muted dark:text-brand-faint">&mdash;</span>
                                @elseif ($b['mengajar'])
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-500/10 px-3 py-1 text-xs font-medium text-brand-500">
                                        <x-icon name="qr-code" class="h-3.5 w-3.5" />
                                        Sedang Mengajar di {{ $b['mengajar']->kode_kelas }}
                                    </span>
                                    <p class="mt-1 text-xs text-brand-muted dark:text-brand-faint">
                                        scan {{ $b['mengajar']->waktu_mulai->format('H:i') }}
                                    </p>
                                @else
                                    <span class="text-sm text-brand-muted dark:text-brand-faint">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center">
                                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-gray-50 text-brand-muted dark:bg-gray-800 dark:text-brand-faint">
                                    <x-icon name="inbox" class="h-7 w-7" />
                                </span>
                                <p class="mt-3 text-sm font-semibold text-brand-ink dark:text-white">
                                    Tidak ada pegawai dengan status ini
                                </p>
                                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                                    Coba ganti penyaring Status atau pilih tanggal lain.
                                </p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
