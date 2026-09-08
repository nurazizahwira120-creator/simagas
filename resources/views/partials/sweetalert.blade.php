{{-- ====================================================================
     SweetAlert2 — pengganti alert() dan confirm() bawaan browser.
     Di-include dari partials/head-assets.blade.php, jadi berlaku di SEMUA
     halaman yang punya <head> sendiri (termasuk Login, Scanner Piket, dan
     halaman Kartu QR).

     ================== KENAPA DIBUNGKUS, BUKAN DIPAKAI LANGSUNG ==================
     Semua pemanggilan lewat window.simagasSwal, bukan Swal.fire() langsung
     di masing-masing halaman. Sebabnya:

       1. TEMANYA CUKUP DITULIS SEKALI. Warna tombol, radius, tombol
          "Batal" di kiri — semuanya di satu tempat. Kalau tiap halaman
          memanggil Swal.fire() sendiri, tiap halaman juga menyalin
          konfigurasi warnanya, dan cepat atau lambat ada satu yang lupa.

       2. ADA JALUR CADANGAN. Berkas SweetAlert2 diambil dari CDN. Internet
          sekolah bisa mati, CDN bisa diblokir, dan pengguna bisa menekan
          tombol sebelum berkasnya selesai diunduh. Kalau window.Swal belum
          ada, pembungkus ini JATUH KE confirm()/alert() bawaan browser —
          tampilannya jelek, tapi tombol "Hapus" tetap menanyakan konfirmasi
          alih-alih menghapus diam-diam. Konfirmasi yang hilang karena
          skrip gagal dimuat adalah kehilangan data, bukan cuma kosmetik.
     ============================================================================

     Cara memakainya ada tiga:

       a. Atribut data-konfirmasi pada <form> atau tombol biasa/Livewire —
          ini yang dipakai di seluruh aplikasi (lihat catatan di bawah).
       b. Dari komponen Livewire: dispatch('swal:modal', ...) untuk pesan,
          dispatch('swal:confirm', ...) untuk konfirmasi.
          Pakai trait App\Livewire\Concerns\BisaSweetAlert supaya nama
          parameternya tidak perlu diingat.
       c. Dari JavaScript halaman: window.simagasSwal.konfirmasi({...})
          yang mengembalikan Promise berisi true/false.
     ==================================================================== --}}

{{-- defer: berkasnya tidak menahan penggambaran halaman. Pembungkus di
     bawah membaca window.Swal saat DIPANGGIL, bukan saat didefinisikan,
     jadi urutan pemuatan tidak jadi masalah. --}}
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11" defer></script>

<script>
(function () {
    'use strict';

    /*
     | Tema global.
     |
     | confirmButtonColor MEMAKAI TOSCA #0d9488, bukan #3C50E0 (indigo bawaan
     | TailAdmin). Alasannya: 71 pemakaian indigo di project ini sudah disapu
     | ke tosca pada tahap re-skin, jadi tombol biru di dalam kotak dialog
     | akan jadi satu-satunya benda indigo yang tersisa di seluruh aplikasi.
     | cancelButtonColor tetap #D34053 sesuai brief — merah TailAdmin memang
     | senada dengan merah yang dipakai di sini.
     |
     | heightAuto: false itu WAJIB di aplikasi ini. Secara bawaan SweetAlert2
     | mengubah tinggi <body> saat kotak dibuka; kerangka halaman kita memakai
     | `h-screen overflow-hidden`, dan perubahan itu membuat isi halaman
     | melompat setiap kali dialog muncul lalu ditutup.
     */
    var TEMA = {
        confirmButtonColor: '#0d9488',
        cancelButtonColor: '#D34053',
        reverseButtons: true,   // "Batal" di kiri, tombol aksi di kanan.
        focusCancel: true,      // Enter tidak langsung menghapus data.
        heightAuto: false,
        scrollbarPadding: false,
        buttonsStyling: true,
        customClass: { popup: 'simagas-swal' },
    };

    function siap() {
        return typeof window.Swal !== 'undefined';
    }

    function gabung(dasar, tambahan) {
        var hasil = {}, k;
        for (k in TEMA) { hasil[k] = TEMA[k]; }
        for (k in dasar) { if (dasar[k] !== undefined) hasil[k] = dasar[k]; }
        for (k in (tambahan || {})) { if (tambahan[k] !== undefined) hasil[k] = tambahan[k]; }
        return hasil;
    }

    function teksPolos(o) {
        return ((o.title || '') + '\n\n' + (o.text || '')).trim();
    }

    /*
     | Payload dari Livewire bisa datang dalam beberapa bentuk:
     |   dispatch('swal:modal', judul: 'x')            -> detail = {judul:'x'}
     |   dispatch('swal:modal', ['x'])                 -> detail = {0:'x'}
     | dan kunci boleh ditulis Indonesia maupun Inggris. Semuanya
     | diseragamkan di sini supaya pemanggilnya tidak perlu hafal satu ejaan.
     */
    function normalkan(detail) {
        var d = detail || {};

        if (Array.isArray(d)) { d = d[0] || {}; }
        if (d && d[0] && typeof d[0] === 'object' && d.title === undefined && d.judul === undefined) {
            d = d[0];
        }

        return {
            title: d.title || d.judul || 'Yakin?',
            text: d.text || d.teks || d.pesan || '',
            html: d.html || undefined,
            icon: d.icon || d.ikon || undefined,
            confirmButtonText: d.confirmButtonText || d.ya || undefined,
            cancelButtonText: d.cancelButtonText || d.batal || undefined,
            timer: d.timer || undefined,
            komponen: d.komponen || d.component || null,
            metode: d.metode || d.method || null,
            params: d.params || d.parameter || [],
            event: d.event || null,
        };
    }

    window.simagasSwal = {
        /** Kotak pesan biasa (pengganti alert). Selalu mengembalikan Promise. */
        pesan: function (o) {
            o = o || {};

            if (!siap()) {
                window.alert(teksPolos(o));
                return Promise.resolve(false);
            }

            return window.Swal.fire(gabung({
                icon: o.icon || 'info',
                title: o.title || '',
                text: o.text || undefined,
                html: o.html || undefined,
                confirmButtonText: o.confirmButtonText || 'Tutup',
                timer: o.timer || undefined,
            })).then(function () { return true; });
        },

        /** Konfirmasi ya/tidak. Promise berisi true kalau pengguna menekan "Ya". */
        konfirmasi: function (o) {
            o = o || {};

            if (!siap()) {
                return Promise.resolve(window.confirm(teksPolos(o)));
            }

            return window.Swal.fire(gabung({
                icon: o.icon || 'warning',
                title: o.title || 'Yakin?',
                text: o.text || undefined,
                html: o.html || undefined,
                showCancelButton: true,
                confirmButtonText: o.confirmButtonText || 'Ya, Lanjutkan!',
                cancelButtonText: o.cancelButtonText || 'Batal',
            })).then(function (hasil) { return !!(hasil && hasil.isConfirmed); });
        },

        /** Notifikasi kecil di pojok — untuk kabar baik yang tidak perlu ditutup manual. */
        toast: function (o) {
            o = o || {};

            if (!siap()) { return Promise.resolve(false); }

            return window.Swal.fire(gabung({
                toast: true,
                position: 'top-end',
                icon: o.icon || 'success',
                title: o.title || '',
                text: o.text || undefined,
                showConfirmButton: false,
                timer: o.timer || 3000,
                timerProgressBar: true,
            })).then(function () { return true; });
        },
    };

    /* ============================================================
       ATRIBUT data-konfirmasi
       ------------------------------------------------------------
       Menggantikan onsubmit="return confirm(...)" dan wire:confirm.

       Dipasang di <form> (untuk aksi POST biasa) atau langsung di tombol
       (untuk wire:click Livewire):

           form ... data-konfirmasi="Hapus kelas 10 IPA 1?"
                    data-konfirmasi-judul="Hapus Kelas Ini?"
                    data-konfirmasi-ikon="warning"
                    data-konfirmasi-ya="Ya, Hapus!"

       Penyadapannya di FASE CAPTURE pada document, bukan pada elemennya.
       Itu bukan gaya-gayaan: Livewire memasang pendengarnya sendiri di
       elemen tombol, dan pendengar di fase capture pada document berjalan
       LEBIH DULU — jadi stopPropagation() di sini benar-benar menahan aksi
       Livewire sampai pengguna menjawab. Kalau dipasang di fase bubble,
       permintaan Livewire sudah terlanjur berangkat sebelum kotak muncul.

       Setelah pengguna menekan "Ya", aksinya diulang dengan penanda
       data-konfirmasi-lolos supaya penyadapan ini melewatinya sekali.
       ============================================================ */
    var LOLOS = 'konfirmasiLolos';

    function tanya(el) {
        return window.simagasSwal.konfirmasi({
            title: el.dataset.konfirmasiJudul || 'Yakin?',
            text: el.dataset.konfirmasi || '',
            icon: el.dataset.konfirmasiIkon || 'warning',
            confirmButtonText: el.dataset.konfirmasiYa || 'Ya, Lanjutkan!',
            cancelButtonText: el.dataset.konfirmasiBatal || 'Batal',
        });
    }

    document.addEventListener('submit', function (e) {
        var form = e.target && e.target.closest ? e.target.closest('form[data-konfirmasi]') : null;

        if (!form) { return; }

        if (form.dataset[LOLOS] === '1') {
            delete form.dataset[LOLOS];
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        var penekan = e.submitter && form.contains(e.submitter) ? e.submitter : null;

        tanya(form).then(function (ya) {
            if (!ya) { return; }

            form.dataset[LOLOS] = '1';

            // requestSubmit() dipakai lebih dulu karena ia MEMBAWA nama/nilai
            // tombol penekan; form.submit() membuangnya. Beberapa form di
            // aplikasi ini membedakan aksi lewat nama tombol.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(penekan || undefined);
            } else {
                form.submit();
            }
        });
    }, true);

    document.addEventListener('click', function (e) {
        var el = e.target && e.target.closest ? e.target.closest('[data-konfirmasi]') : null;

        if (!el || el.tagName === 'FORM') { return; }

        // Tombol di dalam form yang sudah punya data-konfirmasi sendiri:
        // biarkan penyadap 'submit' di atas yang menanganinya, supaya
        // kotaknya tidak muncul dua kali.
        if (el.closest('form[data-konfirmasi]')) { return; }

        if (el.dataset[LOLOS] === '1') {
            delete el.dataset[LOLOS];
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        e.stopImmediatePropagation();

        tanya(el).then(function (ya) {
            if (!ya) { return; }

            el.dataset[LOLOS] = '1';
            el.click();
        });
    }, true);

    /* ============================================================
       EVENT DARI LIVEWIRE
       ------------------------------------------------------------
         $this->dispatch('swal:modal', judul: '...', teks: '...', ikon: 'success');
         $this->dispatch('swal:confirm', judul: '...', teks: '...',
                         komponen: $this->getId(), metode: 'approveAccount',
                         params: [$id]);

       Untuk jalur balik ada dua pilihan dan keduanya didukung:
         - komponen + metode  -> setara pemanggilan langsung method komponen
                                 itu (padanan "this.call" pada dokumentasi
                                 Livewire; tanda @ sengaja tidak ditulis di
                                 sini karena Blade mengompilasinya menjadi
                                 $_instance dan halamannya jadi error 500)
         - event              -> Livewire.dispatch(event, params), dipakai
                                 kalau yang menangani komponen LAIN.
       ============================================================ */
    window.addEventListener('swal:modal', function (e) {
        window.simagasSwal.pesan(normalkan(e.detail));
    });

    window.addEventListener('swal:confirm', function (e) {
        var d = normalkan(e.detail);

        window.simagasSwal.konfirmasi(d).then(function (ya) {
            if (!ya || !window.Livewire) { return; }

            var params = Array.isArray(d.params) ? d.params : [d.params];

            if (d.komponen && d.metode) {
                var komponen = window.Livewire.find(d.komponen);

                if (komponen) {
                    komponen.call.apply(komponen, [d.metode].concat(params));
                    return;
                }
            }

            if (d.event) {
                window.Livewire.dispatch(d.event, params);
            }
        });
    });

    /* Toast dari Livewire: dispatch('swal:toast', judul: 'Tersimpan'). */
    window.addEventListener('swal:toast', function (e) {
        window.simagasSwal.toast(normalkan(e.detail));
    });
})();
</script>
