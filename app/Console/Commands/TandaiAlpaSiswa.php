<?php

namespace App\Console\Commands;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiSiswa;
use App\Models\Siswa;
use App\Services\KalenderAkademik;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Menandai ALPA siswa yang tidak tercatat hadir sampai jam pulang.
 *
 * ============ MASALAH YANG DIPERBAIKI PERINTAH INI ============
 * Sebelum ini, baris di `absensi_siswa` HANYA lahir dari dua hal: scan QR di
 * gerbang, dan pencatatan izin oleh petugas piket. Siswa yang tidak pernah
 * datang tidak menghasilkan baris berstatus alpa — ia tidak menghasilkan
 * baris APA PUN.
 *
 * Akibatnya terbukti di laporan bulanan: siswa yang tidak masuk sebulan
 * penuh tampil dengan kolom Alpa = 0. Ketidakhadirannya hanya bisa
 * DISIMPULKAN dari kolom hadir yang nol, tidak pernah DINYATAKAN. Kepala
 * sekolah yang membaca kolom Alpa melihat angka nol dan menyimpulkan tidak
 * ada yang membolos.
 * =============================================================
 *
 * ============ TIGA PENJAGA YANG MEMBUATNYA AMAN ============
 *   1. HANYA pada hari KBM. Tanpa ini, setiap hari Jumat dan setiap libur
 *      nasional akan menandai SELURUH siswa alpa — ratusan baris salah per
 *      hari libur, dan tidak ada yang memberitahu.
 *
 *   2. HANYA sesudah jam pulang. Menandai alpa jam 07:10 berarti memvonis
 *      anak yang sedang di perjalanan.
 *
 *   3. HANYA siswa yang BELUM punya baris hari itu. Baris yang sudah ada —
 *      hadir hasil scan, izin dari piket, atau koreksi manual admin — tidak
 *      pernah disentuh. Ini juga yang membuat perintahnya aman dijalankan
 *      berkali-kali dalam sehari.
 * ===========================================================
 */
class TandaiAlpaSiswa extends Command
{
    protected $signature = 'simagas:tandai-alpa
        {--tanggal= : Tanggal yang disapu (Y-m-d). Bawaan: hari ini.}
        {--paksa : Jalan walau jam pulang belum lewat. Untuk menyapu hari lampau.}
        {--uji-coba : Hanya menghitung, tidak menulis apa pun.}';

    protected $description = 'Menandai alpa siswa yang tidak tercatat hadir sampai jam pulang sekolah.';

    /** Ditulis ke kolom keterangan supaya barisnya bisa dibedakan dari input manual. */
    public const PENANDA = 'Ditandai otomatis: tidak tercatat hadir sampai jam pulang';

    public function handle(KalenderAkademik $kalender): int
    {
        $tanggal = $this->tanggalSasaran();

        if (! $tanggal) {
            $this->error('Format --tanggal harus Y-m-d, misalnya 2026-09-15.');

            return self::FAILURE;
        }

        // ---- PENJAGA 1: hari KBM ----
        if ($alasan = $kalender->alasanBukanKbm($tanggal)) {
            $this->info(sprintf('%s bukan hari KBM (%s). Tidak ada yang ditandai.', $tanggal->toDateString(), $alasan));

            return self::SUCCESS;
        }

        // ---- PENJAGA 2: jam pulang ----
        $jamPulang = $kalender->jamPulangPada($tanggal);

        if (! $this->option('paksa') && now()->lessThan($jamPulang)) {
            $this->info(sprintf(
                'Belum waktunya — jam pulang %s belum lewat. Tidak ada yang ditandai.',
                $jamPulang->format('H:i'),
            ));

            return self::SUCCESS;
        }

        // ---- PENJAGA 3: hanya yang belum punya baris ----
        $sudahAda = AbsensiSiswa::whereDate('tanggal', $tanggal->toDateString())
            ->pluck('siswa_id')
            ->all();

        $belumTercatat = Siswa::query()
            ->whereNotIn('id', $sudahAda)
            ->select(['id'])
            ->get();

        if ($belumTercatat->isEmpty()) {
            $this->info(sprintf('Semua siswa sudah tercatat pada %s. Tidak ada yang perlu ditandai.', $tanggal->toDateString()));

            return self::SUCCESS;
        }

        if ($this->option('uji-coba')) {
            $this->warn(sprintf('[UJI COBA] %d siswa AKAN ditandai alpa pada %s.', $belumTercatat->count(), $tanggal->toDateString()));

            return self::SUCCESS;
        }

        $sekarang = now();

        /*
         | insert() massal, bukan create() satu per satu.
         |
         | Satu sekolah bisa punya 400+ siswa. Empat ratus INSERT terpisah di
         | hosting bersama sudah cukup untuk membuat cron-nya melewati batas
         | waktu — dan cron yang mati di tengah meninggalkan sebagian siswa
         | ditandai dan sebagian tidak.
         |
         | Konsekuensinya: created_at/updated_at harus diisi sendiri, karena
         | insert() melewati Eloquent sepenuhnya. Kolom jam_masuk SENGAJA
         | null — anaknya memang tidak datang, dan mengisinya dengan jam
         | apa pun akan berbohong ke rekap ketepatan waktu.
         */
        $baris = $belumTercatat->map(fn (Siswa $s) => [
            'siswa_id' => $s->id,
            'tanggal' => $tanggal->toDateString(),
            'jam_masuk' => null,
            'status' => AbsensiStatus::Alpha->value,
            'keterangan' => self::PENANDA,
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ])->all();

        foreach (array_chunk($baris, 200) as $potongan) {
            AbsensiSiswa::insert($potongan);
        }

        $jumlah = count($baris);

        Log::info('Penandaan alpa otomatis selesai.', [
            'tanggal' => $tanggal->toDateString(),
            'ditandai' => $jumlah,
        ]);

        $this->info(sprintf('%d siswa ditandai alpa pada %s.', $jumlah, $tanggal->toDateString()));

        return self::SUCCESS;
    }

    private function tanggalSasaran(): ?Carbon
    {
        $opsi = $this->option('tanggal');

        if (blank($opsi)) {
            return today();
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $opsi)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', (string) $opsi)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
