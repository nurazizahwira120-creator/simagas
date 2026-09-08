<?php

namespace App\Notifications;

use App\Models\AbsensiPegawai;
use Illuminate\Notifications\Notification;

/**
 * Pemberitahuan ke Kepala Sekolah & Super Admin saat ada pegawai mengajukan
 * izin/sakit.
 *
 * SALURANNYA HANYA 'database'. Tidak ada email, tidak ada WhatsApp — dan itu
 * disengaja: pengajuan izin bisa terjadi beberapa kali sehari, dan mengirim
 * WhatsApp untuk tiap-tiapnya berarti kepala sekolah menerima puluhan pesan
 * sebulan sampai ia mematikan notifikasinya sama sekali. Lonceng di aplikasi
 * cukup; pesan WhatsApp disimpan untuk hal yang benar-benar mendesak.
 */
class PengajuanIzinBaru extends Notification
{
    public function __construct(
        private readonly string $namaPegawai,
        private readonly string $jabatan,
        private readonly string $status,
        private readonly string $tanggal,
        private readonly string $alasan,
    ) {}

    public static function dari(AbsensiPegawai $absensi): self
    {
        return new self(
            $absensi->pegawai?->nama ?? 'Pegawai',
            $absensi->pegawai?->jabatan ?: '-',
            $absensi->status->shortLabel(),
            // locale('id') DITULIS EKSPLISIT, bukan mengandalkan setelan
            // global. Teks ini disimpan permanen ke tabel notifications:
            // kalau locale-nya meleset saat baris dibuat, tanggal berbahasa
            // Inggris itu ikut tersimpan selamanya dan tidak bisa diperbaiki
            // dengan mengubah pengaturan belakangan.
            $absensi->tanggal->locale('id')->translatedFormat('l, d F Y'),
            (string) $absensi->alasan,
        );
    }

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Pengajuan izin baru',
            'pesan' => $this->namaPegawai . ' (' . $this->jabatan . ') mengajukan '
                . strtolower($this->status) . ' untuk ' . $this->tanggal . '.',

            // Potongan alasan supaya penerima tahu isinya tanpa membuka
            // halaman — tapi dipotong, karena lonceng bukan tempat membaca
            // paragraf panjang.
            'cuplikan' => \Illuminate\Support\Str::limit($this->alasan, 90),

            'ikon' => 'clipboard-check',
            'warna' => 'warning',

            // Akhiran nama rute, bukan URL jadi. Halaman tujuannya berbeda
            // prefix untuk kepsek dan super admin, dan URL yang dipaku saat
            // notifikasi DIBUAT akan salah begitu penerimanya berbeda peran.
            'rute' => '.daftar-izin-pegawai',
        ];
    }
}
