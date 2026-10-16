@extends('layouts.app')

@section('title', 'Laporan Absensi Harian')

@section('content')
    @php
        $rute = route($panelPrefix . '.laporan-harian');
        $tgl = $tanggal->format('Y-m-d');
        // Tautan yang mempertahankan saringan lain — dipakai chip kelas.
        $tautan = fn (array $ganti) => $rute . '?' . http_build_query(array_filter(
            array_merge(['tanggal' => $tgl, 'kelas' => $kelas?->id, 'status' => $status === 'semua' ? null : $status], $ganti),
            fn ($v) => $v !== null && $v !== ''
        ));
        $kelasInput = 'w-full rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500 dark:bg-gray-900 dark:text-white';
    @endphp

    <div class="mb-5 print:hidden">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Laporan Absensi Harian</h1>
        <p class="mt-1 text-sm text-brand-muted">Kehadiran siswa dan pegawai untuk satu tanggal. Saring per kelas untuk melihat siapa yang belum hadir.</p>
    </div>

    {{-- ============ SARINGAN ============
         Form GET biasa: saringan ikut di URL, jadi bisa dibagikan/di-bookmark
         dan tidak hilang saat halaman dimuat ulang. --}}
    <form method="GET" action="{{ $rute }}"
        class="mb-5 grid grid-cols-2 items-end gap-3 rounded-2xl bg-brand-surface p-4 ring-1 ring-brand-border print:hidden dark:bg-gray-900 sm:flex sm:flex-wrap">
        <div class="col-span-2 min-w-0 sm:col-span-1">
            <label for="tanggal" class="mb-1.5 block text-xs font-semibold text-brand-muted">Tanggal</label>
            <input id="tanggal" name="tanggal" type="date" value="{{ $tgl }}" class="{{ $kelasInput }} sm:w-auto">
        </div>
        <div class="min-w-0">
            <label for="kelas" class="mb-1.5 block text-xs font-semibold text-brand-muted">Kelas</label>
            <select id="kelas" name="kelas" class="{{ $kelasInput }} pr-9 sm:w-52">
                <option value="">Semua kelas</option>
                @foreach ($daftarKelas as $k)
                    <option value="{{ $k->id }}" @selected($kelas?->id === $k->id)>{{ $k->nama_kelas }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-0">
            <label for="status" class="mb-1.5 block text-xs font-semibold text-brand-muted">Status</label>
            <select id="status" name="status" class="{{ $kelasInput }} pr-9 sm:w-44">
                @foreach ($pilihanStatus as $nilai => $label)
                    <option value="{{ $nilai }}" @selected($status === $nilai)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-brand-500/25 hover:bg-brand-600">
            <x-icon name="funnel" class="h-4 w-4" />
            Tampilkan
        </button>
        <button type="button" onclick="window.print()"
            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-brand-surface px-4 py-2.5 text-sm font-semibold text-brand-muted ring-1 ring-brand-border hover:text-brand-ink dark:bg-gray-900">
            <x-icon name="printer" class="h-4 w-4" />
            Cetak
        </button>
        @if ($kelas || $status !== 'semua')
            <a href="{{ $rute . '?tanggal=' . $tgl }}"
                class="col-span-2 inline-flex min-h-11 items-center justify-center rounded-xl px-3 text-sm font-semibold text-brand-muted hover:text-brand-ink sm:col-span-1">
                Hapus saringan
            </a>
        @endif
    </form>

    {{-- ============ RINGKASAN PER KELAS ============
         Kelas dengan siswa belum absen terbanyak di depan; satu ketukan
         langsung menyaring ke kelas itu dan menampilkan yang belum absen. --}}
    @if ($perKelas->isNotEmpty())
        <div class="mb-6 print:hidden">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">Belum absen per kelas</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($perKelas as $pk)
                    @php $aktif = $kelas?->id === $pk['id']; @endphp
                    <a href="{{ $tautan(['kelas' => $pk['id'], 'status' => $pk['belum'] > 0 ? 'belum' : null]) }}"
                        @if ($aktif) aria-current="true" @endif
                        class="inline-flex min-h-10 items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1 transition {{ $aktif ? 'bg-brand-500 text-white ring-brand-500' : ($pk['belum'] > 0 ? 'bg-brand-surface text-brand-ink ring-amber-300 hover:bg-amber-50 dark:bg-gray-900 dark:hover:bg-amber-500/10' : 'bg-brand-surface text-brand-muted ring-brand-border hover:bg-brand-surface-muted dark:bg-gray-900') }}">
                        <span class="font-semibold">{{ $pk['nama'] }}</span>
                        @if ($pk['belum'] > 0)
                            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-bold tabular-nums {{ $aktif ? 'bg-white/20 text-white' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300' }}">{{ $pk['belum'] }} belum</span>
                        @else
                            <span class="inline-flex items-center gap-1 whitespace-nowrap text-xs font-semibold {{ $aktif ? 'text-white' : 'text-emerald-600 dark:text-emerald-400' }}">
                                <x-icon name="check-circle" class="h-3.5 w-3.5" /> lengkap
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Kop cetak --}}
    <div class="hidden print:mb-4 print:block">
        <p class="text-base font-bold">{{ \App\Models\PengaturanSistem::namaSekolah() }}</p>
        <p class="text-sm">
            Laporan Absensi Harian &middot; {{ $tanggal->translatedFormat('l, d F Y') }}
            @if ($kelas) &middot; Kelas {{ $kelas->nama_kelas }} @endif
            @if ($status !== 'semua') &middot; {{ $pilihanStatus[$status] }} @endif
        </p>
        <hr class="mt-3">
    </div>

    <p class="mb-4 text-sm font-semibold text-brand-muted print:hidden">
        {{ $tanggal->translatedFormat('l, d F Y') }}
        @if ($kelas) &middot; Kelas {{ $kelas->nama_kelas }} @endif
        @if ($status !== 'semua') &middot; {{ $pilihanStatus[$status] }} @endif
    </p>

    @php
        $panel = array_filter([
            ['judul' => $kelas ? 'Siswa ' . $kelas->nama_kelas : 'Siswa', 'jenis' => 'siswa', 'icon' => 'identification', 'data' => $siswa, 'batas' => $batasSiswa, 'kolomNomor' => 'NIS', 'kolomGrup' => 'Kelas'],
            $pegawai ? ['judul' => 'Pegawai', 'jenis' => 'pegawai', 'icon' => 'briefcase', 'data' => $pegawai, 'batas' => $batasPegawai, 'kolomNomor' => 'NIP', 'kolomGrup' => 'Jabatan'] : null,
        ]);

        // Label & warna per kategori (kategori dihitung di controller, sama
        // dengan yang dipakai saringan status).
        $chip = [
            'belum' => ['Belum Absen', 'bg-brand-surface-muted text-brand-muted ring-brand-border'],
            'terlambat' => ['Terlambat', 'bg-amber-500/10 text-amber-700 dark:text-amber-400 ring-amber-200'],
            'hadir' => ['Hadir', 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 ring-emerald-200'],
            'izin' => ['Izin', 'bg-sky-500/10 text-sky-700 dark:text-sky-400 ring-sky-200'],
            'sakit' => ['Sakit', 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300'],
            'alpa' => ['Alpa', 'bg-rose-500/10 text-rose-700 dark:text-rose-400 ring-rose-200'],
        ];
    @endphp

    <div class="space-y-6">
        @foreach ($panel as $bagian)
            @php
                $d = $bagian['data'];
            @endphp
            <section class="overflow-hidden rounded-2xl bg-brand-surface ring-1 ring-brand-border" data-panel="{{ $bagian['jenis'] }}">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-4 py-4 sm:px-6">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                            <x-icon name="{{ $bagian['icon'] }}" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-brand-ink">{{ $bagian['judul'] }}</h2>
                            <p class="text-xs text-brand-muted">Batas terlambat pukul {{ $bagian['batas'] }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                        <span class="whitespace-nowrap rounded-full bg-emerald-500/10 px-3 py-1 text-emerald-700 ring-1 ring-emerald-200 dark:text-emerald-400">{{ $d['hadir'] }} hadir</span>
                        <span class="whitespace-nowrap rounded-full bg-brand-surface-muted px-3 py-1 text-brand-muted ring-1 ring-brand-border">{{ $d['belum'] }} belum absen</span>
                        <span class="whitespace-nowrap rounded-full bg-brand-500/10 px-3 py-1 text-brand-accent-text ring-1 ring-brand-200 dark:text-brand-accent-text">{{ $d['total'] }} total</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    {{-- .tabel-kartu: kartu per orang di HP (app.css bagian 6b). --}}
                    <table class="tabel-kartu w-full text-left text-sm md:min-w-[680px]">
                        <thead>
                            <tr class="border-b border-brand-border text-[11px] uppercase tracking-wide text-brand-faint">
                                <th class="px-6 py-3 font-semibold">Nama</th>
                                <th class="px-6 py-3 font-semibold">{{ $bagian['kolomNomor'] }}</th>
                                <th class="px-6 py-3 font-semibold">{{ $bagian['kolomGrup'] }}</th>
                                <th class="px-6 py-3 font-semibold">Jam Masuk</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            @forelse ($d['baris'] as $baris)
                                @php [$label, $warna] = $chip[$baris['kategori']]; @endphp
                                <tr class="hover:bg-brand-surface-muted/60 print:hover:bg-transparent">
                                    <td data-utama class="px-6 py-3.5 font-semibold text-brand-ink">{{ $baris['nama'] }}</td>
                                    <td data-label="{{ $bagian['kolomNomor'] }}" class="px-6 py-3.5 font-mono text-xs text-brand-muted">{{ $baris['nomor'] ?: '—' }}</td>
                                    <td data-label="{{ $bagian['kolomGrup'] }}" class="px-6 py-3.5 text-brand-muted">{{ $baris['grup'] }}</td>
                                    <td data-label="Jam masuk" class="px-6 py-3.5 font-mono text-xs text-brand-muted">{{ $baris['absensi']?->jam_masuk?->format('H:i') ?? '—' }}</td>
                                    <td data-label="Status" class="px-6 py-3.5">
                                        <span class="inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-bold ring-1 {{ $warna }}">{{ $label }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-10 text-center text-sm text-brand-faint">
                                        @if ($d['total'] === 0)
                                            Belum ada data {{ $bagian['jenis'] }}{{ $kelas && $bagian['jenis'] === 'siswa' ? ' di kelas ini' : '' }}.
                                        @elseif ($status === 'belum')
                                            Semua {{ $bagian['jenis'] }} sudah tercatat — tidak ada yang belum absen.
                                        @else
                                            Tidak ada {{ $bagian['jenis'] }} dengan status "{{ $pilihanStatus[$status] }}".
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
