{{--
    View untuk komponen App\Livewire\AbsenGuru.

    Pembagian tugas:
    - JavaScript (Alpine, sudah ikut terpasang bersama Livewire v3) hanya
      bertugas MEMINTA koordinat ke browser.
    - Perhitungan jarak & keputusan boleh/tidak absen dikerjakan di PHP
      (AbsenGuru::prosesAbsen). Sengaja tidak dihitung di JavaScript, karena
      apa pun yang dihitung di browser bisa diubah pengguna lewat konsol.

    Komponen Livewire wajib punya tepat satu elemen akar — semuanya dibungkus
    <div> paling luar di bawah ini.
--}}
<div class="rounded-2xl border border-brand-border bg-brand-surface p-6 shadow-soft"
    x-data="{
        memuat: false,
        pesanJs: null,

        // ---- Pelacak posisi (hanya untuk TAMPILAN) ----------------------
        // Jarak di bawah ini dihitung di browser dan SEMATA-MATA untuk
        // memberi tahu pegawai apakah ia sudah cukup dekat. Keputusan
        // sebenarnya tetap di server (AbsenGuru::prosesAbsen) — apa pun yang
        // dihitung di browser bisa diubah lewat konsol.
        latSekolah: {{ $titik['lat'] }},
        lngSekolah: {{ $titik['lng'] }},
        radius: {{ $titik['radius'] }},
        jarak: null,
        pantauAktif: false,
        posisiTerakhir: null,

        get didalam() { return this.jarak !== null && this.jarak <= this.radius; },

        get statusTeks() {
            if (! this.pantauAktif) return 'Menunggu izin lokasi…';
            if (this.jarak === null) return 'Mencari lokasi Anda…';
            return this.didalam
                ? 'Anda berada di dalam area. Silakan absen. (' + this.jarak + ' m dari titik sekolah)'
                : 'Anda berada di luar radius — ' + this.jarak + ' m dari titik sekolah, batasnya ' + this.radius + ' m.';
        },

        get statusWarna() {
            if (this.jarak === null) return 'text-brand-muted';
            return this.didalam ? 'text-success-600 dark:text-success-400' : 'text-error-600 dark:text-error-400';
        },

        // Tombol dikunci HANYA kalau pelacak benar-benar berhasil membaca
        // posisi dan hasilnya di luar radius. Kalau GPS gagal, izin ditolak,
        // atau peta tidak bisa dimuat, tombolnya tetap bisa ditekan dan
        // server yang memutuskan — kalau tidak, satu gangguan koneksi
        // membuat seluruh sekolah tidak bisa absen sama sekali.
        get terkunci() { return this.memuat || (this.jarak !== null && ! this.didalam); },

        jarakMeter(lat1, lng1, lat2, lng2) {
            var R = 6371000, rad = Math.PI / 180;
            var dLat = (lat2 - lat1) * rad, dLng = (lng2 - lng1) * rad;
            var a = Math.sin(dLat / 2) ** 2
                + Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLng / 2) ** 2;
            return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        },

        mulaiPantau() {
            if (! window.isSecureContext || ! navigator.geolocation) return;

            navigator.geolocation.watchPosition(
                (pos) => {
                    this.pantauAktif = true;
                    this.posisiTerakhir = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                    this.jarak = Math.round(this.jarakMeter(
                        pos.coords.latitude, pos.coords.longitude,
                        this.latSekolah, this.lngSekolah
                    ));
                    window.dispatchEvent(new CustomEvent('simagas-posisi', {
                        detail: { lat: pos.coords.latitude, lng: pos.coords.longitude },
                    }));
                },
                () => { this.pantauAktif = false; this.jarak = null; this.posisiTerakhir = null; },
                { enableHighAccuracy: true, timeout: 20000, maximumAge: 5000 }
            );
        },

        mulai() {
            this.pesanJs = null;

            // navigator.geolocation hanya diizinkan browser di halaman aman
            // (HTTPS atau localhost). Di http:// biasa fungsinya ada tapi
            // selalu gagal — tanpa pengecekan ini, guru cuma melihat tombol
            // yang seolah tidak bereaksi.
            if (! window.isSecureContext) {
                this.pesanJs = 'Browser memblokir akses lokasi karena halaman ini dibuka lewat HTTP biasa. Fitur ini hanya jalan di alamat HTTPS atau di localhost. Hubungi admin untuk memasang sertifikat/HTTPS di server sekolah.';
                return;
            }

            if (! navigator.geolocation) {
                this.pesanJs = 'Browser ini tidak mendukung deteksi lokasi. Coba pakai Chrome atau Safari versi terbaru.';
                return;
            }

            this.memuat = true;

            // Pelacak di atas sudah memegang posisi terbaru. Memakainya
            // langsung membuat absen terasa instan DAN menghindari satu
            // kegagalan yang nyata: permintaan GPS kedua dengan
            // maximumAge:0 memaksa perangkat mencari sinyal dari nol, dan
            // di dalam gedung permintaan itu sering habis waktu — tombolnya
            // seolah menggantung padahal posisinya sudah diketahui.
            if (this.posisiTerakhir) {
                this.$wire.prosesAbsen(this.posisiTerakhir.lat, this.posisiTerakhir.lng)
                    .finally(() => { this.memuat = false; });
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.memuat = false;
                    this.$wire.prosesAbsen(pos.coords.latitude, pos.coords.longitude);
                },
                (err) => {
                    this.memuat = false;

                    if (err.code === 1) {
                        this.pesanJs = 'Izin lokasi ditolak. Buka ikon gembok/info di sebelah alamat situs, lalu izinkan akses Lokasi, kemudian coba lagi.';
                    } else if (err.code === 2) {
                        this.pesanJs = 'Lokasi tidak bisa ditentukan. Pastikan GPS di perangkat aktif dan Anda tidak sedang di dalam ruangan tertutup.';
                    } else if (err.code === 3) {
                        this.pesanJs = 'Waktu tunggu habis saat mencari sinyal GPS. Pindah ke area yang lebih terbuka lalu coba lagi.';
                    } else {
                        this.pesanJs = 'Gagal mengambil lokasi: ' + (err.message || 'penyebab tidak diketahui') + '.';
                    }
                },
                { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
            );
        }
    }"
    x-init="mulaiPantau()">

    {{-- Kepala kartu --}}
    <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-accent-soft text-brand-accent-text">
            <x-icon name="map-pin" class="h-6 w-6" />
        </span>
        <div class="min-w-0">
            <h2 class="text-base font-bold text-brand-ink">Absen Radius</h2>
            <p class="mt-0.5 text-sm text-brand-muted">
                Kehadiran dicatat kalau posisi Anda berada dalam
                <span class="font-semibold text-brand-ink">{{ $titik['radius'] }} meter</span>
                dari titik sekolah.
            </p>
        </div>
    </div>

    {{-- Isi kartu: tiga kemungkinan keadaan --}}
    <div class="mt-5">

        @if (! $pegawai)
            <div class="rounded-xl border border-brand-border bg-brand-surface-muted px-4 py-3 text-sm text-brand-muted">
                Akun Anda belum ditautkan ke data pegawai, jadi kehadiran belum bisa dicatat.
                Hubungi admin sekolah.
            </div>

        @elseif ($absensiHariIni)
            {{-- Sudah ada catatan hari ini — dari tombol ini, tombol absen
                 mandiri, atau scan di gerbang. Semuanya menulis ke tabel yang
                 sama, dan database mengunci satu baris per pegawai per hari. --}}
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand-accent bg-brand-accent-soft px-4 py-3.5">
                <div class="flex items-center gap-2.5 text-brand-accent-text">
                    <x-icon name="shield-check" class="h-5 w-5 shrink-0" />
                    <p class="text-sm font-semibold">
                        Kehadiran hari ini sudah tercatat
                        @if ($absensiHariIni->jam_masuk)
                            <span class="font-normal">&middot; pukul {{ $absensiHariIni->jam_masuk->format('H:i') }}</span>
                        @endif
                    </p>
                </div>
                <span class="rounded-full bg-brand-surface px-3 py-1 text-xs font-semibold text-brand-accent-text">
                    {{ $absensiHariIni->status->shortLabel() }}
                </span>
            </div>

        @else
            {{-- ---- Peta & pelacak posisi ------------------------------
                 Peta ini MEMBANTU, bukan menentukan. Kalau ia gagal dimuat
                 (internet sekolah mati, CDN diblokir), kotaknya disembunyikan
                 dan tombol absen tetap bisa ditekan — server yang memutuskan
                 diterima atau tidak. Menjadikan peta sebagai syarat absen
                 berarti satu gangguan koneksi melumpuhkan absensi sekolah. --}}
            <div wire:ignore class="mb-4">
                <div id="peta-absen"
                    class="z-0 h-64 w-full overflow-hidden rounded-xl border border-brand-border bg-brand-surface-muted"></div>
            </div>

            <p class="mb-4 flex items-start gap-2 text-sm" x-bind:class="statusWarna">
                <x-icon name="map-pin" class="mt-0.5 h-4 w-4 shrink-0" />
                <span x-text="statusTeks">Mencari lokasi Anda…</span>
            </p>

            <button type="button"
                x-on:click="mulai()"
                x-bind:disabled="terkunci"
                x-bind:class="terkunci ? 'cursor-not-allowed opacity-50' : ''"
                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-accent px-5 py-3.5 text-sm font-semibold text-white shadow-soft transition-colors hover:bg-brand-accent-dark disabled:cursor-not-allowed sm:w-auto">

                <span x-show="! memuat" class="inline-flex items-center gap-2">
                    <x-icon name="map-pin" class="h-5 w-5" />
                    Absen Hadir Sekarang
                </span>

                <span x-show="memuat" style="display: none" class="inline-flex items-center gap-2">
                    <svg class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none">
                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                    </svg>
                    Mencari lokasi Anda…
                </span>
            </button>

            <p class="mt-2.5 text-xs text-brand-muted">
                Pastikan GPS/Lokasi HP Anda aktif. Posisi hanya dibaca selama halaman ini terbuka
                dan tidak disimpan ke mana pun kecuali saat Anda menekan tombol absen.
            </p>
        @endif

    </div>

    {{-- Pesan dari sisi JavaScript (izin ditolak, GPS mati, halaman bukan HTTPS). --}}
    <div x-show="pesanJs" style="display: none"
        class="mt-4 flex items-start gap-2.5 rounded-xl border border-amber-300 bg-amber-500/10 px-4 py-3 text-sm text-amber-800 dark:text-amber-400">
        <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
        <p x-text="pesanJs"></p>
    </div>

    {{-- Pesan dari sisi server (di luar radius, berhasil, gagal simpan). --}}
    @if ($notif)
        @php
            $gaya = match ($notif['tipe']) {
                'ok' => ['kotak' => 'border-emerald-300 bg-emerald-500/10 text-emerald-800 dark:text-emerald-400', 'ikon' => 'check-circle'],
                'warn' => ['kotak' => 'border-amber-300 bg-amber-500/10 text-amber-800 dark:text-amber-400', 'ikon' => 'clock'],
                default => ['kotak' => 'border-red-300 bg-red-500/10 text-red-800 dark:text-red-400', 'ikon' => 'x-circle'],
            };
        @endphp

        <div class="mt-4 flex items-start gap-2.5 rounded-xl border px-4 py-3 {{ $gaya['kotak'] }}">
            <x-icon name="{{ $gaya['ikon'] }}" class="mt-0.5 h-5 w-5 shrink-0" />
            <div class="min-w-0">
                <p class="text-sm font-bold">{{ $notif['judul'] }}</p>
                <p class="mt-0.5 text-sm">{{ $notif['pesan'] }}</p>
            </div>
        </div>
    @endif

    {{-- ---- Peta Leaflet (opsional) ---------------------------------------
         Pola yang sama dengan peta di Pengaturan Sistem: menunggu Livewire
         siap, dijaga penanda supaya tidak dipasang dua kali, dan SELALU
         gagal dengan tenang — kotak petanya disembunyikan, sisa halaman
         tetap berfungsi.

         Posisi pegawai diterima lewat event 'simagas-posisi' yang dikirim
         Alpine, bukan dengan memanggil watchPosition kedua kalinya: dua
         pelacak untuk satu layar berarti dua kali konsumsi baterai dan dua
         sumber angka jarak yang bisa berbeda. --}}
    <script>
        (function () {
            var mulai = function () {
                var wadah = document.getElementById('peta-absen');
                if (!wadah || wadah.dataset.petaSiap) return;
                wadah.dataset.petaSiap = '1';

                var latSekolah = {{ $titik['lat'] }};
                var lngSekolah = {{ $titik['lng'] }};
                var radius = {{ $titik['radius'] }};

                window.muatLeaflet().then(function (L) {
                    var peta = L.map(wadah, { zoomControl: false }).setView([latSekolah, lngSekolah], 17);

                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap',
                    }).addTo(peta);

                    L.circle([latSekolah, lngSekolah], {
                        radius: radius,
                        color: '#0d9488',
                        fillColor: '#0d9488',
                        fillOpacity: 0.15,
                        weight: 2,
                    }).addTo(peta);

                    L.circleMarker([latSekolah, lngSekolah], {
                        radius: 6, color: '#0d9488', fillColor: '#0d9488', fillOpacity: 1,
                    }).addTo(peta).bindTooltip('Titik sekolah');

                    // Penanda posisi pegawai dibuat MERAH supaya beda jelas
                    // dari titik sekolah yang teal.
                    var penandaSaya = null;

                    window.addEventListener('simagas-posisi', function (e) {
                        var titik = [e.detail.lat, e.detail.lng];

                        if (!penandaSaya) {
                            penandaSaya = L.circleMarker(titik, {
                                radius: 7, color: '#ffffff', weight: 2,
                                fillColor: '#f04438', fillOpacity: 1,
                            }).addTo(peta).bindTooltip('Posisi Anda');

                            peta.fitBounds(L.latLngBounds([titik, [latSekolah, lngSekolah]]).pad(0.4));
                        } else {
                            penandaSaya.setLatLng(titik);
                        }
                    });

                    setTimeout(function () { peta.invalidateSize(); }, 200);
                }).catch(function () {
                    wadah.classList.add('hidden');
                });
            };

            if (window.Livewire) {
                mulai();
            } else {
                document.addEventListener('livewire:initialized', mulai, { once: true });
            }
        })();
    </script>

</div>
