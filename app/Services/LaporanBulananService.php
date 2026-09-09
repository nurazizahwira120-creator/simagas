<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiMengajar;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\MonthlyReport;
use App\Models\Pegawai;
use App\Models\Siswa;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Merekap kehadiran sebulan penuh lalu menuliskannya jadi berkas PDF.
 *
 * Dipisah dari Command-nya dengan sengaja: perintah artisan mengurus
 * argumen, jadwal, dan pesan di layar; kelas ini mengurus DATA dan BERKAS.
 * Pemisahan itu yang membuat isinya bisa diuji tanpa menjalankan cron.
 *
 * ============ SOAL "HARI KERJA" ============
 * Persentase kehadiran perlu pembagi. Yang dipakai di sini adalah jumlah
 * hari Senin–Sabtu dalam bulan itu — bukan seluruh hari kalender.
 *
 * Ini pendekatan sederhana yang SENGAJA dipilih, dan batasnya perlu
 * diketahui: hari libur nasional dan libur sekolah TIDAK dikecualikan,
 * karena aplikasi ini belum punya tabel kalender akademik. Akibatnya
 * persentase di bulan yang banyak liburnya akan terlihat lebih rendah dari
 * kenyataan.
 *
 * Ditulis terang-terangan di sini dan DICETAK DI KAKI LAPORAN, supaya
 * kepala sekolah membaca angkanya dengan konteks yang benar — bukan
 * menyimpulkan gurunya banyak bolos di bulan Ramadan.
 * ===========================================
 */
class LaporanBulananService
{
    /** Folder di dalam disk 'public'. */
    public const FOLDER = 'laporan-bulanan';

    /**
     * Rekap satu bulan penuh menjadi array siap-render.
     *
     * @return array<string, mixed>
     */
    public function rekap(CarbonImmutable $bulan): array
    {
        $awal = $bulan->startOfMonth();
        $akhir = $bulan->endOfMonth();

        $hariKerja = $this->hitungHariKerja($awal);

        // ---------------------------------------------------------------
        // PEGAWAI
        // ---------------------------------------------------------------
        $pegawai = Pegawai::query()
            ->select(['id', 'nama', 'nip', 'jabatan'])
            ->orderBy('nama')
            ->get();

        $absensiPegawai = AbsensiPegawai::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['pegawai_id', 'status'])
            ->groupBy('pegawai_id');

        // Sesi mengajar yang BENAR-BENAR ditutup lengkap dengan bukti —
        // inilah hasil Validasi Silang KBM yang masuk ke laporan. Sesi yang
        // hanya di-scan lalu ditinggalkan tidak dihitung, dan memang itu
        // gunanya kolom waktu_selesai dibuat.
        $sesiMengajar = AbsensiMengajar::query()
            ->whereBetween('waktu_mulai', [$awal->startOfDay(), $akhir->endOfDay()])
            ->whereNotNull('waktu_selesai')
            ->whereNotNull('foto_bukti')
            ->get(['user_id'])
            ->countBy('user_id');

        $userIdPegawai = Pegawai::query()
            ->whereNotNull('user_id')
            ->pluck('user_id', 'id');

        $barisPegawai = $pegawai->map(function (Pegawai $p) use ($absensiPegawai, $sesiMengajar, $userIdPegawai, $hariKerja) {
            $milik = $absensiPegawai->get($p->id, collect());
            $hitung = $milik->countBy(fn ($a) => $a->status instanceof AbsensiStatus ? $a->status->value : (string) $a->status);

            $hadir = (int) $hitung->get(AbsensiStatus::Hadir->value, 0);
            $userId = $userIdPegawai->get($p->id);

            return [
                'nama' => $p->nama,
                'nip' => $p->nip ?: '—',
                'jabatan' => $p->jabatan ?: '—',
                'hadir' => $hadir,
                'izin' => (int) $hitung->get(AbsensiStatus::Izin->value, 0),
                'sakit' => (int) $hitung->get(AbsensiStatus::Sakit->value, 0),
                'alpha' => (int) $hitung->get(AbsensiStatus::Alpha->value, 0),
                'sesi_mengajar' => $userId ? (int) $sesiMengajar->get($userId, 0) : 0,
                'persen' => $hariKerja > 0 ? round($hadir / $hariKerja * 100, 1) : null,
            ];
        })->all();

        // ---------------------------------------------------------------
        // SISWA
        // ---------------------------------------------------------------
        $siswa = Siswa::query()
            ->with('kelas:id,nama_kelas')
            ->select(['id', 'nama', 'nis', 'kelas_id'])
            ->orderBy('nama')
            ->get();

        $absensiSiswa = AbsensiSiswa::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->get(['siswa_id', 'status'])
            ->groupBy('siswa_id');

        // Bolos hanya ada di jurnal KBM, bukan di absensi gerbang — dan
        // justru angka itulah yang paling ingin dilihat kepala sekolah:
        // siswa yang masuk gerbang tapi tidak sampai ke kelas.
        $bolos = AbsensiKbmSiswa::query()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->where('status', StatusKbm::Bolos->value)
            ->get(['siswa_id'])
            ->countBy('siswa_id');

        $barisSiswa = $siswa->map(function (Siswa $s) use ($absensiSiswa, $bolos, $hariKerja) {
            $milik = $absensiSiswa->get($s->id, collect());
            $hitung = $milik->countBy(fn ($a) => $a->status instanceof AbsensiStatus ? $a->status->value : (string) $a->status);

            $hadir = (int) $hitung->get(AbsensiStatus::Hadir->value, 0);

            return [
                'nama' => $s->nama,
                'nis' => $s->nis,
                'kelas' => $s->kelas?->nama_kelas ?? '—',
                'hadir' => $hadir,
                'izin' => (int) $hitung->get(AbsensiStatus::Izin->value, 0),
                'sakit' => (int) $hitung->get(AbsensiStatus::Sakit->value, 0),
                'alpha' => (int) $hitung->get(AbsensiStatus::Alpha->value, 0),
                'bolos' => (int) $bolos->get($s->id, 0),
                'persen' => $hariKerja > 0 ? round($hadir / $hariKerja * 100, 1) : null,
            ];
        })->all();

        return [
            'periode' => $awal,
            'label_periode' => $awal->translatedFormat('F Y'),
            'hari_kerja' => $hariKerja,
            'dibuat_pada' => now(),

            'pegawai' => $barisPegawai,
            'siswa' => $barisSiswa,

            'ringkas' => [
                'jumlah_pegawai' => count($barisPegawai),
                'jumlah_siswa' => count($barisSiswa),
                'kehadiran_pegawai' => array_sum(array_column($barisPegawai, 'hadir')),
                'kehadiran_siswa' => array_sum(array_column($barisSiswa, 'hadir')),
                'total_sesi_mengajar' => array_sum(array_column($barisPegawai, 'sesi_mengajar')),
                'total_bolos' => array_sum(array_column($barisSiswa, 'bolos')),
            ],
        ];
    }

    /**
     * Rekap + render PDF + simpan berkas + catat ke tabel monthly_reports.
     *
     * ============ KENAPA BUKAN updateOrCreate() ============
     * Bentuk yang wajar ditulis di sini adalah:
     *
     *   MonthlyReport::updateOrCreate(['periode' => $tanggal], [...]);
     *
     * dan itu GAGAL. Kolom `periode` punya cast 'date', jadi Eloquent
     * MENULISNYA sebagai "2026-07-01 00:00:00" sementara pencariannya
     * membandingkan dengan "2026-07-01". Keduanya tidak pernah cocok:
     * baris lamanya tidak ditemukan, Eloquent mencoba INSERT, lalu index
     * unique menolaknya —
     *
     *   SQLSTATE[23000]: UNIQUE constraint failed: monthly_reports.periode
     *
     * Yang membuatnya berbahaya: di MySQL kolom bertipe DATE memotong
     * bagian jamnya diam-diam sehingga pencariannya KEBETULAN cocok, jadi
     * bug ini tidak terlihat di produksi dan baru muncul di SQLite. Kalau
     * suatu saat kolomnya diubah jadi DATETIME, ia akan muncul juga di
     * produksi — saat cron mencoba membuat ulang laporan.
     *
     * whereDate() membandingkan BAGIAN TANGGALNYA saja, jadi cocok di
     * kedua database.
     * =======================================================
     */
    public function buat(CarbonImmutable $bulan): MonthlyReport
    {
        $data = $this->rekap($bulan);

        $nama = self::FOLDER . '/laporan-' . $data['periode']->format('Y-m') . '.pdf';

        // Berkasnya ditulis LEBIH DULU. Kalau render PDF gagal, prosesnya
        // berhenti di sini dan tidak ada baris database yang menunjuk
        // berkas yang tidak pernah ada.
        Storage::disk('public')->put($nama, $this->render($data));

        $isi = [
            'judul' => 'Laporan Kehadiran ' . $data['label_periode'],
            'file_path' => $nama,
            'jumlah_hari_kerja' => $data['hari_kerja'],
            'jumlah_pegawai' => $data['ringkas']['jumlah_pegawai'],
            'jumlah_siswa' => $data['ringkas']['jumlah_siswa'],
            'kehadiran_pegawai' => $data['ringkas']['kehadiran_pegawai'],
            'kehadiran_siswa' => $data['ringkas']['kehadiran_siswa'],
            'dibuat_pada' => now(),
        ];

        $laporan = MonthlyReport::whereDate('periode', $data['periode']->toDateString())->first();

        if ($laporan) {
            $laporan->fill($isi)->save();

            return $laporan;
        }

        return MonthlyReport::create($isi + ['periode' => $data['periode']->toDateString()]);
    }

    /**
     * Ubah data rekap jadi isi berkas PDF.
     *
     * ============ KENAPA class_exists, BUKAN langsung Pdf:: ============
     * barryvdh/laravel-dompdf adalah paket Composer yang HARUS dipasang
     * terpisah. Kalau kode ini sudah ter-deploy tapi `composer require`
     * belum dijalankan di server, pemanggilan langsung menghasilkan
     * "Class 'Barryvdh\DomPDF\Facade\Pdf' not found" — dan karena
     * pemanggilnya adalah CRON, error itu terjadi jam 1 pagi tanpa ada yang
     * melihatnya. Yang tampak keesokan harinya cuma: laporannya tidak ada.
     *
     * Pemeriksaan di bawah mengubah kegagalan diam itu menjadi pesan yang
     * menyebut persis perintah yang kurang dijalankan.
     * ==================================================================
     *
     * @param  array<string, mixed>  $data
     */
    public function render(array $data): string
    {
        $kelas = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($kelas)) {
            throw new RuntimeException(
                'Paket PDF belum terpasang. Jalankan di server: '
                . 'composer require barryvdh/laravel-dompdf'
            );
        }

        return $kelas::loadView('laporan.bulanan-pdf', $data)
            // A4 landscape: tabel rekap punya 9 kolom, dan di potrait
            // kolom terakhirnya terpotong keluar halaman.
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false])
            ->output();
    }

    /**
     * Jumlah hari Senin–Sabtu dalam bulan tersebut.
     *
     * Minggu dikecualikan; hari libur nasional TIDAK (lihat catatan di
     * kepala kelas ini).
     */
    public function hitungHariKerja(CarbonImmutable $bulan): int
    {
        $awal = $bulan->startOfMonth();
        $akhir = $bulan->endOfMonth();
        $jumlah = 0;

        for ($h = $awal; $h->lessThanOrEqualTo($akhir); $h = $h->addDay()) {
            if (! $h->isSunday()) {
                $jumlah++;
            }
        }

        return $jumlah;
    }

    /**
     * Pastikan berkas View PDF-nya ada sebelum dipakai.
     *
     * Dipanggil perintah artisan sebelum bekerja: lebih baik gagal dalam
     * sedetik dengan pesan jelas daripada merekap 400 siswa lalu meledak di
     * langkah terakhir.
     */
    public function siap(): bool
    {
        return View::exists('laporan.bulanan-pdf');
    }
}
