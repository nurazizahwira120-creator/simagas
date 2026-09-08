<?php

namespace App\Livewire;

use App\Enums\AbsensiStatus;
use App\Jobs\SendWhatsAppNotification;
use App\Models\AbsensiSiswa;
use App\Models\Siswa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Scanner kamera HP untuk absensi siswa, versi Livewire.
 *
 * Bedanya dengan halaman Piket (App\Http\Controllers\Piket\ScannerController):
 * halaman Piket adalah layar khusus penuh untuk gerbang sekolah dan memakai
 * fetch() ke endpoint JSON, sementara komponen ini bisa ditempel di halaman
 * mana pun di dalam layout utama dan hasilnya langsung dirender Livewire —
 * tanpa reload dan tanpa perlu menulis pemanggilan fetch manual.
 *
 * Keduanya menulis ke tabel `absensi_siswa` yang sama, dan sama-sama dijaga
 * unique(siswa_id, tanggal) di database, jadi tidak mungkin dobel walaupun
 * satu siswa di-scan di dua tempat sekaligus.
 */
class ScannerKameraSiswa extends Component
{
    /** Riwayat scan selama sesi ini (paling baru di atas), maksimal 8 baris. */
    public array $riwayat = [];

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /** Berapa banyak siswa yang berhasil dicatat lewat komponen ini sesi ini. */
    public int $jumlahBerhasil = 0;

    /**
     * Dipanggil dari JavaScript lewat $wire.prosesAbsen(nis) setiap kali
     * html5-qrcode berhasil membaca sebuah kode.
     */
    public function prosesAbsen($nis): void
    {
        $nis = trim((string) $nis);

        if ($nis === '') {
            return;
        }

        // Kode datang dari pembacaan kamera, jadi isinya bisa apa saja —
        // termasuk QR promo di kaus orang yang lewat. Dibatasi panjangnya
        // supaya tidak ada string raksasa yang masuk ke query.
        if (mb_strlen($nis) > 50) {
            $this->tolak('Kode tidak dikenali', 'Kode yang terbaca terlalu panjang untuk sebuah NIS.', $nis);

            return;
        }

        $siswa = Siswa::with('kelas')->where('nis', $nis)->first();

        if (! $siswa) {
            $this->tolak('Kode tidak dikenali', "NIS \"{$nis}\" tidak ditemukan di data siswa.", $nis);

            return;
        }

        $absensiHariIni = AbsensiSiswa::where('siswa_id', $siswa->id)
            ->whereDate('tanggal', today())
            ->first();

        if ($absensiHariIni) {
            // jam_masuk bisa NULL kalau catatan hari ini dibuat manual oleh
            // wali kelas, dan statusnya belum tentu Hadir — jadi pesannya
            // mengikuti isi catatan yang sebenarnya.
            $jam = $absensiHariIni->jam_masuk?->format('H:i');

            $pesan = $absensiHariIni->status === AbsensiStatus::Hadir
                ? "{$siswa->nama} sudah tercatat hadir hari ini" . ($jam ? " pukul {$jam}." : '.')
                : "{$siswa->nama} sudah punya catatan hari ini: {$absensiHariIni->status->label()}.";

            $this->notif = ['tipe' => 'warn', 'judul' => 'Sudah absen', 'pesan' => $pesan];
            $this->catat('warn', $siswa->nama, $siswa->kelas?->nama_kelas ?? $siswa->nis, $jam);

            return;
        }

        try {
            $absensi = AbsensiSiswa::create([
                'siswa_id' => $siswa->id,
                'tanggal' => today(),
                'jam_masuk' => now()->format('H:i:s'),
                'status' => AbsensiStatus::Hadir,
            ]);
        } catch (QueryException $e) {
            // 23000 = pelanggaran constraint; di sini hampir pasti
            // unique(siswa_id, tanggal) karena dua scan nyaris bersamaan.
            if ((string) $e->getCode() === '23000') {
                $this->notif = ['tipe' => 'warn', 'judul' => 'Sudah absen', 'pesan' => "{$siswa->nama} sudah tercatat hadir hari ini."];
                $this->catat('warn', $siswa->nama, $siswa->kelas?->nama_kelas ?? $siswa->nis, null);

                return;
            }

            Log::error('Gagal menyimpan absensi siswa dari scanner Livewire.', [
                'nis' => $siswa->nis,
                'error' => $e->getMessage(),
            ]);

            $this->tolak('Gagal menyimpan', 'Terjadi kesalahan saat menyimpan kehadiran. Coba scan ulang.', $nis);

            return;
        }

        // Notifikasi WhatsApp ke wali murid diantrekan lewat queue, sama
        // seperti jalur scanner Piket — supaya satu kejadian "siswa hadir"
        // memberi hasil yang sama dari mana pun ia di-scan.
        SendWhatsAppNotification::dispatch($absensi);

        $jam = $absensi->jam_masuk->format('H:i');
        $this->jumlahBerhasil++;

        $this->notif = [
            'tipe' => 'ok',
            'judul' => $siswa->nama,
            'pesan' => 'Tercatat hadir pukul ' . $jam
                . ($siswa->kelas ? ' — kelas ' . $siswa->kelas->nama_kelas : '') . '.',
        ];

        $this->catat('ok', $siswa->nama, $siswa->kelas?->nama_kelas ?? $siswa->nis, $jam);
    }

    private function tolak(string $judul, string $pesan, string $kode): void
    {
        $this->notif = ['tipe' => 'error', 'judul' => $judul, 'pesan' => $pesan];
        $this->catat('error', $kode, $pesan, null);
    }

    /**
     * Simpan satu baris riwayat. Dipotong di 8 baris supaya payload Livewire
     * (yang dikirim bolak-balik setiap scan) tidak terus membengkak selama
     * sesi scan panjang di gerbang.
     */
    private function catat(string $tipe, string $judul, string $sub, ?string $jam): void
    {
        array_unshift($this->riwayat, [
            'tipe' => $tipe,
            'judul' => $judul,
            'sub' => $sub,
            'jam' => $jam ?? now()->format('H:i'),
        ]);

        $this->riwayat = array_slice($this->riwayat, 0, 8);
    }

    public function render()
    {
        return view('livewire.scanner-kamera-siswa');
    }
}
