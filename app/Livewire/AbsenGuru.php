<?php

namespace App\Livewire;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiPegawai;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Pengaturan;
use App\Models\PengaturanSistem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Livewire\Component;

/**
 * Absen Radius — pegawai menekan tombol, browser mengambil koordinat GPS,
 * lalu server menghitung jaraknya ke sekolah dengan rumus Haversine.
 * Kehadiran hanya dicatat kalau jaraknya di dalam radius toleransi.
 *
 * CATATAN soal koordinat sekolah:
 * Koordinat TIDAK dipaku di kode — setiap koreksi titik sekolah (dan koreksi
 * hampir pasti dibutuhkan, karena titik awal jarang langsung tepat) harus
 * bisa dilakukan admin sendiri lewat peta di Pengaturan Sistem.
 *
 * Sumbernya SATU: kolom latitude/longitude/radius_meter di tabel
 * `pengaturan_sistem` — tempat yang sama yang ditulis peta pemilih titik.
 * Kunci lama lat_sekolah/lng_sekolah/radius_gps di tabel `pengaturan` masih
 * dibaca sebagai CADANGAN saja, untuk instalasi yang belum menjalankan
 * migration 000020; begitu kolom barunya terisi, kunci lama tidak lagi
 * berpengaruh. Dua sumber yang sama-sama aktif adalah bug yang paling sulit
 * dilihat di fitur ini: admin menggeser peta, halaman bilang tersimpan, dan
 * absensi tetap memakai titik lama tanpa satu pun pesan error.
 */
class AbsenGuru extends Component
{
    /** Cadangan kalau tabel pengaturan belum diisi (Monas, Jakarta). */
    private const LAT_CADANGAN = -6.175392;
    private const LNG_CADANGAN = 106.827153;
    private const RADIUS_CADANGAN = 50;

    /** Jari-jari rata-rata bumi dalam meter — konstanta rumus Haversine. */
    private const JARI_JARI_BUMI = 6371000;

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /** Jarak hasil pengukuran terakhir (meter), untuk ditampilkan ke pengguna. */
    public ?int $jarakTerakhir = null;

    /**
     * Dipanggil dari JavaScript lewat $wire.prosesAbsen(lat, lng) setelah
     * navigator.geolocation berhasil mendapatkan koordinat.
     */
    public function prosesAbsen($lat, $lng): void
    {
        $this->notif = null;
        $this->jarakTerakhir = null;

        // Koordinat datang dari browser, jadi tidak boleh dipercaya begitu
        // saja — bisa dikirim manual lewat konsol dengan nilai apa pun.
        if (! is_numeric($lat) || ! is_numeric($lng)
            || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            $this->pesan('error', 'Koordinat tidak valid', 'Lokasi yang dikirim perangkat tidak masuk akal. Coba ulangi.');

            return;
        }

        $pegawai = auth()->user()?->pegawai;

        if (! $pegawai) {
            $this->pesan('error', 'Akun belum ditautkan',
                'Akun Anda belum terhubung ke data pegawai, jadi kehadiran belum bisa dicatat. Hubungi admin sekolah.');

            return;
        }

        // Sudah ada catatan hari ini? Termasuk yang dari scan gerbang atau
        // tombol absen mandiri — semuanya menulis ke tabel yang sama.
        $sudahAda = AbsensiPegawai::where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', today())
            ->first();

        if ($sudahAda) {
            $jam = $sudahAda->jam_masuk?->format('H:i');
            $this->pesan('warn', 'Sudah absen',
                $jam
                    ? "Kehadiran Anda hari ini sudah tercatat pukul {$jam}."
                    : 'Kehadiran Anda hari ini sudah tercatat.');

            return;
        }

        $titik = self::titikSekolah();

        $jarak = $this->jarakMeter(
            (float) $lat,
            (float) $lng,
            $titik['lat'],
            $titik['lng'],
        );

        $radius = $titik['radius'];
        $this->jarakTerakhir = (int) round($jarak);

        if ($jarak > $radius) {
            // Jaraknya ikut disebut supaya guru tahu ini soal posisi, bukan
            // aplikasi yang rusak — dan admin bisa menilai apakah radiusnya
            // memang perlu dilonggarkan.
            $this->pesan('error', 'Anda berada di luar radius sekolah',
                "Jarak Anda sekitar {$this->jarakTerakhir} meter dari titik sekolah, sedangkan batasnya {$radius} meter. Mendekatlah ke area sekolah lalu coba lagi.");

            return;
        }

        try {
            $absensi = AbsensiPegawai::create([
                'pegawai_id' => $pegawai->id,
                'tanggal' => today(),
                'jam_masuk' => now()->format('H:i:s'),
                'status' => AbsensiStatus::Hadir,
                'keterangan' => 'Absen lokasi (' . $this->jarakTerakhir . ' m dari sekolah)',
            ]);
        } catch (QueryException $e) {
            // 23000 = pelanggaran constraint; paling mungkin unique(pegawai_id,
            // tanggal) kalau tombol ditekan dua kali nyaris bersamaan.
            if ((string) $e->getCode() === '23000') {
                $this->pesan('warn', 'Sudah absen', 'Kehadiran Anda hari ini sudah tercatat.');

                return;
            }

            Log::error('Gagal menyimpan absen lokasi.', [
                'pegawai_id' => $pegawai->id,
                'error' => $e->getMessage(),
            ]);

            $this->pesan('error', 'Gagal menyimpan', 'Terjadi kesalahan saat menyimpan kehadiran. Coba lagi sebentar.');

            return;
        }

        $this->kirimKonfirmasiWa($pegawai, $absensi);

        $this->pesan('ok', 'Absen berhasil',
            'Kehadiran tercatat pukul ' . $absensi->jam_masuk->format('H:i')
            . " — {$this->jarakTerakhir} meter dari titik sekolah. Selamat bekerja!");
    }

    /**
     * Titik pusat sekolah & radius toleransi yang berlaku.
     *
     * Dibuat static supaya view-nya bisa memakai angka yang PERSIS SAMA
     * dengan yang dipakai server saat memutuskan diterima/ditolak. Kalau
     * peta di layar menggambar lingkaran dari sumber yang berbeda, pegawai
     * bisa melihat dirinya di dalam lingkaran tapi tetap ditolak — dan tidak
     * ada cara baginya untuk menebak kenapa.
     *
     * @return array{lat: float, lng: float, radius: int}
     */
    public static function titikSekolah(): array
    {
        $p = PengaturanSistem::ambil();

        if (filled($p->latitude) && filled($p->longitude)) {
            return [
                'lat' => (float) $p->latitude,
                'lng' => (float) $p->longitude,
                'radius' => (int) ($p->radius_meter ?: self::RADIUS_CADANGAN),
            ];
        }

        // Cadangan: instalasi yang belum menjalankan migration 000020.
        $lama = Pengaturan::ambilBanyak([
            'lat_sekolah' => (string) self::LAT_CADANGAN,
            'lng_sekolah' => (string) self::LNG_CADANGAN,
            'radius_gps' => (string) self::RADIUS_CADANGAN,
        ]);

        return [
            'lat' => (float) $lama['lat_sekolah'],
            'lng' => (float) $lama['lng_sekolah'],
            'radius' => (int) $lama['radius_gps'],
        ];
    }

    /**
     * Rumus Haversine: jarak lingkaran besar antara dua titik di permukaan
     * bumi, dalam meter.
     *
     * Dipakai karena menghitung selisih lat/lng secara datar (Pythagoras)
     * meleset makin jauh dari khatulistiwa — untuk radius puluhan meter
     * selisihnya memang kecil, tapi Haversine benar di semua lintang tanpa
     * biaya tambahan yang berarti.
     */
    private function jarakMeter(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::JARI_JARI_BUMI * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Konfirmasi WhatsApp ke pegawai yang bersangkutan.
     *
     * Penerimanya SENGAJA pegawai itu sendiri, bukan admin atau kepsek:
     * pesan ini gunanya sebagai bukti pegangan ("saya sudah absen jam
     * sekian"), dan mengirimkannya ke pihak lain berarti puluhan pesan
     * masuk setiap pagi ke satu nomor — yang justru membuat notifikasi
     * penting jadi diabaikan.
     *
     * Dibungkus try/catch dan TIDAK pernah menggagalkan absensi: kehadiran
     * sudah tersimpan di database, dan gagal mengirim WhatsApp bukan alasan
     * untuk menampilkan "absen gagal" kepada orang yang sudah berdiri di
     * sekolah.
     */
    private function kirimKonfirmasiWa($pegawai, AbsensiPegawai $absensi): void
    {
        try {
            $nomor = $pegawai->no_hp ?? null;

            if (blank($nomor)) {
                return;
            }

            $pesan = "*Absensi SIMAGAS*\n\n"
                . "Halo {$pegawai->nama}, kehadiran Anda sudah tercatat.\n"
                . 'Tanggal: ' . $absensi->tanggal->translatedFormat('l, d F Y') . "\n"
                . 'Jam masuk: ' . $absensi->jam_masuk->format('H:i') . " WIB\n"
                . "Metode: Absen Radius (GPS)";

            SendWhatsAppNotification::dispatch($nomor, $pesan);
        } catch (\Throwable $e) {
            Log::warning('Konfirmasi WA absen radius gagal diantrekan.', [
                'pegawai_id' => $pegawai->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        $pegawai = auth()->user()?->pegawai;

        return view('livewire.absen-guru', [
            'pegawai' => $pegawai,
            'absensiHariIni' => $pegawai
                ? AbsensiPegawai::where('pegawai_id', $pegawai->id)->whereDate('tanggal', today())->first()
                : null,
            'titik' => self::titikSekolah(),
        ]);
    }
}
