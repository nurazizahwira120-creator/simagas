{{--
    View komponen App\Livewire\Guru\AbsenMengajarQr — gaya TailAdmin.

    PANTANGAN (pelajaran mahal, jangan diulang):
    JANGAN menulis teks yang berbentuk tag HTML — termasuk di dalam KOMENTAR
    JavaScript — di dalam blok <script> pada view Livewire. Parser HTML akan
    keluar dari <script> di situ dan membuat elemen nyata di luar root div,
    sehingga Livewire melempar MultipleRootElementsDetectedException dan
    seluruh halaman jadi 500. Tulis "elemen video", bukan tag-nya.

    Semua logika penyimpanan ada di PHP. JavaScript di sini hanya membaca
    kode dari kamera lalu menyerahkannya ke $wire.prosesAbsenMengajar().
--}}

@once
    @push('scripts')
        {{-- Library pembaca QR lewat kamera HP. Dititipkan ke stack 'scripts'
             di layout supaya dimuat sekali saja. --}}
        <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    @endpush
@endonce

<div class="mx-auto w-full max-w-2xl">

    @if (! $sudahAbsenKehadiran)

        {{-- ============================================================
             KONDISI FALSE — belum absen kehadiran pagi.
             Scanner TIDAK dirender sama sekali (bukan sekadar disembunyikan),
             jadi kamera pun tidak diminta izin.
             ============================================================ --}}
        <div class="flex w-full border-l-4 border-orange-500 bg-orange-500/10 px-7 py-8 shadow-md dark:bg-gray-900/30">
            <div class="mr-5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-orange-500">
                <x-icon name="exclamation-triangle" class="h-5 w-5 text-white" />
            </div>

            <div class="w-full">
                <h5 class="mb-3 text-lg font-bold text-[#9D5425]">
                    Akses Ditolak! Anda belum melakukan Absen Kehadiran pagi ini.
                </h5>

                <p class="leading-relaxed text-[#D0915C]">
                    Silakan lakukan Absen Kehadiran berbasis Radius terlebih dahulu
                    sebelum mengajar.
                </p>

                @unless ($this->akunTertaut)
                    {{-- Sebab yang berbeda, jadi pesannya juga harus berbeda:
                         guru bisa saja SUDAH absen tapi akunnya belum ditautkan
                         ke data pegawai, dan menyuruhnya "absen radius dulu"
                         akan mengirimnya ke jalan buntu. --}}
                    <p class="mt-3 rounded-md bg-white/60 px-4 py-3 text-sm leading-relaxed text-[#9D5425] dark:bg-gray-950/60">
                        <span class="font-semibold">Catatan:</span> akun Anda belum ditautkan ke
                        data pegawai, sehingga kehadiran Anda memang belum bisa dicatat sama
                        sekali. Hubungi Admin TU atau Super Admin untuk menautkannya.
                    </p>
                @endunless

                @if (Route::has($panelPrefix . '.absen-lokasi'))
                    <a href="{{ route($panelPrefix . '.absen-lokasi') }}"
                        class="mt-5 inline-flex items-center gap-2 rounded-md bg-orange-500 px-6 py-3 text-center font-medium text-white transition hover:bg-orange-500/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
                        <x-icon name="map-pin" class="h-5 w-5" />
                        Absen Kehadiran (Radius)
                    </a>
                @endif
            </div>
        </div>

    @else

        {{-- ============================================================
             KONDISI TRUE — sudah absen kehadiran, scanner boleh tampil.
             ============================================================ --}}

        {{-- Ringkasan kehadiran pagi, supaya jelas kenapa halaman ini terbuka. --}}
        <div class="mb-6 flex items-center gap-3 rounded-sm border border-gray-200 bg-brand-surface px-6 py-4 shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-success-500/10 text-success-500">
                <x-icon name="check-circle" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-brand-ink dark:text-white">Absen Kehadiran hari ini sudah tercatat</p>
                <p class="mt-0.5 text-xs text-brand-muted dark:text-brand-faint">
                    {{ now()->translatedFormat('l, d F Y') }}
                    @if ($this->jamKehadiran)
                        &middot; masuk pukul {{ $this->jamKehadiran }}
                    @endif
                </p>
            </div>
        </div>

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

                    // getUserMedia hanya diizinkan browser di halaman aman
                    // (HTTPS atau localhost). Di http:// biasa permintaan
                    // kamera langsung ditolak, dan tanpa pengecekan ini guru
                    // cuma melihat layar hitam tanpa penjelasan.
                    if (! window.isSecureContext) {
                        this.pesanJs = 'Browser memblokir akses kamera karena halaman ini dibuka lewat HTTP biasa. Kamera hanya bisa dipakai di alamat HTTPS atau di localhost. Sementara itu, ketik kode ruangan secara manual di bawah.';
                        return;
                    }

                    if (typeof Html5Qrcode === 'undefined') {
                        this.pesanJs = 'Library scanner gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.';
                        return;
                    }

                    this.menyalakan = true;

                    // Nada pertama hanya terdengar kalau AudioContext-nya lahir
                    // dari sebuah sentuhan. Tombol ini satu-satunya sentuhan
                    // yang pasti ada sebelum scan pertama — lihat
                    // partials/scan-kamera.
                    window.umpanBalikScan?.siapkan();

                    try {
                        this.pemindai = new Html5Qrcode('reader-mengajar');

                        await this.pemindai.start(
                            { facingMode: 'environment' },
                            { fps: 10, qrbox: window.kotakBidikScan },
                            (teks) => this.tangkap(teks),
                            () => { /* tidak ada kode di frame ini — normal */ }
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
                        // Kamera sudah berhenti duluan — tidak fatal.
                    }

                    this.pemindai = null;
                    this.aktif = false;
                },

                tangkap(teks) {
                    const kode = String(teks).trim();
                    const sekarang = Date.now();

                    // Jeda 3 detik. Kamera membaca ~10 frame per detik, jadi
                    // satu stiker yang tetap di depan lensa akan terbaca
                    // berkali-kali — tanpa jeda ini satu guru mengirim
                    // puluhan request untuk satu kali masuk kelas.
                    if (kode === this.kodeTerakhir && (sekarang - this.waktuTerakhir) < 3000) {
                        return;
                    }

                    this.kodeTerakhir = kode;
                    this.waktuTerakhir = sekarang;

                    // PANTANGAN: JANGAN pakai tanda kutip ganda di komentar
                    // ini. Seluruh blok x-data tinggal di dalam atribut HTML
                    // yang dibatasi kutip ganda, jadi satu saja di sini akan
                    // MEMUTUS atributnya di tengah jalan. Gejalanya
                    // menyesatkan: Alpine melempar 'Unexpected token )' lalu
                    // setiap properti dilaporkan 'is not defined'.
                    //
                    // Nada 'mulai' berarti stikernya terbaca dan sedang
                    // dikirim — BUKAN berhasil. Nada hasilnya menyusul dari
                    // server lewat peristiwa hasil-scan, karena hanya server
                    // yang tahu sesinya tercatat, ruangannya salah, atau
                    // jadwalnya tidak cocok.
                    window.umpanBalikScan?.('mulai');
                    this.$wire.prosesAbsenMengajar(kode);
                },

                kirimManual() {
                    const kode = this.kodeManual.trim();
                    if (! kode) return;

                    this.kodeManual = '';

                    // Jalur manual diberi penanda yang sama dengan jalur
                    // kamera, supaya kedua cara mengirim terdengar sama.
                    window.umpanBalikScan?.('mulai');
                    this.$wire.prosesAbsenMengajar(kode);
                }
            }"
            x-on:beforeunload.window="matikan()"
            x-on:hasil-scan.window="window.umpanBalikScan?.($event.detail.tipe)"
            class="rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">

            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">Scan QR Ruangan Kelas</h3>
                <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                    Arahkan kamera ke stiker QR di meja guru. Catatan mengajar tersimpan
                    tanpa memuat ulang halaman.
                </p>
            </div>

            <div class="p-6">

                {{-- Area kamera.

                     wire:ignore WAJIB: html5-qrcode menyuntikkan sendiri elemen
                     video ke dalam div ini. Tanpa wire:ignore, setiap kali
                     Livewire memperbarui komponen (yaitu setiap kali satu kode
                     berhasil di-scan) DOM-nya disamakan lagi dengan hasil render
                     server — elemen videonya ikut terhapus dan kamera mati
                     sendiri sesudah scan pertama. --}}
                <x-bingkai-bidik id="reader-mengajar" wire:ignore
                    petunjuk="Arahkan stiker QR ruangan ke dalam bingkai"
                    class="rounded-sm border border-gray-200 dark:border-gray-800" />

                {{-- Tombol kamera --}}
                <div class="mt-4 flex gap-3">
                    <button type="button" x-show="! aktif" x-on:click="nyalakan()" x-bind:disabled="menyalakan"
                        class="flex flex-1 items-center justify-center gap-2 rounded-md bg-brand-500 px-6 py-3 font-medium text-white transition hover:bg-brand-500/90 disabled:opacity-60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                        <x-icon name="camera" class="h-5 w-5" />
                        <span x-text="menyalakan ? 'Menyalakan…' : 'Nyalakan Kamera'"></span>
                    </button>

                    <button type="button" x-show="aktif" style="display: none" x-on:click="matikan()"
                        class="flex flex-1 items-center justify-center gap-2 rounded-md border border-gray-200 bg-transparent px-6 py-3 font-medium text-brand-ink transition hover:bg-gray-50 dark:border-gray-800 dark:text-white dark:hover:bg-gray-950">
                        <x-icon name="x-circle" class="h-5 w-5" />
                        Matikan Kamera
                    </button>
                </div>

                {{-- Pesan dari sisi JavaScript (bukan HTTPS, izin kamera ditolak). --}}
                <div x-show="pesanJs" style="display: none"
                    class="mt-4 flex w-full border-l-4 border-warning-500 bg-warning-500/10 px-5 py-4">
                    <x-icon name="exclamation-triangle" class="mr-4 mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                    <p class="text-sm leading-relaxed text-[#9D5425]" x-text="pesanJs"></p>
                </div>

                {{-- ---- Notifikasi hasil scan (dari server) ---- --}}
                @if ($notif)
                    @php
                        $gaya = match ($notif['tipe']) {
                            'ok' => ['garis' => 'border-success-500 bg-success-500/10', 'ikon' => 'check-circle', 'warnaIkon' => 'text-success-500', 'judul' => 'text-brand-ink dark:text-white'],
                            'warn' => ['garis' => 'border-warning-500 bg-warning-500/10', 'ikon' => 'clock', 'warnaIkon' => 'text-warning-500', 'judul' => 'text-[#9D5425]'],
                            default => ['garis' => 'border-error-500 bg-error-500/10', 'ikon' => 'x-circle', 'warnaIkon' => 'text-error-500', 'judul' => 'text-[#B45454]'],
                        };
                    @endphp

                    {{-- wire:key memaksa elemen ini dibuat ULANG tiap notifikasi
                         baru, bukan cuma teksnya yang ditukar. Tanpa itu, dua
                         scan berturut-turut dengan pesan mirip tidak terlihat
                         berubah dan guru mengira scan keduanya gagal. --}}
                    <div wire:key="notif-{{ md5($notif['judul'] . $notif['pesan']) }}-{{ now()->format('Hisu') }}"
                        class="mt-4 flex w-full border-l-4 px-5 py-4 shadow-md {{ $gaya['garis'] }}">
                        <x-icon name="{{ $gaya['ikon'] }}" class="mr-4 mt-0.5 h-5 w-5 shrink-0 {{ $gaya['warnaIkon'] }}" />
                        <div class="min-w-0">
                            <h5 class="mb-1 font-semibold {{ $gaya['judul'] }}">{{ $notif['judul'] }}</h5>
                            <p class="text-sm leading-relaxed text-brand-muted dark:text-brand-faint">{{ $notif['pesan'] }}</p>
                        </div>
                    </div>
                @endif

                {{-- Indikator selama Livewire memproses satu kode. --}}
                <div wire:loading wire:target="prosesAbsenMengajar"
                    class="mt-3 flex items-center gap-2 text-sm font-medium text-brand-500">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    Menyimpan catatan mengajar…
                </div>

                {{-- Jalur cadangan: ketik kode ruangan. Dipakai kalau halaman
                     belum HTTPS, izin kamera ditolak, atau stikernya rusak. --}}
                {{-- wire:ignore.self menjaga atribut `open` milik <details>
                     ini dari proses morph Livewire. Tanpa itu, setiap kali
                     satu kode dikirim komponen dirender ulang, HTML dari
                     server tidak punya atribut `open`, dan panelnya menutup
                     sendiri — guru yang kameranya bermasalah harus membuka
                     panel ini lagi untuk SETIAP ruangan yang ia masuki.
                     '.self' dipakai (bukan wire:ignore biasa) supaya isinya
                     tetap ikut diperbarui. --}}
                <details wire:ignore.self class="mt-4 rounded-sm border border-gray-200 dark:border-gray-800">
                    <summary class="flex cursor-pointer select-none items-center gap-2 px-5 py-3 text-sm font-medium text-brand-muted dark:text-brand-faint">
                        <x-icon name="keyboard" class="h-4 w-4" />
                        Kamera bermasalah? Ketik kode ruangan
                    </summary>

                    <div class="flex gap-2 px-5 pb-4">
                        <input type="text" x-model="kodeManual" x-on:keydown.enter.prevent="kirimManual()"
                            placeholder="mis. RUANG-X-RPL-1" maxlength="60"
                            class="w-full rounded-md border border-gray-200 bg-transparent px-4 py-2.5 text-sm text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                        <button type="button" x-on:click="kirimManual()"
                            class="shrink-0 rounded-md bg-brand-500 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-brand-500/90">
                            Kirim
                        </button>
                    </div>
                </details>
            </div>
        </div>

        {{-- ---- Riwayat mengajar hari ini ---- --}}
        <div class="mt-6 rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-800">
                <h3 class="font-semibold text-brand-ink dark:text-white">
                    Mengajar Hari Ini
                    <span class="ml-1 text-sm font-normal text-brand-muted dark:text-brand-faint">
                        ({{ $this->riwayatHariIni->count() }} catatan)
                    </span>
                </h3>
            </div>

            <div class="px-6 py-4">
                @forelse ($this->riwayatHariIni as $baris)
                    <div class="flex items-center justify-between gap-4 border-b border-gray-200 py-3 last:border-0 dark:border-gray-800">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
                                <x-icon name="qr-code" class="h-4 w-4" />
                            </span>
                            <p class="truncate text-sm font-medium text-brand-ink dark:text-white">{{ $baris->kode_kelas }}</p>
                        </div>
                        <p class="shrink-0 font-mono text-sm text-brand-muted dark:text-brand-faint">
                            {{ $baris->waktu_mulai->format('H:i') }}
                        </p>
                    </div>
                @empty
                    <p class="py-3 text-sm text-brand-muted dark:text-brand-faint">
                        Belum ada catatan mengajar hari ini. Scan stiker QR di ruangan pertama Anda.
                    </p>
                @endforelse
            </div>
        </div>

    @endif
</div>
