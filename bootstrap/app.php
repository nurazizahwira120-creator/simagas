<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',

        // Mendaftarkan routes/channels.php SEKALIGUS membuat rute
        // /broadcasting/auth. Keduanya wajib untuk notifikasi real-time:
        // tanpa baris ini setiap langganan channel privat Pusher ditolak 403
        // dan lonceng tidak pernah berbunyi — tanpa error apa pun di sisi PHP,
        // karena kegagalannya seluruhnya terjadi di browser.
        channels: __DIR__.'/../routes/channels.php',

        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Ke mana user yang SUDAH login diarahkan kalau ia membuka halaman
        // khusus tamu (/login atau /daftar).
        //
        // Tanpa baris ini Laravel memakai bawaannya, yaitu '/dashboard' —
        // alamat yang TIDAK ADA di aplikasi ini, sehingga membuka /login di
        // tab kedua saat masih login menghasilkan 404. Diarahkan ke '/', yang
        // sudah tahu cara melempar tiap role ke dashboard-nya masing-masing
        // (lihat rute 'home' + UserRole::dashboardRouteName()), supaya tidak
        // ada daftar tujuan kedua yang ikut harus dijaga.
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
