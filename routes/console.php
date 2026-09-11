<?php

use App\Console\Commands\BersihkanBuktiMengajar;
use App\Console\Commands\BuatLaporanBulanan;
use App\Console\Commands\KalkulasiPenghargaanGuru;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| PENJADWALAN (Task Scheduling)
|--------------------------------------------------------------------------
| Sejak Laravel 11 jadwal ditulis DI SINI — bukan lagi di method schedule()
| pada app/Console/Kernel.php, berkas yang sudah tidak ada lagi.
|
| ============ SATU LANGKAH YANG WAJIB DI cPANEL ============
| Baris di bawah TIDAK berjalan sendiri. Laravel hanya tahu "apa yang harus
| dijalankan dan kapan"; yang membangunkannya tetap cron milik sistem. Tanpa
| langkah ini jadwalnya benar dan tidak pernah terjadi apa-apa — kegagalan
| yang paling sering luput justru karena tidak ada pesan error sama sekali.
|
| cPanel -> Cron Jobs -> Add New Cron Job:
|
|   Common Settings : Once Per Minute   (* * * * *)
|   Command         : /usr/local/bin/php /home/simm6468/backend_simagas/artisan schedule:run >> /dev/null 2>&1
|
| Dijalankan SETIAP MENIT — bukan sebulan sekali. `schedule:run` adalah
| detak jantung yang bertanya "adakah tugas yang jatuh tempo menit ini?"
| lalu hampir selalu langsung keluar tanpa mengerjakan apa pun. Menyetel
| cron-nya sendiri sebulan sekali hampir pasti meleset, karena ia harus
| kebetulan menyala persis pada menit yang dijadwalkan.
|
| Kalau hosting mengeluh soal jumlah proses, jadwal cron "setiap 5 menit"
| juga cukup: tugas di bawah jatuh tempo pada menit :00, dan menit itu
| selalu ikut terkena kelipatan lima.
|
| (Pola cron-nya tidak ditulis apa adanya di sini dengan sengaja — tanda
| bintang-garis-miringnya akan MENUTUP blok komentar ini lebih awal dan
| membuat seluruh berkas gagal di-parse.)
| ===========================================================
*/

/*
|--------------------------------------------------------------------------
| Laporan kehadiran bulanan — tanggal 1 pukul 01:00 WIB
|--------------------------------------------------------------------------
|
| ============ KENAPA Schedule::call(), BUKAN Schedule::command() ============
| Bentuk yang lazim dan lebih pendek adalah:
|
|   Schedule::command('simagas:laporan-bulanan')->monthlyOn(1, '01:00');
|
| dan di hosting ini bentuk itu TIDAK AKAN PERNAH BERHASIL.
|
| Schedule::command() menjalankan perintahnya sebagai PROSES BARU lewat
| Symfony Process, yang bergantung pada fungsi PHP `proc_open`. Hosting ini
| mematikan proc_open — hal yang sama sudah terbukti saat composer gagal
| memanggil `artisan package:discover`:
|
|   The Process class relies on proc_open, which is not available on your
|   PHP installation.
|
| Akibatnya kalau dibiarkan: cron menyala tiap menit, menemukan tugasnya
| jatuh tempo tanggal 1 pukul 01:00, lalu gagal dengan error itu — sekali
| sebulan, jam satu pagi, tanpa ada seorang pun yang melihat layarnya. Yang
| tampak keesokan harinya hanya: laporannya tidak ada.
|
| Schedule::call() memanggil Artisan DI DALAM proses yang sedang berjalan.
| Tidak ada sub-proses, tidak ada proc_open. Seluruh penjadwalannya (jam,
| timezone, penguncian) tetap ditangani Laravel seperti biasa.
| ===========================================================================
|
| ->name() WAJIB ada di sini, tidak seperti pada Schedule::command().
| withoutOverlapping() perlu nama untuk kunci mutex-nya, dan tanpa itu
| Laravel melempar "A scheduled event name is required to prevent
| overlapping" — saat cron berjalan, bukan saat berkas ini disimpan.
|
| timezone() juga WAJIB ditulis eksplisit. Server hosting umumnya berjalan
| di UTC, dan tanpa baris ini "pukul 01:00" berarti 08:00 WIB — laporannya
| tetap jadi, tapi tepat di jam tersibuk sekolah.
*/
Schedule::call(function () {
    $kode = Artisan::call(BuatLaporanBulanan::class);

    if ($kode !== 0) {
        // Kegagalan jam 1 pagi tidak akan dilihat siapa pun sampai ada yang
        // mencari laporannya. Barisnya masuk log supaya jejaknya ada.
        Log::error('Laporan bulanan otomatis GAGAL.', [
            'kode_keluar' => $kode,
            'keluaran' => Artisan::output(),
        ]);

        // return false -> exitCode 1 -> terbaca sebagai gagal oleh penjadwal.
        return false;
    }

    Log::info('Laporan bulanan otomatis berhasil dibuat.');
})
    ->name('simagas-laporan-bulanan')
    ->monthlyOn(1, '01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);

/*
|--------------------------------------------------------------------------
| Guru Teladan & Tertib Administrasi — tanggal 1 pukul 00:01 WIB
|--------------------------------------------------------------------------
|
| Dijalankan 59 menit SEBELUM laporan bulanan (01:00), dan itu disengaja:
| keduanya membaca tabel absensi bulan yang sama, dan menjalankannya di menit
| yang sama pada hosting bersama berarti dua proses berat berebut database di
| saat tidak ada seorang pun yang mengawasi.
|
| ============ TETAP Schedule::call(), BUKAN Schedule::command() ============
| Alasannya sama persis dengan laporan bulanan di atas: hosting ini mematikan
| `proc_open`, sedangkan Schedule::command() menjalankan perintahnya sebagai
| PROSES BARU lewat Symfony Process yang bergantung pada fungsi itu.
|
| Kalau dipaksakan, gejalanya paling buruk yang mungkin: cron menyala, tugasnya
| jatuh tempo, lalu gagal — sekali sebulan, jam 12 malam lewat semenit, tanpa
| ada yang melihat layarnya. Yang tampak keesokan harinya hanya: tidak ada
| Guru Teladan bulan ini.
| ==========================================================================
|
| ->name() WAJIB ada karena withoutOverlapping() memerlukannya untuk kunci
| mutex. timezone() juga WAJIB: server hosting berjalan di UTC, dan tanpa baris
| itu "00:01" berarti 07:01 WIB — tepat saat guru mulai berdatangan.
*/
Schedule::call(function () {
    $kode = Artisan::call(KalkulasiPenghargaanGuru::class);

    if ($kode !== 0) {
        Log::error('Kalkulasi Guru Teladan GAGAL.', [
            'kode_keluar' => $kode,
            'keluaran' => Artisan::output(),
        ]);

        // return false -> exitCode 1 -> terbaca gagal oleh penjadwal.
        return false;
    }

    Log::info('Kalkulasi Guru Teladan selesai.', ['keluaran' => Artisan::output()]);
})
    ->name('simagas-penghargaan-guru')
    ->monthlyOn(1, '00:01')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);

/*
|--------------------------------------------------------------------------
| Pembersihan foto bukti mengajar — tanggal 1 pukul 02:00 WIB
|--------------------------------------------------------------------------
|
| ============ KENAPA 02:00, BUKAN JAM LAIN ============
| Urutannya disengaja dan TIDAK BOLEH ditukar:
|
|   00:01  Guru Teladan     -> membaca absensi_mengajar bulan lalu
|   01:00  Laporan Bulanan  -> membaca absensi_mengajar bulan lalu
|   02:00  Pembersihan ini  -> membuang berkas foto bulan lalu
|
| Keduanya di atas menghitung "sesi KBM tervalidasi" dari kolom `foto_bukti`.
| Pembersih ini memang tidak mengosongkan kolom itu (lihat penjelasan panjang
| di BersihkanBuktiMengajar), jadi secara angka urutannya tidak berpengaruh.
| Yang dijaga di sini adalah hal lain: ketiganya menyentuh tabel yang sama di
| hosting bersama. Menumpuknya pada menit yang sama berarti tiga proses berat
| berebut database pada jam yang tidak ada seorang pun mengawasinya.
|
| Jarak satu jam juga memberi ruang kalau laporan bulanan kebetulan lambat
| (sekolah dengan data setahun penuh), sehingga pembersihannya tidak pernah
| mulai saat laporannya masih membaca.
|
| ============ TETAP Schedule::call(), BUKAN Schedule::command() ============
| Alasannya sama persis dengan dua jadwal di atas: hosting ini mematikan
| `proc_open`, sedangkan Schedule::command() menjalankan perintahnya sebagai
| proses baru lewat Symfony Process yang bergantung pada fungsi itu.
| ==========================================================================
|
| Masa simpannya TIDAK ditulis di sini melainkan dibiarkan memakai nilai
| bawaan perintahnya (--bulan=1). Kalau suatu saat sekolah ingin menyimpan
| tiga bulan, yang diubah cukup satu tempat: default option di
| BersihkanBuktiMengajar — bukan berburu angka yang tercecer di dua berkas.
*/
Schedule::call(function () {
    $kode = Artisan::call(BersihkanBuktiMengajar::class);

    if ($kode !== 0) {
        Log::error('Pembersihan foto bukti mengajar GAGAL.', [
            'kode_keluar' => $kode,
            'keluaran' => Artisan::output(),
        ]);

        // return false -> exitCode 1 -> terbaca gagal oleh penjadwal.
        return false;
    }

    Log::info('Pembersihan foto bukti mengajar selesai.', ['keluaran' => Artisan::output()]);
})
    ->name('simagas-bersihkan-bukti')
    ->monthlyOn(1, '02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);
