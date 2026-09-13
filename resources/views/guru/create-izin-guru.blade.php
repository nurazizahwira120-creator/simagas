@extends('layouts.app')

@section('title', 'Pengajuan Izin Guru')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">Pengajuan Izin Guru</h1>
        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-brand-muted dark:text-brand-faint">
            Saat Anda tidak masuk, ada satu kelas yang tetap datang dan menunggu.
            Karena itu yang ditanyakan di sini bukan hanya alasannya, tapi juga
            <strong>nasib kelasnya</strong>.
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
            <p class="flex items-center gap-2 text-sm font-bold text-brand-danger-text">
                <x-icon name="exclamation-triangle" class="h-5 w-5 shrink-0" />
                Pengajuan belum terkirim
            </p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-brand-danger-text">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- min-w-0 pada KEDUA kolom: item grid lahir dengan min-width auto dan
         menolak menyempit di bawah lebar min-content isinya. Di bawah lg
         keduanya berbagi satu jalur, dan tanpa ini isi kedua kolom meluber
         ke kanan lalu terpotong di layar HP. --}}
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,22rem)] lg:items-start lg:gap-6">

        {{-- ================= FORM ================= --}}
        <form method="POST" action="{{ route($panelPrefix . '.izin-guru.store') }}" enctype="multipart/form-data"
            class="muncul min-w-0 rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900"

            {{-- ============ TOGGLE FORM TUGAS ============
                 Satu-satunya state yang dipegang Alpine di halaman ini:
                 jenis izin yang sedang dipilih.

                 old('jenis_izin') dipakai sebagai nilai awal, BUKAN string
                 kosong. Kalau validasi server menolak (mis. IDT tanpa tugas),
                 halaman digambar ulang — dan tanpa old(), pilihan gurunya
                 kembali ke awal, blok tugas menutup, dan isian yang sudah ia
                 ketik ikut hilang dari pandangan meski masih ada di old().
                 Guru akan mengira sistemnya menghapus ketikannya. --}}
            {{-- ============ KENAPA MEMBACA DOM, BUKAN old() SAJA ============
                 Nilai awalnya diambil dari radio yang SUDAH tercentang di
                 halaman, dan baru jatuh ke old() kalau tidak ada.

                 Sebabnya: x-model bekerja satu arah saat Alpine mulai — ia
                 MENULIS nilai data ke elemennya. Kalau guru sempat menekan
                 IDT sebelum Alpine selesai dimuat (hal yang lumrah pada
                 koneksi sekolah yang lambat, karena HTML-nya tampil lebih
                 dulu daripada skripnya), Alpine akan menimpa pilihan itu
                 dengan string kosong: radionya kembali kosong, blok tugas
                 tidak pernah muncul, dan gurunya menekan berulang kali tanpa
                 tahu apa yang salah.

                 Membaca DOM lebih dulu membuat klik yang terlanjur terjadi
                 tetap dihormati.

                 CATATAN: di dalam x-data TIDAK BOLEH ada tanda kutip ganda
                 — termasuk di dalam komentar JavaScript. Kutip itu menutup
                 atribut HTML-nya lebih awal, dan gejalanya menyesatkan:
                 "Unexpected token" plus "jenis is not defined" untuk properti
                 yang jelas-jelas ada. Penjaganya: tests/Feature/AtributAlpineTest. --}}
            x-data="{ jenis: (document.querySelector('input[name=jenis_izin]:checked') || {}).value || @js(old('jenis_izin', '')) }">
            @csrf

            <div class="border-b border-brand-border px-5 py-4 dark:border-gray-800">
                <h2 class="font-semibold text-brand-ink dark:text-white">Formulir Pengajuan</h2>
                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                    Pengajuan masuk ke kepala sekolah dan berlaku setelah disetujui.
                </p>
            </div>

            <div class="space-y-5 p-5">

                {{-- ---- Rentang tanggal ---- --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="min-w-0">
                        <label for="tanggal_mulai" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                            Tanggal Mulai <span class="text-brand-danger-text">*</span>
                        </label>
                        <input id="tanggal_mulai" type="date" name="tanggal_mulai" required
                            value="{{ old('tanggal_mulai', now()->toDateString()) }}"
                            min="{{ now()->subDays($mundurMaks)->toDateString() }}"
                            max="{{ now()->addDays($majuMaks)->toDateString() }}"
                            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                    </div>

                    <div class="min-w-0">
                        <label for="tanggal_selesai" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                            Tanggal Selesai <span class="text-brand-danger-text">*</span>
                        </label>
                        <input id="tanggal_selesai" type="date" name="tanggal_selesai" required
                            value="{{ old('tanggal_selesai', now()->toDateString()) }}"
                            min="{{ now()->subDays($mundurMaks)->toDateString() }}"
                            max="{{ now()->addDays($majuMaks)->toDateString() }}"
                            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        <p class="mt-1 text-xs text-brand-muted">Untuk izin satu hari, isi sama dengan tanggal mulai.</p>
                    </div>
                </div>

                {{-- ---- Jenis izin ---- --}}
                <fieldset>
                    <legend class="mb-1.5 text-sm font-medium text-brand-ink dark:text-white">
                        Jenis Izin <span class="text-brand-danger-text">*</span>
                    </legend>

                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($jenisIzin as $jenis)
                            @php $idRadio = 'jenis-' . strtolower($jenis->value); @endphp

                            <label for="{{ $idRadio }}" class="cursor-pointer select-none">
                                {{-- Radio asli disembunyikan (sr-only) tapi tetap ada demi
                                     keyboard & pembaca layar; tampilannya diambil alih div
                                     di sebelahnya lewat peer-checked.

                                     Sengaja memakai "peer" (selector sibling) dan BUKAN
                                     has-[:checked] — :has() belum didukung browser HP
                                     lawas, yang membuat pilihan tampak tidak ter-highlight
                                     sama sekali di HP guru. --}}
                                <input type="radio" id="{{ $idRadio }}" name="jenis_izin" value="{{ $jenis->value }}"
                                    class="peer sr-only" required
                                    x-model="jenis"
                                    @checked(old('jenis_izin') === $jenis->value)>

                                <div class="h-full rounded-xl border-2 border-brand-border p-4 transition-colors peer-checked:border-brand-accent peer-checked:bg-brand-accent-soft peer-focus-visible:ring-2 peer-focus-visible:ring-brand-accent/40 dark:border-gray-800">
                                    <span class="flex items-center gap-2">
                                        <x-icon name="{{ $jenis->ikon() }}" class="h-5 w-5 shrink-0 text-brand-accent-text" />
                                        <span class="text-sm font-bold text-brand-ink dark:text-white">{{ $jenis->kode() }}</span>
                                    </span>
                                    <p class="mt-1.5 text-xs font-medium text-brand-ink dark:text-gray-200">
                                        {{ \Illuminate\Support\Str::after($jenis->label(), '— ') }}
                                    </p>
                                    <p class="mt-1 text-xs leading-relaxed text-brand-muted">{{ $jenis->keterangan() }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- ---- Alasan ---- --}}
                <div>
                    <label for="alasan" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                        Alasan Izin <span class="text-brand-danger-text">*</span>
                    </label>
                    <textarea id="alasan" name="alasan" rows="3" required minlength="10" maxlength="1000"
                        placeholder="Mis. menghadiri undangan pernikahan keluarga di luar kota…"
                        class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">{{ old('alasan') }}</textarea>
                </div>

                {{-- ============================================================
                     BLOK TUGAS — HANYA UNTUK IDT
                     ============================================================
                     x-show, BUKAN @if di PHP: pilihannya berubah di browser
                     tanpa memuat ulang halaman, jadi keputusannya harus
                     diambil di browser juga.

                     x-cloak WAJIB dipasang. Tanpa itu, blok ini TERLIHAT
                     sepersekian detik sebelum Alpine sempat berjalan — dan
                     pada koneksi sekolah yang lambat, "sepersekian detik" bisa
                     berarti satu detik penuh. Guru melihat kolom tugas
                     berkedip muncul lalu hilang sendiri, dan itu terbaca
                     sebagai sistem yang rusak. Aturan [x-cloak]{display:none}
                     sudah ada di resources/css/app.css.

                     x-collapse membuatnya MUNCUL KE BAWAH dengan mulus, bukan
                     meloncat — isi di bawahnya tidak tiba-tiba tergeser
                     sejauh 200px tanpa peringatan.

                     ============ YANG TIDAK BOLEH DILUPAKAN ============
                     Menyembunyikan elemen BUKAN validasi. Elemen yang
                     ter-x-show=false TETAP ADA di DOM dan nilainya TETAP
                     terkirim saat form disubmit. Karena itu kewajiban
                     detail_tugas untuk IDT ditegakkan ULANG di server
                     (required_if di IzinGuruController), dan isian tugas pada
                     ITT dikosongkan paksa sebelum disimpan.
                     ============================================================ --}}
                <div x-show="jenis === '{{ \App\Enums\JenisIzinGuru::Idt->value }}'"
                    x-collapse
                    x-cloak
                    class="space-y-4 rounded-xl border border-sky-300 bg-sky-500/5 p-4 dark:border-sky-500/30">

                    <p class="flex items-start gap-2 text-xs leading-relaxed text-sky-800 dark:text-sky-300">
                        <x-icon name="clipboard-check" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            Isian di bawah akan dibacakan petugas piket di depan kelas Anda.
                            Tulis sejelas mungkin — halaman berapa, dikumpulkan ke siapa.
                        </span>
                    </p>

                    <div>
                        <label for="detail_tugas" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                            Deskripsi Tugas <span class="text-brand-danger-text">*</span>
                        </label>
                        <textarea id="detail_tugas" name="detail_tugas" rows="4" maxlength="2000"
                            placeholder="Mis. Kerjakan LKS halaman 40–42, dikumpulkan ke ketua kelas sebelum jam pelajaran berakhir."
                            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2.5 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/30 dark:border-gray-800 dark:bg-gray-950 dark:text-white">{{ old('detail_tugas') }}</textarea>
                    </div>

                    <div>
                        <label for="file_tugas" class="mb-1.5 block text-sm font-medium text-brand-ink dark:text-white">
                            Lampiran Tugas <span class="text-xs font-normal text-brand-muted">(opsional)</span>
                        </label>
                        <input id="file_tugas" type="file" name="file_tugas" accept="application/pdf,image/jpeg,image/png,image/webp"
                            class="w-full rounded-lg border border-brand-border bg-brand-surface px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-brand-accent-soft file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-brand-accent-text dark:border-gray-800 dark:bg-gray-950 dark:text-white">
                        <p class="mt-1 text-xs leading-relaxed text-brand-muted">
                            PDF atau gambar, maksimal 8 MB. Berkasnya disimpan tertutup — hanya
                            bisa dibuka lewat aplikasi ini, tidak bisa diunduh siswa.
                        </p>
                    </div>
                </div>

                {{-- Pengingat untuk ITT, muncul menggantikan blok tugas. --}}
                <div x-show="jenis === '{{ \App\Enums\JenisIzinGuru::Itt->value }}'" x-collapse x-cloak
                    class="flex items-start gap-2 rounded-xl border border-amber-300 bg-amber-500/10 p-4 text-xs leading-relaxed text-amber-800 dark:border-amber-500/30 dark:text-amber-400">
                    <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>
                        Kelas Anda akan tercatat <strong>kosong</strong> pada rentang tanggal ini.
                        Kalau sebenarnya ada tugas yang ditinggalkan, pilih IDT supaya piket
                        bisa menyampaikannya.
                    </span>
                </div>

                <button type="submit"
                    class="kartu-angkat flex w-full items-center justify-center gap-2 rounded-xl bg-brand-accent px-6 py-3 text-sm font-semibold text-white shadow-soft">
                    <x-icon name="upload" class="h-5 w-5" />
                    Ajukan Izin
                </button>
            </div>
        </form>

        {{-- ================= RIWAYAT ================= --}}
        <section class="muncul min-w-0" style="animation-delay: 120ms">
            <div class="rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">
                <h2 class="flex items-center gap-1.5 border-b border-brand-border px-5 py-3.5 text-xs font-semibold uppercase tracking-wide text-brand-muted dark:border-gray-800">
                    <x-icon name="clock" class="h-3.5 w-3.5" />
                    Pengajuan Anda
                </h2>

                @if ($riwayat->isEmpty())
                    <p class="px-5 py-8 text-center text-sm text-brand-muted">Belum ada pengajuan.</p>
                @else
                    <ul class="divide-y divide-brand-border dark:divide-gray-800">
                        @foreach ($riwayat as $izin)
                            <li class="min-w-0 px-5 py-3.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $izin->jenis_izin->kelasBadge() }}">
                                        {{ $izin->jenis_izin->kode() }}
                                    </span>
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold {{ $izin->status_approval->kelasBadge() }}">
                                        <x-icon name="{{ $izin->status_approval->ikon() }}" class="h-3 w-3" />
                                        {{ $izin->status_approval->label() }}
                                    </span>
                                </div>

                                <p class="mt-1.5 text-sm font-medium text-brand-ink dark:text-white">{{ $izin->rentangTanggal() }}</p>
                                <p class="mt-0.5 text-xs leading-relaxed text-brand-muted">{{ $izin->alasan }}</p>

                                @if ($izin->detail_tugas)
                                    <p class="mt-1.5 rounded-lg bg-sky-500/10 px-2.5 py-1.5 text-xs leading-relaxed text-sky-800 dark:text-sky-300">
                                        <span class="font-semibold">Tugas:</span> {{ $izin->detail_tugas }}
                                    </p>
                                @endif

                                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                    @if ($izin->lampiranAda())
                                        <a href="{{ route($panelPrefix . '.izin-guru.lampiran', $izin) }}" target="_blank" rel="noopener"
                                            class="inline-flex items-center gap-1 rounded-lg border border-brand-border px-2.5 py-1 text-[11px] font-medium text-brand-muted hover:bg-brand-surface-muted dark:border-gray-800">
                                            <x-icon name="eye" class="h-3 w-3" />
                                            Lampiran
                                        </a>
                                    @endif

                                    @if ($izin->catatan_penyetuju)
                                        <p class="text-[11px] leading-relaxed text-brand-muted">
                                            <span class="font-semibold">Catatan {{ $izin->penyetuju?->name ?? 'atasan' }}:</span>
                                            {{ $izin->catatan_penyetuju }}
                                        </p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>
    </div>

@endsection
