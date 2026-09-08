<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kartu QR — {{ $judul }}</title>

    @include('partials.head-assets')

    {{-- Generate QR di browser (client-side) — tidak butuh dependency PHP
         tambahan, dan hasilnya digambar ke <canvas> sehingga tombol Unduh
         cukup memakai canvas.toDataURL() tanpa pustaka lain. --}}
    <script src="https://unpkg.com/qrcode-generator@2.0.4/dist/qrcode.js"></script>

    <style>
        /* ============ MODE CETAK ============
           Yang ikut tercetak HANYA kartu-kartunya. Caranya: sembunyikan SEMUA
           elemen, lalu tampilkan kembali area cetak beserta isinya. Ini lebih
           tahan banting daripada menandai satu per satu elemen yang harus
           disembunyikan (header, tombol, dsb) — elemen baru yang ditambahkan
           nanti otomatis ikut tersembunyi, bukan malah bocor ke hasil cetak.
           visibility (bukan display:none) dipakai supaya tata letak di dalam
           area cetak tidak ikut berantakan. */
        @media print {
            @page { margin: 12mm; }

            body * { visibility: hidden; }

            #area-cetak, #area-cetak * { visibility: visible; }

            #area-cetak {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }

            /* Kartu tidak boleh terbelah dua halaman, dan border/latarnya harus
               benar-benar tercetak (bawaan printer membuang warna latar). */
            .kartu-qr {
                break-inside: avoid;
                page-break-inside: avoid;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased print:bg-white">

    {{-- ============ KEPALA HALAMAN ============ --}}
    <header class="border-b border-slate-200 bg-white print:hidden">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <div class="min-w-0">
                <nav class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                    <a href="{{ route($panelPrefix . '.pegawai.index') }}"
                        class="inline-flex items-center gap-1 rounded transition-colors hover:text-teal-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500">
                        <x-icon name="arrow-left" class="h-3.5 w-3.5" />
                        Kelola Pegawai
                    </a>
                    <span class="text-slate-300">/</span>
                    <span class="text-slate-500">Kartu QR</span>
                </nav>

                <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-slate-800">
                    {{ $judul }}
                </h1>
            </div>

            <button type="button" onclick="window.print()"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-teal-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2">
                <x-icon name="printer" class="h-4 w-4" />
                Cetak
            </button>
        </div>
    </header>

    {{-- ============ KARTU ============
         Satu pegawai -> kartu tunggal, dibatasi max-w-md supaya tidak melar
                         memenuhi layar lebar dan terlihat kosong.
         Semua pegawai -> grid 2 kolom, di layar maupun saat dicetak. --}}
    @php $kartuTunggal = $daftarPegawai->count() === 1; @endphp

    <main class="mx-auto max-w-5xl px-4 py-8">
        <div id="area-cetak"
            class="{{ $kartuTunggal ? 'mx-auto max-w-md' : 'grid grid-cols-1 gap-4 sm:grid-cols-2 print:grid-cols-2 print:gap-3' }}">

            @forelse ($daftarPegawai as $pegawai)
                <div class="kartu-qr flex items-center gap-6 rounded-2xl border border-slate-100 bg-white p-6 shadow-sm print:border-slate-300 print:p-4 print:shadow-none">

                    {{-- QR di kiri. Sejak NIP jadi opsional, pegawai bisa saja
                         belum punya nomor — dan QR gerbang isinya justru NIP
                         itu. Tanpa penanganan ini kotaknya cuma kosong dan
                         terlihat seperti fitur yang rusak. --}}
                    @if (filled($pegawai->nip))
                        <div id="qr-pegawai-{{ $pegawai->id }}" class="qr-holder shrink-0" data-kode="{{ $pegawai->nip }}"></div>
                    @else
                        <div class="flex h-[120px] w-[120px] shrink-0 flex-col items-center justify-center gap-1.5 rounded-lg border border-dashed border-slate-300 px-2 text-center">
                            <x-icon name="exclamation-triangle" class="h-5 w-5 text-amber-500" />
                            <p class="text-[10px] font-semibold leading-tight text-slate-500">Belum punya NIP</p>
                        </div>
                    @endif

                    {{-- Identitas di kanan --}}
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-base font-bold leading-tight text-slate-800">{{ $pegawai->nama }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $pegawai->jabatan }}</p>

                        @if (filled($pegawai->nip))
                            <p class="mt-0.5 font-mono text-xs text-slate-400">NIP {{ $pegawai->nip }}</p>

                            <button type="button"
                                class="tombol-unduh-qr mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-teal-600 hover:text-teal-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 print:hidden"
                                data-target="qr-pegawai-{{ $pegawai->id }}" data-nama="{{ $pegawai->nama }}">
                                <x-icon name="download" class="h-3.5 w-3.5" />
                                Unduh QR
                            </button>
                        @else
                            <p class="mt-2 text-xs leading-relaxed text-amber-700">
                                QR gerbang berisi NIP, jadi kartunya belum bisa dibuat.
                                Isi NIP lewat <span class="font-semibold">Kelola Pegawai &rsaquo; Ubah</span>
                                supaya {{ $pegawai->nama }} bisa scan di gerbang.
                            </p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-sm text-slate-500">Tidak ada pegawai untuk dicetak.</p>
            @endforelse

        </div>
    </main>

    @include('partials.skrip-kartu-qr')
</body>
</html>
