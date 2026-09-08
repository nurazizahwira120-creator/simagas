<?php

namespace App\Livewire\Layouts;

use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Lonceng notifikasi di topbar.
 *
 * Dipasang di layouts/app.blade.php, jadi komponen ini ikut dirender di
 * SETIAP halaman aplikasi. Dua konsekuensi yang menentukan bentuknya:
 *
 *   1. Query-nya harus murah. Yang diambil hanya 8 notifikasi belum dibaca
 *      terbaru + satu COUNT, keduanya memakai indeks gabungan
 *      (notifiable_type, notifiable_id, read_at) dari migration 000021.
 *      Mengambil seluruh notifikasi lalu menghitungnya di PHP akan makin
 *      lambat setiap bulan tanpa ada yang menyadari.
 *
 *   2. Tidak ada wire:poll. Lonceng yang menyegar sendiri tiap beberapa detik
 *      berarti satu permintaan HTTP per tab per interval — di sekolah dengan
 *      50 pegawai yang membiarkan tab terbuka seharian, itu ribuan permintaan
 *      untuk sesuatu yang tidak mendesak. Notifikasi muncul saat halaman
 *      berpindah, dan itu cukup untuk pengumuman & pengajuan izin.
 */
class NotificationBell extends Component
{
    /** Banyak notifikasi yang ditampilkan di panel. */
    private const BATAS = 8;

    #[Computed]
    public function belumDibaca()
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        return $user->unreadNotifications()
            ->latest()
            ->limit(self::BATAS)
            ->get();
    }

    #[Computed]
    public function jumlah(): int
    {
        return auth()->user()?->unreadNotifications()->count() ?? 0;
    }

    /**
     * Tandai satu notifikasi terbaca, lalu antar ke halaman terkait.
     *
     * Kepemilikan diperiksa DI SINI, bukan dipercayakan ke tampilan: method
     * Livewire adalah endpoint HTTP tersendiri, jadi id notifikasi milik
     * orang lain bisa dikirim dari konsol browser. Query dibatasi ke
     * notifikasi milik pengguna yang sedang login, sehingga id asing tidak
     * menemukan apa pun.
     */
    public function markAsRead(string $id)
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        $notifikasi = $user->notifications()->whereKey($id)->first();

        if (! $notifikasi) {
            return null;
        }

        $notifikasi->markAsRead();

        unset($this->belumDibaca, $this->jumlah);

        // Rute tujuan disimpan sebagai AKHIRAN nama rute ('.daftar-izin-pegawai'),
        // bukan URL jadi — halaman yang sama punya prefix berbeda untuk kepsek
        // dan super admin. Route::has() menjaga supaya notifikasi lama yang
        // menunjuk halaman yang sudah dihapus tidak melempar
        // RouteNotFoundException, melainkan sekadar tidak berpindah.
        $akhiran = $notifikasi->data['rute'] ?? null;
        $prefix = $user->role?->routePrefix() ?? '';

        if ($akhiran && $prefix !== '' && RouteFacade::has($prefix . $akhiran)) {
            return $this->redirect(route($prefix . $akhiran), navigate: true);
        }

        return null;
    }

    /** Tandai semua terbaca — tanpa berpindah halaman. */
    public function tandaiSemua(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();

        unset($this->belumDibaca, $this->jumlah);
    }

    public function render()
    {
        return view('livewire.layouts.notification-bell');
    }
}
