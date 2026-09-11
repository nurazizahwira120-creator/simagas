{{--
    View untuk komponen App\Livewire\ScannerKameraSiswa.

    Pembagian tugas sama seperti Absen Radius: JavaScript hanya MEMBACA kode
    dari kamera, lalu menyerahkannya ke PHP lewat $wire.prosesAbsen(nis).
    Pencarian siswa, pengecekan dobel, dan penyimpanan semuanya di server.
--}}

@once
    @push('scripts')
        {{-- Library pembaca QR/barcode lewat kamera HP. Dititipkan ke stack
             'scripts' di layout supaya tetap dimuat sekali saja walaupun
             komponen ini nanti dipasang di lebih dari satu halaman. --}}
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    @endpush
@endonce

<div
    x-data="{
        aktif: false,
        menyalakan: false,
        pesanJs: null,
        kodeManual: '',
        pemindai: null,
        kodeTerakhir: null,
        waktuTerakhir: 0,

        async nyalakan() {
            this.pesanJs = null;

            // getUserMedia hanya diizinkan browser di halaman aman (HTTPS
            // atau localhost). Di http:// biasa, permintaan kamera langsung
            // ditolak — tanpa pengecekan ini pengguna hanya melihat layar
            // hitam tanpa penjelasan.
            if (! window.isSecureContext) {
                this.pesanJs = 'Browser memblokir akses kamera karena halaman ini dibuka lewat HTTP biasa. Kamera hanya bisa dipakai di alamat HTTPS atau di localhost. Sementara itu, gunakan input manual di bawah.';
                return;
            }

            if (typeof Html5Qrcode === 'undefined') {
                this.pesanJs = 'Library scanner gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.';
                return;
            }

            this.menyalakan = true;

            // Nada pertama hanya terdengar kalau AudioContext-nya lahir dari
            // sebuah sentuhan. Tombol ini satu-satunya sentuhan yang pasti
            // ada sebelum scan pertama — lihat partials/scan-kamera.
            window.umpanBalikScan?.siapkan();

            try {
                this.pemindai = new Html5Qrcode('reader-siswa');

                await this.pemindai.start(
                    { facingMode: 'environment' },
                    { fps: 10, qrbox: window.kotakBidikScan },
                    (teks) => this.tangkap(teks),
                    () => { /* tidak ada kode di frame ini — normal, diabaikan */ }
                );

                this.aktif = true;
            } catch (err) {
                this.pemindai = null;
                this.pesanJs = 'Tidak bisa mengakses kamera. Pastikan izin kamera sudah diberikan untuk halaman ini, lalu coba lagi.';
            } finally {
                this.menyalakan = false;
            }
        },

        async matikan() {
            if (! this.pemindai) return;

            try {
                await this.pemindai.stop();
                this.pemindai.clear();
            } catch (err) {
                // Kamera sudah berhenti duluan — tidak fatal, abaikan.
            }

            this.pemindai = null;
            this.aktif = false;
        },

        tangkap(teks) {
            const kode = String(teks).trim();
            const sekarang = Date.now();

            // Jeda 2 detik. Kamera membaca ~10 frame per detik, jadi satu
            // kartu yang tetap berada di depan lensa akan terbaca berkali-kali
            // — tanpa jeda ini, satu siswa mengirim puluhan request.
            if (kode === this.kodeTerakhir && (sekarang - this.waktuTerakhir) < 2000) {
                return;
            }

            this.kodeTerakhir = kode;
            this.waktuTerakhir = sekarang;

            // PANTANGAN: JANGAN pakai tanda kutip ganda di komentar ini.
            // Seluruh blok x-data ini tinggal di dalam atribut HTML yang
            // dibatasi kutip ganda, jadi satu saja di sini akan MEMUTUS
            // atributnya di tengah jalan. Gejalanya menyesatkan: Alpine
            // melempar 'Unexpected token )' lalu setiap properti dilaporkan
            // 'is not defined', seolah komponennya yang salah tulis.
            //
            // Nada 'mulai' berarti kodenya terbaca dan sedang dikirim — BUKAN
            // berhasil. Nada hasilnya menyusul dari server lewat peristiwa
            // hasil-scan, karena hanya server yang tahu siswanya tercatat,
            // sudah absen, atau tidak dikenal sama sekali.
            window.umpanBalikScan?.('mulai');
            this.$wire.prosesAbsen(kode);
        },

        kirimManual() {
            const kode = this.kodeManual.trim();
            if (! kode) return;

            this.kodeManual = '';

            // Jalur manual diberi penanda yang sama dengan jalur kamera,
            // supaya kedua cara mengirim kode terdengar persis sama.
            window.umpanBalikScan?.('mulai');
            this.$wire.prosesAbsen(kode);
        }
    }"
    x-on:beforeunload.window="matikan()"
    x-on:hasil-scan.window="window.umpanBalikScan?.($event.detail.tipe)">

    {{-- Area kamera.

         wire:ignore WAJIB di sini: html5-qrcode menyuntikkan sendiri elemen
         <video> ke dalam div ini. Tanpa wire:ignore, setiap kali Livewire
         memperbarui komponen (yaitu setiap kali satu kode berhasil di-scan)
         DOM-nya akan disamakan lagi dengan hasil render server — video-nya
         ikut terhapus dan kamera mati sendiri setelah scan pertama. --}}
    <x-bingkai-bidik id="reader-siswa" wire:ignore
        petunjuk="Arahkan QR/barcode NIS ke dalam bingkai"
        class="rounded-2xl border border-brand-border shadow-soft" />

    {{-- Tombol kamera --}}
    <div class="mt-3 flex gap-2">
        <button type="button" x-show="! aktif" x-on:click="nyalakan()" x-bind:disabled="menyalakan"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-accent px-4 py-3 text-sm font-semibold text-white shadow-soft transition-colors hover:bg-brand-accent-dark disabled:opacity-60">
            <x-icon name="camera" class="h-5 w-5" />
            <span x-text="menyalakan ? 'Menyalakan…' : 'Nyalakan Kamera'"></span>
        </button>

        <button type="button" x-show="aktif" style="display: none" x-on:click="matikan()"
            class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-sm font-semibold text-brand-ink transition-colors hover:bg-brand-surface-muted">
            <x-icon name="x-circle" class="h-5 w-5" />
            Matikan Kamera
        </button>
    </div>

    {{-- Pesan dari sisi JavaScript (bukan HTTPS, izin kamera ditolak). --}}
    <div x-show="pesanJs" style="display: none"
        class="mt-3 flex items-start gap-2.5 rounded-xl border border-amber-300 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-400">
        <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
        <p x-text="pesanJs"></p>
    </div>

    {{-- Hasil dari server untuk scan terakhir. --}}
    <div class="mt-3">
        @if ($notif)
            @php
                $gaya = match ($notif['tipe']) {
                    'ok' => ['kotak' => 'border-emerald-300 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400', 'ikon' => 'check-circle'],
                    'warn' => ['kotak' => 'border-amber-300 bg-amber-500/10 text-amber-800 dark:text-amber-400', 'ikon' => 'clock'],
                    default => ['kotak' => 'border-red-300 bg-red-500/10 text-red-800 dark:text-red-400', 'ikon' => 'x-circle'],
                };
            @endphp

            <div class="flex items-start gap-2.5 rounded-xl border px-4 py-3 {{ $gaya['kotak'] }}">
                <x-icon name="{{ $gaya['ikon'] }}" class="mt-0.5 h-5 w-5 shrink-0" />
                <div class="min-w-0">
                    <p class="truncate text-sm font-bold">{{ $notif['judul'] }}</p>
                    <p class="mt-0.5 text-sm">{{ $notif['pesan'] }}</p>
                </div>
            </div>
        @else
            <div class="flex items-center gap-2.5 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-sm text-brand-muted">
                <x-icon name="qr-code" class="h-5 w-5 shrink-0" />
                Arahkan kamera ke QR/barcode NIS siswa. Hasil muncul di sini tanpa memuat ulang halaman.
            </div>
        @endif

        {{-- Indikator selama Livewire memproses satu kode. --}}
        <div wire:loading wire:target="prosesAbsen"
            class="mt-2 flex items-center gap-2 text-xs font-medium text-brand-accent-text">
            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
            </svg>
            Menyimpan kehadiran…
        </div>
    </div>

    {{-- Input manual: jalur cadangan kalau kamera tidak bisa dipakai (halaman
         bukan HTTPS, izin ditolak, atau kartu siswanya rusak). --}}
    <details class="mt-3 rounded-xl border border-brand-border bg-brand-surface open:pb-3">
        <summary class="flex cursor-pointer select-none items-center gap-2 px-4 py-3 text-sm font-medium text-brand-muted">
            <x-icon name="keyboard" class="h-4 w-4" />
            Input manual NIS
        </summary>
        <form class="flex gap-2 px-4" x-on:submit.prevent="kirimManual()">
            <input type="text" inputmode="numeric" placeholder="Ketik NIS siswa…" x-model="kodeManual"
                class="flex-1 rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
            <button type="submit" class="rounded-lg bg-brand-ink px-4 py-2 text-sm font-medium text-white hover:opacity-90">
                Kirim
            </button>
        </form>
    </details>

    {{-- Riwayat sesi --}}
    <div class="mt-5">
        <h3 class="mb-2 flex items-center justify-between px-1 text-xs font-semibold uppercase tracking-wide text-brand-muted">
            <span class="flex items-center gap-1.5">
                <x-icon name="clock" class="h-3.5 w-3.5" />
                Riwayat scan sesi ini
            </span>
            @if ($jumlahBerhasil > 0)
                <span class="rounded-full bg-brand-accent-soft px-2.5 py-0.5 text-[11px] font-bold normal-case tracking-normal text-brand-accent-text">
                    {{ $jumlahBerhasil }} tercatat
                </span>
            @endif
        </h3>

        @if (empty($riwayat))
            <p class="rounded-xl border border-dashed border-brand-border px-4 py-6 text-center text-sm text-brand-muted">
                Belum ada yang di-scan.
            </p>
        @else
            <ul class="divide-y divide-brand-border/60 rounded-xl border border-brand-border bg-brand-surface px-4">
                @foreach ($riwayat as $baris)
                    @php
                        $warnaBaris = match ($baris['tipe']) {
                            'ok' => 'bg-emerald-100 text-emerald-700 dark:text-emerald-400',
                            'warn' => 'bg-amber-100 text-amber-700 dark:text-amber-400',
                            default => 'bg-red-100 text-red-700 dark:text-red-400',
                        };
                        $ikonBaris = match ($baris['tipe']) {
                            'ok' => 'check-circle',
                            'warn' => 'clock',
                            default => 'x-circle',
                        };
                    @endphp
                    <li class="flex items-center gap-3 py-2.5">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg {{ $warnaBaris }}">
                            <x-icon name="{{ $ikonBaris }}" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-brand-ink">{{ $baris['judul'] }}</p>
                            <p class="truncate text-xs text-brand-muted">{{ $baris['sub'] }}</p>
                        </div>
                        <span class="shrink-0 font-mono text-xs text-brand-muted">{{ $baris['jam'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

</div>
