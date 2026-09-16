@extends('layouts.app')

@section('title', 'Bantuan & Panduan')

@php
    /*
    |--------------------------------------------------------------------------
    | Isi panduan — satu sumber, dipakai dua kali
    |--------------------------------------------------------------------------
    | Ditaruh sebagai array di kepala berkas, bukan ditulis langsung sebagai
    | HTML di bawah. Alasannya satu: isinya dipakai DUA KALI — sekali untuk
    | tombol tabnya (judul + ikon) dan sekali untuk panel isinya. Menulisnya
    | dua kali berarti suatu hari judul tab dan judul panel berbeda, dan yang
    | mengubah salah satunya tidak akan pernah tahu.
    |
    | 'kunci' harus sama persis dengan nilai yang dikembalikan
    | PanduanController::tabUntuk(). Kalau meleset, tab awalnya tidak cocok
    | dengan panel mana pun dan halamannya terbuka kosong — penjaganya ada di
    | tests/Feature/PanduanTest.
    */
    $babPanduan = [
        [
            'kunci' => 'admin',
            'judul' => 'Super Admin & Kepala Sekolah',
            'ringkas' => 'Pemantauan, persetujuan, dan data induk',
            'ikon' => 'chart-bar',
            'bagian' => [
                [
                    'judul' => 'Live Monitoring KBM',
                    'isi' => 'Menampilkan kelas yang SEDANG berlangsung pada jam ini juga — bukan rekap kemarin. Halaman ini tidak menyegarkan dirinya sendiri; muat ulang halaman untuk melihat keadaan terbaru.',
                    'langkah' => [
                        'Buka menu <strong>Live Monitoring KBM</strong>. Yang tampil hanya jadwal yang jam mulainya sudah lewat dan jam selesainya belum tiba.',
                        'Baca warnanya: <strong>Aman</strong> berarti gurunya sudah absen mengajar. <strong>Menunggu</strong> berarti jamnya baru mulai dan masih dalam toleransi 10 menit.',
                        '<strong>Perhatian</strong> berarti sudah lewat 10 menit sejak jam masuk tetapi belum ada absen mengajar — inilah yang perlu ditindak.',
                        '<strong>Kosong</strong> berarti tidak ada jadwal untuk ruang itu pada jam ini.',
                        '<strong>Klik kartunya</strong> untuk melihat detail: status sesi (sudah diakhiri atau belum), daftar hadir, siapa saja yang tidak masuk beserta surat izinnya, dan foto bukti mengajar.',
                    ],
                    'catatan' => 'Toleransi 10 menit itu disengaja. Tanpa jeda, setiap kelas akan berwarna merah di menit pertama hanya karena gurunya sedang berjalan dari ruang guru.',
                ],
                [
                    'judul' => 'Persetujuan Izin Guru (ITT/IDT)',
                    'isi' => 'Pengajuan izin guru TIDAK langsung berlaku. Selama statusnya "Menunggu Persetujuan", kehadiran guru yang bersangkutan belum berubah sama sekali.',
                    'langkah' => [
                        'Buka <strong>Persetujuan Izin Guru</strong>. Angka di sebelah judul menunjukkan berapa yang menunggu.',
                        'Periksa jenisnya. <strong>IDT</strong> berarti guru meninggalkan tugas — isinya wajib terbaca jelas karena petugas piket yang akan menyampaikannya ke kelas.',
                        'Tekan <strong>Setujui</strong>. Seluruh hari dalam rentang tanggal itu langsung ditandai izin pada absensi pegawai.',
                        'Kalau menolak, alasannya wajib diisi — gurunya hanya bisa memperbaiki pengajuan kalau tahu apa yang salah.',
                    ],
                    'catatan' => 'Selama belum disetujui, guru itu masih terhitung tidak hadir. Jangan menunda pemeriksaan sampai akhir bulan — rekap kehadiran ikut terpengaruh.',
                ],
                [
                    'judul' => 'Persetujuan Rapor',
                    'isi' => 'Nilai yang diinput guru tidak otomatis terlihat wali murid. Rapor baru muncul di akun orang tua setelah Anda menyetujuinya.',
                    'langkah' => [
                        'Buka <strong>Persetujuan Rapor</strong> dan pilih kelas yang akan diperiksa.',
                        'Periksa kelengkapan nilainya lebih dulu; setelah disetujui, wali murid langsung bisa membukanya.',
                        'Setujui per kelas, bukan per siswa — supaya satu kelas terbit bersamaan.',
                    ],
                ],
                [
                    'judul' => 'Kalender Pendidikan & Hari Libur',
                    'isi' => 'Kalender ini yang menentukan hari mana yang dihitung sebagai hari sekolah. Ia bukan tampilan saja — angka di seluruh laporan ikut berubah mengikutinya.',
                    'langkah' => [
                        'Buka <strong>Kalender Pendidikan</strong>. Kotak merah berarti libur, kotak putih berarti hari KBM.',
                        'Libur nasional dan libur semester sudah terisi dari <strong>Kalender Dinas Provinsi Lampung</strong> dan tidak bisa diubah dari layar ini.',
                        'Untuk libur khusus sekolah (haul, banjir, kegiatan pesantren), klik tanggalnya lalu tekan <strong>Tambah Agenda / Libur</strong>.',
                        'Pilih jenis <strong>Libur</strong> kalau hari itu memang tidak ada KBM. <strong>Kegiatan</strong> dan <strong>Ujian</strong> tetap hari masuk — siswa tetap wajib hadir.',
                        'Hari libur <em>mingguan</em> (di sekolah ini: Jumat) diatur terpisah di Pengaturan Sistem, bukan di kalender.',
                    ],
                    'catatan' => 'Menandai satu hari sebagai Libur berarti hari itu berhenti menandai alpa DAN berhenti dihitung sebagai penyebut persentase kehadiran. Jangan memakai jenis Libur untuk hari yang sebenarnya masuk.',
                ],
                [
                    'judul' => 'Pantauan Kehadiran Siswa',
                    'isi' => 'Layar harian kehadiran gerbang, dibaca dari atas ke bawah: status hari dulu, lalu angka seluruh sekolah, baru rincian per kelas.',
                    'langkah' => [
                        'Baris paling atas menyebut <strong>hari KBM atau libur</strong>. Pada hari libur, wajar bila tidak ada kehadiran yang tercatat.',
                        '<strong>Alpa</strong> berarti sudah lewat jam pulang dan anak tidak pernah tercatat — ini yang perlu ditindak.',
                        '<strong>Belum Tercatat</strong> sebelum jam pulang berarti mungkin masih di perjalanan; sesudah jam pulang, sistem mengubahnya jadi Alpa otomatis.',
                        '<strong>Izin</strong> dan <strong>Sakit</strong> sudah diurus petugas piket — tidak perlu ditelepon lagi.',
                        'Tabel per kelas menjawab "kelas mana yang bermasalah hari ini" tanpa perlu membuka kelas satu per satu.',
                    ],
                    'catatan' => 'Kalau muncul peringatan merah "Penandaan alpa otomatis BELUM berjalan", berarti cron di cPanel belum aktif. Selama itu, kolom Alpa akan terus nol walau ada siswa yang tidak masuk.',
                ],
                [
                    'judul' => 'Master Data Sekolah',
                    'isi' => 'Data induk: pegawai, siswa, kelas, jadwal pelajaran, dan kenaikan kelas.',
                    'langkah' => [
                        '<strong>Data Pegawai & Data Siswa</strong> — tambah, ubah, dan cetak kartu QR untuk scan gerbang.',
                        '<strong>Import / Export</strong> — untuk memasukkan data banyak sekaligus dari Excel di awal tahun ajaran.',
                        '<strong>Kenaikan Kelas</strong> — memindahkan siswa ke tingkat berikutnya. Jalankan sekali di akhir tahun ajaran, bukan di tengah semester.',
                        '<strong>Laporan Bulanan (PDF)</strong> — berkas siap cetak untuk rapat komite.',
                    ],
                    'catatan' => 'Menghapus data pegawai atau siswa yang sudah punya riwayat absensi akan ditolak sistem. Itu bukan error — riwayat kehadiran adalah dokumen yang tidak boleh hilang bersama orangnya.',
                ],
            ],
        ],

        [
            'kunci' => 'guru',
            'judul' => 'Guru Pengampu & Wali Kelas',
            'ringkas' => 'Absensi, jurnal, nilai, dan izin',
            'ikon' => 'academic-cap',
            'bagian' => [
                [
                    'judul' => 'Urutan hariannya — ini yang paling sering keliru',
                    'isi' => 'Ada dua absensi yang berbeda dan mudah tertukar. Yang satu mencatat kehadiran ANDA, yang satu mencatat kehadiran SISWA.',
                    'langkah' => [
                        '<strong>Pagi:</strong> buka <em>Absen Kehadiran (Radius)</em>. Harus dilakukan dari lokasi sekolah — browser akan meminta izin lokasi.',
                        '<strong>Tiap jam mengajar:</strong> buka <em>Absen Mengajar (QR)</em> dan pindai QR ruang/jadwal.',
                        '<strong>Di dalam kelas:</strong> buka <em>Jurnal &amp; Absen Kelas</em> untuk mencatat kehadiran siswa.',
                    ],
                    'catatan' => 'Absen Mengajar akan DITOLAK kalau Anda belum melakukan Absen Kehadiran pagi itu. Pesannya muncul jelas di layar — bukan sistem yang rusak.',
                ],
                [
                    'judul' => 'Jurnal & Absen Kelas',
                    'isi' => 'Daftar hadir siswa per jam pelajaran, sekaligus catatan materi yang diajarkan.',
                    'langkah' => [
                        'Pilih jadwal yang sedang berlangsung. Daftar siswanya terisi otomatis dari kelas itu.',
                        'Status siswa sudah terisi <strong>Hadir</strong> secara bawaan — Anda hanya perlu mengubah yang tidak hadir.',
                        'Siswa yang paginya sudah dicatat izin/sakit di gerbang akan <strong>terisi otomatis</strong> dan ditandai di layar. Tidak perlu mengisinya lagi.',
                        'Unggah <strong>foto bukti mengajar</strong>, lalu tekan <strong>Simpan Absensi KBM</strong>. Setelah selesai, tekan <strong>Akhiri Sesi</strong> — jurnalnya lalu terkunci.',
                    ],
                    'catatan' => 'Siswa yang Anda tandai Alpa padahal tadi pagi tercatat masuk gerbang akan otomatis dicatat sebagai <strong>Bolos</strong> — anak itu ada di sekolah tapi tidak ada di kelas Anda.',
                ],
                [
                    'judul' => 'Input Nilai (termasuk nilai praktik)',
                    'isi' => 'Satu layar berisi seluruh siswa kelas itu dengan tiga kolom nilai.',
                    'langkah' => [
                        '<strong>Formatif</strong> — penilaian harian selama proses belajar.',
                        '<strong>Sumatif</strong> — penilaian akhir bab atau ujian.',
                        '<strong>Praktik</strong> — penilaian keterampilan atau unjuk kerja. Kolom ini boleh dikosongkan untuk mapel yang memang tidak ada praktiknya.',
                        'Nilai tersimpan per mata pelajaran yang Anda ampu, lalu menunggu persetujuan kepala sekolah sebelum terbit sebagai rapor.',
                    ],
                ],
                [
                    'judul' => 'Pengajuan Izin ITT / IDT',
                    'isi' => 'Saat Anda tidak masuk, ada satu kelas yang tetap datang dan menunggu. Karena itu yang ditanyakan bukan hanya alasannya, tapi juga nasib kelasnya.',
                    'langkah' => [
                        '<strong>ITT (Izin Tanpa Tugas)</strong> — kelas Anda tercatat kosong. Tidak ada yang harus dikerjakan siswa.',
                        '<strong>IDT (Izin Dengan Tugas)</strong> — kelas tetap berjalan. <strong>Deskripsi tugas wajib diisi</strong>, karena isian itulah yang dibacakan petugas piket di depan kelas Anda.',
                        'Lampiran tugas (PDF atau gambar) boleh ditambahkan, dan tersimpan tertutup — tidak bisa diunduh siswa.',
                        'Untuk izin satu hari, isi tanggal selesai sama dengan tanggal mulai.',
                    ],
                    'catatan' => 'Kehadiran Anda BELUM berubah sampai kepala sekolah menyetujuinya. Ajukan jauh-jauh hari bila memungkinkan, jangan di pagi hari keberangkatan.',
                ],
            ],
        ],

        [
            'kunci' => 'piket',
            'judul' => 'Petugas Piket & Staff',
            'ringkas' => 'Gerbang dan izin siswa satu pintu',
            'ikon' => 'shield-check',
            'bagian' => [
                [
                    'judul' => 'Scan Gerbang',
                    'isi' => 'Satu layar, satu kamera, untuk siswa maupun pegawai. Tidak ada lagi pemilihan mode — sistem mengenali sendiri kartu siapa yang dipindai.',
                    'langkah' => [
                        'Buka <strong>Piket Scan Gerbang</strong>, tekan tombol mulai, lalu izinkan browser memakai kamera.',
                        'Arahkan kartu ke dalam bingkai bidik di layar. Ada bunyi dan getaran singkat setiap kali kode terbaca.',
                        'Kartu <strong>siswa</strong> (NIS) tercatat sebagai kehadiran siswa; kartu <strong>pegawai</strong> (NIP) tercatat sebagai kehadiran pegawai. Anda tidak perlu memilih apa pun.',
                        'Kalau kamera tidak mau menyala, periksa izin kamera di browser — bukan menutup dan membuka ulang halamannya berkali-kali.',
                    ],
                    'catatan' => 'Jam kedatangan yang sudah tercatat tidak akan tertimpa kalau kartu yang sama dipindai dua kali. Aman untuk dicoba ulang.',
                ],
                [
                    'judul' => 'Pencatatan Izin Siswa — Satu Pintu',
                    'isi' => 'Siswa tidak membawa HP, jadi izin dicatat oleh petugas di gerbang. Panel di sebelah kanan layar scan adalah satu-satunya tempat izin siswa dimasukkan.',
                    'langkah' => [
                        'Ketik nama atau NIS siswa di kotak pencarian — hasilnya muncul langsung tanpa menekan tombol apa pun.',
                        'Pilih siswanya, lalu pilih jenis izinnya: <strong>Sakit</strong>, <strong>Izin</strong>, atau <strong>Dispensasi</strong>.',
                        '<strong>Dispensasi</strong> dipakai untuk penugasan sekolah — lomba, rapat OSIS, upacara. Bukan untuk keperluan pribadi.',
                        'Foto surat izin boleh dilampirkan. Berkasnya tersimpan tertutup dan hanya bisa dibuka lewat aplikasi ini.',
                        'Tekan simpan. Kamera tetap menyala — Anda bisa langsung melanjutkan scan.',
                    ],
                    'catatan' => 'Izin yang dicatat di sini otomatis muncul di Jurnal Kelas semua guru hari itu. Guru tidak perlu diberi tahu satu per satu, dan tidak akan menandainya Alpa.',
                ],
                [
                    'judul' => 'Kalau terjadi kesalahan input',
                    'isi' => 'Satu siswa hanya bisa punya satu catatan izin per hari. Bila Anda salah memilih siswa atau jenis izin, sistem akan memberi tahu bahwa izin hari itu sudah ada.',
                    'langkah' => [
                        'Hubungi Admin TU atau Super Admin untuk mengoreksi catatannya.',
                        'Jangan mencatatkan izin atas nama siswa lain sebagai jalan pintas — jejaknya tercatat atas nama akun Anda.',
                    ],
                ],
            ],
        ],

        [
            'kunci' => 'ortu',
            'judul' => 'Wali Murid',
            'ringkas' => 'Memantau kehadiran dan nilai anak',
            'ikon' => 'users',
            'bagian' => [
                [
                    'judul' => 'Absensi Kedatangan',
                    'isi' => 'Riwayat anak Anda masuk gerbang sekolah, lengkap dengan jamnya.',
                    'langkah' => [
                        'Buka <strong>Absensi Kedatangan</strong> untuk melihat tanggal dan jam kedatangan.',
                        'Baris kosong berarti anak tidak tercatat masuk gerbang pada hari itu.',
                    ],
                ],
                [
                    'judul' => 'Pantauan KBM Harian',
                    'isi' => 'Kehadiran anak di setiap JAM PELAJARAN, bukan hanya di gerbang. Dua hal ini berbeda, dan selisihnya penting.',
                    'langkah' => [
                        'Buka <strong>Pantauan KBM Harian</strong> untuk melihat status per mata pelajaran.',
                        'Status <strong>Bolos</strong> berarti anak masuk gerbang pagi itu tetapi tidak ada di kelas pada jam tersebut.',
                        'Status <strong>Izin</strong> atau <strong>Sakit</strong> berarti sudah tercatat resmi oleh petugas di gerbang.',
                    ],
                ],
                [
                    'judul' => 'Kalau anak tidak scan di gerbang',
                    'isi' => 'Sistem menandai sendiri anak yang tidak tercatat hadir sampai jam pulang sekolah.',
                    'langkah' => [
                        'Sesudah jam pulang, anak yang belum tercatat hadir dan belum punya surat izin akan <strong>ditandai Alpa otomatis</strong>.',
                        'Penandaan ini <strong>hanya berjalan pada hari KBM</strong> — tidak pada hari Jumat maupun hari libur di Kalender Pendidikan.',
                        'Kalau anak sebenarnya masuk tetapi lupa scan, hubungi wali kelas untuk dikoreksi. Catatan yang sudah ada tidak pernah ditimpa sistem.',
                        'Buka menu <strong>Kalender Pendidikan</strong> untuk melihat hari mana yang libur.',
                    ],
                ],
                [
                    'judul' => 'Rekap Akademik & Rapor',
                    'isi' => 'Rekap kehadiran berjalan setiap hari; rapor baru terbit setelah disetujui kepala sekolah.',
                    'langkah' => [
                        '<strong>Rekap &amp; Laporan Akademik</strong> — ringkasan kehadiran dan perkembangan, tersedia kapan saja.',
                        '<strong>Rapor Anak</strong> — nilai resmi. Halaman ini kosong sampai kepala sekolah menyetujui rapor kelas anak Anda.',
                        'Grafik nilai dibaca dari kiri ke kanan mengikuti waktu. Batang yang lebih tinggi berarti nilai lebih baik; garis mendatar menunjukkan rata-rata.',
                    ],
                    'catatan' => 'Nilai yang belum disetujui memang sengaja tidak ditampilkan — supaya tidak ada nilai yang berubah setelah Anda melihatnya.',
                ],
                [
                    'judul' => 'Notifikasi Otomatis',
                    'isi' => 'Pemberitahuan dikirim ke HP Anda tanpa perlu membuka aplikasi.',
                    'langkah' => [
                        'Saat pertama membuka aplikasi, akan muncul tawaran <strong>Aktifkan notifikasi</strong>. Tekan Aktifkan dan izinkan di browser.',
                        'Anda akan diberi tahu saat anak tercatat masuk gerbang dan saat ada pengumuman sekolah.',
                        'Bila tidak ada notifikasi yang masuk, periksa izin notifikasi browser di pengaturan HP.',
                    ],
                ],
            ],
        ],
    ];

    $kunciTab = array_column($babPanduan, 'kunci');

    /*
    | Jaring pengaman: kalau controller mengirim kunci yang tidak ada di daftar
    | (mis. peran baru ditambahkan tapi tabnya belum dibuat), jatuh ke tab
    | pertama. Tanpa ini, halamannya terbuka dengan SELURUH panel tersembunyi —
    | terlihat persis seperti halaman rusak, padahal isinya lengkap.
    */
    $tabAwal = in_array($tabAwal, $kunciTab, true) ? $tabAwal : $kunciTab[0];
@endphp

@section('content')

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink dark:text-gray-100">
            Pusat Bantuan &amp; Panduan Penggunaan SIMAGAS
        </h1>
        <p class="mt-1 max-w-3xl text-sm leading-relaxed text-brand-muted dark:text-brand-faint">
            Panduan dikelompokkan per peran. Tab yang terbuka sudah disesuaikan dengan akun Anda —
            tab lain tetap bisa dibaca bila Anda perlu memahami pekerjaan rekan.
        </p>
    </div>

    {{--
        ============ KENAPA x-data ADA DI PEMBUNGKUS TERLUAR ============
        Tombol tab dan panel isinya harus berbagi SATU state. Kalau x-data
        dipasang terpisah di masing-masing, keduanya jadi dua komponen yang
        tidak saling kenal: tombolnya berubah warna, panelnya tidak ikut.

        CATATAN: di dalam x-data dan x-on TIDAK BOLEH ada tanda kutip ganda —
        termasuk di dalam komentar JavaScript. Kutip itu menutup atribut HTML
        lebih awal, dan gejalanya menyesatkan: "Unexpected token" disertai
        "tab is not defined" untuk properti yang jelas-jelas ada.
        Penjaganya: tests/Feature/AtributAlpineTest.
    --}}
    <div x-data="{ tab: @js($tabAwal), daftar: @js($kunciTab) }"
        class="grid min-w-0 gap-5 lg:grid-cols-[minmax(0,17rem)_minmax(0,1fr)] lg:items-start lg:gap-6">

        {{-- ================= DAFTAR TAB ================= --}}
        {{--
            Vertikal di layar lebar, jalur gulir mendatar di HP.
            snap-x membuat kartunya berhenti rapi di tepi saat digulir dengan
            jempol, bukan berhenti setengah terpotong.
        --}}
        <div role="tablist" aria-label="Pilih peran"
            class="-mx-1 flex min-w-0 snap-x snap-mandatory gap-2 overflow-x-auto px-1 pb-2 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0"
            @keydown.arrow-right.prevent="tab = daftar[(daftar.indexOf(tab) + 1) % daftar.length]; $nextTick(() => $el.querySelector('[aria-selected=true]')?.focus())"
            @keydown.arrow-down.prevent="tab = daftar[(daftar.indexOf(tab) + 1) % daftar.length]; $nextTick(() => $el.querySelector('[aria-selected=true]')?.focus())"
            @keydown.arrow-left.prevent="tab = daftar[(daftar.indexOf(tab) - 1 + daftar.length) % daftar.length]; $nextTick(() => $el.querySelector('[aria-selected=true]')?.focus())"
            @keydown.arrow-up.prevent="tab = daftar[(daftar.indexOf(tab) - 1 + daftar.length) % daftar.length]; $nextTick(() => $el.querySelector('[aria-selected=true]')?.focus())">

            @foreach ($babPanduan as $bab)
                {{--
                    ============ KENAPA GAYA AKTIF LEWAT aria-selected ============
                    Warnanya TIDAK diatur :class, melainkan varian
                    aria-selected:* milik Tailwind, dan aria-selected-nya sendiri
                    sudah ditulis server sejak awal.

                    Akibatnya: kalau Alpine gagal dimuat (koneksi sekolah putus di
                    tengah), tab yang benar tetap tersorot dan panelnya tetap
                    terbaca. Kalau gayanya bergantung pada :class, halaman ini
                    akan terbuka tanpa satu pun tab tersorot — tampak rusak
                    padahal isinya utuh.

                    :aria-selected sengaja mengembalikan STRING, bukan boolean:
                    Alpine MENGHAPUS atribut yang nilainya false, dan atribut yang
                    hilang membuat aturan CSS-nya ikut hilang.
                --}}
                <button type="button" role="tab"
                    id="tab-{{ $bab['kunci'] }}"
                    aria-controls="panel-{{ $bab['kunci'] }}"
                    aria-selected="{{ $tabAwal === $bab['kunci'] ? 'true' : 'false' }}"
                    :aria-selected="tab === @js($bab['kunci']) ? 'true' : 'false'"
                    :tabindex="tab === @js($bab['kunci']) ? 0 : -1"
                    @click="tab = @js($bab['kunci'])"
                    class="group flex w-64 shrink-0 snap-start items-start gap-3 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-left transition-colors hover:bg-brand-surface-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-accent/40 aria-selected:border-brand-accent aria-selected:bg-brand-accent-soft dark:border-gray-800 dark:bg-gray-900 lg:w-full">

                    <x-icon name="{{ $bab['ikon'] }}"
                        class="mt-0.5 h-5 w-5 shrink-0 text-brand-muted group-aria-selected:text-brand-accent-text" />

                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-brand-ink group-aria-selected:text-brand-accent-text dark:text-white">
                            {{ $bab['judul'] }}
                        </span>
                        <span class="mt-0.5 block text-xs leading-snug text-brand-muted">{{ $bab['ringkas'] }}</span>
                    </span>
                </button>
            @endforeach
        </div>

        {{-- ================= PANEL ISI ================= --}}
        <div class="min-w-0">
            @foreach ($babPanduan as $bab)
                {{--
                    x-cloak HANYA pada panel yang bukan tab awal.

                    Panel bawaannya dibiarkan terlihat sejak HTML dikirim, jadi
                    isinya sudah terbaca sebelum JavaScript apa pun berjalan —
                    dan tetap terbaca kalau JavaScript-nya tidak pernah datang.
                    Memasang x-cloak di SEMUA panel akan membuat halaman ini
                    kosong total bagi siapa pun yang Alpine-nya gagal dimuat.
                --}}
                <section role="tabpanel" tabindex="0"
                    id="panel-{{ $bab['kunci'] }}"
                    aria-labelledby="tab-{{ $bab['kunci'] }}"
                    x-show="tab === @js($bab['kunci'])"
                    @if ($tabAwal !== $bab['kunci']) x-cloak @endif
                    class="muncul min-w-0 space-y-4 focus-visible:outline-none">

                    @foreach ($bab['bagian'] as $i => $bagian)
                        <article class="min-w-0 rounded-2xl border border-brand-border bg-brand-surface shadow-soft dark:border-gray-800 dark:bg-gray-900">

                            <div class="flex items-start gap-3 border-b border-brand-border px-5 py-4 dark:border-gray-800">
                                <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-accent-soft text-sm font-bold text-brand-accent-text">
                                    {{ $i + 1 }}
                                </span>
                                <div class="min-w-0">
                                    <h2 class="text-base font-bold text-brand-ink dark:text-white">{{ $bagian['judul'] }}</h2>
                                    <p class="mt-1 text-sm leading-relaxed text-brand-muted dark:text-brand-faint">{{ $bagian['isi'] }}</p>
                                </div>
                            </div>

                            <div class="space-y-3 px-5 py-4">
                                <ol class="space-y-2.5">
                                    @foreach ($bagian['langkah'] as $langkah)
                                        <li class="flex items-start gap-2.5 text-sm leading-relaxed text-brand-ink dark:text-gray-200">
                                            <x-icon name="arrow-right" class="mt-1 h-3.5 w-3.5 shrink-0 text-brand-accent-text" />
                                            {{-- Isi 'langkah' ditulis sendiri di berkas ini (bukan dari
                                                 basis data atau masukan pengguna), jadi {!! !!} di sini
                                                 aman dan memang diperlukan untuk penekanan <strong>. --}}
                                            <span class="min-w-0">{!! $langkah !!}</span>
                                        </li>
                                    @endforeach
                                </ol>

                                @if (! empty($bagian['catatan']))
                                    <p class="flex items-start gap-2.5 rounded-xl border border-amber-300 bg-amber-500/10 px-3.5 py-3 text-sm leading-relaxed text-amber-900 dark:border-amber-500/30 dark:text-amber-300">
                                        <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                                        <span class="min-w-0">{!! $bagian['catatan'] !!}</span>
                                    </p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </section>
            @endforeach

            <p class="mt-5 flex items-start gap-2.5 rounded-xl border border-dashed border-brand-border px-4 py-3 text-xs leading-relaxed text-brand-muted dark:border-gray-800">
                <x-icon name="inbox" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>
                    Menu yang disebut di panduan ini hanya muncul bila akun Anda memang berhak membukanya.
                    Kalau ada menu yang Anda butuhkan tetapi tidak terlihat, hubungi Super Admin sekolah.
                </span>
            </p>
        </div>
    </div>

@endsection
