<?php

namespace App\Livewire\Ekskul\Concerns;

use App\Enums\UserRole;
use App\Models\JadwalEkskul;
use App\Models\Pegawai;

/**
 * Aturan siapa boleh apa di modul Ekstrakurikuler — ditulis SEKALI di sini,
 * dipakai tiga komponen (KelolaEkskul, AnggotaEkskul, AbsensiEkskul).
 *
 * ============ KENAPA DISATUKAN ============
 * Halaman ekskul dibuka SEMUA peran, tapi yang boleh dilakukan berbeda-beda:
 * admin mengelola jadwal, pembina mengelola anggotanya sendiri dan mengisi
 * absensi, wali murid hanya melihat anaknya. Aturan seperti itu paling sering
 * bocor ketika ditulis ulang di tiap komponen — satu tempat diperbaiki, dua
 * tempat lain ketinggalan, dan celahnya tidak kelihatan sampai ada yang
 * mencoba. Semua komponen memanggil method di bawah, tidak ada yang menulis
 * perbandingan peran sendiri.
 *
 * Perlu diingat: ini dipanggil ULANG di setiap method Livewire yang mengubah
 * data, bukan sekali di mount(). Method Livewire adalah endpoint HTTP
 * tersendiri; pemeriksaan yang hanya dilakukan saat halaman dibuka tidak
 * menghalangi siapa pun yang memanggil methodnya langsung dari konsol.
 * ==========================================
 */
trait PeranEkskul
{
    /** Peran yang boleh menambah/mengubah/menghapus JADWAL ekskul. */
    protected function bolehKelolaJadwal(): bool
    {
        $peran = auth()->user()?->role;

        return $peran !== null
            && in_array($peran, [UserRole::SuperAdmin, UserRole::Kepsek], true);
    }

    /** Baris `pegawai` milik akun yang sedang login (null untuk wali murid). */
    protected function pegawaiSaya(): ?Pegawai
    {
        return auth()->user()?->pegawai;
    }

    /**
     * Boleh mengelola ANGGOTA & ABSENSI ekskul ini: admin/kepsek, atau
     * pegawai yang memang tercatat sebagai pembinanya.
     */
    protected function bolehKelolaEkskul(?JadwalEkskul $jadwal): bool
    {
        if (! $jadwal) {
            return false;
        }

        if ($this->bolehKelolaJadwal()) {
            return true;
        }

        $pegawai = $this->pegawaiSaya();

        return $pegawai !== null
            && $jadwal->pembina_id !== null
            && (int) $jadwal->pembina_id === (int) $pegawai->id;
    }

    protected function sayaWaliMurid(): bool
    {
        return auth()->user()?->role === UserRole::WaliMurid;
    }

    /**
     * Id anak-anak dari wali murid yang sedang login.
     *
     * Dipakai untuk MEMBATASI query, bukan untuk menyaring hasil di PHP
     * setelah semuanya terlanjur diambil — supaya nama siswa lain tidak
     * pernah ikut terkirim ke browser sejak awal.
     *
     * @return array<int, int>
     */
    protected function idAnakSaya(): array
    {
        $user = auth()->user();

        if (! $user || $user->role !== UserRole::WaliMurid) {
            return [];
        }

        return $user->siswaWali()->pluck('siswa.id')->all();
    }
}
