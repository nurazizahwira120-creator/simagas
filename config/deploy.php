<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token endpoint deploy
    |--------------------------------------------------------------------------
    | Dipakai App\Http\Controllers\DeployController untuk memastikan yang
    | memanggil endpoint pembersih cache memang GitHub Actions milik Anda.
    |
    | Isi di .env server:
    |
    |     DEPLOY_TOKEN=token-acak-panjang-anda
    |
    | dan simpan nilai yang SAMA sebagai repository secret bernama
    | DEPLOY_TOKEN di GitHub (Settings > Secrets and variables > Actions).
    |
    | ============ KENAPA BERKAS CONFIG SENDIRI, BUKAN env() LANGSUNG ============
    | env() hanya bekerja selama config BELUM di-cache. Begitu
    | `php artisan config:cache` dijalankan di server — dan itu justru
    | dianjurkan untuk kecepatan — semua env() di luar berkas config
    | mengembalikan null. Endpoint deploy-nya lalu menjawab 404, langkah
    | GitHub Actions gagal, dan penyebabnya sangat sulit ditebak karena
    | tokennya jelas-jelas ada di .env.
    |
    | Ditaruh di berkas config SENDIRI (bukan menyisip ke config/app.php)
    | supaya pembaruan paket tidak pernah menimpa config/app.php milik Anda.
    | ===========================================================================
    */

    'token' => env('DEPLOY_TOKEN', ''),

];
