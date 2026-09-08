<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Rpp;
use App\Models\User;

/**
 * Hak akses Manajemen RPP.
 *
 * Ditemukan otomatis oleh Laravel 11 (App\Models\Rpp -> App\Policies\RppPolicy),
 * jadi TIDAK perlu didaftarkan di AuthServiceProvider.
 *
 * ============ SATU TEMPAT, BUKAN TERSEBAR DI CONTROLLER ============
 * Aturannya kecil tapi dipakai di enam tempat: index, show, store, destroy,
 * berkas (stream PDF), dan di Blade untuk menyembunyikan tombol. Kalau ditulis
 * ulang sebagai if-else di masing-masing tempat, cepat atau lambat ada satu
 * yang ketinggalan saat aturannya berubah — dan yang ketinggalan biasanya
 * justru yang tidak kelihatan dari layar (endpoint hapus, atau stream berkas).
 * ===================================================================
 */
class RppPolicy
{
    /**
     * Peran yang boleh MENGUNGGAH RPP.
     *
     * Wali Kelas ikut karena di sekolah ini ia tetap guru yang mengajar dan
     * menyusun RPP; memisahkannya berarti separuh guru tidak bisa memakai
     * fitur ini tanpa alasan yang bisa dijelaskan ke mereka.
     */
    public const PERAN_GURU = [UserRole::Guru, UserRole::WaliKelas];

    /** Peran yang boleh MELIHAT SELURUH RPP (tapi tidak mengubah apa pun). */
    public const PERAN_PENGAWAS = [UserRole::Kepsek, UserRole::SuperAdmin];

    public static function adalahGuru(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::PERAN_GURU, true);
    }

    public static function adalahPengawas(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::PERAN_PENGAWAS, true);
    }

    /** Boleh membuka daftar RPP (isinya yang berbeda — lihat controller). */
    public function viewAny(User $user): bool
    {
        return self::adalahGuru($user) || self::adalahPengawas($user);
    }

    /**
     * Boleh membuka satu RPP: pemiliknya sendiri, atau pengawas.
     *
     * Guru lain TIDAK boleh — RPP guru A bukan urusan guru B. Ini juga yang
     * menjaga endpoint stream berkas: tanpa pemeriksaan ini, cukup menebak
     * /guru/rpp/12/berkas untuk membaca dokumen milik orang lain.
     */
    public function view(User $user, Rpp $rpp): bool
    {
        if (self::adalahPengawas($user)) {
            return true;
        }

        return self::adalahGuru($user) && (int) $rpp->user_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return self::adalahGuru($user);
    }

    /**
     * Hanya pemiliknya. Kepala Sekolah SENGAJA tidak bisa menghapus:
     * briefnya meminta ia hanya memantau, dan dokumen yang bisa dihapus oleh
     * orang yang tidak menyusunnya adalah cara paling cepat kehilangan berkas
     * tanpa jejak siapa yang menghapus.
     */
    public function delete(User $user, Rpp $rpp): bool
    {
        return self::adalahGuru($user) && (int) $rpp->user_id === (int) $user->id;
    }

    /** Belum ada halaman edit; disediakan supaya aturannya tidak ditebak. */
    public function update(User $user, Rpp $rpp): bool
    {
        return $this->delete($user, $rpp);
    }
}
