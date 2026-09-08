<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Route as RouteFacade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Lonceng notifikasi di topbar — dipakai SEMUA peran.
 *
 * Dipasang di layouts/app.blade.php, jadi komponen ini ikut dirender di
 * SETIAP halaman aplikasi. Dua konsekuensi yang menentukan bentuknya:
 *
 *   1. Query-nya harus murah. Yang diambil hanya 8 notifikasi terbaru +
 *      satu COUNT, keduanya memakai indeks gabungan
 *      (notifiable_type, notifiable_id, read_at) dari migration 000021.
 *      Mengambil seluruh notifikasi lalu menghitungnya di PHP akan makin
 *      lambat setiap bulan tanpa ada yang menyadari.
 *
 *   2. TIDAK ADA wire:poll. Penyegarannya didorong Pusher lewat event
 *      'segarkan-lonceng' dari layout — jadi tidak ada satu pun permintaan
 *      HTTP selama tidak ada notifikasi baru. Lonceng ber-wire:poll berarti
 *      satu permintaan per tab per interval; di sekolah dengan 50 pegawai
 *      yang membiarkan tab terbuka seharian itu ribuan permintaan sia-sia.
 *
 * ============ SEMUA AKSI MEMERIKSA KEPEMILIKAN ULANG ============
 * Method Livewire adalah endpoint HTTP tersendiri: id notifikasi milik orang
 * lain bisa dikirim dari konsol browser. Karena itu setiap method di bawah
 * memulai query-nya dari $user->notifications(), bukan dari
 * DatabaseNotification::find($id) — id asing tidak menemukan apa pun,
 * bukan menemukan lalu ditolak.
 * ================================================================
 */
class NotificationBell extends Component
{
    /** Banyak notifikasi yang ditampilkan di panel. */
    private const BATAS = 8;

    /** Tampilkan yang sudah dibaca juga, bukan hanya yang belum. */
    public bool $tampilkanSemua = false;

    #[Computed]
    public function daftar()
    {
        $user = auth()->user();

        if (! $user) {
            return collect();
        }

        $q = $this->tampilkanSemua
            ? $user->notifications()
            : $user->unreadNotifications();

        return $q->latest()->limit(self::BATAS)->get();
    }

    #[Computed]
    public function jumlah(): int
    {
        return auth()->user()?->unreadNotifications()->count() ?? 0;
    }

    #[Computed]
    public function totalSemua(): int
    {
        return auth()->user()?->notifications()->count() ?? 0;
    }

    private function segarkan(): void
    {
        unset($this->daftar, $this->jumlah, $this->totalSemua);
    }

    /**
     * Dipanggil dari browser saat Pusher mengabarkan ada notifikasi baru.
     *
     * Isinya TIDAK diambil dari payload Pusher, melainkan dibaca ulang dari
     * database milik pengguna yang sedang login. Payload Pusher datang dari
     * luar dan cuma berfungsi sebagai ketukan pintu — menampilkannya apa
     * adanya berarti apa pun yang bisa menembus channel ikut tampil sebagai
     * notifikasi resmi.
     */
    #[On('segarkan-lonceng')]
    public function segarkanDariSiaran(): void
    {
        $this->segarkan();
    }

    public function alihkanTampilan(): void
    {
        $this->tampilkanSemua = ! $this->tampilkanSemua;
        $this->segarkan();
    }

    /**
     * Tandai satu notifikasi terbaca, lalu antar ke halaman terkait.
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

        $this->segarkan();

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

        $this->segarkan();
    }

    /**
     * Hapus SATU notifikasi milik sendiri.
     *
     * Yang dihapus hanya baris di tabel `notifications` milik pengguna ini.
     * Data pengumuman induknya (tabel `pengumuman`) TIDAK tersentuh, dan
     * salinan notifikasi milik pengguna lain juga tidak — setiap penerima
     * punya barisnya sendiri.
     */
    public function hapus(string $id): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $user->notifications()->whereKey($id)->delete();

        $this->segarkan();
    }

    /** Bersihkan seluruh notifikasi milik sendiri. */
    public function hapusSemua(): void
    {
        auth()->user()?->notifications()->delete();

        $this->tampilkanSemua = false;
        $this->segarkan();
    }

    public function render()
    {
        return view('livewire.notification-bell');
    }
}
