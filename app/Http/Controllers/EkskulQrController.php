<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\JadwalEkskul;

/**
 * Stiker QR satu ekskul, siap cetak — dipindai pembina untuk Mulai Sesi.
 *
 * Boleh dibuka Super Admin / Kepala Sekolah dan PEMBINA ekskul itu sendiri
 * (aturan yang sama dengan PeranEkskul::bolehKelolaEkskul). Peran lain
 * mendapat 403: stiker ini kunci untuk memulai sesi dan mencatat honor.
 */
class EkskulQrController extends Controller
{
    public function show(int $jadwal)
    {
        $baris = JadwalEkskul::with('pembina:id,nama,user_id')->findOrFail($jadwal);
        $user = auth()->user();

        $boleh = in_array($user?->role, [UserRole::SuperAdmin, UserRole::Kepsek], true)
            || ($baris->pembina_id && $user?->pegawai && (int) $user->pegawai->id === (int) $baris->pembina_id);

        abort_unless($boleh, 403);

        // Ekskul lama yang kodenya entah kenapa kosong langsung dibuatkan.
        if (blank($baris->kode_qr)) {
            $baris->forceFill(['kode_qr' => JadwalEkskul::buatKodeQr($baris->id)])->save();
        }

        return view('ekskul.qr', ['jadwal' => $baris]);
    }
}
