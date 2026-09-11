<?php

use App\Http\Controllers\DeployController;
use App\Http\Controllers\FcmTokenController;
use App\Http\Controllers\LaporanBulananController;
use App\Http\Controllers\RekapKbmController;
use App\Http\Controllers\ValidasiRaporController;
use App\Http\Controllers\RppController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Guru\NilaiController;
use App\Http\Controllers\Guru\WaliKelasController;
use App\Http\Controllers\Kepsek\KelasController;
use App\Http\Controllers\Kepsek\KepsekController;
use App\Http\Controllers\Kepsek\LaporanController;
use App\Http\Controllers\Kepsek\SiswaController;
use App\Http\Controllers\Kepsek\SiswaQrController;
use App\Http\Controllers\Kepsek\UserController;
use App\Http\Controllers\Ortu\OrtuController;
use App\Http\Controllers\Ortu\RaporWaliMuridController;
use App\Http\Controllers\Pegawai\PegawaiDashboardController;
use App\Http\Controllers\Piket\ScannerController;
use App\Http\Controllers\SuperAdmin\AbsensiPegawaiLaporanController;
use App\Http\Controllers\SuperAdmin\JadwalPelajaranController;
use App\Http\Controllers\SuperAdmin\KenaikanKelasController;
use App\Http\Controllers\SuperAdmin\LaporanHarianController;
use App\Http\Controllers\SuperAdmin\PegawaiController;
use App\Http\Controllers\SuperAdmin\PegawaiQrController;
use App\Http\Controllers\SuperAdmin\SettingController;
use App\Http\Controllers\SuperAdmin\SuperAdminController;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman utama
|--------------------------------------------------------------------------
| Belum login -> dilempar ke halaman login.
| Sudah login  -> langsung dilempar ke dashboard sesuai role-nya, jadi
| bookmark ke "/" selalu membawa tiap user ke tempat yang benar.
| Satu-satunya sumber pemetaan role -> rute ada di UserRole::dashboardRouteName().
*/
Route::get('/', function (Request $request) {
    if ($user = $request->user()) {
        return redirect()->route($user->role->dashboardRouteName());
    }

    return redirect()->route('login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
| Satu halaman login untuk SEMUA 8 role — tidak ada halaman login terpisah
| per role. Setelah berhasil login, AuthController::store() yang menentukan
| redirect berdasarkan role user (lihat UserRole::dashboardRouteName()).
*/
/*
|--------------------------------------------------------------------------
| Endpoint deploy — DI LUAR middleware 'auth', dan memang harus begitu
|--------------------------------------------------------------------------
| GitHub Actions memanggilnya dengan cURL, bukan lewat browser yang login.
| Pengamannya token, bukan sesi — lihat catatan panjang di DeployController.
|
| throttle:6,1 = maksimal 6 permintaan per menit per IP. Ini yang menahan
| percobaan menebak token: tanpa throttle, sebuah skrip bisa mencoba ribuan
| kombinasi per menit dan endpoint ini jadi pintu belakang yang terbuka.
|
| POST, bukan GET: token dikirim lewat header, dan permintaan POST tidak
| pernah ikut ter-bookmark, ter-prefetch browser, atau tercatat di riwayat.
| Query string ?token= tetap diterima sebagai cadangan, tapi jangan dipakai
| kalau bisa dihindari — query string tercatat apa adanya di access log.
*/
Route::post('/deploy/bersihkan', [DeployController::class, 'bersihkan'])
    ->middleware('throttle:6,1')
    ->name('deploy.bersihkan');

/*
| Penanda versi rilis yang sedang berjalan. Dipanggil berkala oleh halaman
| yang SEDANG TERBUKA di browser pengguna, supaya tab yang sudah dibuka
| sejak pagi tahu ada pembaruan tanpa perlu ditutup dulu.
|
| Tanpa autentikasi dengan sengaja — isinya hanya satu angka waktu, dan
| pemantaunya harus tetap bekerja di halaman login sekalipun.
*/
Route::get('/versi.json', [DeployController::class, 'versi'])
    ->middleware('throttle:60,1')
    ->name('versi');

Route::middleware('guest')->group(function () {
    // Login & Registrasi adalah komponen Livewire full-page, jadi rutenya
    // menunjuk langsung ke kelas komponen — tidak ada lagi POST /login
    // terpisah, karena form-nya disubmit lewat endpoint Livewire.
    //
    // KONSEKUENSI PENTING: middleware `throttle:5,1` yang dulu menempel di
    // POST /login otomatis tidak berlaku lagi. Pembatasan percobaan login
    // sekarang dikerjakan di dalam App\Livewire\Auth\Login memakai
    // RateLimiter, dengan aturan yang sama (5 percobaan / menit / email+IP).
    Route::get('/login', Login::class)->name('login');

    // Pendaftaran mandiri. Akun yang dibuat berstatus 'pending' dan masuk ke
    // antrean Super Admin > Pengaturan Sistem > Approval Akun Baru.
    Route::get('/daftar', Register::class)->name('register');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    /*
    |----------------------------------------------------------------------
    | Token perangkat untuk Notifikasi HP (Web Push / Firebase)
    |----------------------------------------------------------------------
    | Dipanggil browser di latar belakang, BUKAN dibuka manusia — tidak ada
    | halaman apa pun di balik dua rute ini.
    |
    | Berada DI DALAM grup 'auth' dan tanpa 'role:...' apa pun: semua peran
    | boleh memasang notifikasi di HP-nya. Yang menentukan token itu milik
    | siapa adalah SESI yang sedang login, bukan isi permintaannya — karena
    | itu tidak ada parameter user_id di mana pun. Kalau ada, siapa saja
    | bisa menempelkan token perangkatnya sendiri ke akun orang lain dan
    | sejak itu ikut menerima notifikasi kehadiran anak orang tersebut.
    |
    | throttle:30,1 — pemanggilnya skrip yang jalan otomatis di setiap
    | pembukaan halaman: longgar untuk pemakaian wajar, tetap menahan skrip
    | yang macet dalam perulangan.
    */
    Route::post('/fcm/token', [FcmTokenController::class, 'simpan'])
        ->middleware('throttle:30,1')
        ->name('fcm.token.simpan');

    Route::delete('/fcm/token', [FcmTokenController::class, 'hapus'])
        ->middleware('throttle:30,1')
        ->name('fcm.token.hapus');

    /*
    |----------------------------------------------------------------------
    | Rute per role — 8 role, 8 prefix URL, 8 middleware 'role:...' TERPISAH
    | (masing-masing grup HANYA menerima satu role, tidak ada middleware
    | multi-role di sini). Ini sengaja dibuat ketat: satu grup rute = satu
    | role saja, supaya tidak mungkin ada kebocoran akses antar role bahkan
    | secara tidak sengaja. Contoh: wali_murid yang mencoba membuka
    | /kepsek/dashboard akan menerima 403, bukan ikut masuk — begitu juga
    | kepsek yang mencoba membuka /super-admin/pegawai akan ditolak, walau
    | dua-duanya "level admin".
    |----------------------------------------------------------------------
    */

    // Profil Saya — SETIAP role punya, termasuk Super Admin, Wali Murid, dan
    // Guru Piket. Ini satu-satunya halaman yang tidak dibeda-bedakan per peran:
    // semua orang berhak mengubah foto, kontak, dan kata sandinya sendiri.
    $daftarkanProfil = function () {
        Route::view('/profil', 'profil')->name('profil');
    };

    // Absen Kehadiran (Radius) — halaman yang memuat komponen Livewire
    // App\Livewire\AbsenGuru: pegawai menekan tombol, browser mengambil
    // koordinat GPS, server menghitung jaraknya ke sekolah.
    //
    // "ABSENSI BIASA" SUDAH DIHAPUS. Dulu ada POST /absensi-mandiri —
    // tombol "Absen Masuk" sekali klik tanpa verifikasi apa pun — beserta
    // App\Http\Controllers\Pegawai\PegawaiAbsensiController dan partial
    // resources/views/partials/kartu-absensi-mandiri.blade.php. Ketiganya
    // dihapus, bukan sekadar disembunyikan tombolnya: selama rutenya masih
    // terdaftar, siapa pun bisa mengirim POST ke situ dari mana saja dan
    // tercatat hadir tanpa pernah ke sekolah. Sekarang kehadiran pegawai
    // hanya bisa masuk lewat tiga jalur yang semuanya terverifikasi:
    // Absen Radius (GPS), scan QR di gerbang oleh Guru Piket, atau input
    // manual oleh admin.
    //
    // Super Admin tetap tidak mendapat rute ini — sesuai aturan "Super Admin
    // tidak punya fitur absensi".
    $daftarkanAbsensiMandiri = function () {
        Route::view('/absen-lokasi', 'pegawai.absen-lokasi')->name('absen-lokasi');
    };

    // Pengajuan Izin — komponen App\Livewire\Pegawai\FormIzin.
    //
    // Closure TERPISAH dari $daftarkanAbsensiMandiri walau keduanya sama-sama
    // "absensi milik diri sendiri": Guru Piket memakai absen radius tapi tidak
    // diberi pengajuan izin di tahap ini, dan menggabungkan keduanya berarti
    // satu peran mendapat halaman yang belum disepakati hanya karena kebetulan
    // berbagi closure.
    $daftarkanPengajuanIzin = function () {
        Route::view('/pengajuan-izin', 'pegawai.form-izin')->name('pengajuan-izin');
    };

    // Kelola Pengumuman — App\Livewire\Pengumuman\KelolaPengumuman.
    // HANYA untuk Super Admin & Kepala Sekolah. Hak aksesnya ditegakkan dua
    // kali: di sini (rutenya tidak didaftarkan untuk peran lain) DAN di dalam
    // setiap method komponennya — karena method Livewire adalah endpoint HTTP
    // tersendiri yang bisa dipanggil dari konsol browser oleh siapa pun yang
    // sudah login.
    $daftarkanPengumuman = function () {
        Route::view('/pengumuman', 'pengumuman.kelola')->name('pengumuman');
    };

    /*
     |--------------------------------------------------------------------
     | Manajemen RPP — App\Http\Controllers\RppController
     |--------------------------------------------------------------------
     | DUA closure, bukan satu, karena hak aksesnya memang berbeda bentuk:
     | guru punya create/store/destroy, kepala sekolah tidak. Rute yang tidak
     | didaftarkan jauh lebih kuat daripada tombol yang disembunyikan —
     | menembak URL-nya langsung menghasilkan 404, bukan halaman yang terbuka.
     |
     | Lapis keduanya App\Policies\RppPolicy, yang dipanggil di setiap
     | method controller. Dua lapis ini bukan berlebihan: yang pertama
     | menjaga PERAN, yang kedua menjaga KEPEMILIKAN (guru A tidak boleh
     | membuka RPP guru B, padahal rutenya sama-sama terdaftar untuk keduanya).
     */
    $daftarkanRppGuru = function () {
        Route::get('/rpp', [RppController::class, 'index'])->name('rpp.index');
        Route::get('/rpp/create', [RppController::class, 'create'])->name('rpp.create');
        Route::post('/rpp', [RppController::class, 'store'])->name('rpp.store');

        // {rpp} dibatasi angka supaya '/rpp/create' di atas tidak pernah
        // tertangkap sebagai id — sumber 404 yang membingungkan.
        Route::get('/rpp/{rpp}', [RppController::class, 'show'])
            ->whereNumber('rpp')->name('rpp.show');

        Route::get('/rpp/{rpp}/berkas', [RppController::class, 'berkas'])
            ->whereNumber('rpp')->name('rpp.berkas');

        Route::delete('/rpp/{rpp}', [RppController::class, 'destroy'])
            ->whereNumber('rpp')->name('rpp.destroy');
    };

    // Kepala Sekolah (& Super Admin): hanya baca. Tidak ada create, store,
    // maupun destroy di sini — dan itu disengaja.
    $daftarkanRppPengawas = function () {
        Route::get('/rpp', [RppController::class, 'index'])->name('rpp.index');

        Route::get('/rpp/{rpp}', [RppController::class, 'show'])
            ->whereNumber('rpp')->name('rpp.show');

        Route::get('/rpp/{rpp}/berkas', [RppController::class, 'berkas'])
            ->whereNumber('rpp')->name('rpp.berkas');
    };

    // Jadwal Ekstrakurikuler — App\Livewire\Ekskul\KelolaEkskul.
    // HANYA untuk Super Admin & Kepala Sekolah, sama seperti Pengumuman.
    // Hak aksesnya ditegakkan dua kali: di sini (rutenya tidak didaftarkan
    // untuk peran lain) DAN di dalam setiap method komponennya.
    $daftarkanEkskul = function () {
        // Daftar jadwal: DIBUKA UNTUK SEMUA PERAN. Yang membedakan bukan
        // rutenya melainkan isi halamannya — form tambah/ubah hanya muncul
        // untuk Super Admin & Kepala Sekolah, dan komponennya memeriksa
        // ulang hak akses di setiap aksi.
        Route::view('/ekskul', 'ekskul.index')->name('ekskul');

        // Anggota: admin/kepsek + pembina ekskul yang bersangkutan.
        // Absensi : pembina mengisi, wali murid melihat riwayat anaknya,
        //           peran lain melihat rekap. Pemisahannya di komponen.
        //
        // Route::view (bukan rute closure) supaya `php artisan route:cache`
        // tetap bisa dijalankan di server — closure membuat perintah itu
        // gagal total. Id jadwalnya dibaca view lewat request()->route().
        Route::view('/ekskul/{jadwal}/anggota', 'ekskul.anggota')
            ->whereNumber('jadwal')->name('ekskul.anggota');

        Route::view('/ekskul/{jadwal}/absensi', 'ekskul.absensi')
            ->whereNumber('jadwal')->name('ekskul.absensi');
    };

    // Absen Mengajar (QR) — guru men-scan stiker QR ruangan saat masuk kelas.
    // Didaftarkan TERPISAH dari absen kehadiran karena penerimanya berbeda:
    // kepsek & guru piket tidak mengajar, jadi mereka tidak mendapatkannya.
    $daftarkanAbsenMengajar = function () {
        Route::view('/absen-mengajar', 'absen-mengajar')->name('absen-mengajar');
    };

    // Scanner kamera siswa versi Livewire (App\Livewire\ScannerKameraSiswa).
    // Berbeda dengan halaman Piket di /piket/scanner yang berdiri sendiri
    // sebagai layar gerbang, halaman ini tampil di dalam layout utama dan
    // ditujukan untuk guru/wali kelas yang men-scan siswa di dalam kelas.
    // Tidak didaftarkan untuk staff & admin_tu (tidak memegang kelas) maupun
    // Super Admin (tidak punya fitur absensi).
    $daftarkanScannerSiswa = function () {
        Route::view('/scanner-siswa', 'pegawai.scanner-siswa')->name('scanner-siswa');
    };

    // Jurnal & Absen Kelas — komponen App\Livewire\Guru\JurnalAbsenKelas.
    // Hanya untuk yang benar-benar mengajar (guru & wali kelas); staff dan
    // admin TU tidak mendapatkannya. Aturan "harus sudah scan QR ruangan"
    // ditegakkan DI DALAM komponen, bukan di rute — karena syaratnya berubah
    // tiap jam pelajaran, bukan sesuatu yang bisa dijawab middleware.
    $daftarkanJurnalKelas = function () {
        Route::view('/jurnal-kelas', 'jurnal-kelas')->name('jurnal-kelas');
    };

    // Halaman khusus wali murid: pantauan KBM harian & rekap akademik.
    $daftarkanPantauanAnak = function () {
        Route::view('/pantauan-kbm', 'pantauan-kbm')->name('pantauan-kbm');
        Route::view('/rekap-akademik', 'rekap-akademik')->name('rekap-akademik');
    };

    // Jadwal Pelajaran (HANYA BACA) — komponen App\Livewire\JadwalPelajaran.
    //
    // Halaman ini MENGGANTIKAN rute lama 'jadwal-mengajar' beserta
    // JadwalMengajarController dan view pegawai/jadwal-mengajar.blade.php.
    // Dua halaman yang menampilkan jadwal yang sama hanya menimbulkan
    // pertanyaan "yang mana yang benar" begitu salah satunya diperbaiki dan
    // yang lain tidak — jadi yang lama dihapus, bukan sekadar dibiarkan.
    //
    // Isinya menyesuaikan peran secara otomatis di dalam komponen:
    //   guru & wali kelas -> jadwal mengajarnya sendiri
    //   wali murid        -> jadwal kelas anaknya
    //   staff             -> seluruh jadwal sekolah
    // Super Admin TIDAK mendapat rute ini: ia sudah punya menu CRUD-nya
    // sendiri di /super-admin/jadwal yang memuat data sama plus kemampuan
    // mengubahnya.
    $daftarkanJadwalPelajaran = function () {
        Route::view('/jadwal-pelajaran', 'jadwal-pelajaran')->name('jadwal-pelajaran');
    };

    // Layar pengawasan Kepala Sekolah — KEDUANYA HANYA BACA.
    //
    // Didaftarkan hanya untuk grup kepsek, bukan lewat closure bersama:
    // Super Admin sudah punya Laporan Absensi Harian yang menjawab pertanyaan
    // yang sama, dan menambah dua halaman lagi ke panelnya hanya membuat menu
    // 6 butir yang sudah rapi jadi bercabang tanpa manfaat baru.
    $daftarkanPantauanKepsek = function () {
        Route::view('/pantauan-pegawai', 'pantauan-pegawai')->name('pantauan-pegawai');
        Route::view('/pantauan-siswa', 'pantauan-siswa')->name('pantauan-siswa');

        // Rekap pengajuan izin & sakit pegawai — App\Livewire\Kepsek\DaftarIzinPegawai.
        // Sama seperti dua di atas: tidak ada satu pun aksi yang mengubah data.
        Route::view('/daftar-izin-pegawai', 'daftar-izin-pegawai')->name('daftar-izin-pegawai');
    };

    // Kumpulan rute panel administrasi kesiswaan (dashboard, laporan, CRUD
    // Pengguna/Kelas/Siswa) — isinya PERSIS SAMA untuk role kepsek maupun
    // super_admin (controller & view yang dipakai identik), makanya
    // didaftarkan lewat closure ini supaya tidak ada duplikasi/typo antara
    // dua grup di bawah. Yang membedakan hanya prefix URL & middleware role.
    $daftarkanPanelAdminKesiswaan = function () {

        Route::get('/dashboard', [KepsekController::class, 'index'])->name('dashboard');

        Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan');

        Route::resource('users', UserController::class)->except(['show']);

        // ->parameters(...) dipasang eksplisit: "kelas" berakhiran huruf
        // "s" dan bisa salah di-singularize otomatis oleh Laravel
        // (mis. jadi {kela}), yang akan membuat route-model-binding ke
        // KelasController tidak nyambung dengan nama parameter di
        // controller ($kelas). Dipasang juga di "siswa" biar konsisten
        // & tidak bergantung pada tebakan pluralizer.
        Route::resource('kelas', KelasController::class)
            ->except(['show'])
            ->parameters(['kelas' => 'kelas']);

        Route::resource('siswa', SiswaController::class)
            ->except(['show'])
            ->parameters(['siswa' => 'siswa']);

        Route::get('/siswa/{siswa}/qr', [SiswaQrController::class, 'show'])->name('siswa.qr');
        Route::get('/kelas/{kelas}/qr-siswa', [SiswaQrController::class, 'kelas'])->name('siswa.qr-kelas');
    };

    /*
    |----------------------------------------------------------------------
    | SIAKAD — Input Nilai (guru & wali kelas)
    |----------------------------------------------------------------------
    | Dipakai dua grup peran yang sama-sama mengajar. Ditulis sebagai closure
    | yang dipanggil di keduanya, bukan disalin — dua salinan rute berarti
    | perbaikan di satu tempat diam-diam tidak ikut di tempat lain.
    */
    $daftarkanInputNilai = function () {
        Route::get('/nilai', [NilaiController::class, 'index'])->name('nilai');
        Route::post('/nilai', [NilaiController::class, 'simpan'])->name('nilai.simpan');
    };

    /*
    |----------------------------------------------------------------------
    | SIAKAD — Validasi & Persetujuan Rapor
    |----------------------------------------------------------------------
    | Halaman yang SAMA dibuka empat peran, dengan kewenangan berbeda:
    |   wali kelas & admin TU -> boleh Mengajukan
    |   kepala sekolah        -> boleh Menyetujui / Mengembalikan
    |   super admin           -> keduanya
    |
    | Pembedanya BUKAN rute, melainkan pemeriksaan peran di dalam
    | ValidasiRaporController. Membuat empat rute berbeda berarti empat tempat
    | yang harus ingat aturan yang sama.
    */
    $daftarkanValidasiRapor = function () {
        Route::get('/rapor/validasi', [ValidasiRaporController::class, 'index'])->name('rapor.validasi');
        Route::post('/rapor/{kelas}/ajukan', [ValidasiRaporController::class, 'ajukan'])->name('rapor.ajukan');
        Route::post('/rapor/{kelas}/setujui', [ValidasiRaporController::class, 'setujuiRapor'])->name('rapor.setujui');
        Route::post('/rapor/{kelas}/kembalikan', [ValidasiRaporController::class, 'kembalikan'])->name('rapor.kembalikan');
    };

    /*
    |----------------------------------------------------------------------
    | Laporan Bulanan (Executive View)
    |----------------------------------------------------------------------
    | Dipakai DUA grup: kepsek dan super_admin. Ditulis sebagai closure yang
    | dipanggil di keduanya, bukan disalin dua kali — dua salinan rute
    | berarti perbaikan di satu tempat diam-diam tidak ikut di tempat lain.
    |
    | Rute unduhnya TIDAK memakai Route::resource: yang dibutuhkan hanya dua
    | (daftar + unduh), dan resource akan mendaftarkan lima rute lain yang
    | tidak punya controller-nya.
    */
    $daftarkanLaporanBulanan = function () {
        Route::view('/laporan-bulanan', 'laporan.bulanan')->name('laporan-bulanan');

        // {laporan} di-binding ke model MonthlyReport lewat type-hint di
        // controller. Hak aksesnya tetap diperiksa ulang di sana — lihat
        // catatan di LaporanBulananController.
        Route::get('/laporan-bulanan/{laporan}/unduh', [LaporanBulananController::class, 'unduh'])
            ->name('laporan-bulanan.unduh');

        /*
        | Rekap KBM per Jadwal — ditumpangkan pada closure yang sama karena
        | hak aksesnya persis sama (kepsek + super_admin) dan keduanya berada
        | di kelompok menu "Pusat Laporan". Closure terpisah hanya akan
        | menambah satu nama variabel yang harus diingat untuk di-`use` di dua
        | grup rute di bawah.
        |
        | Rute unduhnya TANPA parameter jalur: seluruh saringannya (periode,
        | kelas, mapel, guru) dibawa sebagai query string, sama persis dengan
        | yang ada di URL halaman. Itu yang membuat tombol "Unduh PDF" cukup
        | meneruskan saringan yang sedang tampil, tanpa jalur pembentukan data
        | kedua yang bisa menyimpang dari layar.
        */
        Route::view('/rekap-kbm', 'laporan.rekap-kbm')->name('rekap-kbm');

        Route::get('/rekap-kbm/unduh', [RekapKbmController::class, 'unduh'])
            ->name('rekap-kbm.unduh');
    };

    // role:kepsek — hanya kepsek, TIDAK termasuk super_admin (lihat grup
    // 'super-admin.' di bawah, yang punya panel sendiri di URL berbeda).
    Route::middleware('role:kepsek')
        ->prefix('kepsek')
        ->name('kepsek.')
        ->group(function () use ($daftarkanPanelAdminKesiswaan, $daftarkanAbsensiMandiri, $daftarkanPengajuanIzin, $daftarkanPengumuman, $daftarkanEkskul, $daftarkanProfil, $daftarkanPantauanKepsek, $daftarkanRppPengawas, $daftarkanLaporanBulanan, $daftarkanValidasiRapor) {
            $daftarkanRppPengawas();
            $daftarkanValidasiRapor();
            $daftarkanLaporanBulanan();
            $daftarkanPanelAdminKesiswaan();
            $daftarkanPengumuman();
            $daftarkanEkskul();
            $daftarkanProfil();
            $daftarkanPantauanKepsek();

            // Kepsek TETAP punya tombol absen mandiri — ia pegawai sekolah
            // yang kehadirannya juga dicatat. Super Admin tidak (lihat bawah).
            $daftarkanAbsensiMandiri();
            $daftarkanPengajuanIzin();
        });

    // role:super_admin — punya panel administrasi kesiswaan yang SAMA
    // dengan kepsek (di URL /super-admin/* sendiri, bukan numpang ke
    // /kepsek/*) DITAMBAH panel eksklusif untuk data kepegawaian
    // (pegawai + absensi_pegawai) yang tidak dibuka untuk kepsek biasa.
    Route::middleware('role:super_admin')
        ->prefix('super-admin')
        ->name('super-admin.')
        ->group(function () use ($daftarkanPanelAdminKesiswaan, $daftarkanPengumuman, $daftarkanEkskul, $daftarkanProfil, $daftarkanRppPengawas, $daftarkanLaporanBulanan, $daftarkanValidasiRapor) {
            $daftarkanRppPengawas();
            $daftarkanValidasiRapor();
            $daftarkanLaporanBulanan();
            $daftarkanPanelAdminKesiswaan();
            $daftarkanPengumuman();
            $daftarkanEkskul();
            $daftarkanProfil();

            // CATATAN: $daftarkanAbsensiMandiri() SENGAJA tidak dipanggil di
            // sini. Super Admin memegang kendali penuh sistem tapi tidak
            // melakukan absensi. Pembatasan ini ditegakkan di level rute —
            // bukan sekadar menyembunyikan tombol — sehingga menembak URL
            // /super-admin/absen-lokasi secara manual pun menghasilkan 404.

            Route::resource('pegawai', PegawaiController::class)
                ->except(['show'])
                ->parameters(['pegawai' => 'pegawai']);

            Route::get('/pegawai/{pegawai}/qr', [PegawaiQrController::class, 'show'])->name('pegawai.qr');
            Route::get('/pegawai-qr', [PegawaiQrController::class, 'semua'])->name('pegawai.qr-semua');

            Route::get('/laporan-pegawai', [AbsensiPegawaiLaporanController::class, 'index'])->name('laporan-pegawai');

            // Laporan absensi harian (siswa & pegawai dalam satu tampilan).
            Route::get('/laporan-harian', [LaporanHarianController::class, 'index'])->name('laporan-harian');

            // Pencarian cepat (Livewire) & kenaikan kelas.
            Route::view('/cari-data', 'super-admin.cari-data')->name('cari-data');

            Route::get('/kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas');
            Route::post('/kenaikan-kelas', [KenaikanKelasController::class, 'proses'])->name('kenaikan-kelas.proses');

            // Pengaturan Sistem: waktu, lokasi/GPS, approval akun, tahun ajaran.
            Route::get('/pengaturan', [SettingController::class, 'index'])->name('pengaturan');
            Route::put('/pengaturan/waktu', [SettingController::class, 'updateWaktu'])->name('pengaturan.waktu');
            Route::put('/pengaturan/lokasi', [SettingController::class, 'updateLokasi'])->name('pengaturan.lokasi');
            Route::patch('/pengaturan/akun/{id}/setujui', [SettingController::class, 'approveAkun'])->name('pengaturan.approve');
            Route::patch('/pengaturan/akun/{id}/tolak', [SettingController::class, 'tolakAkun'])->name('pengaturan.tolak');
            Route::put('/pengaturan/tahun-ajaran', [SettingController::class, 'updateTahunAjaran'])->name('pengaturan.tahun-ajaran');

            // CRUD Jadwal Pelajaran. ->parameters() dipasang eksplisit supaya
            // nama parameter rutenya {jadwal} (cocok dengan argumen
            // JadwalPelajaran $jadwal di controller), bukan hasil tebakan
            // pluralizer Laravel.
            Route::resource('jadwal', JadwalPelajaranController::class)
                ->except(['show'])
                ->parameters(['jadwal' => 'jadwal']);

            // Otomatisasi administrasi: import massal dari Excel & export ke
            // Excel untuk tiga entitas Master Data (Siswa, Pegawai, Kelas).
            // Lihat SuperAdminController — sengaja terpisah dari CRUD manual
            // di atas, murni untuk operasi massal lewat file.
            Route::prefix('master-data')
                ->name('master-data.')
                ->group(function () {
                    Route::get('/', [SuperAdminController::class, 'index'])->name('index');

                    Route::get('/siswa/template', [SuperAdminController::class, 'templateSiswa'])->name('siswa.template');
                    Route::get('/siswa/export', [SuperAdminController::class, 'exportSiswa'])->name('siswa.export');
                    Route::post('/siswa/import', [SuperAdminController::class, 'importSiswa'])->name('siswa.import');

                    Route::get('/pegawai/template', [SuperAdminController::class, 'templatePegawai'])->name('pegawai.template');
                    Route::get('/pegawai/export', [SuperAdminController::class, 'exportPegawai'])->name('pegawai.export');
                    Route::post('/pegawai/import', [SuperAdminController::class, 'importPegawai'])->name('pegawai.import');

                    Route::get('/kelas/template', [SuperAdminController::class, 'templateKelas'])->name('kelas.template');
                    Route::get('/kelas/export', [SuperAdminController::class, 'exportKelas'])->name('kelas.export');
                    Route::post('/kelas/import', [SuperAdminController::class, 'importKelas'])->name('kelas.import');
                });
        });

    // role:wali_kelas — dashboard kelas yang ia ampu + form absensi manual.
    Route::middleware('role:wali_kelas')
        ->prefix('wali-kelas')
        ->name('wali-kelas.')
        ->group(function () use ($daftarkanAbsensiMandiri, $daftarkanPengajuanIzin, $daftarkanAbsenMengajar, $daftarkanJurnalKelas, $daftarkanScannerSiswa, $daftarkanProfil, $daftarkanJadwalPelajaran, $daftarkanEkskul, $daftarkanRppGuru, $daftarkanInputNilai, $daftarkanValidasiRapor) {
            $daftarkanEkskul();
            $daftarkanRppGuru();
            $daftarkanInputNilai();
            $daftarkanValidasiRapor();
            Route::get('/dashboard', [WaliKelasController::class, 'index'])->name('dashboard');

            Route::post('/absensi', [WaliKelasController::class, 'simpanAbsensi'])->name('absensi.simpan');

            $daftarkanAbsensiMandiri();
            $daftarkanPengajuanIzin();
            $daftarkanAbsenMengajar();
            $daftarkanJurnalKelas();
            $daftarkanScannerSiswa();
            $daftarkanProfil();
            $daftarkanJadwalPelajaran();
        });

    // role:wali_murid — dashboard kehadiran anak.
    Route::middleware('role:wali_murid')
        ->prefix('wali-murid')
        ->name('wali-murid.')
        ->group(function () use ($daftarkanProfil, $daftarkanJadwalPelajaran, $daftarkanPantauanAnak, $daftarkanEkskul) {
            $daftarkanEkskul();
            // Dashboard = ringkasan singkat + pintasan.
            Route::get('/dashboard', [OrtuController::class, 'dashboard'])->name('dashboard');

            // Riwayat scan gerbang — isi yang DULU ada di dashboard.
            Route::get('/absensi-kedatangan', [OrtuController::class, 'index'])->name('absensi-kedatangan');

            // Rapor anak — hanya menampilkan nilai yang rapornya SUDAH
            // disetujui kepala sekolah (lihat RaporWaliMuridController).
            Route::get('/rapor', [RaporWaliMuridController::class, 'index'])->name('rapor');

            $daftarkanPantauanAnak();
            $daftarkanProfil();

            // Wali murid melihat jadwal KELAS ANAKNYA — berguna untuk
            // menyiapkan buku dan mengetahui jam pulang.
            $daftarkanJadwalPelajaran();
        });

    // role:guru, role:staff, role:admin_tu — tiga grup TERPISAH (masing-
    // masing hanya menerima satu role, bukan middleware multi-role), tapi
    // ketiganya memakai controller & view yang sama: dashboard self-service
    // (profil + riwayat absensi sendiri), karena ketiganya belum punya
    // dashboard spesifik peran seperti wali kelas.
    Route::middleware('role:guru')
        ->prefix('guru')
        ->name('guru.')
        ->group(function () use ($daftarkanAbsensiMandiri, $daftarkanPengajuanIzin, $daftarkanAbsenMengajar, $daftarkanJurnalKelas, $daftarkanScannerSiswa, $daftarkanProfil, $daftarkanJadwalPelajaran, $daftarkanEkskul, $daftarkanRppGuru, $daftarkanInputNilai) {
            $daftarkanEkskul();
            $daftarkanRppGuru();
            $daftarkanInputNilai();
            Route::get('/dashboard', [PegawaiDashboardController::class, 'index'])->name('dashboard');

            $daftarkanAbsensiMandiri();
            $daftarkanPengajuanIzin();
            $daftarkanAbsenMengajar();
            $daftarkanJurnalKelas();
            $daftarkanScannerSiswa();
            $daftarkanProfil();
            $daftarkanJadwalPelajaran();
        });

    Route::middleware('role:staff')
        ->prefix('staff')
        ->name('staff.')
        ->group(function () use ($daftarkanAbsensiMandiri, $daftarkanPengajuanIzin, $daftarkanAbsenMengajar, $daftarkanProfil, $daftarkanJadwalPelajaran, $daftarkanEkskul) {
            $daftarkanEkskul();
            Route::get('/dashboard', [PegawaiDashboardController::class, 'index'])->name('dashboard');

            $daftarkanAbsensiMandiri();
            $daftarkanPengajuanIzin();

            // Staff ikut mendapat Absen Mengajar sesuai permintaan, walau ia
            // tidak mengajar — halamannya tetap menegakkan aturan yang sama
            // (wajib absen kehadiran dulu).
            $daftarkanAbsenMengajar();

            $daftarkanProfil();

            // Staff tidak mengajar, tapi ia yang ditanya "kelas XI RPL 1
            // sekarang di ruang mana" — jadi baginya halaman ini menampilkan
            // SELURUH jadwal sekolah (lihat App\Livewire\JadwalPelajaran).
            $daftarkanJadwalPelajaran();
        });

    Route::middleware('role:admin_tu')
        ->prefix('admin-tu')
        ->name('admin-tu.')
        ->group(function () use ($daftarkanAbsensiMandiri, $daftarkanPengajuanIzin, $daftarkanProfil, $daftarkanEkskul, $daftarkanValidasiRapor) {
            $daftarkanEkskul();
            $daftarkanValidasiRapor();
            Route::get('/dashboard', [PegawaiDashboardController::class, 'index'])->name('dashboard');

            $daftarkanAbsensiMandiri();
            $daftarkanPengajuanIzin();
            $daftarkanProfil();
        });

    // role:guru_piket — halaman scanner dengan SATU endpoint AJAX hybrid
    // yang otomatis mengenali NIS siswa maupun NIP pegawai (lihat
    // ScannerController::store()) — tidak ada lagi pemilihan mode manual.
    Route::middleware('role:guru_piket')
        ->prefix('piket')
        ->name('piket.')
        ->group(function () use ($daftarkanScannerSiswa, $daftarkanProfil, $daftarkanEkskul) {
            $daftarkanEkskul();
            Route::get('/scanner', [ScannerController::class, 'index'])->name('scanner');

            // Versi Livewire dari scanner siswa, di dalam layout utama —
            // berguna kalau guru piket ingin men-scan sambil melihat menu
            // lain, bukan di layar gerbang yang berdiri sendiri.
            $daftarkanScannerSiswa();

            // Dipanggil lewat AJAX oleh halaman scanner setiap kali sebuah
            // kode berhasil ditangkap. throttle:60,1 — batasi 60 scan/menit
            // (jauh di atas kecepatan scan wajar) supaya tidak dijadikan
            // celah spam.
            Route::post('/scan', [ScannerController::class, 'store'])
                ->middleware('throttle:60,1')
                ->name('scan');

            $daftarkanProfil();
        });
});
