@extends('layouts.app')

@section('title', 'Persetujuan Izin Guru')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">Persetujuan Izin Guru</h1>
        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-brand-muted dark:text-brand-faint">
            Pengajuan di halaman ini <strong>belum</strong> memengaruhi kehadiran siapa pun.
            Kehadiran guru baru ditandai izin setelah Anda menekan Setujui.
        </p>
    </div>

    @if (session('sukses'))
        <div class="muncul mb-5 flex items-start gap-3 rounded-2xl border border-emerald-300 bg-emerald-500/10 p-4" role="status">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <p class="text-sm leading-relaxed font-semibold text-emerald-800 dark:text-emerald-400">{{ session('sukses') }}</p>
        </div>
    @endif

    @if (session('gagal'))
        <div class="muncul mb-5 flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-500/10 p-4" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-400" />
            <p class="text-sm leading-relaxed font-semibold text-amber-800 dark:text-amber-400">{{ session('gagal') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="muncul mb-5 rounded-2xl border border-red-300 bg-red-500/10 p-4" role="alert">
            <ul class="list-inside list-disc space-y-1 text-sm text-brand-danger-text">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ================= MENUNGGU PERSETUJUAN ================= --}}
    <section class="mb-8 min-w-0">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-brand-muted">
            <x-icon name="clock" class="h-4 w-4" />
            Menunggu Persetujuan
            @if ($menunggu->isNotEmpty())
                {{-- .denyut hanya dipasang saat memang ADA yang perlu
                     ditindak. Animasi yang berdenyut terus-menerus walau tidak
                     ada apa-apa cepat berubah jadi latar yang diabaikan mata,
                     dan saat benar-benar ada yang mendesak, tidak ada bedanya. --}}
                <span class="denyut inline-flex items-center rounded-full bg-amber-500/15 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:text-amber-400">
                    {{ $menunggu->count() }}
                </span>
            @endif
        </h2>

        @if ($menunggu->isEmpty())
            <div class="rounded-2xl border border-dashed border-brand-border p-10 text-center dark:border-gray-800">
                <x-icon name="check-circle" class="mx-auto h-8 w-8 text-emerald-500" />
                <p class="mt-2 text-sm font-medium text-brand-ink dark:text-white">Tidak ada pengajuan yang menunggu.</p>
                <p class="mt-0.5 text-xs text-brand-muted">Semua pengajuan guru sudah diputuskan.</p>
            </div>
        @else
            <div class="tampil-berurutan grid gap-4 lg:grid-cols-2">
                @foreach ($menunggu as $izin)
                    {{-- min-w-0 pada item grid: item grid lahir dengan
                         min-width auto dan menolak menyempit di bawah lebar
                         min-content isinya. Nama guru yang panjang berdampingan
                         dengan lencana tanggal sudah cukup untuk melebarkan
                         jalur gridnya melampaui layar HP. --}}
                    {{-- flex-col + mt-auto pada blok tombol: dua kartu bersebelahan
                         hampir tidak pernah sama tinggi (yang IDT membawa kotak tugas,
                         yang ITT tidak). Tanpa ini tombol Setujui keduanya berhenti di
                         ketinggian berbeda, dan mata harus mencari-cari tiap kali —
                         persis di layar yang dipakai untuk memutuskan dengan cepat. --}}
                    <article class="kartu-angkat flex min-w-0 flex-col rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">

                        <div class="border-b border-brand-border px-5 py-4 dark:border-gray-800">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $izin->jenis_izin->kelasBadge() }}">
                                    <x-icon name="{{ $izin->jenis_izin->ikon() }}" class="h-3 w-3" />
                                    {{ $izin->jenis_izin->kode() }}
                                </span>
                                <span class="text-xs font-medium text-brand-muted">{{ $izin->jumlahHari() }} hari</span>
                            </div>

                            <p class="mt-1.5 truncate text-base font-bold text-brand-ink dark:text-white">
                                {{ $izin->guru?->name ?? 'Guru tidak ditemukan' }}
                            </p>
                            <p class="text-sm text-brand-muted">{{ $izin->rentangTanggal() }}</p>
                        </div>

                        <div class="space-y-3 px-5 py-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-brand-muted">Alasan</p>
                                <p class="mt-0.5 text-sm leading-relaxed text-brand-ink dark:text-gray-200">{{ $izin->alasan }}</p>
                            </div>

                            @if ($izin->detail_tugas)
                                <div class="rounded-xl border border-sky-300 bg-sky-500/5 p-3 dark:border-sky-500/30">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-sky-700 dark:text-sky-400">Tugas yang ditinggalkan</p>
                                    <p class="mt-0.5 text-sm leading-relaxed text-brand-ink dark:text-gray-200">{{ $izin->detail_tugas }}</p>

                                    @if ($izin->lampiranAda())
                                        <a href="{{ route($panelPrefix . '.persetujuan-izin-guru.lampiran', $izin) }}" target="_blank" rel="noopener"
                                            class="mt-2 inline-flex items-center gap-1 rounded-lg border border-sky-300 px-2.5 py-1 text-[11px] font-semibold text-sky-700 hover:bg-sky-500/10 dark:border-sky-500/30 dark:text-sky-400">
                                            <x-icon name="eye" class="h-3 w-3" />
                                            Buka Lampiran
                                        </a>
                                    @endif
                                </div>
                            @elseif ($izin->jenis_izin->butuhTugas())
                                {{-- Tidak seharusnya terjadi (detail_tugas wajib untuk
                                     IDT), tapi ditampilkan apa adanya kalau sampai ada
                                     — lebih baik terlihat janggal daripada tampil
                                     seolah-olah normal. --}}
                                <p class="rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-800 dark:text-amber-400">
                                    Ditandai IDT tetapi tidak ada deskripsi tugas.
                                </p>
                            @endif
                        </div>

                        {{-- ---- Tombol keputusan ---- --}}
                        <div class="mt-auto border-t border-brand-border px-5 py-4 dark:border-gray-800"
                            x-data="{ tolak: false }">

                            <div x-show="! tolak" class="flex flex-wrap gap-2">
                                {{-- Dua <form> terpisah, bukan satu form dengan dua tombol
                                     submit bernilai beda: keduanya punya aturan validasi
                                     yang berbeda (catatan wajib pada penolakan, opsional
                                     pada persetujuan), dan menyatukannya berarti aturan
                                     yang lebih longgar berlaku untuk keduanya. --}}
                                <form method="POST" action="{{ route($panelPrefix . '.persetujuan-izin-guru.setujui', $izin) }}" class="flex-1">
                                    @csrf
                                    <button type="submit"
                                        class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-soft hover:bg-emerald-700">
                                        <x-icon name="check" class="h-4 w-4" />
                                        Setujui
                                    </button>
                                </form>

                                <button type="button" @click="tolak = true"
                                    class="flex items-center justify-center gap-2 rounded-xl border border-red-300 px-4 py-2.5 text-sm font-semibold text-brand-danger-text hover:bg-red-500/10">
                                    <x-icon name="x-mark" class="h-4 w-4" />
                                    Tolak
                                </button>
                            </div>

                            {{-- Form penolakan muncul di tempat, menggantikan tombol.
                                 x-cloak WAJIB: tanpa itu blok ini terlihat sekejap
                                 sebelum Alpine berjalan, dan pada koneksi sekolah yang
                                 lambat "sekejap" bisa berarti satu detik penuh. --}}
                            <form x-show="tolak" x-cloak method="POST"
                                action="{{ route($panelPrefix . '.persetujuan-izin-guru.tolak', $izin) }}"
                                class="space-y-2">
                                @csrf
                                <label for="tolak-{{ $izin->id }}" class="block text-xs font-semibold text-brand-ink dark:text-white">
                                    Alasan penolakan <span class="text-brand-danger-text">*</span>
                                </label>
                                <textarea id="tolak-{{ $izin->id }}" name="catatan_penyetuju" rows="2" required minlength="5" maxlength="500"
                                    placeholder="Mis. tanggalnya bentrok dengan ujian tengah semester."
                                    class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white"></textarea>

                                <div class="flex gap-2">
                                    <button type="submit"
                                        class="flex-1 rounded-xl bg-brand-danger px-4 py-2 text-sm font-semibold text-white">
                                        Kirim Penolakan
                                    </button>
                                    <button type="button" @click="tolak = false"
                                        class="rounded-xl border border-brand-border px-4 py-2 text-sm font-medium text-brand-muted dark:border-gray-800">
                                        Batal
                                    </button>
                                </div>
                            </form>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>

    {{-- ================= RIWAYAT KEPUTUSAN ================= --}}
    <section class="min-w-0">
        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-brand-muted">
            <x-icon name="document-report" class="h-4 w-4" />
            Riwayat Keputusan
        </h2>

        @if ($riwayat->isEmpty())
            <p class="rounded-2xl border border-dashed border-brand-border p-8 text-center text-sm text-brand-muted dark:border-gray-800">
                Belum ada pengajuan yang diputuskan.
            </p>
        @else
            <div class="overflow-x-auto rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">
                <table class="w-full min-w-[42rem] text-sm">
                    <thead class="border-b border-brand-border text-left text-xs uppercase tracking-wide text-brand-muted dark:border-gray-800">
                        <tr>
                            <th class="px-4 py-3 font-semibold">Guru</th>
                            <th class="px-4 py-3 font-semibold">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Jenis</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Diputuskan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-border dark:divide-gray-800">
                        @foreach ($riwayat as $izin)
                            <tr>
                                <td class="px-4 py-3 font-medium text-brand-ink dark:text-white">{{ $izin->guru?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-brand-muted">{{ $izin->rentangTanggal() }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-bold {{ $izin->jenis_izin->kelasBadge() }}">
                                        {{ $izin->jenis_izin->kode() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-semibold {{ $izin->status_approval->kelasBadge() }}">
                                        <x-icon name="{{ $izin->status_approval->ikon() }}" class="h-3 w-3" />
                                        {{ $izin->status_approval->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-xs text-brand-muted">
                                    {{ $izin->penyetuju?->name ?? '—' }}
                                    @if ($izin->diputuskan_pada)
                                        <span class="block">{{ $izin->diputuskan_pada->translatedFormat('d M Y, H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

@endsection
