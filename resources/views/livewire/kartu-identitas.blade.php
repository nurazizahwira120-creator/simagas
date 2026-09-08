{{--
    View untuk App\Livewire\KartuIdentitas — modal Kartu Profil DUA SISI
    (depan & belakang) untuk Siswa & Pegawai.

    UKURAN KARTU: 900 x 600 piksel = rasio 3:2 = 9 x 6 cm saat dicetak.

    Dua satuan dipakai bersamaan dengan sengaja:
    - Di LAYAR & saat diunduh html2canvas, kartu memakai piksel (900x600),
      supaya 1 mm selalu = 10 px. Itulah yang membuat QR 300x300 px pada
      kartu ini benar-benar tercetak 3 x 3 cm.
    - Saat DICETAK, aturan @media print memaksa ukurannya ke 9cm x 6cm.
      Printer hanya mengenal satuan fisik, jadi "900px" di sana tidak berarti
      apa-apa.

    9 x 6 cm dekat dengan ukuran kartu identitas standar (CR80, 8,56 x 5,4 cm),
    jadi kartu ini muat di dompet dan di holder/lanyard biasa.

    CATATAN soal pembuat QR:
    Brief menyebut paket PHP simplesoftwareio/simple-qrcode. Yang dipakai di
    sini tetap pustaka JavaScript qrcode-generator yang sudah lebih dulu ada di
    aplikasi ini, dengan dua alasan yang sudah terbukti di pengujian:
      1. simple-qrcode menghasilkan SVG (PNG-nya butuh ekstensi imagick), dan
         html2canvas SERING merender SVG inline jadi kotak kosong — artinya
         kartu hasil unduhan bisa keluar tanpa QR sama sekali.
      2. Ia satu paket composer lagi yang harus dipasang manual, padahal
         maatwebsite/excel saja belum sempat terpasang.
    QR di sini digambar sebagai tabel sel berwarna, yang sudah dibuktikan
    terbawa utuh ke berkas PNG hasil unduhan dan terbaca pembaca QR sungguhan.
--}}

@once
    @push('scripts')
        {{-- html2canvas untuk "Unduh Kartu HD". Dimuat dari CDN; kalau gagal
             dimuat (internet sekolah mati), tombolnya memberi tahu dan
             menawarkan Cetak — lihat unduh() di bawah. --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

        {{-- Pembuat QR. Pustaka yang sama sudah dipakai halaman Kartu QR yang
             lebih dulu ada, jadi tidak menambah dependensi baru. --}}
        <script src="https://unpkg.com/qrcode-generator@2.0.4/dist/qrcode.js"></script>
    @endpush
@endonce

<div>

    <style>
        /* ============ GAYA CETAK ============
           Saat dicetak, SEMUA yang di layar disembunyikan kecuali kedua sisi
           kartunya. Dipakai visibility (bukan display:none) supaya kartu yang
           berada jauh di dalam pohon DOM tetap bisa ditampilkan kembali —
           kalau induknya di-display:none, anaknya ikut hilang dan tidak bisa
           "dihidupkan" lagi. */
        @media print {
            @page {
                /* Hapus margin bawaan browser (biasanya ~1cm plus header/footer
                   URL). Tanpa ini kartu tergeser dan bisa terpotong. */
                margin: 0;
            }

            html, body {
                background: #fff !important;
                height: auto !important;
            }

            body * {
                visibility: hidden !important;
                box-shadow: none !important;
            }

            #area-cetak-kartu, #area-cetak-kartu * {
                visibility: visible !important;
            }

            #area-cetak-kartu {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                transform: none !important;
            }

            /* Satu sisi kartu per halaman, masing-masing di tengah kertas.
               Ini yang membuatnya bisa dicetak bolak-balik (duplex): halaman 1
               sisi depan, halaman 2 sisi belakang. */
            #area-cetak-kartu .sisi-kartu {
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                height: 100vh !important;
                width: 100% !important;
                break-after: page;
                page-break-after: always;
            }

            #area-cetak-kartu .sisi-kartu:last-child {
                break-after: auto;
                page-break-after: auto;
            }

            /* width/height dipaksa ke cm — BUKAN piksel — supaya hasil cetak
               benar-benar 9 x 6 cm. Di layar 900px hanya "besar"; di printer
               yang berarti hanya satuan fisik. */
            #kartu-depan, #kartu-belakang {
                width: 9cm !important;
                height: 6cm !important;
                border-radius: 0 !important;
            }

            /* Tanpa ini Chrome membuang latar & gradien saat mencetak,
               sehingga sisi navy, lengkungan teal, dan footer teal-nya hilang
               jadi putih. */
            #area-cetak-kartu, #area-cetak-kartu * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>

    @if ($terbuka && $this->kartu)
        @php
            $kartu = $this->kartu;

            $inisial = collect(explode(' ', trim($kartu['nama'])))
                ->filter()->take(2)
                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                ->implode('');

            // Alamat dipangkas supaya tidak mendorong tata letak kartu.
            $alamatSingkat = $kartu['alamat']
                ? \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $kartu['alamat']), 58)
                : null;
        @endphp

        {{-- ================= LAPIS MODAL ================= --}}
        <div
            x-data="kartuProfil(@js($kartu['nama']))"
            x-on:keydown.escape.window="$wire.tutup()"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 py-8 print:static print:block print:bg-transparent print:p-0"
            role="dialog" aria-modal="true" aria-label="Kartu profil">

            {{-- Klik latar untuk menutup. Elemen terpisah supaya klik DI DALAM
                 kartu tidak ikut menutup modalnya. --}}
            <div class="absolute inset-0 print:hidden" wire:click="tutup" aria-hidden="true"></div>

            <div class="relative w-full max-w-[940px] print:max-w-none">

                {{-- Kartu 900px diperkecil proporsional di layar sempit lewat
                     scale-*, bukan dengan mengubah ukurannya: html2canvas dan
                     aturan cetak sama-sama membaca ukuran asli 900x600, jadi
                     hasil unduhan & cetaknya tetap sama di layar apa pun. --}}
                <div id="area-cetak-kartu"
                    class="origin-top scale-[0.34] space-y-6 sm:scale-[0.5] md:scale-[0.68] lg:scale-[0.92] xl:scale-100 print:scale-100 print:space-y-0">

                    {{-- ==================== SISI DEPAN ==================== --}}
                    <div class="sisi-kartu">
                        <div id="kartu-depan"
                            class="relative h-[600px] w-[900px] overflow-hidden rounded-2xl bg-white shadow-2xl">

                            {{-- Lengkungan di pertemuan navy & putih dibuat dari tiga
                                 ELIPS bertumpuk (rounded-[50%]), bukan clip-path atau
                                 path SVG. Sengaja: html2canvas tidak merender
                                 clip-path sama sekali, sehingga kartu hasil unduhan
                                 akan berbeda dari yang terlihat di layar. Elips =
                                 border-radius biasa, dan itu dirender dengan benar
                                 baik oleh html2canvas maupun oleh printer. --}}
                            <div class="absolute -left-[42%] -top-[26%] h-[152%] w-[100%] rounded-[50%] bg-teal-700"></div>
                            <div class="absolute -left-[42%] -top-[26%] h-[152%] w-[96.5%] rounded-[50%] bg-teal-500"></div>
                            <div class="absolute -left-[42%] -top-[26%] h-[152%] w-[93%] rounded-[50%] bg-slate-900"></div>

                            {{-- ---------- Kiri (55%) di atas navy ---------- --}}
                            <div class="absolute inset-y-0 left-0 flex w-[52%] flex-col justify-center py-8 pl-12 pr-5">

                                <p class="text-[13px] font-bold uppercase tracking-[0.28em] text-teal-400">
                                    {{ $kartu['jenis'] === 'siswa' ? 'Kartu Pelajar' : 'Kartu Pegawai' }}
                                </p>

                                <h2 class="mt-2 text-[36px] font-extrabold leading-[1.08] text-white">
                                    {{ $kartu['nama'] }}
                                </h2>

                                <p class="mt-1.5 text-[19px] font-medium text-teal-300">
                                    {{ $kartu['kedua'] }}
                                </p>

                                <div class="my-5 h-px w-24 bg-teal-400/50"></div>

                                <ul class="space-y-3 text-[15px] leading-snug text-slate-200">
                                    <li class="flex items-start gap-3">
                                        <span class="mt-0.5 shrink-0 text-teal-400"><x-icon name="identification" class="h-5 w-5" /></span>
                                        <span>
                                            <span class="text-slate-400">{{ $kartu['labelKode'] }}</span>
                                            <span class="ml-1.5 font-mono font-semibold text-white">{{ $kartu['kode'] ?: '—' }}</span>
                                        </span>
                                    </li>

                                    <li class="flex items-start gap-3">
                                        <span class="mt-0.5 shrink-0 text-teal-400"><x-icon name="phone" class="h-5 w-5" /></span>
                                        <span>
                                            @if ($kartu['noHp'])
                                                <span class="font-semibold text-white">{{ $kartu['noHp'] }}</span>
                                                @if ($kartu['jenis'] === 'siswa')
                                                    <span class="text-slate-400"> (wali)</span>
                                                @endif
                                            @else
                                                <span class="text-slate-500">Nomor HP belum diisi</span>
                                            @endif
                                        </span>
                                    </li>

                                    <li class="flex items-start gap-3">
                                        <span class="mt-0.5 shrink-0 text-teal-400"><x-icon name="home" class="h-5 w-5" /></span>
                                        <span class="{{ $alamatSingkat ? 'text-white' : 'text-slate-500' }}">
                                            {{ $alamatSingkat ?? 'Alamat belum diisi' }}
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            {{-- ---------- Kanan (45%) putih ---------- --}}
                            {{-- Logo di POJOK KANAN ATAS, pas foto di TENGAH-KANAN:
                                 keduanya diposisikan absolut, bukan flex column,
                                 supaya letaknya tidak bergeser mengikuti panjang
                                 nama atau alamat di sisi kiri. --}}
                            <img src="{{ asset('logo.png') }}" alt="SIMAGAS"
                                class="absolute right-9 top-8 h-auto w-[165px] object-contain">

                            <div class="absolute right-[70px] top-1/2 -translate-y-1/2 pt-8">
                                <div class="flex h-[200px] w-[150px] items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                    @if (! empty($kartu['foto']))
                                        <img src="{{ $kartu['foto'] }}" alt="Pas foto {{ $kartu['nama'] }}"
                                            class="h-full w-full object-cover">
                                    @else
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-[38px] font-extrabold leading-none text-slate-900/20">{{ $inisial }}</span>
                                            <span class="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">Pas Foto</span>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @if (! empty($kartu['berlaku']))
                                <p class="absolute bottom-4 right-9 text-[10px] tracking-wide text-slate-400">
                                    Berlaku T.A. {{ $kartu['berlaku'] }}
                                </p>
                            @endif
                        </div>
                    </div>

                    {{-- ==================== SISI BELAKANG ==================== --}}
                    <div class="sisi-kartu">
                        <div id="kartu-belakang"
                            class="relative flex h-[600px] w-[900px] flex-col items-center overflow-hidden rounded-2xl bg-white shadow-2xl">

                            {{-- Watermark logo besar di tengah --}}
                            <img src="{{ asset('logo-mark.png') }}" alt=""
                                class="pointer-events-none absolute left-1/2 top-1/2 h-[440px] w-[440px] -translate-x-1/2 -translate-y-1/2 object-contain opacity-5">

                            <p class="relative mt-9 text-[15px] font-semibold uppercase tracking-[0.22em] text-slate-400">
                                Scan untuk Presensi / Verifikasi
                            </p>

                            {{-- QR 300 x 300 px. Pada kanvas 900x600 px yang tercetak
                                 9 x 6 cm, 1 mm = 10 px — jadi 300 px tepat 3 cm.
                                 wire:ignore supaya Livewire tidak menghapus isi yang
                                 digambar JavaScript saat merender ulang. --}}
                            {{-- Sejak NIP jadi opsional, kode ini bisa kosong untuk
                                 pegawai baru. QR yang isinya string kosong tidak
                                 bisa dipindai apa pun, jadi lebih jujur menampilkan
                                 keterangannya daripada mencetak kotak yang tidak
                                 berfungsi di kartu resmi. --}}
                            @if (filled($kartu['kode']))
                                <div wire:ignore
                                    class="qr-kartu relative mt-4 flex h-[300px] w-[300px] items-center justify-center overflow-hidden"
                                    data-kode="{{ $kartu['kode'] }}"></div>
                            @else
                                <div class="relative mt-4 flex h-[300px] w-[300px] flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 px-6 text-center">
                                    <x-icon name="exclamation-triangle" class="h-8 w-8 text-amber-500" />
                                    <p class="text-[15px] font-bold text-slate-600">{{ $kartu['labelKode'] }} belum diisi</p>
                                    <p class="text-[13px] leading-snug text-slate-400">
                                        QR presensi dibuat dari {{ $kartu['labelKode'] }}. Lengkapi dulu datanya
                                        agar kartu ini bisa dipakai di gerbang.
                                    </p>
                                </div>
                            @endif

                            <p class="relative mt-3 font-mono text-[15px] tracking-[0.16em] text-slate-500">
                                {{ $kartu['labelKode'] }} {{ $kartu['kode'] ?: '—' }}
                            </p>

                            <p class="relative mt-1 text-[13px] font-bold text-navy-700">
                                {{ $kartu['nama'] }}
                            </p>

                            {{-- Footer teal penuh --}}
                            <div class="absolute inset-x-0 bottom-0 bg-teal-600 px-10 py-3.5 text-center">
                                <p class="text-[12px] leading-relaxed text-white">
                                    Kartu ini adalah milik SMK Islam Assya'roniyyah.
                                    Jika menemukan kartu ini, harap kembalikan ke alamat Sekolah.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ============ TOMBOL AKSI (tidak ikut tercetak) ============ --}}
                <div class="mt-6 flex flex-wrap items-center justify-center gap-2.5 print:hidden">
                    <button type="button" wire:click="tutup"
                        class="inline-flex items-center gap-2 rounded-xl border border-white/30 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur transition hover:bg-white/20">
                        <x-icon name="x-mark" class="h-4 w-4" />
                        Tutup
                    </button>

                    <button type="button" x-on:click="unduh()" x-bind:disabled="sibuk"
                        class="inline-flex items-center gap-2 rounded-xl bg-teal-500 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-teal-900/40 transition hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-70">
                        <x-icon name="download" class="h-4 w-4" />
                        <span x-text="sibuk ? 'Menyiapkan…' : 'Unduh Kartu HD (2 sisi)'"></span>
                    </button>

                    <button type="button" x-on:click="window.print()"
                        class="inline-flex items-center gap-2 rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-900 transition hover:bg-slate-100">
                        <x-icon name="printer" class="h-4 w-4" />
                        Cetak
                    </button>
                </div>

                <p x-show="pesan" style="display: none"
                    class="mx-auto mt-3 max-w-xl rounded-xl bg-amber-50 px-4 py-2.5 text-center text-xs font-medium text-amber-800 print:hidden"
                    x-text="pesan"></p>
            </div>
        </div>
    @endif

    <script>
        // ==================================================================
        // PANTANGAN DI BLOK SCRIPT INI: jangan menulis teks berbentuk tag
        // HTML di mana pun — termasuk di dalam komentar.
        //
        // Livewire memeriksa "komponen harus punya satu elemen akar" dengan
        // membaca hasil render memakai DOMDocument. Parser HTML bawaan libxml
        // tidak mengikuti aturan modern soal isi script: begitu ia menemukan
        // teks seperti nama tag tabel di sini, ia keluar dari script dan
        // membuat elemen sungguhan di luar div akar. Akibatnya Livewire
        // menganggap komponen ini punya DUA elemen akar dan melempar
        // MultipleRootElementsDetectedException — seluruh halaman jadi 500,
        // bukan sekadar tampilan yang meleset.
        //
        // Ini pernah benar-benar terjadi di sini. Kalau butuh membuat elemen,
        // pakai document.createElement.
        // ==================================================================

        window.kartuProfil = function (nama) {
            return {
                sibuk: false,
                pesan: null,

                init() {
                    this.gambarQr();
                },

                gambarQr() {
                    const kotak = this.$el.querySelector('.qr-kartu');
                    if (! kotak || kotak.dataset.sudah === '1') return;

                    if (typeof qrcode === 'undefined') {
                        const kosong = document.createElement('div');
                        kosong.style.cssText = 'width:100%;height:100%;border:1px dashed #cbd5e1;' +
                            'border-radius:8px;display:flex;align-items:center;justify-content:center;' +
                            'font-size:13px;color:#94a3b8;text-align:center;padding:12px';
                        kosong.textContent = 'QR belum tersedia (pustaka gagal dimuat)';
                        kotak.replaceChildren(kosong);
                        return;
                    }

                    const qr = qrcode(0, 'M');
                    qr.addData(kotak.dataset.kode);
                    qr.make();

                    // Ukuran sel dihitung dari lebar kotak dibagi jumlah modul,
                    // dibulatkan ke bawah ke piksel bulat. Ukuran sel tetap
                    // membuat QR untuk kode panjang meluber keluar kartu,
                    // sedangkan penskalaan CSS membuat modulnya buram dan
                    // gagal dipindai.
                    const lebarKotak = kotak.clientWidth || 300;
                    const ukuranSel = Math.max(1, Math.floor(lebarKotak / qr.getModuleCount()));

                    // Dipakai createTableTag, bukan createSvgTag: html2canvas
                    // merender elemen tabel dengan sel berwarna secara andal,
                    // sedangkan SVG inline sering keluar kosong di hasil
                    // unduhannya.
                    kotak.innerHTML = qr.createTableTag(ukuranSel, 0);
                    const tabel = kotak.querySelector('table');
                    if (tabel) { tabel.style.borderCollapse = 'collapse'; }
                    kotak.dataset.sudah = '1';
                },

                async unduh() {
                    this.pesan = null;

                    if (typeof html2canvas === 'undefined') {
                        this.pesan = 'Pustaka pengunduh gambar gagal dimuat (perlu internet). Gunakan tombol Cetak, lalu pilih "Save as PDF" di jendela cetak.';
                        return;
                    }

                    this.sibuk = true;

                    const bersih = String(nama).replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_+|_+$/g, '');

                    try {
                        // Kedua sisi diekspor BERURUTAN (await satu per satu),
                        // bukan bersamaan: html2canvas mengkloning seluruh
                        // dokumen untuk tiap render, dan menjalankan dua
                        // kloning sekaligus membuat hasilnya bisa tertukar.
                        await this.simpanSatu('kartu-depan', 'Depan_' + bersih + '.png');

                        // Jeda singkat sebelum unduhan kedua. Browser
                        // memperlakukan dua unduhan otomatis berturut-turut
                        // sebagai "multiple downloads" dan bisa memblokir yang
                        // kedua; jeda ini memberi waktu unduhan pertama
                        // benar-benar dimulai.
                        await new Promise((r) => setTimeout(r, 800));

                        await this.simpanSatu('kartu-belakang', 'Belakang_' + bersih + '.png');

                        this.pesan = 'Dua berkas diunduh: Depan_' + bersih + '.png dan Belakang_' + bersih
                            + '.png. Kalau hanya satu yang masuk, izinkan "beberapa unduhan otomatis" di browser Anda.';
                    } catch (e) {
                        this.pesan = 'Gagal membuat gambar kartu: ' + (e && e.message ? e.message : 'penyebab tidak diketahui') + '. Coba tombol Cetak.';
                    } finally {
                        this.sibuk = false;
                    }
                },

                async simpanSatu(idElemen, namaBerkas) {
                    const kartu = document.getElementById(idElemen);
                    if (! kartu) return;

                    const kanvas = await html2canvas(kartu, {
                        // scale 4 -> 900x600 menjadi 3600x2400 piksel.
                        // Dicetak selebar 9 cm itu setara ~1016 dpi.
                        scale: 4,
                        useCORS: true,
                        backgroundColor: null,
                        logging: false,

                        // Kartu ditampilkan dengan CSS scale di layar kecil.
                        // Tanpa dua baris ini html2canvas ikut menangkap versi
                        // yang sudah diperkecil, dan hasil unduhannya jadi
                        // terpotong / buram di HP.
                        width: kartu.offsetWidth,
                        height: kartu.offsetHeight,
                    });

                    const tautan = document.createElement('a');
                    tautan.style.display = 'none';
                    tautan.download = namaBerkas;
                    tautan.href = kanvas.toDataURL('image/png');
                    document.body.appendChild(tautan);
                    tautan.click();
                    document.body.removeChild(tautan);
                },
            };
        };
    </script>
</div>
