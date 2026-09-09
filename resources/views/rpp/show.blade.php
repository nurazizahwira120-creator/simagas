@extends('layouts.app')

@section('title', 'Lihat RPP')

@section('content')

    @php
        // Satu URL untuk semuanya: penampil PDF.js, cadangan <object>, dan
        // tautan unduh menunjuk rute yang sama, yang memeriksa login +
        // RppPolicy sebelum mengalirkan berkas.
        $urlBerkas = route($panelPrefix . '.rpp.berkas', $rpp);
    @endphp

    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-extrabold tracking-tight text-gray-800 dark:text-gray-100">
                {{ $rpp->judul_rpp }}
            </h1>
            <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                <span>{{ $rpp->mata_pelajaran }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $rpp->user?->name ?? 'Akun terhapus' }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ $rpp->created_at->translatedFormat('d F Y, H:i') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($adaBerkas)
                <a href="{{ $urlBerkas }}?unduh=1"
                    class="inline-flex items-center gap-2 rounded-lg border border-brand-500/40 px-4 py-2.5 text-sm font-semibold text-brand-accent-text transition hover:bg-brand-500/10">
                    <x-icon name="download" class="h-4 w-4" />
                    Unduh
                </a>
            @endif

            <a href="{{ route($panelPrefix . '.rpp.index') }}"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-600 transition hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]">
                <x-icon name="arrow-left" class="h-4 w-4" />
                Kembali
            </a>
        </div>
    </div>

    @if (! $adaBerkas)
        {{-- Baris database ada, berkasnya tidak. Ini bisa terjadi kalau folder
             storage/ ikut terhapus saat deploy. Ditampilkan terang-terangan —
             viewer PDF kosong tanpa penjelasan justru membuat orang mengira
             seluruh fiturnya rusak. --}}
        <div class="flex items-start gap-3 rounded-2xl border border-error-200 bg-error-500/10 p-5 dark:border-error-500/30" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-error-700 dark:text-error-400" />
            <div>
                <p class="text-sm font-bold text-error-700 dark:text-error-400">Berkas PDF-nya tidak ditemukan di server.</p>
                <p class="mt-1 text-sm text-error-700 dark:text-error-400">
                    Datanya masih tercatat, tetapi berkas
                    <code class="font-mono text-xs">storage/app/public/{{ $rpp->file_path }}</code>
                    sudah tidak ada. Unggah ulang dokumennya, lalu hapus entri yang lama.
                </p>
            </div>
        </div>
    @else

        {{-- ================================================================
             PENAMPIL PDF — DIGAMBAR SENDIRI DENGAN PDF.js

             ============ KENAPA BUKAN <object>/<iframe> LAGI ============
             Cara lama menyerahkan berkasnya ke <object data="...pdf"> dan
             membiarkan browser yang menampilkan. Di komputer itu berhasil,
             DI HP TIDAK — dan gagalnya diam-diam:

               * Chrome Android TIDAK PUNYA penampil PDF untuk <iframe>
                 maupun <object>. Kotaknya tampil ABU-ABU KOSONG: tanpa
                 pesan error, dan tanpa memicu konten cadangan di dalamnya
                 (cadangan hanya muncul kalau elemennya GAGAL dimuat — di
                 sini elemennya "berhasil", isinya saja yang kosong).
               * Safari iOS menampilkan HALAMAN PERTAMA saja, tidak bisa
                 di-scroll, dan sering terpotong di tengah.

             PDF.js menggambar tiap halaman ke <canvas> dengan JavaScript
             biasa. Tidak ada plugin browser yang terlibat, jadi hasilnya
             sama di HP maupun komputer.

             Tiga lapis pengaman, semuanya tetap ada:
               1. PDF.js (jalur utama).
               2. Kalau pustakanya gagal dimuat -> kotak <object> lama
                  dipakai sebagai cadangan (masih berguna di komputer).
               3. Tautan "Buka di tab baru" / "Unduh" yang SELALU terlihat
                  di bawah — di HP, membuka PDF sebagai halaman penuh
                  memakai penampil bawaan sistem, dan itu selalu bekerja.
             ================================================================ --}}

        <div id="pdf-wadah" data-url="{{ $urlBerkas }}"
            class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-theme-sm dark:border-gray-800 dark:bg-white/[0.03]">

            {{-- Bilah alat. Disembunyikan sampai dokumennya benar-benar
                 terbaca, supaya tidak ada tombol yang bisa ditekan padahal
                 belum ada isinya. --}}
            <div id="pdf-bilah" hidden
                class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-white/[0.03]">

                <div class="flex items-center gap-1">
                    <button type="button" id="pdf-sebelum"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                        aria-label="Halaman sebelumnya">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                    </button>

                    <span class="px-2 text-sm font-semibold tabular-nums text-gray-700 dark:text-gray-200">
                        Hal. <span id="pdf-halaman">1</span> / <span id="pdf-total">?</span>
                    </span>

                    <button type="button" id="pdf-sesudah"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-gray-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                        aria-label="Halaman berikutnya">
                        <x-icon name="arrow-right" class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex items-center gap-1">
                    <button type="button" id="pdf-perkecil"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-lg font-bold leading-none text-gray-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                        aria-label="Perkecil tampilan">&minus;</button>

                    <span id="pdf-zoom" class="w-14 text-center text-sm font-semibold tabular-nums text-gray-700 dark:text-gray-200">100%</span>

                    <button type="button" id="pdf-perbesar"
                        class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-lg font-bold leading-none text-gray-600 transition hover:bg-white disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/[0.05]"
                        aria-label="Perbesar tampilan">+</button>
                </div>
            </div>

            {{-- Panggung tempat halaman digambar. overflow-auto supaya saat
                 diperbesar halamannya bisa digeser, bukan menjebol layout. --}}
            <div id="pdf-panggung" class="max-h-[70vh] overflow-auto bg-gray-100 p-2 dark:bg-gray-900">

                <div id="pdf-memuat" class="flex flex-col items-center gap-3 py-16 text-center">
                    <svg class="h-6 w-6 animate-spin text-brand-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                    </svg>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Memuat dokumen&hellip;</p>
                </div>

                <canvas id="pdf-kanvas" hidden class="mx-auto block max-w-full rounded-md shadow-sm"></canvas>
            </div>

            {{-- Cadangan lapis 2: baru ditampilkan kalau PDF.js gagal. --}}
            <div id="pdf-cadangan" hidden>
                <p id="pdf-alasan" class="border-b border-warning-200 bg-warning-500/10 px-4 py-3 text-sm text-warning-700 dark:border-warning-500/30 dark:text-warning-400"></p>

                <object data="{{ $urlBerkas }}" type="application/pdf"
                    aria-label="Penampil RPP: {{ $rpp->judul_rpp }}"
                    class="block w-full bg-gray-100 dark:bg-gray-900" style="height: 600px;">

                    <div class="flex flex-col items-center gap-3 p-10 text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Browser ini tidak bisa menampilkan PDF langsung di halaman.
                        </p>
                        <a href="{{ $urlBerkas }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-600">
                            Buka PDF di Tab Baru
                        </a>
                    </div>
                </object>
            </div>
        </div>

        {{-- Lapis 3: tautan yang SELALU terlihat, di luar penampil mana pun.
             Kalau seluruh JavaScript-nya gagal sekalipun, jalan keluarnya
             tetap ada di halaman. --}}
        <p class="mt-4 text-center text-sm text-gray-500 dark:text-gray-400">
            PDF tidak tampil?
            <a href="{{ $urlBerkas }}" target="_blank" rel="noopener"
                class="font-semibold text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">
                Buka di tab baru
            </a>
            atau
            <a href="{{ $urlBerkas }}?unduh=1"
                class="font-semibold text-brand-600 underline-offset-2 hover:underline dark:text-brand-400">
                unduh berkasnya
            </a>.
        </p>

        @push('scripts')
            <script>
                (function () {
                    'use strict';

                    var wadah = document.getElementById('pdf-wadah');

                    if (! wadah) {
                        return;
                    }

                    var URL_PDF = wadah.dataset.url;

                    // Versi DIKUNCI, bukan "latest". Berkas pustaka dan berkas
                    // worker-nya harus versi yang sama persis; kalau salah satu
                    // ikut naik sendiri, PDF.js berhenti dengan pesan
                    // "API version does not match Worker version".
                    var VERSI = '3.11.174';
                    var DASAR = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/' + VERSI + '/';

                    var panggung = document.getElementById('pdf-panggung');
                    var kanvas = document.getElementById('pdf-kanvas');
                    var memuat = document.getElementById('pdf-memuat');
                    var bilah = document.getElementById('pdf-bilah');
                    var cadangan = document.getElementById('pdf-cadangan');
                    var alasan = document.getElementById('pdf-alasan');
                    var labelHalaman = document.getElementById('pdf-halaman');
                    var labelTotal = document.getElementById('pdf-total');
                    var labelZoom = document.getElementById('pdf-zoom');
                    var tblSebelum = document.getElementById('pdf-sebelum');
                    var tblSesudah = document.getElementById('pdf-sesudah');
                    var tblPerkecil = document.getElementById('pdf-perkecil');
                    var tblPerbesar = document.getElementById('pdf-perbesar');

                    var dokumen = null;
                    var halaman = 1;
                    var zoom = 1;
                    var tugasRender = null;

                    /**
                     * Menyerah dengan sopan: penampil kanvas disembunyikan,
                     * kotak <object> lama yang tampil. Tidak pernah
                     * meninggalkan layar kosong tanpa keterangan.
                     */
                    function menyerah(pesan) {
                        panggung.hidden = true;
                        bilah.hidden = true;
                        alasan.textContent = pesan;
                        cadangan.hidden = false;
                    }

                    function gambar() {
                        if (! dokumen) {
                            return;
                        }

                        // Render sebelumnya DIBATALKAN dulu. Tanpa ini, memutar
                        // HP atau menekan "berikutnya" dua kali cepat membuat
                        // dua render menulis ke kanvas yang sama, dan hasilnya
                        // dua halaman tercetak bertumpuk.
                        if (tugasRender) {
                            tugasRender.cancel();
                            tugasRender = null;
                        }

                        dokumen.getPage(halaman).then(function (hal) {
                            var lebarTersedia = Math.max(panggung.clientWidth - 24, 240);
                            var asli = hal.getViewport({ scale: 1 });

                            // Titik awalnya "muat selebar layar", lalu dikali
                            // zoom. Di HP inilah bedanya dengan <iframe>:
                            // halamannya menyesuaikan lebar layar, bukan
                            // terpotong.
                            var skala = (lebarTersedia / asli.width) * zoom;

                            // Layar HP punya kerapatan piksel 2–3x. Tanpa
                            // pengali ini hurufnya tampak kabur berbayang.
                            // Dibatasi 2 supaya kanvasnya tidak jadi raksasa
                            // dan kehabisan memori di HP kelas bawah.
                            var dpr = Math.min(window.devicePixelRatio || 1, 2);
                            var tampilan = hal.getViewport({ scale: skala * dpr });

                            kanvas.width = Math.floor(tampilan.width);
                            kanvas.height = Math.floor(tampilan.height);
                            kanvas.style.width = Math.floor(tampilan.width / dpr) + 'px';
                            kanvas.style.height = Math.floor(tampilan.height / dpr) + 'px';

                            tugasRender = hal.render({
                                canvasContext: kanvas.getContext('2d'),
                                viewport: tampilan,
                            });

                            return tugasRender.promise;
                        }).then(function () {
                            tugasRender = null;
                            memuat.hidden = true;
                            kanvas.hidden = false;
                            perbaruiTombol();
                        }).catch(function (e) {
                            // Pembatalan yang kita minta sendiri bukan kegagalan.
                            if (e && e.name === 'RenderingCancelledException') {
                                return;
                            }

                            menyerah('Halaman ini gagal digambar. Gunakan tautan di bawah untuk membukanya langsung.');
                        });
                    }

                    function perbaruiTombol() {
                        labelHalaman.textContent = halaman;
                        labelZoom.textContent = Math.round(zoom * 100) + '%';
                        tblSebelum.disabled = halaman <= 1;
                        tblSesudah.disabled = ! dokumen || halaman >= dokumen.numPages;
                        tblPerkecil.disabled = zoom <= 0.5;
                        tblPerbesar.disabled = zoom >= 3;
                    }

                    function keHalaman(baru) {
                        if (! dokumen) {
                            return;
                        }

                        halaman = Math.min(Math.max(baru, 1), dokumen.numPages);
                        panggung.scrollTop = 0;
                        gambar();
                    }

                    function aturZoom(baru) {
                        zoom = Math.min(Math.max(baru, 0.5), 3);
                        gambar();
                    }

                    tblSebelum.addEventListener('click', function () { keHalaman(halaman - 1); });
                    tblSesudah.addEventListener('click', function () { keHalaman(halaman + 1); });
                    tblPerkecil.addEventListener('click', function () { aturZoom(zoom - 0.25); });
                    tblPerbesar.addEventListener('click', function () { aturZoom(zoom + 0.25); });

                    // Memutar HP mengubah lebar panggung, jadi halamannya harus
                    // digambar ulang. Ditunda sesaat supaya tidak menggambar
                    // puluhan kali selama jendela masih diseret di komputer.
                    var jeda = null;
                    window.addEventListener('resize', function () {
                        clearTimeout(jeda);
                        jeda = setTimeout(gambar, 250);
                    });

                    function mulai() {
                        if (typeof window.pdfjsLib === 'undefined') {
                            menyerah('Pustaka penampil PDF tidak bisa dimuat dari internet.');
                            return;
                        }

                        window.pdfjsLib.GlobalWorkerOptions.workerSrc = DASAR + 'pdf.worker.min.js';

                        window.pdfjsLib.getDocument({
                            url: URL_PDF,
                            // Rute berkasnya dilindungi login, jadi cookie sesi
                            // WAJIB ikut. Tanpa ini permintaannya dialihkan ke
                            // halaman login dan PDF.js menerima HTML, bukan PDF.
                            withCredentials: true,
                        }).promise.then(function (doc) {
                            dokumen = doc;
                            labelTotal.textContent = doc.numPages;
                            bilah.hidden = false;
                            gambar();
                        }).catch(function () {
                            menyerah('Dokumen tidak bisa dibaca oleh penampil bawaan halaman ini.');
                        });
                    }

                    var skrip = document.createElement('script');
                    skrip.src = DASAR + 'pdf.min.js';
                    skrip.onload = mulai;
                    skrip.onerror = function () {
                        menyerah('Pustaka penampil PDF tidak bisa dimuat — periksa koneksi internet.');
                    };
                    document.head.appendChild(skrip);
                })();
            </script>
        @endpush
    @endif

@endsection
