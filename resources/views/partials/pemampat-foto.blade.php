{{--
    Pemampat foto DI BROWSER — memperkecil foto SEBELUM diunggah ke server.

    ============ KENAPA DI BROWSER, PADAHAL SERVER JUGA MEMAMPATKAN ============
    Server sudah memampatkan foto bukti (App\Services\PemampatFoto, 1280 px,
    mutu 70). Tapi itu terjadi SESUDAH berkas aslinya terkirim utuh:
      - foto HP 4–12 MB tetap menempuh jaringan seluler guru yang lambat,
        dan unggahan sebesar itu yang paling sering putus di tengah jalan;
      - berkas aslinya sempat mendarat di folder livewire-tmp di cPanel;
      - foto di atas 4 MB ditolak validasi sebelum sempat dipampatkan —
        guru harus memotret ulang dengan resolusi lebih rendah.
    Dengan pemampatan di browser, yang terkirim hanya ±150–400 KB.

    Pemampatan server TETAP dibiarkan sebagai jaring pengaman untuk browser
    lama yang tidak mendukung kanvas/toBlob — di sana berkas asli dikirim
    seperti sebelumnya, dan tidak ada yang rusak.
    ============================================================================

    Angkanya SAMA dengan PemampatFoto (sisi terpanjang 1280 px, mutu 70%)
    supaya hasil akhirnya seragam dari jalur mana pun fotonya datang.

    Dimuat lewat @push('scripts') oleh halaman pemakainya, BUKAN ditulis di
    dalam view komponen Livewire — tag <script> di dalam view Livewire bisa
    memicu MultipleRootElementsDetectedException (lihat catatan di
    jurnal-absen-kelas.blade.php).
--}}
<script>
    (function () {
        if (window.SimagasPemampat) {
            return;
        }

        var MAKS_SISI = 1280;
        var MUTU = 0.7;

        // Orientasi EXIF: foto HP sering tersimpan "miring" dengan catatan
        // putar di EXIF. createImageBitmap dengan imageOrientation
        // 'from-image' menegakkannya; <img> di browser modern juga sudah
        // menegakkannya otomatis. Tanpa ini foto bukti tersimpan miring.
        function muatViaImg(berkas) {
            return new Promise(function (berhasil, gagal) {
                var alamat = URL.createObjectURL(berkas);
                var gambar = new Image();
                gambar.onload = function () { URL.revokeObjectURL(alamat); berhasil(gambar); };
                gambar.onerror = function () { URL.revokeObjectURL(alamat); gagal(new Error('Gambar tidak bisa dibaca')); };
                gambar.src = alamat;
            });
        }

        function muatGambar(berkas) {
            if (typeof window.createImageBitmap === 'function') {
                try {
                    return createImageBitmap(berkas, { imageOrientation: 'from-image' })
                        .catch(function () { return muatViaImg(berkas); });
                } catch (e) {
                    return muatViaImg(berkas);
                }
            }

            return muatViaImg(berkas);
        }

        /**
         * Mengembalikan Promise berisi berkas hasil pampatan (JPEG), atau
         * berkas ASLI kalau pemampatan tidak mungkin / tidak membuatnya
         * lebih kecil. Tidak pernah menolak (reject): kegagalan apa pun
         * jatuh ke berkas asli, dan server tetap memampatkannya.
         */
        function pampatkan(berkas, opsi) {
            opsi = opsi || {};
            var maksSisi = opsi.maksSisi || MAKS_SISI;
            var mutu = opsi.mutu || MUTU;

            if (!berkas || !/^image\//.test(berkas.type || '')) {
                return Promise.resolve(berkas);
            }

            return muatGambar(berkas).then(function (gambar) {
                var lebar = gambar.width;
                var tinggi = gambar.height;
                var skala = Math.min(1, maksSisi / Math.max(lebar, tinggi));
                var lebarBaru = Math.max(1, Math.round(lebar * skala));
                var tinggiBaru = Math.max(1, Math.round(tinggi * skala));

                var kanvas = document.createElement('canvas');
                kanvas.width = lebarBaru;
                kanvas.height = tinggiBaru;

                var ctx = kanvas.getContext('2d');

                // Latar putih: PNG/WEBP transparan yang diubah ke JPEG
                // tanpa latar akan berlatar HITAM.
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, lebarBaru, tinggiBaru);
                ctx.drawImage(gambar, 0, 0, lebarBaru, tinggiBaru);

                if (typeof gambar.close === 'function') {
                    gambar.close();
                }

                return new Promise(function (berhasil) {
                    kanvas.toBlob(function (blob) { berhasil(blob); }, 'image/jpeg', mutu);
                });
            }).then(function (blob) {
                // Foto yang sudah kecil bisa jadi LEBIH BESAR setelah
                // dikodekan ulang — pakai aslinya saja.
                if (!blob || blob.size >= berkas.size) {
                    return berkas;
                }

                var nama = (berkas.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';

                try {
                    return new File([blob], nama, { type: 'image/jpeg', lastModified: Date.now() });
                } catch (e) {
                    // Browser tanpa konstruktor File: Blob bernama tetap
                    // diterima unggahan Livewire.
                    blob.name = nama;
                    return blob;
                }
            }).catch(function () {
                return berkas;
            });
        }

        function ukuran(bita) {
            if (bita >= 1048576) {
                return (bita / 1048576).toFixed(1).replace('.', ',') + ' MB';
            }

            return Math.max(1, Math.round(bita / 1024)) + ' KB';
        }

        window.SimagasPemampat = { pampatkan: pampatkan, ukuran: ukuran, MAKS_SISI: MAKS_SISI, MUTU: MUTU };
    })();
</script>
