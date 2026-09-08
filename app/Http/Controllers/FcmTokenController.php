<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menerima & menghapus token perangkat (FCM) milik pengguna yang sedang
 * login.
 *
 * Dipanggil dari browser oleh resources/views/partials/firebase-push.blade.php
 * setiap kali aplikasi dibuka. Bukan hanya saat login pertama — DENGAN
 * SENGAJA: Firebase bisa mengganti token perangkat kapan saja (pembaruan
 * browser, data situs dibersihkan, izin dicabut lalu diberikan lagi), dan
 * token lama langsung berhenti berfungsi tanpa pemberitahuan apa pun.
 * Mengirim ulang di setiap pembukaan aplikasi adalah cara termurah untuk
 * memastikan yang tersimpan selalu token yang hidup.
 */
class FcmTokenController extends Controller
{
    /**
     * Panjang maksimum token yang diterima.
     *
     * Token FCM saat ini ±163 karakter. 255 memberi ruang lega untuk
     * perubahan format di masa depan, sekaligus sama dengan lebar kolomnya
     * — supaya penolakannya terjadi di validasi (pesan jelas), bukan di
     * database (error 500 "Data too long for column").
     */
    private const MAKS_PANJANG = 255;

    /**
     * Simpan / perbarui token perangkat milik pengguna yang sedang login.
     */
    public function simpan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:' . self::MAKS_PANJANG],
        ]);

        $user = $request->user();
        $token = trim($data['token']);

        // Sudah sama persis? Tidak ada yang perlu ditulis. Endpoint ini
        // dipanggil setiap kali halaman dibuka, jadi jalur inilah yang
        // paling sering ditempuh — dan ia tidak menyentuh database sama
        // sekali.
        if ($user->fcm_token === $token) {
            return response()->json(['status' => 'sama']);
        }

        try {
            DB::transaction(function () use ($user, $token) {
                /*
                 | ============ LANGKAH YANG TIDAK BOLEH DILEWAT ============
                 | Token FCM melekat pada PERANGKAT, bukan pada akun.
                 |
                 | Satu HP yang dipakai bergantian — ayah login, logout,
                 | lalu ibu login di HP yang sama — menghasilkan token yang
                 | PERSIS SAMA untuk dua akun berbeda.
                 |
                 | Tanpa baris ini, akun ayah tetap memegang token itu dan
                 | HP tersebut terus menerima notifikasi kehadiran anak
                 | asuhan ayah, walau yang sedang memakainya orang lain.
                 | Kolomnya juga unique, jadi tanpa pelepasan ini
                 | penyimpanannya akan gagal dengan error integritas.
                 |
                 | Dibungkus transaksi supaya tidak pernah ada keadaan
                 | antara: token terlepas dari pemilik lama TAPI gagal
                 | menempel ke pemilik baru.
                 | ==========================================================
                 */
                User::query()
                    ->where('fcm_token', $token)
                    ->whereKeyNot($user->getKey())
                    ->update([
                        'fcm_token' => null,
                        'fcm_token_updated_at' => null,
                    ]);

                // Pemberian nilai LANGSUNG, bukan update([...]) massal:
                // 'fcm_token' sengaja tidak ada di $fillable (lihat
                // catatan di App\Models\User).
                $user->fcm_token = $token;
                $user->fcm_token_updated_at = now();
                $user->save();
            });
        } catch (\Throwable $e) {
            Log::warning('Gagal menyimpan token FCM.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            // 200, bukan 500. Browser memanggil ini di latar belakang saat
            // halaman dibuka; jawaban 500 hanya menghasilkan error merah di
            // konsol untuk sesuatu yang tidak menghalangi pemakaian
            // aplikasi sama sekali.
            return response()->json(['status' => 'gagal'], 200);
        }

        return response()->json(['status' => 'tersimpan']);
    }

    /**
     * Lepaskan token perangkat ini dari akun.
     *
     * Dipanggil TEPAT SEBELUM logout (lihat partial firebase-push). Kalau
     * tidak dilepas, HP yang dipinjamkan ke orang lain akan terus berbunyi
     * membawakan kabar kehadiran anak pemilik akun sebelumnya — kebocoran
     * data yang sangat mudah terjadi dan sangat sulit dijelaskan.
     */
    public function hapus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:' . self::MAKS_PANJANG],
        ]);

        $user = $request->user();
        $token = isset($data['token']) ? trim((string) $data['token']) : null;

        // Kalau browser menyebutkan tokennya, hanya token ITU yang dilepas.
        // Tanpa penjagaan ini, membuka aplikasi di komputer sekolah lalu
        // logout di sana akan ikut mematikan notifikasi di HP pribadinya.
        if ($token !== null && $token !== '' && $user->fcm_token !== $token) {
            return response()->json(['status' => 'bukan-perangkat-ini']);
        }

        $user->fcm_token = null;
        $user->fcm_token_updated_at = null;
        $user->save();

        return response()->json(['status' => 'terhapus']);
    }
}
