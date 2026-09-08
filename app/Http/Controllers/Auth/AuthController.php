<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Tinggal menangani LOGOUT saja.
 *
 * Form login-nya sudah pindah ke komponen Livewire App\Livewire\Auth\Login
 * (halaman /login), lengkap dengan pengecekan status akun, pembatasan
 * percobaan, dan redirect per role. Method create() & store() di sini
 * dihapus supaya tidak ada dua jalur login yang harus dijaga bersamaan —
 * kalau nanti aturannya berubah (mis. syarat status akun), cukup ada satu
 * tempat yang perlu diubah.
 *
 * Logout tetap memakai controller biasa, bukan Livewire: ia dipanggil dari
 * <form method="POST"> di sidebar dan di beberapa halaman yang TIDAK memuat
 * Livewire sama sekali (mis. halaman cetak kartu QR), jadi ia harus tetap
 * bekerja sebagai submit HTML polos.
 */
class AuthController extends Controller
{
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
