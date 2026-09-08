{{-- Skrip kartu QR — dipakai bersama oleh halaman Kartu QR Siswa dan Pegawai.

     Dijadikan satu partial supaya kedua halaman itu tidak menyimpan salinan
     kode yang sama. Sebelumnya keduanya punya blok skrip sendiri, dan setiap
     perbaikan harus dikerjakan dua kali — persis situasi yang membuat satu
     halaman diam-diam tertinggal dari yang lain.

     Kontrak dengan halaman pemanggil:
       - setiap wadah QR  : <div class="qr-holder" data-kode="NIS/NIP"></div>
       - setiap tombol    : class "tombol-unduh-qr", data-target="id wadah",
                            data-nama="nama orangnya" --}}
<script>
    (function () {
        // QR digambar ke canvas beresolusi tinggi lalu diperkecil lewat CSS.
        // Dua alasannya:
        //   1. canvas.toDataURL() langsung memberi berkas PNG untuk tombol
        //      Unduh — tanpa perlu html2canvas sama sekali, jadi fitur ini
        //      tetap jalan walau koneksi internet sekolah mati.
        //   2. digambar pada ~1024 px lalu ditampilkan 120 px; hasil cetak
        //      maupun berkas unduhannya tetap tajam, tidak pecah.
        var SISI = 1024;   // sisi kanvas dalam piksel
        var TEPI = 4;      // lebar zona sunyi, dalam modul (standar QR)

        document.querySelectorAll('.qr-holder').forEach(function (el) {
            var kode = el.dataset.kode;
            if (! kode) return;

            if (typeof qrcode === 'undefined') {
                el.textContent = 'QR gagal dimuat — periksa koneksi internet.';
                el.className = 'shrink-0 text-xs text-rose-600';
                return;
            }

            var qr = qrcode(0, 'M');
            qr.addData(kode);
            qr.make();

            var modul = qr.getModuleCount();
            var total = modul + TEPI * 2;
            var sel = Math.floor(SISI / total);
            var sisi = sel * total;

            var kanvas = document.createElement('canvas');
            kanvas.width = sisi;
            kanvas.height = sisi;
            kanvas.className = 'block h-[120px] w-[120px] print:h-[110px] print:w-[110px]';

            var ctx = kanvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, sisi, sisi);
            ctx.fillStyle = '#0f172a';

            for (var baris = 0; baris < modul; baris++) {
                for (var kolom = 0; kolom < modul; kolom++) {
                    if (qr.isDark(baris, kolom)) {
                        ctx.fillRect((kolom + TEPI) * sel, (baris + TEPI) * sel, sel, sel);
                    }
                }
            }

            el.replaceChildren(kanvas);
        });

        // Tombol unduh QR per orang.
        document.querySelectorAll('.tombol-unduh-qr').forEach(function (tombol) {
            tombol.addEventListener('click', function () {
                var wadah = document.getElementById(tombol.dataset.target);
                var kanvas = wadah && wadah.querySelector('canvas');

                if (! kanvas) return;

                var bersih = (tombol.dataset.nama || 'QR')
                    .replace(/[^a-zA-Z0-9]+/g, '_')
                    .replace(/^_+|_+$/g, '');

                var tautan = document.createElement('a');
                tautan.style.display = 'none';
                tautan.download = 'QR-' + bersih + '.png';
                tautan.href = kanvas.toDataURL('image/png');
                document.body.appendChild(tautan);
                tautan.click();
                document.body.removeChild(tautan);
            });
        });
    })();
</script>
