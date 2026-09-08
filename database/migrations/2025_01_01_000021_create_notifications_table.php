<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel notifikasi BAWAAN LARAVEL — bentuknya sengaja dibuat persis sama
 * dengan hasil `php artisan make:notifications-table`.
 *
 * Kenapa mengikuti bentuk baku, bukan bikin tabel sendiri: dengan bentuk ini
 * seluruh mesin notifikasi Laravel langsung bisa dipakai apa adanya —
 * $user->notify(...), $user->unreadNotifications, ->markAsRead(), broadcast,
 * antrean. Tabel buatan sendiri berarti semua itu harus ditulis ulang, dan
 * setiap fitur notifikasi berikutnya membayar ongkosnya lagi.
 *
 * Kunci utamanya UUID, bukan auto-increment: id notifikasi ikut muncul di URL
 * saat ditandai terbaca, dan angka berurutan membocorkan berapa banyak
 * notifikasi yang pernah dikirim seluruh sekolah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Lonceng di topbar menjalankan query "milik saya, yang belum
            // dibaca" di SETIAP halaman. Tanpa indeks gabungan ini, query itu
            // memindai seluruh tabel — dan tabel notifikasi adalah tabel yang
            // tumbuh paling cepat di sistem seperti ini.
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_belum_dibaca_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
