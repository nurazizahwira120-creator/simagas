{{--
    View komponen App\Livewire\Guru\KelasPengganti — gaya TailAdmin.

    Dua keadaan:
      1. Daftar kelas hari ini yang gurunya berhalangan (kartu per jam).
      2. Satu kelas sedang dibuka -> daftar hadir siswa (tabel bersama
         dengan halaman Jurnal & Absen Kelas, lihat partial di bawah).
--}}
<div class="mx-auto w-full max-w-5xl">

    @if ($notif)
        @php
            $gaya = match ($notif['tipe']) {
                'ok' => ['garis' => 'border-success-500 bg-success-500/10', 'ikon' => 'check-circle', 'warnaIkon' => 'text-success-500'],
                'warn' => ['garis' => 'border-warning-500 bg-warning-500/10', 'ikon' => 'clock', 'warnaIkon' => 'text-warning-500'],
                default => ['garis' => 'border-error-500 bg-error-500/10', 'ikon' => 'x-circle', 'warnaIkon' => 'text-error-500'],
            };
        @endphp

        <div wire:key="notif-pengganti-{{ md5($notif['pesan']) }}-{{ now()->format('Hisu') }}"
            class="mb-6 flex w-full border-l-4 px-5 py-4 shadow-md {{ $gaya['garis'] }}" role="status">
            <x-icon name="{{ $gaya['ikon'] }}" class="mr-4 mt-0.5 h-5 w-5 shrink-0 {{ $gaya['warnaIkon'] }}" />
            <div class="min-w-0">
                <h5 class="mb-1 font-semibold text-brand-ink dark:text-white">{{ $notif['judul'] }}</h5>
                <p class="text-sm leading-relaxed text-brand-muted dark:text-brand-faint">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    @if ($this->dipilih)

        {{-- ============ FORM DAFTAR HADIR (PENGGANTI) ============ --}}
        @php
            $item = $this->dipilih;
            $jadwal = $item['jadwal'];
        @endphp

        <div class="mb-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center gap-5 px-6 py-5">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-warning-500/10 text-warning-500">
                    <x-icon name="swap" class="h-6 w-6" />
                </span>

                <div class="min-w-0 flex-1">
                    <p class="text-lg font-bold text-brand-ink dark:text-white">{{ $jadwal->mata_pelajaran }}</p>
                    <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                        Kelas <span class="font-semibold">{{ $jadwal->kelas?->nama_kelas ?? '—' }}</span>
                        &middot; <span class="font-mono">{{ $jadwal->rentangJam() }}</span>
                        &middot; guru: {{ $jadwal->guru?->nama ?? '—' }}
                        <span class="font-semibold text-error-600">({{ $item['alasan']['label'] }})</span>
                    </p>
                    @if ($item['alasan']['rinci'])
                        <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">{{ $item['alasan']['rinci'] }}</p>
                    @endif
                </div>

                <button type="button" wire:click="tutup"
                    class="shrink-0 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                    Kembali ke daftar
                </button>
            </div>

            <p class="border-t border-gray-200 px-6 py-3 text-xs text-brand-muted dark:border-gray-800 dark:text-brand-faint">
                Anda mengisi sebagai <strong>pengganti</strong>. Nama akun Anda tercatat sebagai pengisi absensi
                jam ini. Aturannya sama dengan jurnal guru: siswa yang ditandai Alpa padahal tadi pagi masuk
                gerbang otomatis dicatat Bolos, dan wali muridnya menerima peringatan WhatsApp.
            </p>
        </div>

        <form wire:submit="simpan"
            class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">
                    Daftar Hadir Siswa
                    <span class="ml-1 text-sm font-normal text-brand-muted dark:text-brand-faint">
                        ({{ $this->daftarSiswa->count() }} siswa)
                    </span>
                </h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    Semua siswa sudah terisi <span class="font-semibold">Hadir</span>. Ubah hanya yang tidak masuk.
                </p>
            </div>

            @include('livewire.guru.partials.tabel-hadir-siswa')

            @if ($this->daftarSiswa->isNotEmpty())
                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 px-6 py-4 dark:border-gray-800">
                    <button type="submit" wire:loading.attr="disabled"
                        class="inline-flex items-center gap-2 rounded-md bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-500/90 disabled:opacity-60">
                        <x-icon name="check-circle" class="h-5 w-5" />
                        <span wire:loading.remove wire:target="simpan">Simpan Absensi KBM</span>
                        <span wire:loading wire:target="simpan">Menyimpan…</span>
                    </button>
                </div>
            @endif
        </form>

    @else

        {{-- ============ DAFTAR KELAS YANG GURUNYA BERHALANGAN ============ --}}
        @if ($this->libur)
            <div class="rounded-2xl border border-warning-200 bg-warning-500/10 p-5 text-sm text-warning-700 dark:border-warning-500/30 dark:text-warning-400" role="status">
                Hari ini bukan hari KBM ({{ $this->libur }}), jadi tidak ada kelas yang perlu digantikan.
            </div>
        @elseif ($this->daftarKelas->isEmpty())
            <div class="rounded-sm border border-gray-200 bg-brand-surface px-6 py-12 text-center shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                    <x-icon name="check-circle" class="h-6 w-6" />
                </span>
                <p class="mt-4 font-semibold text-brand-ink dark:text-white">Tidak ada kelas yang perlu digantikan hari ini</p>
                <p class="mx-auto mt-1 max-w-md text-sm text-brand-muted dark:text-brand-faint">
                    Kelas muncul di sini setelah izin gurunya disetujui, atau absensi harian gurunya
                    tercatat izin, sakit, atau alpa.
                </p>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($this->daftarKelas as $item)
                    @php $j = $item['jadwal']; @endphp

                    <div wire:key="kelas-{{ $j->id }}"
                        class="flex flex-col rounded-sm border bg-brand-surface p-5 shadow-theme-sm dark:bg-gray-900 {{ $item['terisi'] ? 'border-success-500/30' : 'border-warning-500' }}">

                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-semibold text-brand-ink dark:text-white">{{ $j->rentangJam() }}</p>
                                <p class="mt-1 text-lg font-bold text-brand-ink dark:text-white">{{ $j->kelas?->nama_kelas ?? '—' }}</p>
                                <p class="text-sm text-brand-muted dark:text-brand-faint">{{ $j->mata_pelajaran }}</p>
                            </div>
                            <span class="shrink-0 rounded-full bg-error-500/10 px-2.5 py-1 text-xs font-semibold text-error-600">
                                {{ $item['alasan']['label'] }}
                            </span>
                        </div>

                        <p class="mt-3 text-xs text-brand-muted dark:text-brand-faint">
                            Guru: <span class="font-semibold">{{ $j->guru?->nama ?? '—' }}</span>
                            @if ($item['alasan']['rinci'])
                                &middot; {{ $item['alasan']['rinci'] }}
                            @endif
                        </p>

                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                            @if ($item['terisi'])
                                <p class="text-xs font-medium text-success-500">
                                    Sudah diisi {{ $item['jumlah'] }} siswa
                                    @if ($item['pengisi']) oleh {{ $item['pengisi'] }} @endif
                                    @if ($item['diisi_pada']) pukul {{ $item['diisi_pada'] }} @endif
                                </p>
                            @elseif ($item['terbuka'])
                                <p class="text-xs font-medium text-warning-500">Belum ada yang mengisi</p>
                            @else
                                <p class="text-xs text-brand-muted dark:text-brand-faint">Bisa diisi mulai {{ $item['buka_pukul'] }}</p>
                            @endif

                            <button type="button" wire:click="pilih({{ $j->id }})"
                                @disabled(! $item['terbuka'])
                                class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600 disabled:cursor-not-allowed disabled:opacity-50">
                                <x-icon name="clipboard-check" class="h-4 w-4" />
                                {{ $item['terisi'] ? 'Koreksi Absensi' : 'Isi Absensi' }}
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    @endif
</div>
