<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Kepsek = 'kepsek';
    case WaliKelas = 'wali_kelas';
    case Guru = 'guru';
    case Staff = 'staff';
    case AdminTu = 'admin_tu';
    case WaliMurid = 'wali_murid';
    case GuruPiket = 'guru_piket';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Kepsek => 'Kepala Sekolah',
            self::WaliKelas => 'Wali Kelas',
            self::Guru => 'Guru',
            self::Staff => 'Staff',
            self::AdminTu => 'Admin TU',
            self::WaliMurid => 'Wali Murid',
            self::GuruPiket => 'Guru Piket',
        };
    }

    /**
     * Prefix rute (dan prefix URL) milik role ini — satu role, satu prefix,
     * tidak ada yang berbagi alamat dengan role lain. Dipakai untuk
     * mendaftarkan grup rute di routes/web.php (prefix() & name()) dan oleh
     * $panelPrefix (lihat AppServiceProvider) supaya link/redirect di
     * controller & view yang dipakai bersama (mis. panel kepsek vs
     * super-admin, keduanya memakai view yang sama) selalu mengarah ke
     * alamat milik role yang sedang login, bukan hardcode ke satu role saja.
     */
    public function routePrefix(): string
    {
        return match ($this) {
            self::SuperAdmin => 'super-admin',
            self::Kepsek => 'kepsek',
            self::WaliKelas => 'wali-kelas',
            self::Guru => 'guru',
            self::Staff => 'staff',
            self::AdminTu => 'admin-tu',
            self::WaliMurid => 'wali-murid',
            self::GuruPiket => 'piket',
        };
    }

    /**
     * Rute dashboard tujuan setelah login, berdasarkan role.
     * Satu-satunya tempat pemetaan role -> rute didefinisikan, dipakai oleh
     * AuthController (redirect setelah login) dan rute '/'.
     *
     * Semua role punya rute '{prefix}.dashboard' sendiri-sendiri, kecuali
     * guru_piket yang landing page-nya adalah scanner, bukan dashboard biasa.
     */
    public function dashboardRouteName(): string
    {
        return match ($this) {
            self::GuruPiket => $this->routePrefix() . '.scanner',
            default => $this->routePrefix() . '.dashboard',
        };
    }

    /**
     * Apakah role ini mewakili pegawai/staf sekolah — artinya butuh baris di
     * tabel `pegawai` (NIP + jabatan). Hanya wali murid yang bukan: ia orang
     * tua siswa, bukan orang sekolah.
     *
     * Dipakai halaman Registrasi untuk memutuskan apakah input NIP & Jabatan
     * perlu dimunculkan DAN divalidasi. Sengaja ditaruh di enum, bukan
     * ditulis dua kali sebagai in_array($role, ['kepsek','guru','staff']) —
     * sekali di Blade untuk menampilkan, sekali di PHP untuk memvalidasi.
     * Dua salinan aturan yang sama adalah cara paling gampang menciptakan
     * celah: yang satu diubah, yang lain lupa, dan NIP lolos tanpa validasi.
     */
    public function adalahPegawai(): bool
    {
        return $this !== self::WaliMurid;
    }

    /**
     * Role yang boleh dipilih di halaman Registrasi Mandiri.
     *
     * super_admin, wali_kelas, admin_tu, dan guru_piket TIDAK ada di sini
     * dengan sengaja: keempatnya adalah penugasan yang diberikan sekolah,
     * bukan sesuatu yang orang klaim sendiri saat mendaftar. Super Admin
     * membuat/menaikkan akun itu lewat menu Manajemen Pengguna.
     *
     * @return array<int, self>
     */
    public static function untukPendaftaran(): array
    {
        return [self::Kepsek, self::Guru, self::Staff, self::WaliMurid];
    }
}
