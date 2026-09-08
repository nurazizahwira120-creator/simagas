<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Membatasi akses rute berdasarkan role user yang login.
 *
 * Daftarkan alias-nya di bootstrap/app.php:
 *   $middleware->alias(['role' => \App\Http\Middleware\EnsureUserHasRole::class]);
 *
 * Pemakaian di rute:
 *   Route::middleware(['auth', 'role:kepsek'])->group(...);
 *   Route::middleware(['auth', 'role:wali_kelas,kepsek'])->group(...); // lebih dari satu role
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // Middleware ini tidak menggantikan 'auth' — jaga-jaga kalau dipasang
        // sendirian tanpa 'auth' di depannya.
        if (! $user) {
            abort(401, 'Anda harus login terlebih dahulu.');
        }

        $allowedRoles = collect($roles)->map(
            fn (string $role) => UserRole::from($role)
        );

        if (! $allowedRoles->contains($user->role)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
