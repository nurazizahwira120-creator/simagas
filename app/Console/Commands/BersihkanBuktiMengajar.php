<?php

namespace App\Console\Commands;

use App\Models\AbsensiMengajar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Membuang berkas foto bukti mengajar yang sudah lewat masa simpannya.
 *
 * ============ KENAPA FITUR INI ADA ============
 * Foto bukti diambil langsung dari kamera HP guru. Polanya di sekolah ini
 * kira-kira 11 guru x 4 sesi x 25 hari = 1.100 foto per bulan. Sejak
 * App\Services\PemampatFoto dipasang, satu foto tinggal ~150 KB — jadi
 * sekitar 165 MB per bulan, hampir 2 GB setahun, dan tidak ada satu pun
 * proses yang pernah menghapusnya.
 *
 * Gejala kehabisan kuota pada hosting bersama tidak pernah berbunyi "disk
 * penuh". Yang terjadi: unggahan tiba-tiba gagal, dan halaman error tanpa
 * sebab yang jelas.
 *
 * ============ YANG DIHAPUS HANYA BERKASNYA, BUKAN CATATANNYA ============
 * Perintah ini TIDAK mengosongkan kolom `foto_bukti`, dan itu keputusan yang
 * paling penting di berkas ini.
 *
 * Dua hal bergantung pada kolom itu TERISI:
 *   - LaporanBulananService  -> menghitung "sesi KBM tervalidasi"
 *   - KalkulasiPenghargaanGuru -> menilai ketertiban administrasi guru
 *
 * Keduanya hanya bertanya "apakah gurunya dulu mengunggah bukti", bukan
 * "apakah berkasnya masih ada hari ini". Kalau kolomnya ikut dikosongkan,
 * rekam jejak guru berubah surut: laporan bulan-bulan lalu yang sudah
 * dicetak dan ditandatangani tiba-tiba menunjukkan angka berbeda, dan Guru
 * Teladan bulan lalu kehilangan dasar penilaiannya.
 *
 * Yang dipakai untuk membedakan "belum pernah ada" dari "sudah dibuang"
 * adalah kolom `bukti_dihapus_pada` (migrasi 000035).
 *
 * ============ KENAPA AMAN MENGHAPUS FOTO LAMA ============
 * Foto bukti hanya pernah ditampilkan di SATU tempat: halaman Jurnal & Absen
 * Kelas, dan hanya untuk sesi HARI INI (lihat JurnalAbsenKelas::scanCocok
 * yang menyaring whereDate('waktu_mulai', today())). Tidak ada satu pun
 * halaman yang menampilkan foto sesi kemarin, apalagi bulan lalu. Jadi
 * membuang berkas lama tidak menghilangkan apa pun dari layar siapa pun.
 */
class BersihkanBuktiMengajar extends Command
{
    protected $signature = 'simagas:bersihkan-bukti
                            {--bulan=1 : Simpan foto selama berapa bulan terakhir.}
                            {--uji-coba : Hanya menghitung, tidak menghapus apa pun.}';

    protected $description = 'Menghapus berkas foto bukti mengajar yang sudah lewat masa simpan.';

    /** Folder foto di dalam disk 'public'. Sama dengan JurnalAbsenKelas::FOLDER_BUKTI. */
    private const FOLDER = 'bukti-mengajar';

    /**
     * Diproses per potongan, bukan sekaligus.
     *
     * Setahun berjalan berarti belasan ribu baris. Memuat semuanya ke memori
     * pada hosting bersama adalah cara paling pasti membuat cron jam 2 pagi
     * mati diam-diam karena kehabisan memori.
     */
    private const UKURAN_POTONGAN = 200;

    public function handle(): int
    {
        $bulan = (int) $this->option('bulan');

        if ($bulan < 1) {
            $this->error('--bulan minimal 1. Menghapus foto hari ini tidak masuk akal: fotonya masih ditampilkan di halaman jurnal.');

            return self::INVALID;
        }

        $ujiCoba = (bool) $this->option('uji-coba');
        $batas = now()->subMonthsNoOverflow($bulan);

        $this->info(sprintf(
            '%sMembuang foto bukti mengajar sebelum %s (masa simpan %d bulan)...',
            $ujiCoba ? '[UJI COBA] ' : '',
            $batas->translatedFormat('d F Y'),
            $bulan,
        ));

        $disk = Storage::disk('public');

        $jumlahBaris = 0;
        $jumlahBerkas = 0;
        $totalByte = 0;
        $hilangDuluan = 0;

        /*
         | Hanya baris yang:
         |   - punya foto_bukti,
         |   - BELUM pernah dibersihkan (bukti_dihapus_pada masih kosong),
         |   - sesinya lebih tua dari batas.
         |
         | Syarat kedua yang membuat perintah ini murah kalau dijalankan
         | berulang: baris yang sudah dibersihkan tidak pernah disentuh lagi.
         */
        AbsensiMengajar::query()
            ->whereNotNull('foto_bukti')
            ->whereNull('bukti_dihapus_pada')
            ->where('waktu_mulai', '<', $batas)
            ->select(['id', 'foto_bukti', 'waktu_mulai'])
            ->chunkById(self::UKURAN_POTONGAN, function ($potongan) use (
                $disk, $ujiCoba, &$jumlahBaris, &$jumlahBerkas, &$totalByte, &$hilangDuluan
            ) {
                $sudah = [];

                foreach ($potongan as $sesi) {
                    $jumlahBaris++;

                    try {
                        if ($disk->exists($sesi->foto_bukti)) {
                            $totalByte += (int) $disk->size($sesi->foto_bukti);

                            if (! $ujiCoba) {
                                $disk->delete($sesi->foto_bukti);
                            }

                            $jumlahBerkas++;
                        } else {
                            // Berkasnya memang sudah tidak ada (mis. folder
                            // storage sempat terhapus saat deploy). Barisnya
                            // tetap ditandai supaya tidak diperiksa lagi
                            // setiap bulan sampai kapan pun.
                            $hilangDuluan++;
                        }

                        $sudah[] = $sesi->id;
                    } catch (\Throwable $e) {
                        // Satu berkas yang bermasalah tidak boleh menghentikan
                        // pembersihan sepuluh ribu berkas lainnya.
                        Log::warning('Gagal menghapus satu foto bukti mengajar.', [
                            'absensi_mengajar_id' => $sesi->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                /*
                 | Penandaannya SATU query untuk seluruh potongan, bukan satu
                 | per baris. 200 baris berarti 1 query, bukan 200.
                 |
                 | Memakai query builder langsung (bukan $model->save()) juga
                 | menjaga kolom `updated_at` tidak ikut berubah — pembersihan
                 | oleh mesin bukan "perubahan data absensi", dan mengubahnya
                 | akan mengacaukan urutan riwayat.
                 */
                if (! $ujiCoba && $sudah !== []) {
                    AbsensiMengajar::whereIn('id', $sudah)
                        ->update(['bukti_dihapus_pada' => now()]);
                }
            });

        $mb = round($totalByte / 1024 / 1024, 2);

        $this->newLine();
        $this->table(
            ['Sesi diperiksa', 'Berkas dihapus', 'Berkas sudah hilang', 'Ruang dibebaskan'],
            [[$jumlahBaris, $jumlahBerkas, $hilangDuluan, number_format($mb, 2, ',', '.') . ' MB']],
        );

        if ($ujiCoba) {
            $this->warn('Mode uji coba: tidak ada yang benar-benar dihapus. Jalankan tanpa --uji-coba untuk membersihkan.');

            return self::SUCCESS;
        }

        if ($jumlahBaris === 0) {
            $this->info('Tidak ada foto yang perlu dibuang.');

            return self::SUCCESS;
        }

        Log::info('Pembersihan foto bukti mengajar selesai.', [
            'sesi' => $jumlahBaris,
            'berkas_dihapus' => $jumlahBerkas,
            'mb_dibebaskan' => $mb,
        ]);

        $this->info('Selesai. Catatan sesinya tetap utuh — hanya berkas fotonya yang dibuang.');

        return self::SUCCESS;
    }
}
