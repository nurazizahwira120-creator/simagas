<?php

namespace App\Notifications;

use App\Models\JadwalPelajaran;
use App\Services\PengingatAkhiriSesi;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Isi LONCENG untuk pengingat "Akhiri Sesi".
 *
 * Bunyi & getar di HP-nya datang dari push Firebase yang dikirim bersamaan
 * (lihat perintah simagas:pengingat-akhiri-sesi). Baris lonceng ini adalah
 * jejaknya: tetap ada setelah notifikasi di layar kunci diusap, dan bisa
 * diketuk untuk langsung membuka Jurnal & Absen Kelas.
 */
class AkhiriSesiBelumDitekan extends Notification
{
    public function __construct(
        private readonly string $mapel,
        private readonly string $pesan,
        private readonly string $judul = 'Sesi kelas belum diakhiri',
        private readonly string $rute = '.jurnal-kelas',
    ) {}

    /**
     * Versi EKSKUL. Rutenya ke daftar Jadwal Ekskul: lonceng hanya
     * menyimpan akhiran nama rute TANPA parameter, jadi halaman absensi
     * satu ekskul tidak bisa dituju langsung dari sini.
     */
    public static function untukEkskul(\App\Models\JadwalEkskul $jadwal, Carbon $batas): self
    {
        return new self(
            'Ekskul ' . $jadwal->nama_ekskul,
            \App\Services\AturanSesiEkskul::kalimat($jadwal, $batas),
            'Sesi ekskul belum diakhiri',
            '.ekskul',
        );
    }

    public static function untuk(JadwalPelajaran $jadwal, Carbon $batas): self
    {
        return new self(
            (string) $jadwal->mata_pelajaran,
            PengingatAkhiriSesi::kalimat($jadwal, $batas),
        );
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => $this->judul,
            'pesan' => $this->pesan,
            'ikon' => 'clock',
            'warna' => 'danger',
            'mapel' => $this->mapel,

            // Akhiran nama rute — prefix panel (guru/wali-kelas/kepsek)
            // ditambahkan NotificationBell sesuai peran pembacanya.
            'rute' => $this->rute,
        ];
    }
}
