{{-- ====================================================================
     PERKAKAS BERSAMA UNTUK SEMUA LAYAR SCAN KAMERA

     Dipakai tiga halaman yang sama-sama memindai kartu/stiker:
       - Scanner Piket (gerbang)            -> resources/views/piket/scanner.blade.php
       - Scanner Siswa (Livewire)           -> livewire/scanner-kamera-siswa
       - Scan QR Ruangan oleh guru          -> livewire/guru/absen-mengajar-qr

     ============ KENAPA SKRIP KLASIK DI <head>, BUKAN app.js ============
     Alasannya sama persis dengan window.simagasTema dan window.muatLeaflet
     yang sudah ada di head-assets: app.js dimuat @vite sebagai
     <script type="module">, dan module SELALU ditunda sampai seluruh HTML
     selesai diurai. Artinya ia berjalan SESUDAH blok skrip di dalam badan
     halaman. Scanner Piket memanggil perkakas ini dari skrip biasa di akhir
     body — kalau definisinya ada di app.js, pemanggilnya mendapat undefined
     dan yang terjadi adalah kegagalan paling menyesatkan: tidak ada error,
     hanya scanner yang diam tanpa bunyi.
     ====================================================================

     ============ KENAPA UMPAN BALIKNYA BUKAN SEKADAR HIASAN ============
     Petugas piket berdiri di gerbang pukul 06.30 memegang HP satu tangan,
     dan matanya ada pada KARTU SISWA — bukan pada layar. Tulisan hasil scan
     sebagus apa pun tidak akan terbaca dalam keadaan itu.

     Karena itu setiap hasil diberi NADA dan POLA GETAR yang berbeda, supaya
     bisa dibedakan tanpa melihat layar sama sekali:

       tercatat   -> satu nada tinggi, getar pendek
       sudah ada  -> dua nada sedang, getar putus-putus
       gagal      -> satu nada rendah panjang, getar panjang

     Kilatan warna satu layar penuh dipakai sebagai cadangan untuk tempat
     yang terlalu bising — bukan sebagai penanda utama.
     ==================================================================== --}}
<script>
    window.umpanBalikScan = (function () {
        'use strict';

        /*
         | Satu nada = [frekuensi Hz, durasi detik, volume 0-1, bentuk gelombang].
         |
         | Nada gagal sengaja RENDAH dan memakai gelombang kotak (square):
         | telinga menangkap nada rendah sebagai "ada yang salah" tanpa perlu
         | belajar artinya lebih dulu, dan itu berlaku juga di tempat ramai.
         */
        var SUARA = {
            mulai: [[1320, 0.035, 0.05, 'sine']],
            ok: [[880, 0.11, 0.16, 'sine']],
            warn: [[620, 0.08, 0.15, 'sine'], [620, 0.08, 0.15, 'sine']],
            error: [[200, 0.32, 0.18, 'square']],
        };

        /* Pola getar dalam milidetik. null = tidak bergetar sama sekali. */
        var GETAR = {
            mulai: null,
            ok: 40,
            warn: [40, 70, 40],
            error: 220,
        };

        /*
         | SATU AudioContext untuk seluruh halaman, dibuat saat pertama kali
         | dibutuhkan.
         |
         | Versi sebelumnya membuat AudioContext BARU setiap kali berbunyi lalu
         | menutupnya di onended. Itu tampak rapi tapi menyimpan masalah: Safari
         | iOS membatasi jumlah AudioContext yang boleh dibuat satu halaman, dan
         | penutupannya tidak selalu langsung mengembalikan jatahnya. Pada sesi
         | scan panjang di gerbang — ratusan kartu dalam setengah jam — bunyinya
         | berhenti di tengah jalan tanpa error apa pun.
         */
        var ctx = null;

        function konteks() {
            if (ctx) {
                return ctx;
            }

            var Ctx = window.AudioContext || window.webkitAudioContext;

            if (!Ctx) {
                return null;
            }

            try {
                ctx = new Ctx();
            } catch (e) {
                return null;
            }

            return ctx;
        }

        function bunyikan(pola) {
            var c = konteks();

            if (!c || !pola) {
                return;
            }

            // Browser mobile menidurkan AudioContext yang dibuat di luar
            // sentuhan pengguna. resume() dipanggil setiap kali, bukan sekali,
            // karena konteksnya juga ditidurkan lagi saat tab dipindah.
            if (c.state === 'suspended') {
                c.resume().catch(function () { /* tetap lanjut tanpa suara */ });
            }

            var mulai = c.currentTime;

            for (var i = 0; i < pola.length; i++) {
                var nada = pola[i];

                try {
                    var osc = c.createOscillator();
                    var gain = c.createGain();

                    osc.type = nada[3];
                    osc.frequency.value = nada[0];

                    /*
                     | Volumenya DILUNCURKAN turun, bukan dipotong mendadak.
                     | Menghentikan oscillator selagi volumenya masih penuh
                     | menghasilkan "klik" keras di speaker HP — kecil tapi
                     | terdengar jelek kalau berbunyi ratusan kali sehari.
                     | Nilai tujuannya 0.0001, bukan 0: exponential ramp tidak
                     | boleh menuju nol.
                     */
                    gain.gain.setValueAtTime(nada[2], mulai);
                    gain.gain.exponentialRampToValueAtTime(0.0001, mulai + nada[1]);

                    osc.connect(gain);
                    gain.connect(c.destination);

                    osc.start(mulai);
                    osc.stop(mulai + nada[1]);
                } catch (e) {
                    return;
                }

                mulai += nada[1] + 0.06;
            }
        }

        function getarkan(pola) {
            // navigator.vibrate tidak ada sama sekali di iPhone. Pemeriksaannya
            // wajib: memanggilnya di sana melempar TypeError yang akan
            // menghentikan sisa fungsi ini, termasuk kilatan layarnya.
            if (!pola || typeof navigator.vibrate !== 'function') {
                return;
            }

            try {
                navigator.vibrate(pola);
            } catch (e) { /* perangkat menolak bergetar — bukan masalah */ }
        }

        var lapisanKilat = null;
        var waktuKilat = null;

        function kilatkan(tipe) {
            if (!document.body) {
                return;
            }

            if (!lapisanKilat) {
                lapisanKilat = document.createElement('div');
                lapisanKilat.className = 'kilat-scan';
                // aria-hidden: pembaca layar tidak perlu mengumumkan lapisan
                // warna ini. Isi hasilnya sudah diumumkan kotak notifikasi.
                lapisanKilat.setAttribute('aria-hidden', 'true');
                document.body.appendChild(lapisanKilat);
            }

            lapisanKilat.className = 'kilat-scan';

            // Membaca offsetWidth memaksa browser menghitung ulang tata letak
            // SEBELUM kelas berikutnya dipasang. Tanpa ini, dua hasil sejenis
            // berturut-turut tidak memicu animasi kedua — browser menganggap
            // kelasnya tidak pernah berubah.
            void lapisanKilat.offsetWidth;

            lapisanKilat.classList.add('kilat-' + tipe);

            clearTimeout(waktuKilat);
            waktuKilat = setTimeout(function () {
                if (lapisanKilat) {
                    lapisanKilat.className = 'kilat-scan';
                }
            }, 500);
        }

        function jalankan(tipe) {
            var jenis = SUARA[tipe] ? tipe : 'ok';

            bunyikan(SUARA[jenis]);
            getarkan(GETAR[jenis]);

            // 'mulai' hanya penanda "kode terbaca, sedang dikirim" — memberinya
            // kilatan layar akan membuat setiap scan berkedip dua kali.
            if (jenis !== 'mulai') {
                kilatkan(jenis);
            }
        }

        /*
         | Dipanggil dari tombol "Nyalakan Kamera".
         |
         | AudioContext yang dibuat di luar sentuhan pengguna lahir dalam
         | keadaan 'suspended' di Chrome Android maupun Safari. Membuatnya
         | tepat saat tombol ditekan — satu-satunya sentuhan yang pasti ada
         | sebelum scan pertama — membuat bunyi pertama langsung terdengar,
         | bukan hilang diam-diam.
         */
        jalankan.siapkan = function () {
            var c = konteks();

            if (c && c.state === 'suspended') {
                c.resume().catch(function () {});
            }
        };

        /*
         | Jaring pengaman: sentuhan PERTAMA di mana pun pada halaman ikut
         | membangunkan AudioContext.
         |
         | Perlu karena Scanner Piket menyalakan kameranya sendiri saat halaman
         | dibuka — tidak lewat tombol. Kalau izin kameranya sudah pernah
         | diberikan, seluruh sesi scan bisa berjalan tanpa satu pun sentuhan
         | tombol, dan tanpa baris ini nada scan pertama hilang diam-diam.
         |
         | { once: true } membuat ketiganya melepas dirinya sendiri setelah
         | terpakai sekali.
         */
        ['pointerdown', 'touchstart', 'keydown'].forEach(function (peristiwa) {
            document.addEventListener(peristiwa, jalankan.siapkan, { once: true, passive: true });
        });

        return jalankan;
    })();

    /*
     |--------------------------------------------------------------------
     | Ukuran kotak bidik html5-qrcode
     |--------------------------------------------------------------------
     | Dipakai sebagai nilai opsi `qrbox`, dan bentuknya FUNGSI, bukan angka
     | tetap seperti { width: 240, height: 240 } yang dipakai sebelumnya.
     |
     | Alasannya ada di dalam library: kalau kotak yang diminta lebih besar
     | daripada video kameranya, html5-qrcode TIDAK MENGGAMBAR kotak itu sama
     | sekali (lihat possiblyInsertShadingElement). Di HP dengan video pratinjau
     | sempit, permintaan 240px membuat bingkai bidiknya lenyap tanpa pesan
     | apa pun — dan bersamanya hilang pula seluruh petunjuk arah untuk
     | pengguna.
     |
     | Kotaknya dibuat MELEBAR (4:3), bukan bujur sangkar: kartu siswa memakai
     | barcode satu dimensi yang panjang mendatar, sementara QR ruangan tetap
     | muat nyaman di dalamnya.
     */
    window.kotakBidikScan = function (lebarVideo, tinggiVideo) {
        var sisi = Math.floor(Math.min(lebarVideo, tinggiVideo) * 0.78);

        return {
            width: sisi,
            height: Math.floor(sisi * 0.72),
        };
    };
</script>
