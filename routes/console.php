<?php

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
| ===========================================================
*/

/*
 | Laporan kehadiran bulanan.
 |
 | Tanggal 1 pukul 01:00 — dini hari, saat tidak ada seorang pun memakai
 | aplikasi. Merekap ratusan siswa sementara guru piket sedang men-scan di
 | gerbang akan membuat keduanya lambat.
 |
 | timezone() WAJIB ditulis eksplisit. Server hosting umumnya berjalan di
 | UTC, dan tanpa baris ini "pukul 01:00" berarti 08:00 WIB — laporannya
 | tetap jadi, tapi tepat di jam tersibuk sekolah.
 |
 | withoutOverlapping() mencegah dua proses merekap bulan yang sama
 | bersamaan kalau yang pertama belum selesai.
 |
 | runInBackground() SENGAJA tidak dipakai: perintah ini menulis berkas dan
 | baris database, dan kalau gagal kita ingin kode keluarnya terbaca cron —
 | bukan hilang di proses latar belakang.
 */
Schedule::command('simagas:laporan-bulanan')
    ->monthlyOn(1, '01:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30)
    ->onSuccess(function () {
        Log::info('Laporan bulanan otomatis berhasil dibuat.');
    })
    ->onFailure(function () {
        // Kegagalan jam 1 pagi tidak akan dilihat siapa pun sampai ada yang
        // mencari laporannya. Barisnya masuk log supaya jejaknya ada.
        Log::error('Laporan bulanan otomatis GAGAL. Periksa storage/logs/laravel.log.');
    });
