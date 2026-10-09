{{--
    KARTU SESI EKSKUL — hanya untuk PEMBINA, pada hari ekskulnya.
    Alurnya sama dengan KBM: scan QR ekskul -> absensi anggota -> foto
    bukti -> Akhiri Sesi. Semua aturan ditegakkan di server
    (App\Services\AturanSesiEkskul); layar ini hanya menuntun langkahnya.

    PANTANGAN: jangan menulis teks berbentuk tag HTML di dalam JavaScript
    di bawah — lihat catatan di livewire/guru/absen-mengajar-qr.
--}}
@php
    $k = $this->sesiHariIni;
    $sesi = $k['sesi'];
    $w = $k['waktu'];
@endphp

<div id="sesi-ekskul" class="rounded-2xl border-2 border-brand-500/30 bg-white shadow-theme-sm dark:bg-white/[0.03]">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-6 py-4 dark:border-gray-800">
        <div>
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Sesi Ekskul Hari Ini</h3>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                {{ $w['mulai']->format('H:i') }}–{{ $w['selesai']->format('H:i') }}
                &middot; scan QR mulai {{ $w['buka']->format('H:i') }}
                &middot; Akhiri Sesi paling lambat {{ $w['batas']->format('H:i') }}
            </p>
        </div>
        @if ($sesi?->sudahSelesai())
            <span class="rounded-full bg-success-500/10 px-3 py-1 text-xs font-semibold text-success-700 dark:text-success-400">Selesai {{ $sesi->waktu_selesai->format('H:i') }}</span>
        @elseif ($sesi)
            <span class="rounded-full bg-brand-500/10 px-3 py-1 text-xs font-semibold text-brand-accent-text">Berlangsung sejak {{ $sesi->waktu_mulai->format('H:i') }}</span>
        @else
            <span class="rounded-full bg-gray-500/10 px-3 py-1 text-xs font-semibold text-gray-600 dark:text-gray-300">Belum dimulai</span>
        @endif
    </div>

    <div class="p-6">
        @if (! $sesi && $k['alasan'])

            <p class="flex items-start gap-2 text-sm text-gray-600 dark:text-gray-300">
                <x-icon name="clock" class="mt-0.5 h-4 w-4 shrink-0 text-warning-500" />
                {{ $k['alasan'] }}
            </p>

        @elseif (! $sesi)

            {{-- ============ LANGKAH 1: SCAN QR EKSKUL ============ --}}
            <div x-data="{
                    aktif: false, menyalakan: false, pesanJs: null, kodeManual: '',
                    pemindai: null, kodeTerakhir: null, waktuTerakhir: 0,
                    async nyalakan() {
                        this.pesanJs = null;
                        if (! window.isSecureContext) { this.pesanJs = 'Kamera hanya bisa dipakai di alamat HTTPS. Ketik kode di bawah stiker QR sebagai gantinya.'; return; }
                        if (typeof Html5Qrcode === 'undefined') { this.pesanJs = 'Library scanner gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.'; return; }
                        this.menyalakan = true;
                        window.umpanBalikScan?.siapkan();
                        try {
                            this.pemindai = new Html5Qrcode('reader-ekskul');
                            await this.pemindai.start({ facingMode: 'environment' }, { fps: 10, qrbox: window.kotakBidikScan },
                                (teks) => this.tangkap(teks), () => {});
                            this.aktif = true;
                        } catch (err) {
                            this.pemindai = null;
                            this.pesanJs = 'Tidak bisa mengakses kamera. Pastikan izin kamera sudah diberikan, lalu coba lagi.';
                        } finally { this.menyalakan = false; }
                    },
                    async matikan() {
                        if (! this.pemindai) return;
                        try { await this.pemindai.stop(); this.pemindai.clear(); } catch (err) {}
                        this.pemindai = null; this.aktif = false;
                    },
                    tangkap(teks) {
                        const kode = String(teks).trim();
                        const kini = Date.now();
                        if (kode === this.kodeTerakhir && (kini - this.waktuTerakhir) < 3000) return;
                        this.kodeTerakhir = kode; this.waktuTerakhir = kini;
                        window.umpanBalikScan?.('mulai');
                        this.matikan();
                        this.$wire.mulaiSesi(kode);
                    },
                    kirimManual() {
                        const kode = this.kodeManual.trim();
                        if (! kode) return;
                        this.kodeManual = '';
                        window.umpanBalikScan?.('mulai');
                        this.$wire.mulaiSesi(kode);
                    }
                }"
                x-on:beforeunload.window="matikan()"
                x-on:hasil-scan.window="window.umpanBalikScan?.($event.detail.tipe)">

                <p class="mb-3 text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">Langkah 1.</span> Scan stiker QR ekskul untuk memulai sesi.
                </p>

                <x-bingkai-bidik id="reader-ekskul" wire:ignore
                    petunjuk="Arahkan stiker QR ekskul ke dalam bingkai"
                    class="rounded-sm border border-gray-200 dark:border-gray-800" />

                <div class="mt-4 flex gap-3">
                    <button type="button" x-show="! aktif" x-on:click="nyalakan()" x-bind:disabled="menyalakan"
                        class="flex flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-500/90 disabled:opacity-60">
                        <x-icon name="camera" class="h-5 w-5" />
                        <span x-text="menyalakan ? 'Menyalakan…' : 'Scan QR Ekskul'"></span>
                    </button>
                    <button type="button" x-show="aktif" style="display: none" x-on:click="matikan()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-md border border-gray-200 px-6 py-3 font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-800 dark:text-white">
                        <x-icon name="x-circle" class="h-5 w-5" />
                        Matikan Kamera
                    </button>
                </div>

                <p x-show="pesanJs" style="display: none" x-text="pesanJs"
                    class="mt-3 rounded-lg bg-warning-500/10 px-4 py-3 text-sm text-warning-700 dark:text-warning-400"></p>

                <div wire:loading wire:target="mulaiSesi" class="mt-3 text-sm font-medium text-brand-500">Memulai sesi…</div>

                <details wire:ignore.self class="mt-4 rounded-sm border border-gray-200 dark:border-gray-800">
                    <summary class="flex cursor-pointer select-none items-center gap-2 px-5 py-3 text-sm font-medium text-gray-500">
                        <x-icon name="keyboard" class="h-4 w-4" />
                        Kamera bermasalah? Ketik kode di bawah stiker QR
                    </summary>
                    <div class="flex gap-2 px-5 pb-4">
                        <input type="text" x-model="kodeManual" x-on:keydown.enter.prevent="kirimManual()"
                            placeholder="EKSKUL-…" autocomplete="off" autocapitalize="characters"
                            class="min-w-0 flex-1 rounded-md border border-gray-300 bg-transparent px-4 py-2.5 font-mono text-sm uppercase dark:border-gray-700 dark:text-white">
                        <button type="button" x-on:click="kirimManual()"
                            class="rounded-md bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900 dark:bg-gray-700">Mulai</button>
                    </div>
                </details>
            </div>

        @elseif ($sesi->sudahSelesai())

            <p class="flex items-start gap-2 text-sm text-success-700 dark:text-success-400">
                <x-icon name="check-circle" class="mt-0.5 h-4 w-4 shrink-0" />
                Sesi hari ini sudah diakhiri pukul {{ $sesi->waktu_selesai->format('H:i') }}. Terima kasih!
            </p>

        @else

            {{-- ============ LANGKAH 2–4 ============ --}}
            <ol class="space-y-5">
                <li class="flex items-start gap-3">
                    <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0 text-success-500" />
                    <p class="text-sm text-gray-700 dark:text-gray-200"><span class="font-semibold">QR ekskul</span> di-scan pukul {{ $sesi->waktu_mulai->format('H:i') }}.</p>
                </li>

                <li class="flex items-start gap-3">
                    <x-icon name="{{ $k['absensi'] ? 'check-circle' : 'clock' }}" class="mt-0.5 h-5 w-5 shrink-0 {{ $k['absensi'] ? 'text-success-500' : 'text-warning-500' }}" />
                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        <span class="font-semibold">Absensi anggota</span>
                        @if ($k['absensi'])
                            sudah disimpan. Masih bisa diubah sebelum sesi diakhiri.
                        @else
                            belum disimpan. <a href="#ab-form" class="font-semibold text-brand-500 underline">Isi di bawah</a>, lalu tekan Simpan.
                        @endif
                    </p>
                </li>

                <li class="flex items-start gap-3">
                    <x-icon name="{{ $sesi->adaBukti() ? 'check-circle' : 'camera' }}" class="mt-0.5 h-5 w-5 shrink-0 {{ $sesi->adaBukti() ? 'text-success-500' : 'text-warning-500' }}" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-700 dark:text-gray-200">
                            <span class="font-semibold">Foto bukti kegiatan</span>
                            {{ $sesi->adaBukti() ? 'sudah tersimpan. Pilih foto lain bila ingin mengganti.' : 'belum diunggah.' }}
                        </p>
                        @if ($url = $sesi->urlBukti())
                            <img src="{{ $url }}" alt="Bukti kegiatan ekskul" class="mt-2 h-20 w-32 rounded object-cover ring-1 ring-gray-200 dark:ring-gray-700">
                        @endif

                        <div class="mt-3" x-data="{
                                memampatkan: false, galat: '',
                                pilih(event) {
                                    const asli = event.target.files && event.target.files[0];
                                    if (! asli) return;
                                    this.memampatkan = true; this.galat = '';
                                    const pemampat = window.SimagasPemampat;
                                    const proses = pemampat ? pemampat.pampatkan(asli) : Promise.resolve(asli);
                                    proses.then((berkas) => {
                                        this.memampatkan = false;
                                        this.$wire.upload('fotoBukti', berkas, () => {}, () => { this.galat = 'Unggahan foto gagal. Periksa sinyal lalu pilih fotonya lagi.'; });
                                    });
                                },
                            }">
                            <input type="file" id="fotoBuktiEkskul" x-on:change="pilih($event)" data-pampatkan="1"
                                accept="image/jpeg,image/png,image/webp" capture="environment"
                                class="w-full cursor-pointer rounded-md border border-gray-300 bg-transparent text-sm text-gray-700 file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2.5 file:text-sm file:font-medium dark:border-gray-700 dark:text-white dark:file:bg-gray-800 dark:file:text-white">
                            <p x-show="memampatkan" x-cloak class="mt-2 text-xs font-medium text-brand-500">Memperkecil foto…</p>
                            <p x-show="galat" x-cloak x-text="galat" class="mt-2 text-sm text-error-500"></p>
                        </div>
                        @error('fotoBukti') <p class="mt-2 text-sm text-error-500">{{ $message }}</p> @enderror
                        <div wire:loading wire:target="fotoBukti" class="mt-2 text-xs font-medium text-brand-500">Mengunggah foto…</div>

                        <button type="button" wire:click="unggahBukti" wire:loading.attr="disabled" wire:target="unggahBukti,fotoBukti"
                            @disabled(! $fotoBukti)
                            class="mt-3 inline-flex items-center gap-2 rounded-md bg-gray-800 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-gray-900 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-gray-700">
                            <x-icon name="upload" class="h-4 w-4" />
                            <span wire:loading.remove wire:target="unggahBukti">Simpan Bukti</span>
                            <span wire:loading wire:target="unggahBukti">Menyimpan…</span>
                        </button>
                    </div>
                </li>

                <li class="flex items-start gap-3">
                    <x-icon name="clipboard-check" class="mt-0.5 h-5 w-5 shrink-0 text-brand-500" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-gray-700 dark:text-gray-200">
                            <span class="font-semibold">Akhiri Sesi</span> setelah absensi dan foto bukti tersimpan.
                        </p>
                        <button type="button" wire:click="akhiriSesi" wire:loading.attr="disabled" wire:target="akhiriSesi"
                            @disabled(! ($k['absensi'] && $sesi->adaBukti()))
                            data-konfirmasi="Sesudah diakhiri, sesi ekskul hari ini ditutup."
                            data-konfirmasi-judul="Akhiri Sesi Ekskul?" data-konfirmasi-ya="Ya, Akhiri Sesi"
                            class="mt-3 inline-flex items-center gap-2 rounded-md bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-500/90 disabled:cursor-not-allowed disabled:opacity-50">
                            <x-icon name="check-circle" class="h-5 w-5" />
                            <span wire:loading.remove wire:target="akhiriSesi">Akhiri Sesi Ekskul</span>
                            <span wire:loading wire:target="akhiriSesi">Menutup sesi…</span>
                        </button>
                    </div>
                </li>
            </ol>

        @endif
    </div>
</div>
