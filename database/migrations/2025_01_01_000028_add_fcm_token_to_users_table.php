<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyiapkan tabel `users` untuk Web Push Notification (Firebase Cloud
 * Messaging).
 *
 * ============ KENAPA HANYA SATU KOLOM, BUKAN TABEL SENDIRI ============
 * Satu kolom berarti SATU PERANGKAT per akun: token yang paling terakhir
 * mendaftar itulah yang menerima notifikasi. Untuk SIMAGAS itu memang yang
 * diinginkan — wali murid memakai satu HP, dan guru yang login di komputer
 * sekolah tidak perlu ikut berdering.
 *
 * Kalau suatu saat satu akun harus berdering di beberapa perangkat
 * sekaligus, yang berubah nanti hanya tempat penyimpanannya (tabel
 * fcm_tokens ber-baris banyak); seluruh sisa fitur ini sudah dipisah rapi
 * di App\Services\FirebasePushService dan tidak ikut berubah.
 * ======================================================================
 *
 * ============ KENAPA UNIQUE, DAN KENAPA ITU SOAL PRIVASI ============
 * Token FCM melekat pada PERANGKAT, bukan pada akun. Satu HP yang dipakai
 * bergantian — ayah login, lalu ibu login di HP yang sama — menghasilkan
 * token yang PERSIS SAMA untuk dua akun berbeda.
 *
 * Tanpa unique, kedua baris menyimpan token itu dan kedua akun mengirim
 * notifikasi ke HP yang sama: ibu menerima pemberitahuan tentang anak yang
 * bukan asuhannya. Unique memaksa kode pemanggil melepas token dari
 * pemilik lama sebelum menempelkannya ke pemilik baru (lihat
 * App\Http\Controllers\FcmTokenController::simpan()).
 * ====================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Panjang 255 dipilih dengan sengaja, bukan TEXT: token FCM saat
            // ini ±163 karakter, dan kolom TEXT tidak bisa diberi index
            // unique di MySQL tanpa menyebut panjang prefix-nya.
            if (! Schema::hasColumn('users', 'fcm_token')) {
                $table->string('fcm_token', 255)
                    ->nullable()
                    ->unique()
                    ->after('no_hp');
            }

            // Dipakai untuk membuang token yang sudah lama tidak diperbarui.
            // Firebase mendaur ulang token perangkat yang menganggur; token
            // berumur lebih dari beberapa bulan hampir pasti sudah mati dan
            // hanya menambah panggilan HTTP yang pasti gagal.
            if (! Schema::hasColumn('users', 'fcm_token_updated_at')) {
                $table->timestamp('fcm_token_updated_at')
                    ->nullable()
                    ->after('fcm_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Index unique DIBUANG lebih dulu dan terpisah. MySQL menolak
            // menghapus kolom yang masih dipakai sebuah index, dan pesan
            // errornya ("check that column/key exists") sama sekali tidak
            // menyebut index sebagai penyebabnya.
            //
            // Di SQLite dropUnique tidak berlaku dan melempar error, jadi
            // dilewati — di sana kolomnya bisa langsung dibuang.
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                try {
                    $table->dropUnique('users_fcm_token_unique');
                } catch (\Throwable $e) {
                    // Index-nya memang belum pernah dibuat. Bukan kegagalan.
                }
            }

            $table->dropColumn(['fcm_token', 'fcm_token_updated_at']);
        });
    }
};
