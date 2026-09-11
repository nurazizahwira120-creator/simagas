{{-- ====================================================================
     ANGKA YANG MENGHITUNG NAIK

     Dipakai komponen resources/views/components/angka-naik.blade.php pada
     kartu statistik dashboard.

     ============ ANGKANYA SUDAH BENAR SEBELUM SKRIP INI JALAN ============
     Ini bagian terpenting dari berkas ini. Angka yang sesungguhnya sudah
     ditulis Blade ke dalam HTML dari server, lengkap dengan pemisah ribuan
     dan satuannya. Skrip ini hanya MEMINJAMNYA sebentar untuk dihitung
     naik, lalu mengembalikannya persis seperti semula.

     Urutan itu dipilih dengan sengaja. Cara yang lebih lazim — menaruh 0 di
     HTML lalu membiarkan JavaScript mengisinya — berarti satu kegagalan
     kecil (skrip gagal diunduh, browser lawas menolak sebuah sintaks)
     membuat kepala sekolah membuka dashboard dan membaca "0 siswa hadir".
     Angka yang salah jauh lebih berbahaya daripada angka yang tidak
     beranimasi.

     Dengan urutan di sini, kegagalan apa pun menghasilkan angka yang benar
     tanpa animasi. Itu batas terburuk yang bisa terjadi.
     ====================================================================

     ============ KENAPA SKRIP KLASIK DI <head> ============
     Sama seperti window.simagasTema dan partials/scan-kamera: app.js dimuat
     sebagai module yang selalu ditunda, sehingga ia berjalan SESUDAH
     atribut x-init milik Alpine dieksekusi. Fungsi ini harus sudah ada
     sebelum itu.
     ====================================================================== --}}
<script>
    window.hitungNaik = (function () {
        'use strict';

        /* Durasi hitungan. Cukup lama untuk terbaca sebagai gerakan, cukup
           pendek agar pembaca yang ingin tahu angkanya tidak perlu menunggu. */
        var DURASI = 750;

        /* easeOutCubic: cepat di awal lalu melambat mendekati angka akhir.
           Kurva ini dipilih karena yang ingin ditonjolkan justru berhentinya
           — angka akhir itulah informasinya, bukan perjalanannya. */
        function pelan(t) {
            return 1 - Math.pow(1 - t, 3);
        }

        return function (el, nilai, desimal) {
            if (!el) {
                return;
            }

            // Teks asli dari server disimpan lebih dulu. Inilah yang akan
            // dikembalikan di akhir, sehingga format ribuan, tanda persen,
            // dan satuan apa pun tidak perlu ditiru ulang di JavaScript —
            // dan tidak mungkin berbeda dengan yang dihitung PHP.
            var teksAsli = el.textContent;

            var akhir = Number(nilai);

            if (!isFinite(akhir)) {
                return;
            }

            // Pengguna yang meminta hemat gerak tidak diberi animasi sama
            // sekali; angkanya sudah benar di layar sejak awal.
            try {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }
            } catch (e) { /* matchMedia tidak ada — lanjutkan dengan animasi */ }

            if (typeof requestAnimationFrame !== 'function') {
                return;
            }

            var d = Math.max(0, Math.min(3, Number(desimal) || 0));

            // Satuan (mis. tanda persen) ikut digambar pada setiap angka
            // antara. Tanpa ini, tanda persennya hilang selama hitungan
            // berjalan lalu muncul lagi di akhir — kedipan kecil yang
            // justru lebih terlihat daripada animasinya sendiri.
            var akhiran = el.dataset ? (el.dataset.akhiran || '') : '';

            var format = function (n) {
                try {
                    return n.toLocaleString('id-ID', {
                        minimumFractionDigits: d,
                        maximumFractionDigits: d,
                    });
                } catch (e) {
                    return n.toFixed(d);
                }
            };

            var mulai = null;

            function langkah(waktu) {
                if (mulai === null) {
                    mulai = waktu;
                }

                var maju = Math.min(1, (waktu - mulai) / DURASI);

                if (maju >= 1) {
                    // Teks aslinya dikembalikan apa adanya — BUKAN hasil
                    // format JavaScript. Keduanya biasanya sama, tapi kalau
                    // suatu saat berbeda (satuan, pembulatan, tanda), yang
                    // benar adalah versi dari server.
                    el.textContent = teksAsli;

                    return;
                }

                el.textContent = format(akhir * pelan(maju)) + akhiran;

                requestAnimationFrame(langkah);
            }

            requestAnimationFrame(langkah);
        };
    })();
</script>
