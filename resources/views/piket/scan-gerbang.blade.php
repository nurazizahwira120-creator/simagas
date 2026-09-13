@extends('layouts.app')

@section('title', 'Gerbang — Scan & Pencatatan Izin')

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">Gerbang Sekolah</h1>
        <p class="mt-1 max-w-3xl text-sm text-brand-muted dark:text-brand-faint">
            Scan kartu siswa di sebelah kiri, catat izin yang masuk di sebelah kanan.
            Keduanya di satu layar supaya kamera tidak perlu dimatikan saat ada wali murid
            menyerahkan surat.
        </p>
    </div>

    @if (session('sukses'))
        <div class="muncul mb-5 flex items-start gap-3 rounded-2xl border border-emerald-300 bg-emerald-500/10 p-4" role="status">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600" />
            <p class="text-sm font-semibold text-emerald-800 dark:text-emerald-400">{{ session('sukses') }}</p>
        </div>
    @endif

    @if (session('gagal'))
        <div class="muncul mb-5 flex items-start gap-3 rounded-2xl border border-amber-300 bg-amber-500/10 p-4" role="alert">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0 text-amber-700 dark:text-amber-400" />
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-400">{{ session('gagal') }}</p>
        </div>
    @endif

    {{-- ============ SPLIT SCREEN ============
         min-w-0 pada KEDUA kolom, dan itu bukan hiasan.

         Item grid lahir dengan min-width: auto, artinya ia menolak menyempit
         di bawah lebar min-content isinya. Di bawah lg kedua kolom berbagi
         satu jalur, dan daftar izin di kolom kanan (nama panjang + lencana
         dalam satu baris) punya min-content yang lebar — tanpa min-w-0,
         jalur itu memaksa SELURUH isi kedua kolom meluber ke kanan dan
         terpotong di layar HP. Persis itu yang pernah terjadi di dashboard
         guru dan butuh waktu untuk dilacak. --}}
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] lg:items-start lg:gap-6">

        {{-- ================= KIRI: AREA SCAN ================= --}}
        <section class="muncul min-w-0">
            <div class="overflow-hidden rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">

                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-5 py-4 dark:border-gray-800">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-brand-ink dark:text-white">Area Scan Barcode Siswa</h2>
                        <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                            Mengenali NIS siswa maupun NIP pegawai secara otomatis.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-brand-border px-3 py-1 text-xs font-medium text-brand-muted">
                        <x-icon name="qr-code" class="h-3.5 w-3.5" />
                        QR &amp; Barcode
                    </span>
                </div>

                <div class="p-5">
                    {{-- Komponen bingkai bidik yang sama dengan scanner piket:
                         siku bidiknya menempel pada area yang BENAR-BENAR
                         dipindai html5-qrcode, bukan digambar di posisi tebakan. --}}
                    <x-bingkai-bidik id="reader-gerbang"
                        petunjuk="Arahkan QR/barcode NIS atau NIP ke dalam bingkai"
                        class="rounded-xl border border-brand-border dark:border-gray-800" />

                    <div class="mt-4 flex gap-2">
                        <button id="gerbang-mulai" type="button"
                            class="kartu-angkat flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-accent px-4 py-3 text-sm font-semibold text-white shadow-soft">
                            <x-icon name="camera" class="h-5 w-5" />
                            Nyalakan Kamera
                        </button>
                        <button id="gerbang-stop" type="button"
                            class="hidden flex-1 items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-sm font-semibold text-brand-ink dark:border-gray-800 dark:text-white">
                            <x-icon name="x-circle" class="h-5 w-5" />
                            Matikan Kamera
                        </button>
                    </div>

                    <div id="gerbang-kabar"
                        class="mt-3 flex min-h-[3.25rem] items-center gap-2.5 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-sm font-medium text-brand-muted dark:border-gray-800">
                        <x-icon name="qr-code" class="h-5 w-5 shrink-0" />
                        <span id="gerbang-kabar-teks">Tekan &ldquo;Nyalakan Kamera&rdquo; untuk mulai.</span>
                    </div>

                    <details class="mt-3 rounded-xl border border-brand-border open:pb-3 dark:border-gray-800">
                        <summary class="flex cursor-pointer select-none items-center gap-2 px-4 py-3 text-sm font-medium text-brand-muted">
                            <x-icon name="keyboard" class="h-4 w-4" />
                            Input manual NIS / NIP
                        </summary>
                        <form id="gerbang-manual" class="flex gap-2 px-4">
                            <input id="gerbang-kode" type="text" inputmode="numeric" placeholder="Ketik NIS atau NIP…"
                                class="min-w-0 flex-1 rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none dark:border-gray-800 dark:bg-gray-950">
                            <button type="submit" class="rounded-lg bg-brand-ink px-4 py-2 text-sm font-medium text-white">Kirim</button>
                        </form>
                    </details>
                </div>
            </div>
        </section>

        {{-- ================= KANAN: FORM IZIN (REAL-TIME) =================

             Komponen Livewire, BUKAN form POST biasa — dan itu bukan soal
             selera. Form biasa memuat ulang seluruh halaman setiap kali
             Simpan ditekan, dan pemuatan ulang itu MEMATIKAN KAMERA SCANNER
             di panel sebelah kiri. Di gerbang pagi hari, petugas harus
             menyalakannya lagi sementara antrean anak tetap berjalan.

             Dengan Livewire hanya panel ini yang digambar ulang; elemen
             video di kiri tidak pernah tersentuh. --}}
        <section class="muncul min-w-0" style="animation-delay: 120ms">
            @livewire('gerbang.form-izin')
        </section>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
        <script>
            (function () {
                'use strict';

                var urlScan = @json(route($panelPrefix . '.gerbang.scan'));
                var csrf = @json(csrf_token());

                var tombolMulai = document.getElementById('gerbang-mulai');
                var tombolStop = document.getElementById('gerbang-stop');
                var kabar = document.getElementById('gerbang-kabar');
                var kabarTeks = document.getElementById('gerbang-kabar-teks');
                var formManual = document.getElementById('gerbang-manual');
                var inputManual = document.getElementById('gerbang-kode');

                var pemindai = null;
                var sibuk = false;
                var kodeTerakhir = null;
                var waktuTerakhir = 0;

                var GAYA = {
                    diam: 'border-brand-border bg-brand-surface text-brand-muted',
                    proses: 'border-brand-border bg-brand-surface-muted text-brand-ink',
                    ok: 'border-emerald-300 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400',
                    warn: 'border-amber-300 bg-amber-500/10 text-amber-800 dark:text-amber-400',
                    error: 'border-red-300 bg-red-500/10 text-red-800 dark:text-red-400',
                };

                function setKabar(jenis, teks) {
                    kabar.className = 'mt-3 flex min-h-[3.25rem] items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium transition-colors ' + (GAYA[jenis] || GAYA.diam);
                    kabarTeks.textContent = teks;
                }

                // Nada, getar dan kilatan layar tinggal di partials/scan-kamera
                // supaya ketiga layar scan berbunyi persis sama. Dibungkus
                // supaya halaman tetap hidup kalau partial-nya gagal dimuat.
                function umpanBalik(jenis) {
                    if (typeof window.umpanBalikScan === 'function') {
                        window.umpanBalikScan(jenis);
                    }
                }

                async function kirim(kode) {
                    if (sibuk) return;

                    var sekarang = Date.now();

                    // Kamera membaca ~10 frame/detik. Tanpa jeda ini, satu kartu
                    // yang tetap berada di depan lensa mengirim puluhan request.
                    if (kode === kodeTerakhir && (sekarang - waktuTerakhir) < 4000) return;

                    sibuk = true;
                    kodeTerakhir = kode;
                    waktuTerakhir = sekarang;

                    umpanBalik('mulai');
                    setKabar('proses', 'Memproses kode ' + kode + '…');

                    try {
                        var res = await fetch(urlScan, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                            },
                            body: JSON.stringify({ kode: kode }),
                        });

                        var hasil = await res.json();

                        if (res.ok && hasil.success) {
                            umpanBalik('ok');
                            setKabar('ok', hasil.message);
                        } else if (res.status === 409) {
                            umpanBalik('warn');
                            setKabar('warn', hasil.message);
                        } else {
                            umpanBalik('error');
                            setKabar('error', hasil.message || 'Gagal memproses scan.');
                        }
                    } catch (e) {
                        umpanBalik('error');
                        setKabar('error', 'Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.');
                    } finally {
                        setTimeout(function () { sibuk = false; }, 1200);
                    }
                }

                async function nyalakan() {
                    if (pemindai) return;

                    if (!window.isSecureContext) {
                        setKabar('error', 'Browser memblokir kamera karena halaman ini dibuka lewat HTTP biasa. Gunakan input manual di bawah.');
                        return;
                    }

                    if (typeof Html5Qrcode === 'undefined') {
                        setKabar('error', 'Library scanner gagal dimuat. Periksa koneksi lalu muat ulang halaman.');
                        return;
                    }

                    // AudioContext yang lahir di luar sentuhan pengguna
                    // langsung ditidurkan browser mobile, dan nada scan
                    // pertama hilang tanpa jejak. Tombol ini sentuhannya.
                    if (window.umpanBalikScan) window.umpanBalikScan.siapkan();

                    pemindai = new Html5Qrcode('reader-gerbang');

                    try {
                        await pemindai.start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: window.kotakBidikScan },
                            function (teks) { kirim(String(teks).trim()); },
                            function () { /* tidak ada kode di frame ini — normal */ }
                        );
                        tombolMulai.classList.add('hidden');
                        tombolStop.classList.remove('hidden');
                        tombolStop.classList.add('flex');
                        setKabar('diam', 'Kamera aktif — arahkan ke QR/barcode NIS atau NIP.');
                    } catch (e) {
                        pemindai = null;
                        setKabar('error', 'Tidak bisa mengakses kamera. Pastikan izinnya sudah diberikan untuk halaman ini.');
                    }
                }

                async function matikan() {
                    if (!pemindai) return;
                    try {
                        await pemindai.stop();
                        pemindai.clear();
                    } catch (e) { /* kamera sudah berhenti duluan */ }
                    pemindai = null;
                    tombolMulai.classList.remove('hidden');
                    tombolStop.classList.add('hidden');
                    tombolStop.classList.remove('flex');
                    setKabar('diam', 'Kamera dimatikan.');
                }

                tombolMulai.addEventListener('click', nyalakan);
                tombolStop.addEventListener('click', matikan);

                formManual.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var kode = inputManual.value.trim();
                    if (!kode) return;
                    inputManual.value = '';
                    kirim(kode);
                });
            })();
        </script>
    @endpush

@endsection
