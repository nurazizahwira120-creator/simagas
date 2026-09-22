{{--
    Sidebar TailAdmin — dipakai oleh layouts/app.blade.php lewat <x-sidebar />.

    CATATAN PENTING soal pengecekan role:
    Brief aslinya memakai perbandingan  role == 'super_admin'  (enum vs string).
    Itu SELALU false di project ini, karena kolom `role` di Model User di-cast
    ke enum App\Enums\UserRole — membandingkan objek enum dengan string
    menghasilkan false, sehingga menunya tidak akan pernah muncul.
    Perbandingan yang benar (dan yang dipakai di bawah) adalah:
        auth()->user()->role === \App\Enums\UserRole::SuperAdmin
    Kalau lebih suka bentuk string, gunakan  role->value == 'super_admin'.
--}}
@php
    use App\Enums\UserRole;

    $pengguna = auth()->user();
    $adalahSuperAdmin = $pengguna?->role === UserRole::SuperAdmin;

    /*
     | Struktur menu Super Admin — 6 butir sesuai brief. Butir yang membawahi
     | beberapa halaman dibuat bersubmenu supaya semua halaman tetap terjangkau
     | tanpa menambah butir baru di tingkat atas.
     |
     | 'route' berisi akhiran nama rute; prefix-nya ($panelPrefix) ditempel
     | saat render, jadi menu ini otomatis mengarah ke panel milik role yang
     | sedang login.
     |
     | CATATAN: Super Admin memegang kendali penuh sistem tapi SENGAJA tidak
     | punya fitur absensi apa pun — tidak ada tombol absen mandiri, tidak ada
     | scanner. Perannya mengelola dan mengawasi, bukan menjadi objek absensi.
     | Pembatasan ini tidak cuma menyembunyikan menu: rute absen-lokasi &
     | absen-mengajar memang tidak didaftarkan untuk grup super-admin di
     | routes/web.php.
     */
    $menuSuperAdmin = [
        ['label' => 'Dashboard System',       'icon' => 'chart-bar',        'route' => '.dashboard',   'match' => '.dashboard'],

        // Dua butir ini ditulis apa adanya, bukan memakai $butirGerbang /
        // $butirLiveMonitoring di bawah: PHP membaca berkas ini dari atas ke
        // bawah, dan variabel-variabel itu baru lahir beberapa puluh baris
        // setelah blok ini. Memakainya di sini menghasilkan array kosong
        // tanpa error apa pun — butirnya hilang diam-diam dari menu.
        ['label' => 'Live Monitoring KBM',    'icon' => 'eye',              'route' => '.live-monitoring', 'match' => '.live-monitoring'],
        ['label' => 'Piket Scan Gerbang',     'icon' => 'shield-check',     'route' => '.gerbang',         'match' => '.gerbang*'],
        ['label' => 'Persetujuan Izin Guru',  'icon' => 'check-circle',     'route' => '.persetujuan-izin-guru', 'match' => '.persetujuan-izin-guru*'],

        // Ditulis apa adanya, bukan $butirPanduan — variabel itu baru lahir
        // beberapa puluh baris di bawah blok ini (lihat catatan di atas).
        ['label' => 'Kalender Pendidikan',    'icon' => 'calendar',         'route' => '.kalender-pendidikan', 'match' => '.kalender-pendidikan', 'grup' => 'Lainnya'],
        ['label' => 'Bantuan & Panduan',      'icon' => 'inbox',            'route' => '.panduan',     'match' => '.panduan', 'grup' => 'Lainnya'],

        ['label' => 'Manajemen Pengguna',     'icon' => 'users',            'match' => '.users.*|.pengaturan', 'anak' => [
            ['label' => 'Daftar Akun',        'icon' => 'users',            'route' => '.users.index', 'match' => '.users.*'],
            ['label' => 'Approval Akun Baru', 'icon' => 'shield-check',     'route' => '.pengaturan',  'match' => '.pengaturan', 'query' => ['tab' => 'approval']],
        ]],

        ['label' => 'Master Data Sekolah',    'icon' => 'academic-cap',     'match' => '.pegawai.*|.siswa.*|.kelas.*|.master-data.*|.cari-data', 'anak' => [
            ['label' => 'Data Pegawai',       'icon' => 'briefcase',        'route' => '.pegawai.index',     'match' => '.pegawai.*'],
            ['label' => 'Data Siswa',         'icon' => 'identification',   'route' => '.siswa.index',       'match' => '.siswa.*'],
            ['label' => 'Data Kelas',         'icon' => 'academic-cap',     'route' => '.kelas.index',       'match' => '.kelas.*'],
            ['label' => 'Pencarian Cepat',    'icon' => 'search',           'route' => '.cari-data',         'match' => '.cari-data'],
            ['label' => 'Import / Export',    'icon' => 'upload',           'route' => '.master-data.index', 'match' => '.master-data.*'],
        ]],

        ['label' => 'Manajemen Akademik',     'icon' => 'calendar',         'match' => '.jadwal.*|.ekskul*|.rpp.*|.kenaikan-kelas*', 'anak' => [
            ['label' => 'Jadwal Pelajaran',   'icon' => 'calendar',         'route' => '.jadwal.index',     'match' => '.jadwal.*'],
            ['label' => 'Jadwal Ekskul',      'icon' => 'clock',            'route' => '.ekskul',           'match' => '.ekskul*'],
            ['label' => 'Pantauan RPP Guru',  'icon' => 'document-report',  'route' => '.rpp.index',        'match' => '.rpp.*'],
            ['label' => 'Persetujuan Rapor',  'icon' => 'clipboard-check',  'route' => '.rapor.validasi',   'match' => '.rapor.*'],
            ['label' => 'Kenaikan Kelas',     'icon' => 'arrow-right',      'route' => '.kenaikan-kelas',   'match' => '.kenaikan-kelas*'],
        ]],

        ['label' => 'Pusat Laporan',          'icon' => 'chart-pie',        'match' => '.laporan-harian|.laporan|.laporan-pegawai|.laporan-bulanan*|.rekap-kbm*', 'anak' => [
            ['label' => 'Absensi Harian',       'icon' => 'clock',            'route' => '.laporan-harian',  'match' => '.laporan-harian'],
            ['label' => 'Rekap Bulanan Siswa',  'icon' => 'document-report',  'route' => '.laporan',         'match' => '.laporan'],
            // Acuannya JADWAL PELAJARAN, bukan siswa. Menjawab pertanyaan
            // yang tidak bisa dijawab butir mana pun di atas maupun di bawah:
            // jam & mata pelajaran mana yang paling banyak ditinggalkan.
            ['label' => 'Rekap KBM per Jadwal', 'icon' => 'calendar',         'route' => '.rekap-kbm',       'match' => '.rekap-kbm*'],
            // Arsip PDF hasil Cron Job — beda dari 'Rekap Bulanan Siswa' di
            // atas yang dihitung langsung saat halaman dibuka. Yang ini
            // berkas jadi, siap unduh & cetak, dan mencakup pegawai juga.
            ['label' => 'Laporan Bulanan (PDF)', 'icon' => 'download',        'route' => '.laporan-bulanan', 'match' => '.laporan-bulanan*'],
            ['label' => 'Rekap Bulanan Pegawai','icon' => 'briefcase',        'route' => '.laporan-pegawai', 'match' => '.laporan-pegawai'],
        ]],

        ['label' => 'Kelola Pengumuman',        'icon' => 'bell',             'route' => '.pengumuman',  'match' => '.pengumuman'],

        // 'kecualiTab' supaya butir ini TIDAK ikut menyala saat yang dibuka
        // adalah tab Approval — tab itu sudah diwakili menu Manajemen Pengguna.
        ['label' => 'Pengaturan Sistem',      'icon' => 'cog',              'route' => '.pengaturan',  'match' => '.pengaturan', 'kecualiTab' => ['approval'], 'grup' => 'Lainnya'],
    ];

    /*
     |--------------------------------------------------------------------
     | Menu per peran (selain Super Admin)
     |--------------------------------------------------------------------
     | Brief-nya menyebut rangkaian @if/@elseif per role. Yang dipakai di
     | sini bentuk setara tapi berbasis data: satu daftar menu per peran,
     | lalu dipilih dengan match(). Alasannya, markup <nav> di bawah panjang
     | (submenu, indikator aktif, ikon, state fokus); menuliskan ulang markup
     | itu di tiap cabang @elseif berarti empat salinan yang harus diperbaiki
     | bersamaan setiap kali stylingnya disentuh — sumber bug klasik "menu
     | guru sudah rapi, menu wali murid belum". Hasil yang dilihat pengguna
     | sama persis: tiap peran mendapat daftar menunya sendiri.
     |
     | Lapisan pengaman tetap ada: $saring() di bawah membuang butir yang
     | rutenya tidak terdaftar untuk peran tersebut, jadi salah menaruh butir
     | di sini paling banter membuatnya tidak muncul — tidak sampai melempar
     | RouteNotFoundException di tengah halaman.
     */

    // Butir yang isinya sama di beberapa peran — ditulis sekali saja.
    $butirDashboard = ['label' => 'Dashboard',      'icon' => 'chart-bar',      'route' => '.dashboard', 'match' => '.dashboard'];
    // 'grup' menentukan di bawah judul mana butir ini muncul di sidebar
    // (gaya TailAdmin: "MENU" lalu "LAINNYA"). Butir tanpa 'grup' otomatis
    // masuk "Menu", jadi menambah butir baru tidak wajib memikirkannya.
    $butirProfil    = ['label' => 'Profil Pribadi', 'icon' => 'identification', 'route' => '.profil',    'match' => '.profil', 'grup' => 'Lainnya'];

    /*
     | Bantuan & Panduan — satu-satunya butir yang muncul untuk SEMUA peran
     | tanpa kecuali, termasuk wali murid dan guru piket.
     |
     | Ditaruh di grup "Lainnya" bersama Profil, dan sengaja di URUTAN
     | TERAKHIR: orang membuka panduan saat tersesat, dan tempat yang paling
     | mudah ditemukan saat tersesat adalah dasar daftar — bukan di tengah
     | menu kerja yang justru sedang membingungkannya.
     */
    // Kalender Pendidikan — dilihat semua peran, diubah hanya Super Admin &
    // Kepala Sekolah (ditegakkan di dalam komponennya, bukan di sini).
    $butirKalender = ['label' => 'Kalender Pendidikan', 'icon' => 'calendar', 'route' => '.kalender-pendidikan', 'match' => '.kalender-pendidikan', 'grup' => 'Lainnya'];

    $butirPanduan = ['label' => 'Bantuan & Panduan', 'icon' => 'inbox', 'route' => '.panduan', 'match' => '.panduan', 'grup' => 'Lainnya'];

    // Dua pintu absensi yang tersisa. "Absensi biasa" (tombol Absen Masuk
    // sekali klik tanpa verifikasi apa pun) SUDAH DIHAPUS dari seluruh sistem
    // — rutenya pun tidak lagi didaftarkan di routes/web.php, jadi kehadiran
    // pegawai hanya bisa tercatat lewat GPS, scan QR gerbang, atau input
    // manual admin.
    $butirAbsenHadir = ['label' => 'Absen Kehadiran (Radius)', 'icon' => 'map-pin', 'route' => '.absen-lokasi',   'match' => '.absen-lokasi'];

    // Pengajuan Izin — ditaruh TEPAT di bawah Absen Kehadiran karena
    // keduanya menjawab pertanyaan yang sama dari sisi pegawai: "hari ini
    // saya bagaimana". Datang -> absen radius; tidak datang -> ajukan izin.
    $butirIzin = ['label' => 'Pengajuan Izin', 'icon' => 'document-report', 'route' => '.pengajuan-izin', 'match' => '.pengajuan-izin'];

    /*
     | Pengajuan Izin Guru (ITT/IDT) — HANYA muncul untuk peran yang mengajar.
     |
     | Butir ini dan $butirIzin di atas sengaja SAMA-SAMA dimasukkan ke
     | $menuPegawai, dan itu bukan menu ganda: rutenya berbeda, dan tiap peran
     | hanya punya SATU di antaranya terdaftar di routes/web.php (guru & wali
     | kelas dapat .izin-guru, staff & admin TU dapat .pengajuan-izin).
     | $saring() di bawah membuang yang rutenya tidak ada.
     |
     | Menaruh keduanya di sini justru yang membuat aturannya berada di SATU
     | tempat — routes/web.php — bukan tersebar di dua berkas yang harus
     | diingat untuk diubah bersamaan.
     */
    $butirIzinGuru = ['label' => 'Pengajuan Izin (ITT/IDT)', 'icon' => 'clipboard-check', 'route' => '.izin-guru.create', 'match' => '.izin-guru*'];

    // Sisi penyetuju — kepala sekolah & super admin.
    $butirPersetujuanIzinGuru = ['label' => 'Persetujuan Izin Guru', 'icon' => 'check-circle', 'route' => '.persetujuan-izin-guru', 'match' => '.persetujuan-izin-guru*'];
    $butirAbsenAjar  = ['label' => 'Absen Mengajar (QR)',      'icon' => 'qr-code', 'route' => '.absen-mengajar', 'match' => '.absen-mengajar'];

    // "Scan Siswa" TIDAK sama dengan "Absen Mengajar": yang ini mencatat
    // kehadiran SISWA di kelas, yang itu mencatat kehadiran GURU-nya.
    /*
     | DIHAPUS: butir "Scan Siswa" (.scanner-siswa) dan "Scanner QR"
     | (.scanner).
     |
     | Keduanya membuka kamera dan mencatat kehadiran, persis seperti
     | "Piket Scan Gerbang" — bedanya keduanya TIDAK bisa mencatat izin.
     | Guru piket sebelumnya melihat KETIGANYA sekaligus tanpa satu pun
     | petunjuk di layar yang menjelaskan bedanya, dan yang membuka menu
     | yang salah akan mencari form izin yang memang tidak ada di sana.
     */

    // Jurnal & Absen Kelas — daftar hadir siswa per jam pelajaran. Hanya bisa
    // dibuka kalau guru sudah scan Absen Mengajar untuk jadwal yang sedang
    // berlangsung (aturannya ditegakkan di dalam komponennya, bukan di menu).
    $butirJurnal = ['label' => 'Jurnal & Absen Kelas', 'icon' => 'clipboard-check', 'route' => '.jurnal-kelas', 'match' => '.jurnal-kelas'];

    // Label jadwal dibedakan per peran walau rutenya sama: bagi guru isinya
    // memang jadwal mengajarnya sendiri, sedangkan bagi staff & wali murid
    // isinya jadwal orang lain — menyebutnya "Jadwal Mengajar" di sana akan
    // menyesatkan.
    $butirJadwalMengajar  = ['label' => 'Jadwal Mengajar',  'icon' => 'calendar', 'route' => '.jadwal-pelajaran', 'match' => '.jadwal-pelajaran'];
    $butirJadwalPelajaran = ['label' => 'Jadwal Pelajaran', 'icon' => 'calendar', 'route' => '.jadwal-pelajaran', 'match' => '.jadwal-pelajaran'];

    // Jadwal Ekskul dibuka untuk SEMUA peran — isi halamannya yang berbeda:
    // admin menyusun jadwal, pembina mengisi absensi ekskulnya, wali murid
    // melihat riwayat kehadiran anaknya. 'match' memakai '.ekskul*' supaya
    // menunya tetap menyala saat berada di halaman Anggota maupun Absensi.
    $butirEkskul = ['label' => 'Jadwal Ekskul', 'icon' => 'clock', 'route' => '.ekskul', 'match' => '.ekskul*'];

    // RPP: label berbeda per peran walau rutenya sama. Bagi guru isinya
    // dokumen miliknya sendiri; bagi kepala sekolah isinya RPP seluruh guru —
    // menyebutnya "RPP Saya" di sana akan menyesatkan.
    $butirRppGuru = ['label' => 'RPP Saya', 'icon' => 'document-report', 'route' => '.rpp.index', 'match' => '.rpp.*'];
    $butirRppPengawas = ['label' => 'Pantauan RPP Guru', 'icon' => 'document-report', 'route' => '.rpp.index', 'match' => '.rpp.*'];

    /*
     | SIAKAD. Keduanya didaftarkan di beberapa menu sekaligus dan dibiarkan
     | disaring $saring() di bawah: butir yang rutenya tidak terdaftar untuk
     | peran itu otomatis tidak muncul. Guru mendapat "Input Nilai" tanpa
     | "Persetujuan Rapor"; wali kelas mendapat keduanya.
     */
    /*
     | Gerbang & Live Monitoring.
     |
     | Keduanya ditulis sebagai variabel, bukan array literal yang disalin ke
     | tiap menu peran. $saring() di bawah yang menentukan butir mana benar-
     | benar muncul: kalau rutenya tidak terdaftar untuk peran itu, butirnya
     | hilang dengan sendirinya. Jadi menaruhnya di beberapa menu sekaligus
     | AMAN dan tidak pernah menghasilkan tautan yang berujung 404.
     */
    $butirGerbang = ['label' => 'Piket Scan Gerbang', 'icon' => 'shield-check', 'route' => '.gerbang', 'match' => '.gerbang*'];
    $butirLiveMonitoring = ['label' => 'Live Monitoring KBM', 'icon' => 'eye', 'route' => '.live-monitoring', 'match' => '.live-monitoring'];

    $butirInputNilai = ['label' => 'Input Nilai', 'icon' => 'pencil', 'route' => '.nilai', 'match' => '.nilai*'];
    $butirValidasiRapor = ['label' => 'Persetujuan Rapor', 'icon' => 'clipboard-check', 'route' => '.rapor.validasi', 'match' => '.rapor.*'];

    // Guru, Wali Kelas, Staff & Admin TU — urutan sesuai brief, dengan
    // "Scan Siswa" ditambahkan sebagai butir ke-6 (fiturnya tetap dipakai).
    $menuPegawai = [
        $butirDashboard,
        $butirGerbang,
        $butirProfil,
        $butirAbsenHadir,
        $butirIzin,
        $butirIzinGuru,
        $butirAbsenAjar,
        $butirJurnal,
        $butirInputNilai,
        $butirValidasiRapor,
        $butirJadwalMengajar,
        $butirEkskul,
        $butirRppGuru,
        $butirKalender,
        $butirPanduan,
    ];

    /*
     | Wali Murid — enam butir, mengikuti alur yang dilihat orang tua:
     | anaknya masuk gerbang, lalu diikuti per jam pelajaran, lalu direkap.
     |
     | 'Absensi Kedatangan' menunjuk halaman riwayat gerbang yang memang sudah
     | ada sejak awal (dulu jadi isi dashboard). Dashboard-nya sendiri kini
     | ringkasan singkat yang menautkan ke tiga halaman di bawahnya — bukan
     | salinan kedua dari halaman yang sama.
     */
    $menuWaliMurid = [
        $butirDashboard,
        $butirProfil,
        ['label' => 'Absensi Kedatangan',        'icon' => 'qr-code',         'route' => '.absensi-kedatangan', 'match' => '.absensi-kedatangan'],
        ['label' => 'Pantauan KBM Harian',       'icon' => 'eye',             'route' => '.pantauan-kbm',       'match' => '.pantauan-kbm'],
        ['label' => 'Rekap & Laporan Akademik',  'icon' => 'document-report', 'route' => '.rekap-akademik',     'match' => '.rekap-akademik'],
        // Rapor resmi — hanya terisi setelah kepala sekolah menyetujuinya,
        // berbeda dari "Rekap & Laporan Akademik" di atas yang menampilkan
        // kehadiran harian dan berjalan tanpa menunggu persetujuan.
        ['label' => 'Rapor Anak',                'icon' => 'academic-cap',    'route' => '.rapor',              'match' => '.rapor'],
        ['label' => 'Jadwal Pelajaran Anak',     'icon' => 'calendar',        'route' => '.jadwal-pelajaran',   'match' => '.jadwal-pelajaran'],
        $butirEkskul,
        $butirKalender,
        $butirPanduan,
    ];

    /*
     |--------------------------------------------------------------------
     | Kepala Sekolah — lima butir sesuai brief, plus satu submenu.
     |--------------------------------------------------------------------
     | Brief menyebut `@elseif(auth()->user()->role == 'kepsek')`.
     | PERBANDINGAN ITU SELALU FALSE di project ini — kolom `role` di model
     | User di-cast ke enum App\Enums\UserRole, dan objek enum tidak pernah
     | sama dengan string. Kalau ditulis begitu, menu kepsek tidak akan
     | muncul sama sekali dan penyebabnya sulit dilacak karena tidak ada
     | error apa pun, hanya menu yang kosong. Pemilihannya di bawah memakai
     | perbandingan enum yang benar (lihat catatan di kepala file ini).
     |
     | Butir "Manajemen Data Sekolah" TIDAK ada di brief; saya tambahkan
     | karena halaman Kelola Pengguna / Kelas / Siswa milik kepsek sudah ada,
     | berfungsi, dan rutenya tetap terdaftar. Kalau lima butir itu dipasang
     | apa adanya, ketiga halaman itu kehilangan satu-satunya tautannya dan
     | jadi tidak bisa dibuka dari mana pun kecuali mengetik URL. Silakan
     | hapus butir ini kalau memang kepsek tidak boleh lagi mengelola data.
     */
    $menuKepsek = [
        ['label' => 'Dashboard Eksekutif',          'icon' => 'chart-bar',       'route' => '.dashboard',         'match' => '.dashboard'],

        // Ditaruh persis di bawah Dashboard, dan itu disengaja: isinya
        // menjawab pertanyaan yang paling cepat basi ("jam ini ada kelas
        // kosong?"). Menaruhnya di dasar daftar berarti ia baru dibuka
        // setelah jam pelajarannya lewat.
        $butirLiveMonitoring,
        $butirGerbang,

        /*
         |------------------------------------------------------------------
         | KEGIATAN MENGAJAR — kepala sekolah di sini ikut memegang kelas
         |------------------------------------------------------------------
         | Urutannya SENGAJA mengikuti urutan pekerjaannya di lapangan, bukan
         | abjad: lihat jadwal -> absen kehadiran pagi -> scan QR ruangan ->
         | isi daftar hadir siswa.
         |
         | "Absen Kehadiran (Radius)" WAJIB ada di kelompok ini dan tidak
         | boleh dianggap pelengkap. AbsenMengajarQr menolak siapa pun yang
         | belum absen kehadiran pagi itu — tanpa butir ini, kepala sekolah
         | menekan Absen Mengajar, ditolak, dan tidak punya satu pun menu
         | untuk memenuhi syaratnya.
         */
        ['label' => 'Kegiatan Mengajar', 'icon' => 'academic-cap', 'match' => '.jadwal-pelajaran|.absen-lokasi|.absen-mengajar|.jurnal-kelas', 'anak' => [
            $butirJadwalPelajaran,
            $butirAbsenHadir,
            $butirAbsenAjar,
            $butirJurnal,
        ]],

        /*
         |------------------------------------------------------------------
         | PANTAU KEHADIRAN — "siapa yang tidak ada HARI INI"
         |------------------------------------------------------------------
         | Dipisahkan dari Rekap Absensi di bawah, dan pemisahan itu bukan
         | soal kerapian. Keduanya menjawab pertanyaan dengan JANGKA WAKTU
         | yang berbeda: yang ini keadaan hari ini yang masih bisa ditindak
         | sekarang juga, yang itu rekap periode yang sudah lewat.
         */
        ['label' => 'Pantau Kehadiran', 'icon' => 'eye', 'match' => '.pantauan-siswa|.pantauan-pegawai', 'anak' => [
            ['label' => 'Kehadiran Siswa',   'icon' => 'academic-cap', 'route' => '.pantauan-siswa',   'match' => '.pantauan-siswa'],
            ['label' => 'Kehadiran Pegawai', 'icon' => 'briefcase',    'route' => '.pantauan-pegawai', 'match' => '.pantauan-pegawai'],
        ]],

        /*
         |------------------------------------------------------------------
         | PERIZINAN
         |------------------------------------------------------------------
         | Kedua butirnya tampak mirip tapi berbeda tajam, dan bedanya
         | ditulis di label supaya tidak perlu ditebak:
         |
         |   Daftar Izin Pegawai  -> HANYA MENAMPILKAN izin yang sudah
         |                           berlaku. Tidak ada tombol apa pun.
         |   Persetujuan Izin Guru -> satu-satunya tempat izin guru bisa
         |                           disetujui. Tanpa dibuka, setiap
         |                           pengajuan mengendap Pending dan absensi
         |                           gurunya terhitung ALPA di akhir bulan.
         */
        ['label' => 'Perizinan', 'icon' => 'clipboard-check', 'match' => '.daftar-izin-pegawai|.persetujuan-izin-guru*', 'anak' => [
            $butirPersetujuanIzinGuru,
            ['label' => 'Daftar Izin Pegawai', 'icon' => 'document-report', 'route' => '.daftar-izin-pegawai', 'match' => '.daftar-izin-pegawai'],
        ]],

        /*
         |------------------------------------------------------------------
         | REKAP ABSENSI — periode yang sudah lewat
         |------------------------------------------------------------------
         | Tiga butir yang urutannya menjawab pertanyaan bersambung:
         |   1. Laporan & Rekapitulasi  -> kehadiran di GERBANG per siswa
         |   2. Rekap KBM per Jadwal    -> kehadiran di KELAS per jam
         |   3. Laporan Bulanan (PDF)   -> berkasnya untuk rapat komite
         |
         | Selisih antara nomor 1 dan 2 persis yang dicari kepala sekolah:
         | anak yang masuk gerbang pagi lalu hilang di jam ketiga.
         */
        ['label' => 'Rekap Absensi', 'icon' => 'document-report', 'match' => '.laporan|.rekap-kbm*|.laporan-bulanan*', 'anak' => [
            ['label' => 'Laporan & Rekapitulasi', 'icon' => 'document-report', 'route' => '.laporan',         'match' => '.laporan'],
            ['label' => 'Rekap KBM per Jadwal',   'icon' => 'calendar',        'route' => '.rekap-kbm',       'match' => '.rekap-kbm*'],
            ['label' => 'Laporan Bulanan (PDF)',  'icon' => 'download',        'route' => '.laporan-bulanan', 'match' => '.laporan-bulanan*'],
        ]],

        ['label' => 'Manajemen Data Sekolah',       'icon' => 'users',           'match' => '.users.*|.kelas.*|.siswa.*', 'anak' => [
            ['label' => 'Pengguna', 'icon' => 'users',          'route' => '.users.index', 'match' => '.users.*'],
            ['label' => 'Kelas',    'icon' => 'academic-cap',   'route' => '.kelas.index', 'match' => '.kelas.*'],
            ['label' => 'Siswa',    'icon' => 'identification', 'route' => '.siswa.index', 'match' => '.siswa.*'],
        ]],

        /*
         | Tiga butir di bawah SENGAJA berdiri sendiri, tidak dikelompokkan.
         | Ketiganya TINDAKAN yang dikerjakan kepala sekolah, bukan laporan
         | yang dibaca — dan tindakan yang disembunyikan di dalam submenu
         | cenderung tidak dikerjakan. Persetujuan Rapor khususnya: selama
         | belum ditekan, seluruh wali murid melihat halaman rapor kosong.
         */
        $butirValidasiRapor,
        $butirRppPengawas,
        ['label' => 'Kelola Pengumuman',            'icon' => 'bell',            'route' => '.pengumuman',        'match' => '.pengumuman'],

        /*
         | DIHAPUS: 'Jadwal Ekskul' (.ekskul).
         |
         | Jadwal ekskul adalah pekerjaan pembina ekskul dan staff kesiswaan.
         | Rutenya ikut dicabut di routes/web.php — bukan hanya butirnya
         | disembunyikan di sini.
         */

        $butirKalender,

        // Kepsek juga pegawai yang kehadirannya dicatat, jadi ia punya
        // halaman pengajuan izin untuk dirinya sendiri — ditaruh di grup
        // "Lainnya" bersama Profil supaya tidak tercampur dengan menu
        // pengawasan di atas.
        array_merge($butirIzin, ['grup' => 'Lainnya']),
        $butirPanduan,
    ];

    // Guru Piket & peran lain: daftar lengkap, $saring() yang menentukan
    // butir mana yang benar-benar muncul.
    $menuUmum = [
        $butirDashboard,
        $butirAbsenHadir,
        $butirIzin,
        $butirIzinGuru,
        $butirAbsenAjar,
        $butirJurnal,
        $butirGerbang,
        $butirJadwalPelajaran,
        $butirEkskul,
        $butirRppGuru,
        ['label' => 'Laporan Bulanan',  'icon' => 'document-report', 'route' => '.laporan',     'match' => '.laporan'],
        ['label' => 'Pengguna',         'icon' => 'users',           'route' => '.users.index', 'match' => '.users.*'],
        ['label' => 'Kelas',            'icon' => 'academic-cap',    'route' => '.kelas.index', 'match' => '.kelas.*'],
        ['label' => 'Siswa',            'icon' => 'identification',  'route' => '.siswa.index', 'match' => '.siswa.*'],
        $butirProfil,
        $butirKalender,
        $butirPanduan,
    ];

    $menuPeran = match ($pengguna?->role) {
        UserRole::SuperAdmin => $menuSuperAdmin,
        UserRole::Kepsek => $menuKepsek,
        UserRole::Guru, UserRole::WaliKelas, UserRole::Staff, UserRole::AdminTu => $menuPegawai,
        UserRole::WaliMurid => $menuWaliMurid,
        default => $menuUmum,
    };

    // Buang butir yang rutenya tidak ada untuk role ini (juga menyaring anak
    // submenu; induk yang kehabisan anak ikut dibuang).
    $adaRute = fn ($akhiran) => $panelPrefix !== '' && Route::has($panelPrefix . $akhiran);

    $saring = function (array $daftar) use ($adaRute, &$saring) {
        $hasil = [];
        foreach ($daftar as $butir) {
            if (isset($butir['anak'])) {
                $butir['anak'] = $saring($butir['anak']);
                if ($butir['anak']) {
                    $hasil[] = $butir;
                }
            } elseif ($adaRute($butir['route'])) {
                $hasil[] = $butir;
            }
        }
        return $hasil;
    };

    $menu = $saring($menuPeran);

    /*
     | Satu butir dianggap aktif kalau rute sekarang cocok dengan salah satu
     | pola di 'match' (dipisah "|" untuk butir bersubmenu).
     |
     | Dua butir bisa menunjuk rute yang SAMA tapi tab berbeda (mis. Approval
     | dan Pengaturan Sistem sama-sama ke '.pengaturan'). Tanpa penyaring
     | tambahan keduanya akan menyala bersamaan dan nav-nya terlihat rusak.
     | Karena itu ada dua kunci opsional:
     |   'query'      => butir hanya aktif kalau query string-nya cocok
     |   'kecualiTab' => butir TIDAK aktif kalau ?tab= bernilai salah satu ini
     */
    $sedangAktif = function (array $butir) use ($panelPrefix) {
        $cocok = false;
        foreach (explode('|', $butir['match']) as $pola) {
            if (request()->routeIs($panelPrefix . $pola)) {
                $cocok = true;
                break;
            }
        }

        if (! $cocok) {
            return false;
        }

        foreach ($butir['query'] ?? [] as $kunci => $nilai) {
            if ((string) request()->query($kunci) !== (string) $nilai) {
                return false;
            }
        }

        if (in_array((string) request()->query('tab'), $butir['kecualiTab'] ?? [], true)) {
            return false;
        }

        return true;
    };
@endphp

@php
    /*
     | Pengelompokan butir menjadi judul-judul bergaya TailAdmin ("MENU",
     | "LAINNYA"). Urutan grup mengikuti urutan kemunculan pertamanya, jadi
     | menambah grup baru cukup dengan menulis 'grup' => '...' pada butirnya.
     */
    $grupMenu = [];
    foreach ($menu as $butir) {
        $grupMenu[$butir['grup'] ?? 'Menu'][] = $butir;
    }
@endphp

{{-- Lebar 290px mengikuti TailAdmin. Di layar besar sidebar ini BUKAN
     fixed melainkan bagian dari flexbox di layouts/app.blade.php — itu yang
     membuat isi halaman menyesuaikan sendiri saat sidebar diciutkan, tanpa
     perlu menghitung padding kiri di dua tempat.

     Class .sidebar dipakai oleh aturan "mode ciut" di resources/css/app.css;
     jangan dihapus walau terlihat tidak mengubah apa-apa di sini. --}}
<aside id="sidebar"
    class="sidebar fixed inset-y-0 left-0 z-50 flex w-[290px] shrink-0 -translate-x-full flex-col border-r border-gray-200 bg-white transition-all duration-300 ease-out motion-reduce:transition-none lg:static lg:translate-x-0 dark:border-gray-800 dark:bg-gray-900 print:hidden"
    aria-label="Navigasi utama">

    {{-- ---- Merek ---------------------------------------------------- --}}
    <div class="sidebar-merek flex items-center gap-3 px-5 pb-6 pt-7">
        @php $adaDashboard = $panelPrefix !== '' && Route::has($panelPrefix . '.dashboard'); @endphp

        <{{ $adaDashboard ? 'a' : 'div' }}
            @if ($adaDashboard) href="{{ route($panelPrefix . '.dashboard') }}" @endif
            class="flex min-w-0 flex-1 items-center gap-3 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-lg">
            <img src="{{ asset('logo-mark.png') }}" alt="" class="h-10 w-10 shrink-0 object-contain">
            <span class="label-menu min-w-0">
                <span class="block truncate text-[15px] font-extrabold leading-tight tracking-tight text-gray-800 dark:text-gray-100">{{ config('sekolah.aplikasi') }}</span>
                <span class="block truncate text-[11px] font-medium text-gray-400 dark:text-gray-500">{{ config('sekolah.tagline') }}</span>
            </span>
        </{{ $adaDashboard ? 'a' : 'div' }}>

        <button type="button" id="sidebar-close"
            class="label-menu flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 motion-reduce:transition-none lg:hidden dark:hover:bg-gray-800 dark:hover:text-gray-300"
            aria-label="Tutup menu">
            <x-icon name="x-mark" class="h-5 w-5" />
        </button>
    </div>

    {{-- ---- Daftar menu ---------------------------------------------- --}}
    <nav class="no-scrollbar flex-1 overflow-y-auto px-4 pb-4">
        @forelse ($grupMenu as $namaGrup => $butirGrup)
            <div class="mb-6 last:mb-0">
                {{-- Judul grup menghilang saat sidebar diciutkan dan diganti
                     tiga titik — persis perilaku TailAdmin. Tanpa penggantinya,
                     jarak antar grup terlihat seperti celah tak disengaja. --}}
                <h3 class="mb-3 px-3 text-xs font-semibold uppercase leading-5 tracking-wider text-gray-400 dark:text-gray-500">
                    <span class="judul-grup">{{ $namaGrup }}</span>
                    <span class="ikon-grup" aria-hidden="true">&middot;&middot;&middot;</span>
                </h3>

                <ul class="space-y-1">
                    @foreach ($butirGrup as $butir)
                        @php $aktif = $sedangAktif($butir); @endphp

                        <li>
                            @isset($butir['anak'])
                                {{-- Butir bersubmenu: <details> dipakai supaya buka/tutupnya
                                     jalan tanpa JavaScript sama sekali, dan otomatis terbuka
                                     saat salah satu anaknya sedang dibuka. --}}
                                <details class="group/menu" @if ($aktif) open @endif>
                                    {{-- Class .menu-item / .menu-item-active /
                                         .menu-item-inactive datang dari TailAdmin,
                                         didefinisikan sebagai @utility di
                                         resources/css/app.css. --}}
                                    <summary class="menu-item group {{ $aktif ? 'menu-item-active' : 'menu-item-inactive' }} cursor-pointer list-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                        <x-icon name="{{ $butir['icon'] }}" class="h-5 w-5 shrink-0 {{ $aktif ? 'menu-item-icon-active' : 'menu-item-icon-inactive' }}" />
                                        {{-- Label dibiarkan MEMBUNGKUS, bukan dipotong dengan
                                             truncate. Butir seperti "Pantauan Kehadiran Pegawai"
                                             dan "Pantauan Kehadiran Siswa" sama-sama terpotong
                                             jadi "Pantauan Kehadiran P…" — dua menu berbeda
                                             yang terlihat identik. --}}
                                        <span class="label-menu flex-1 leading-snug">{{ $butir['label'] }}</span>
                                        <x-icon name="chevron-down" class="panah-menu h-4 w-4 shrink-0 text-gray-400 transition-transform group-open/menu:rotate-180 motion-reduce:transition-none" />
                                    </summary>

                                    <ul class="submenu ml-6 mt-1 space-y-1 border-l border-gray-200 pl-3 dark:border-gray-800">
                                        @foreach ($butir['anak'] as $anak)
                                            @php $anakAktif = $sedangAktif($anak); @endphp
                                            <li>
                                                <a href="{{ route($panelPrefix . $anak['route'], $anak['query'] ?? []) }}"
                                                    @if ($anakAktif) aria-current="page" @endif
                                                    class="menu-dropdown-item {{ $anakAktif ? 'menu-dropdown-item-active' : 'menu-dropdown-item-inactive' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                                    <x-icon name="{{ $anak['icon'] }}" class="h-4 w-4 shrink-0 {{ $anakAktif ? 'text-brand-500 dark:text-brand-400' : 'text-gray-400' }}" />
                                                    <span class="truncate">{{ $anak['label'] }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </details>
                            @else
                                <a href="{{ route($panelPrefix . $butir['route'], $butir['query'] ?? []) }}"
                                    @if ($aktif) aria-current="page" @endif
                                    title="{{ $butir['label'] }}"
                                    class="menu-item group {{ $aktif ? 'menu-item-active' : 'menu-item-inactive' }} focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                    <x-icon name="{{ $butir['icon'] }}" class="h-5 w-5 shrink-0 {{ $aktif ? 'menu-item-icon-active' : 'menu-item-icon-inactive' }}" />
                                    <span class="label-menu flex-1 leading-snug">{{ $butir['label'] }}</span>
                                </a>
                            @endisset
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="px-3 text-sm text-gray-400">Tidak ada menu untuk peran ini.</p>
        @endforelse
    </nav>

    {{-- ---- Kartu pengguna -------------------------------------------- --}}
    @if ($pengguna)
        @php
            $inisialSidebar = Str::of($pengguna->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
            $adaProfil = $panelPrefix !== '' && Route::has($panelPrefix . '.profil');
        @endphp

        <div class="kartu-pengguna m-4 rounded-2xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="flex items-center gap-3">
                {{-- Kartu ini SEKALIGUS pintu masuk ke halaman Profil Saya.
                     Dibuat begitu daripada menambah butir menu baru: avatar
                     adalah tempat yang sudah dicari orang untuk mengurus
                     akunnya sendiri. Route::has() menjaga supaya tetap aman
                     kalau rutenya belum terdaftar untuk peran ini. --}}
                <{{ $adaProfil ? 'a' : 'div' }}
                    @if ($adaProfil) href="{{ route($panelPrefix . '.profil') }}" @endif
                    class="flex min-w-0 flex-1 items-center gap-3 rounded-xl {{ $adaProfil ? 'transition-colors hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:hover:bg-white/5' : '' }}">

                    {{-- Inisial SELALU dirender sebagai dasar, foto ditumpuk di
                         atasnya. Kalau berkas fotonya hilang (mis. `php artisan
                         storage:link` belum dijalankan), onerror membuang <img>-nya
                         dan yang tersisa adalah inisial — bukan ikon "gambar rusak"
                         yang terlihat seperti aplikasi error. --}}
                    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-brand-500 text-xs font-bold text-white">
                        {{ $inisialSidebar }}
                        @if ($pengguna->foto_url)
                            <img src="{{ $pengguna->foto_url }}" alt="" onerror="this.remove()"
                                class="absolute inset-0 h-full w-full rounded-full object-cover">
                        @endif
                    </span>

                    <span class="label-menu min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-semibold leading-tight text-gray-800 dark:text-gray-200">{{ $pengguna->name }}</span>
                        <span class="block truncate text-[11px] text-gray-400 dark:text-gray-500">{{ $adaProfil ? 'Lihat profil saya' : $pengguna->role->label() }}</span>
                    </span>
                </{{ $adaProfil ? 'a' : 'div' }}>

                <form method="POST" action="{{ route('logout') }}" class="label-menu">
                    @csrf
                    <button type="submit"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition-colors hover:bg-white hover:text-error-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-error-500 motion-reduce:transition-none dark:hover:bg-white/5 dark:hover:text-error-500"
                        aria-label="Keluar">
                        <x-icon name="logout" class="h-4 w-4" />
                    </button>
                </form>
            </div>
        </div>
    @endif
</aside>
