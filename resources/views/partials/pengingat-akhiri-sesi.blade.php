{{--
    ALARM PENGINGAT "AKHIRI SESI" — di layar yang SEDANG dibuka guru.

    Aturan waktunya (lihat App\Services\PengingatAkhiriSesi):
      jam selesai KBM + 5 menit  -> bunyi + getar + kartu merah dengan hitung mundur
      jam selesai KBM + 15 menit -> batas; tombol "Akhiri Sesi" terkunci

    Partial ini pasangan dari perintah terjadwal simagas:pengingat-akhiri-sesi:
      perintah -> push Firebase: berbunyi di layar kunci walau aplikasi DITUTUP
      partial  -> alarm di halaman: berbunyi selama aplikasi DIBUKA, berulang
                  tiap menit sampai sesinya diakhiri, disenyapkan, atau lewat batas

    Ditampilkan di SEMUA halaman (bukan hanya Jurnal), karena guru yang lupa
    biasanya justru sedang membuka halaman lain.

    Hanya dihitung untuk peran yang punya halaman Jurnal & Absen Kelas, dan
    hanya kalau ada sesi terbuka hari ini — selain itu partial ini tidak
    mengeluarkan apa pun, termasuk skripnya.

    ============ BATASAN BROWSER YANG PERLU DIKETAHUI ============
    Browser MENOLAK bunyi dan getar sebelum pengguna pernah mengetuk halaman.
    Guru pasti sudah mengetuk (scan QR, unggah foto), jadi biasanya aman —
    tapi halaman yang baru dibuka lalu dibiarkan tanpa disentuh bisa diam.
    Karena itu kartunya SELALU tampil (visual tidak butuh izin), bunyi
    dicoba ulang setiap menit, dan push Firebase tetap menjadi jalur utama
    untuk HP yang layarnya mati.
    ===============================================================
--}}
@php
    $pengingatSesi = [];
    $penggunaPengingat = auth()->user();
    $prefixPengingat = $penggunaPengingat?->role?->routePrefix();

    if ($prefixPengingat && Route::has($prefixPengingat . '.jurnal-kelas') && $penggunaPengingat->pegawai) {
        try {
            $pengingatSesi = app(\App\Services\PengingatAkhiriSesi::class)
                ->sesiTerbuka($penggunaPengingat->pegawai->id)
                ->map(fn (array $b) => [
                    'id' => $b['sesi']->id,
                    'judul' => $b['jadwal']->mata_pelajaran . ' — ' . ($b['jadwal']->kelas?->nama_kelas ?? '-'),
                    'selesai' => $b['selesai']->format('H:i'),
                    'batasJam' => $b['batas']->format('H:i'),
                    'adaBukti' => $b['sesi']->adaBukti(),
                    // Milidetik epoch. Dihitung di SERVER, lalu dikoreksi
                    // terhadap selisih jam HP di bawah: jam HP guru bisa
                    // meleset beberapa menit, dan untuk jendela 10 menit itu
                    // sudah cukup untuk membunyikan alarm di waktu yang salah.
                    'pengingat' => $b['pengingat']->getTimestampMs(),
                    'batas' => $b['batas']->getTimestampMs(),
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            // Alarm adalah pelengkap. Kegagalannya tidak boleh membuat
            // SELURUH halaman guru gagal dimuat.
            report($e);
            $pengingatSesi = [];
        }
    }
@endphp

@if ($pengingatSesi)
    <div id="pengingat-akhiri-sesi" hidden role="alert" aria-live="assertive"
        class="fixed inset-x-3 bottom-3 z-[60] mx-auto max-w-xl rounded-2xl border border-error-200 bg-white p-4 shadow-theme-lg dark:border-error-500/30 dark:bg-gray-900 print:hidden">
        <div class="flex items-start gap-3">
            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-error-500/10 text-error-600 dark:text-error-400">
                <x-icon name="clock" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-gray-800 dark:text-gray-100" data-pengingat="judul">Sesi kelas belum diakhiri</p>
                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300" data-pengingat="teks"></p>
                <p class="mt-1 text-xs font-semibold text-error-600 dark:text-error-400" data-pengingat="sisa"></p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <a href="{{ route($prefixPengingat . '.jurnal-kelas') }}" data-pengingat="buka"
                        class="inline-flex items-center rounded-lg bg-error-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-error-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error-500">
                        Buka Jurnal &amp; Akhiri Sesi
                    </a>
                    <button type="button" data-pengingat="ke-tombol" hidden
                        class="inline-flex items-center rounded-lg bg-error-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-error-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error-500">
                        Ke tombol Akhiri Sesi
                    </button>
                    <button type="button" data-pengingat="senyap"
                        class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Senyapkan bunyi
                    </button>
                    <button type="button" data-pengingat="tutup" hidden
                        class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 transition-colors hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function () {
        'use strict';

        /*
         | Halaman yang berpindah lewat wire:navigate MENJALANKAN ULANG skrip
         | ini tanpa membuang yang lama. Tanpa penjagaan berikut, setiap
         | perpindahan halaman menambah satu set pewaktu lagi — dan alarmnya
         | berbunyi dua, tiga, empat kali bersamaan.
         */
        if (window.__simagasPengingat) {
            window.__simagasPengingat.hentikan();
        }

        var SESI = @json($pengingatSesi);
        // Selisih jam server dan jam HP (ms), lihat catatan di @php di atas.
        var KOREKSI = {{ now()->getTimestampMs() }} - Date.now();
        var ULANG_MS = 60 * 1000;
        var POLA_GETAR = [400, 150, 400, 150, 400];

        var kartu = document.getElementById('pengingat-akhiri-sesi');
        if (! kartu) { return; }

        var el = {
            teks: kartu.querySelector('[data-pengingat="teks"]'),
            sisa: kartu.querySelector('[data-pengingat="sisa"]'),
            buka: kartu.querySelector('[data-pengingat="buka"]'),
            senyap: kartu.querySelector('[data-pengingat="senyap"]'),
            tutup: kartu.querySelector('[data-pengingat="tutup"]'),
            keTombol: kartu.querySelector('[data-pengingat="ke-tombol"]'),
        };
        var main = document.querySelector('main');

        var pewaktu = [];
        var diakhiri = {};
        var ditutup = false;
        var terakhirCoba = {};
        var pernahBunyi = {};
        var pernahGetar = {};
        var audioCtx = null;

        function sekarang() { return Date.now() + KOREKSI; }

        function kunciSenyap(id) { return 'simagas-senyap-akhiri-' + id; }

        function disenyapkan(id) {
            try { return window.sessionStorage.getItem(kunciSenyap(id)) === '1'; } catch (e) { return false; }
        }

        /* ---------------- BUNYI (Web Audio, tanpa berkas) ----------------
         | Nada dibangkitkan langsung, bukan memutar berkas mp3: satu berkas
         | lagi yang bisa tertinggal saat deploy berarti alarm yang diam.
         | Tiga ketukan naik-turun dibedakan sengaja dari bunyi notifikasi
         | biasa, supaya terdengar sebagai PERINGATAN, bukan kabar. */
        function pastikanAudio() {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (! Ctx) { return null; }
            if (! audioCtx) {
                try { audioCtx = new Ctx(); } catch (e) { return null; }
            }
            return audioCtx;
        }

        // Pengguna sudah pernah mengetuk halaman ini? Tanpa itu browser
        // menolak bunyi DAN getar — dan Chrome menulis peringatan merah di
        // konsol setiap kali dicoba. Jadi tidak dicoba sama sekali.
        function sudahDiketuk() {
            return ! navigator.userActivation || navigator.userActivation.hasBeenActive;
        }

        function mainkan(ctx) {
            [0, 0.3, 0.6].forEach(function (mulai, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.type = 'square';
                osc.frequency.value = i === 1 ? 660 : 880;
                gain.gain.setValueAtTime(0.0001, ctx.currentTime + mulai);
                gain.gain.exponentialRampToValueAtTime(0.25, ctx.currentTime + mulai + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + mulai + 0.22);
                osc.connect(gain).connect(ctx.destination);
                osc.start(ctx.currentTime + mulai);
                osc.stop(ctx.currentTime + mulai + 0.25);
            });
        }

        /** @return {boolean} true bila bunyi (kemungkinan besar) terdengar. */
        function bunyikan() {
            var ctx = pastikanAudio();
            if (! ctx) { return false; }

            if (ctx.state === 'running') {
                mainkan(ctx);
                return true;
            }

            if (! sudahDiketuk()) { return false; }

            ctx.resume().then(function () { mainkan(ctx); }).catch(function () {});
            return true;
        }

        /** @return {boolean} true bila getar diterima perangkat. */
        function getarkan() {
            try {
                if (navigator.vibrate && sudahDiketuk()) {
                    return navigator.vibrate(POLA_GETAR) !== false;
                }
            } catch (e) { /* tidak didukung (mis. iPhone); abaikan. */ }
            return false;
        }

        // Ketukan pertama apa pun membuka kunci audio browser. Kalau saat itu
        // alarm sedang aktif tapi belum pernah berhasil berbunyi, bunyikan
        // sekarang juga — tidak menunggu putaran menit berikutnya.
        function bukaKunciAudio() {
            perbarui('paksa');
        }
        ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
            window.addEventListener(ev, bukaKunciAudio, { once: true, passive: true });
        });

        /* ---------------- LOGIKA TAMPILAN ---------------- */
        function format(ms) {
            var detik = Math.max(0, Math.ceil(ms / 1000));
            var m = Math.floor(detik / 60);
            var s = detik % 60;
            return m + ':' + (s < 10 ? '0' : '') + s;
        }

        // Sesi yang SEDANG di jendela pengingat, paling mendesak dulu.
        function aktif() {
            var t = sekarang();
            return SESI.filter(function (s) {
                return ! diakhiri[s.id] && t >= s.pengingat && t < s.batas;
            }).sort(function (a, b) { return a.batas - b.batas; });
        }

        function lewatBatas() {
            var t = sekarang();
            return SESI.filter(function (s) {
                return ! diakhiri[s.id] && t >= s.batas && t < s.batas + 10 * 60 * 1000;
            });
        }

        /*
         | Kartu ini mengambang di bawah layar. Tanpa ganjal, di HP ia
         | MENUTUPI bagian bawah halaman — termasuk tombol "Akhiri Sesi
         | Kelas" yang justru ingin ditekan guru (ketemu saat uji coba).
         | Selama kartu tampil, <main> diberi ruang kosong setinggi kartu
         | supaya semua isi halaman tetap bisa digulir ke atasnya.
         */
        function aturGanjal() {
            if (! main) { return; }
            main.style.paddingBottom = kartu.hidden ? '' : (kartu.offsetHeight + 24) + 'px';
        }

        function tampilkan(ya) {
            if (kartu.hidden === ! ya) { return; }
            kartu.hidden = ! ya;
            aturGanjal();
        }

        function perbarui(bolehBunyi) {
            var daftar = aktif();
            var t = sekarang();

            if (daftar.length) {
                var s = daftar[0];
                ditutup = false;
                tampilkan(true);
                el.tutup.hidden = true;
                el.buka.hidden = diJurnal;
                el.keTombol.hidden = ! diJurnal;
                el.senyap.hidden = disenyapkan(s.id);
                el.teks.textContent = s.judul + ' selesai pukul ' + s.selesai
                    + (s.adaBukti ? '. Tekan' : '. Unggah foto bukti lalu tekan')
                    + ' "Akhiri Sesi Kelas" sebelum pukul ' + s.batasJam + '.'
                    + (daftar.length > 1 ? ' (+' + (daftar.length - 1) + ' sesi lain)' : '');
                el.sisa.textContent = 'Sisa waktu ' + format(s.batas - t);

                // Bunyi + getar saat pertama kali masuk jendela, lalu diulang
                // setiap menit sampai sesinya diakhiri / disenyapkan.
                // 'paksa' = ketukan pertama: boleh menyela putaran menit kalau
                // alarm ini belum pernah berhasil berbunyi.
                if (bolehBunyi !== false && ! disenyapkan(s.id)) {
                    var giliran = ! terakhirCoba[s.id] || t - terakhirCoba[s.id] >= ULANG_MS;

                    if (giliran) {
                        terakhirCoba[s.id] = t;
                        if (bunyikan()) { pernahBunyi[s.id] = true; }
                        if (getarkan()) { pernahGetar[s.id] = true; }
                    } else if (bolehBunyi === 'paksa') {
                        // Hanya yang TADI gagal yang diulang saat ketukan pertama.
                        if (! pernahBunyi[s.id] && bunyikan()) { pernahBunyi[s.id] = true; }
                        if (! pernahGetar[s.id] && getarkan()) { pernahGetar[s.id] = true; }
                    }
                }

                return;
            }

            var lewat = lewatBatas();
            if (lewat.length && ! ditutup) {
                tampilkan(true);
                el.senyap.hidden = true;
                el.tutup.hidden = false;
                // Jurnal sudah terkunci; mengajak membukanya hanya menyesatkan.
                el.buka.hidden = true;
                el.keTombol.hidden = true;
                el.teks.textContent = lewat[0].judul + ': batas menekan "Akhiri Sesi" (pukul '
                    + lewat[0].batasJam + ') sudah lewat. Laporkan ke admin bila perlu dikoreksi.';
                el.sisa.textContent = 'Waktu habis';
                return;
            }

            tampilkan(false);

            // Semua sesi sudah diakhiri atau jauh lewat batas: tidak ada lagi
            // yang perlu diawasi, pewaktunya dihentikan.
            var selesaiSemua = SESI.every(function (x) {
                return diakhiri[x.id] || t >= x.batas + 10 * 60 * 1000;
            });
            if (selesaiSemua) { hentikan(); }
        }

        el.senyap.addEventListener('click', function () {
            aktif().forEach(function (s) {
                try { window.sessionStorage.setItem(kunciSenyap(s.id), '1'); } catch (e) {}
            });
            perbarui(false);
        });

        el.tutup.addEventListener('click', function () {
            ditutup = true;
            tampilkan(false);
        });

        // Di halaman Jurnal sendiri, "Buka Jurnal" diganti tombol yang
        // menggulir langsung ke tombol "Akhiri Sesi Kelas" (atau ke kotak
        // unggah foto bila tombolnya belum aktif).
        var diJurnal = false;
        try {
            diJurnal = new URL(el.buka.href).pathname === window.location.pathname;
        } catch (e) {}

        el.keTombol.addEventListener('click', function () {
            var sasaran = document.querySelector('[wire\\:click="akhiriSesi"]')
                || document.querySelector('[wire\\:click="unggahBukti"]');
            if (sasaran) {
                sasaran.scrollIntoView({ block: 'center' });
                if (typeof sasaran.focus === 'function') { sasaran.focus({ preventScroll: true }); }
            }
        });

        // Dikirim JurnalAbsenKelas::akhiriSesi() begitu sesi berhasil ditutup.
        function saatDiakhiri(e) {
            var id = e && e.detail ? Number(e.detail.id) : NaN;
            if (! isNaN(id)) { diakhiri[id] = true; }
            perbarui(false);
        }
        window.addEventListener('sesi-diakhiri', saatDiakhiri);

        // Detik demi detik untuk hitung mundur. Ringan: hanya menulis teks.
        pewaktu.push(window.setInterval(function () { perbarui(true); }, 1000));
        perbarui(true);

        function hentikan() {
            if (main) { main.style.paddingBottom = ''; }
            pewaktu.forEach(window.clearInterval);
            pewaktu = [];
            window.removeEventListener('sesi-diakhiri', saatDiakhiri);
            window.removeEventListener('pointerdown', bukaKunciAudio);
            window.removeEventListener('keydown', bukaKunciAudio);
            window.removeEventListener('touchstart', bukaKunciAudio);
        }

        // Pindah halaman lewat wire:navigate: halaman berikutnya mungkin tidak
        // memuat partial ini sama sekali (sesinya sudah diakhiri), jadi
        // pewaktu halaman ini harus berhenti sendiri.
        document.addEventListener('livewire:navigating', hentikan, { once: true });

        window.__simagasPengingat = {
            hentikan: hentikan,
            // Untuk pengujian dari konsol: window.__simagasPengingat.sesi
            sesi: SESI,
        };
    })();
    </script>
@endif
