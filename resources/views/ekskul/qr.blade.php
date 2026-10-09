<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Ekskul — {{ $jadwal->nama_ekskul }}</title>

    @include('partials.head-assets')

    {{-- Sama dengan Kartu QR Siswa: QR digambar di browser (lihat partials/skrip-kartu-qr). --}}
    <script src="https://unpkg.com/qrcode-generator@2.0.4/dist/qrcode.js"></script>

    <style>
        @media print {
            @page { margin: 12mm; }
            body * { visibility: hidden; }
            #area-cetak, #area-cetak * { visibility: visible; }
            #area-cetak { position: absolute; left: 0; top: 0; width: 100%; }
            .kartu-qr { break-inside: avoid; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
        /* QR stiker ekskul dicetak lebih besar dari kartu siswa: ditempel di
           dinding/papan dan dipindai dari jarak satu lengan. */
        .qr-besar canvas, .qr-besar img { width: 220px !important; height: 220px !important; }
    </style>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased print:bg-white">

    <header class="border-b border-slate-200 bg-white print:hidden">
        <div class="mx-auto flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4 py-4">
            <div class="min-w-0">
                <p class="text-xs font-medium text-slate-400">Stiker QR Ekskul</p>
                <h1 class="mt-1 truncate text-xl font-extrabold tracking-tight text-slate-800">{{ $jadwal->nama_ekskul }}</h1>
            </div>
            <button type="button" onclick="window.print()"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-teal-700">
                <x-icon name="printer" class="h-4 w-4" />
                Cetak
            </button>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8">
        <div id="area-cetak" class="mx-auto max-w-sm">
            <div class="kartu-qr flex flex-col items-center rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm print:border-slate-400 print:shadow-none">
                <p class="text-xs font-bold uppercase tracking-widest text-teal-700">Scan untuk Mulai Sesi Ekskul</p>
                <div id="qr-ekskul" class="qr-holder qr-besar mt-4" data-kode="{{ $jadwal->kode_qr }}"></div>
                <p class="mt-4 text-xl font-extrabold text-slate-800">{{ $jadwal->nama_ekskul }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ $jadwal->hari }} &middot; {{ $jadwal->rentangJam() }}</p>
                <p class="mt-1 text-sm text-slate-500">Pembina: {{ $jadwal->namaPembina() }}</p>
                {{-- Kode ditulis juga, untuk diketik bila kamera HP bermasalah. --}}
                <p class="mt-3 rounded bg-slate-100 px-3 py-1 font-mono text-sm tracking-wider text-slate-700">{{ $jadwal->kode_qr }}</p>

                <button type="button"
                    class="tombol-unduh-qr mt-4 inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-teal-600 hover:text-teal-600 print:hidden"
                    data-target="qr-ekskul" data-nama="QR {{ $jadwal->nama_ekskul }}">
                    <x-icon name="download" class="h-3.5 w-3.5" />
                    Unduh QR
                </button>
            </div>
            <p class="mt-4 text-center text-xs text-slate-400 print:hidden">
                Tempel di tempat kegiatan ekskul. Pembina men-scan stiker ini dari halaman Absensi Ekskul untuk memulai sesi.
            </p>
        </div>
    </main>

    @include('partials.skrip-kartu-qr')
</body>
</html>
