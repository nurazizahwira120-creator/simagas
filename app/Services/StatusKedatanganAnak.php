<?php

namespace App\Services;

use App\Console\Commands\TandaiAlpaSiswa;
use App\Enums\AbsensiStatus;
use App\Models\AbsensiSiswa;
use App\Models\Pengaturan;
use App\Models\Siswa;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Isi kartu "Kedatangan hari ini" untuk wali murid.
 *
 * ============ MASALAH YANG DIPERBAIKI ============
 * Dulu, selama belum ada baris absensi, kartu ini menampilkan "Belum Absen"
 * MERAH BESAR — warna yang sama dengan Alpa — di setiap keadaan: tengah
 * malam, pukul 06.00 saat anak masih di rumah, bahkan di hari Jumat yang
 * memang libur. Orang tua yang membukanya mengira anaknya membolos.
 * =================================================
 *
 * ============ ATURANNYA ============
 * Ada catatan di absensi_siswa (scan gerbang / piket / wali kelas):
 *   hadir  -> hijau  "Sudah tiba di sekolah"
 *   izin   -> ungu   "Izin"         (keterangan dari piket/wali kelas)
 *   sakit  -> ungu   "Sakit"
 *   alpha  -> MERAH  "Tidak hadir tanpa keterangan"  <- satu-satunya merah
 *
 * Belum ada catatan:
 *   bukan hari KBM (libur mingguan / agenda libur) -> abu-abu "Hari ini libur"
 *   sebelum batas terlambat                        -> biru    "Belum tiba"
 *   lewat batas terlambat, sebelum jam pulang      -> kuning  "Belum tercatat di gerbang"
 *   lewat jam pulang                               -> kuning  "Tidak tercatat hadir hari ini"
 *
 * Merah TIDAK pernah dipakai untuk "belum ada catatan": yang memutuskan
 * seorang siswa alpa adalah sekolah (TandaiAlpaSiswa sesudah jam pulang,
 * atau wali kelas), bukan halaman ini. Jam dan hari libur dibaca dari
 * sumber yang SAMA dengan TandaiAlpaSiswa — pengaturan batas_terlambat_siswa
 * dan KalenderAkademik — supaya kartu ini tidak pernah berkata "libur"
 * pada hari yang ternyata ditandai alpa, atau sebaliknya.
 * ===================================
 */
class StatusKedatanganAnak
{
    /** @var array<string, string|null>|null  dibaca sekali per request */
    private ?array $aturan = null;

    public function __construct(private readonly KalenderAkademik $kalender)
    {
    }

    /**
     * @return array{
     *     jenis: string, judul: string, keterangan: string, ikon: string,
     *     denyut: bool, hubungi: bool
     * }
     *   jenis: libur | belum | telat | hadir | izin | sakit | alpa
     *   hubungi: true = tampilkan tombol "Hubungi Wali Kelas"
     */
    public function untuk(?AbsensiSiswa $absensi, ?Carbon $sekarang = null): array
    {
        $sekarang = ($sekarang ?? now())->copy();

        if ($absensi && $absensi->status) {
            return $this->dariCatatan($absensi);
        }

        // ---- Belum ada catatan sama sekali ----

        if ($libur = $this->alasanLibur($sekarang)) {
            return $this->hasil('libur', 'Hari ini libur', $libur . '. Tidak ada kegiatan belajar di sekolah.', 'sun');
        }

        $jamMasuk = $this->jam('jam_masuk_siswa', '07:00');
        $batas = $this->jam('batas_terlambat_siswa', '07:15');
        $jamPulang = $this->jamPulang($sekarang);

        if ($sekarang->lt($this->pada($sekarang, $batas))) {
            return $this->hasil(
                'belum',
                'Belum tiba di sekolah',
                "Jam masuk {$this->titik($jamMasuk)} · batas terlambat {$this->titik($batas)}. Status berubah otomatis begitu anak scan di gerbang.",
                'clock',
                denyut: true,
            );
        }

        if ($jamPulang === null || $sekarang->lt($jamPulang)) {
            return $this->hasil(
                'telat',
                'Belum tercatat di gerbang',
                "Batas masuk {$this->titik($batas)} sudah lewat. Bila anak sudah berangkat, mungkin lupa scan — silakan konfirmasi ke wali kelas.",
                'exclamation-triangle',
                hubungi: true,
            );
        }

        return $this->hasil(
            'telat',
            'Tidak tercatat hadir hari ini',
            'Sampai jam pulang (' . $jamPulang->format('H.i') . ') belum ada catatan kedatangan. Bila ini keliru, hubungi wali kelas.',
            'exclamation-triangle',
            hubungi: true,
        );
    }

    /* ===================== KONTAK WALI KELAS ===================== */

    /**
     * Tautan WhatsApp ke wali kelas anak, atau null kalau nomornya tidak
     * tersimpan / tidak sah.
     *
     * Nomor dibaca dari profil akun wali kelas (users.no_hp), cadangannya
     * data pegawai (pegawai.no_hp). Pemanggil wajib sudah memuat
     * kelas.waliKelas.pegawai supaya tidak ada query tambahan.
     *
     * @return array{nama: string, url: string}|null
     */
    public function kontakWaliKelas(Siswa $anak, ?Carbon $tanggal = null): ?array
    {
        $wali = $anak->kelas?->waliKelas;

        if (! $wali) {
            return null;
        }

        $nomor = WhatsAppService::normalkanNomor($wali->no_hp)
            ?? WhatsAppService::normalkanNomor($wali->pegawai?->no_hp);

        if (! $nomor) {
            return null;
        }

        $tanggal = ($tanggal ?? now())->translatedFormat('l, d F Y');
        $pesan = "Assalamu'alaikum, Bapak/Ibu {$wali->name}. Saya wali murid dari {$anak->nama}"
            . ($anak->kelas?->nama_kelas ? " ({$anak->kelas->nama_kelas})" : '')
            . ", ingin menanyakan kehadiran anak saya hari ini, {$tanggal}.";

        return [
            'nama' => $wali->name,
            'url' => 'https://wa.me/' . $nomor . '?text=' . rawurlencode($pesan),
        ];
    }

    /* ===================== BANTU ===================== */

    private function dariCatatan(AbsensiSiswa $a): array
    {
        return match ($a->status) {
            AbsensiStatus::Hadir => $this->hasil(
                'hadir',
                $a->jam_masuk ? 'Sudah tiba · ' . $a->jam_masuk->format('H.i') : 'Sudah tiba di sekolah',
                $this->keteranganHadir($a),
                'check-circle',
            ),
            AbsensiStatus::Izin => $this->hasil('izin', 'Izin', $a->keterangan ?: 'Tidak masuk dengan izin.', 'mail'),
            AbsensiStatus::Sakit => $this->hasil('sakit', 'Sakit', $a->keterangan ?: 'Tidak masuk karena sakit.', 'mail'),
            AbsensiStatus::Alpha => $this->hasil(
                'alpa',
                'Tidak hadir tanpa keterangan',
                // Penanda otomatis TandaiAlpaSiswa ditulis untuk admin; orang
                // tua mendapat kalimat yang lebih jelas.
                ($a->keterangan && $a->keterangan !== TandaiAlpaSiswa::PENANDA)
                    ? $a->keterangan . ' Bila ini keliru, hubungi wali kelas.'
                    : 'Tidak tercatat hadir sampai jam pulang. Bila ini keliru, hubungi wali kelas.',
                'x-circle',
                hubungi: true,
            ),
        };
    }

    private function keteranganHadir(AbsensiSiswa $a): string
    {
        if (! $a->jam_masuk) {
            return 'Ditandai hadir oleh wali kelas.';
        }

        $batas = $this->jam('batas_terlambat_siswa', '07:15');

        // Dibandingkan sebagai "HH:MM" — jam_masuk ber-cast datetime dan
        // bisa membawa tanggal apa pun dari kolom TIME.
        return $a->jam_masuk->format('H:i') > $batas
            ? 'Tercatat scan di gerbang — terlambat (batas ' . $this->titik($batas) . ').'
            : 'Tercatat scan QR di gerbang sekolah.';
    }

    private function alasanLibur(Carbon $sekarang): ?string
    {
        // Kalender yang gagal dibaca tidak boleh mematikan dashboard; tanpa
        // keterangan libur, kartu jatuh ke aturan jam biasa.
        try {
            return $this->kalender->alasanBukanKbm($sekarang->copy()->startOfDay());
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function jamPulang(Carbon $sekarang): ?Carbon
    {
        try {
            return $this->kalender->jamPulangPada($sekarang->copy()->startOfDay());
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Nilai jam dari pengaturan; nilai rusak diganti bawaan, bukan melempar
     * exception. Kedua kunci dibaca dalam SATU query lalu disimpan.
     */
    private function jam(string $kunci, string $bawaan): string
    {
        $this->aturan ??= Pengaturan::ambilBanyak([
            'jam_masuk_siswa' => '07:00',
            'batas_terlambat_siswa' => '07:15',
        ]);

        $nilai = trim((string) ($this->aturan[$kunci] ?? $bawaan));

        return preg_match('/^([01]\d|2[0-3]):[0-5]\d/', $nilai) ? substr($nilai, 0, 5) : $bawaan;
    }

    private function pada(Carbon $hari, string $jam): Carbon
    {
        [$h, $m] = explode(':', $jam);

        return $hari->copy()->setTime((int) $h, (int) $m);
    }

    /** "07:15" -> "07.15" — penulisan jam yang lazim dibaca orang tua. */
    private function titik(string $jam): string
    {
        return str_replace(':', '.', $jam);
    }

    private function hasil(string $jenis, string $judul, string $keterangan, string $ikon, bool $denyut = false, bool $hubungi = false): array
    {
        return compact('jenis', 'judul', 'keterangan', 'ikon', 'denyut', 'hubungi');
    }
}
