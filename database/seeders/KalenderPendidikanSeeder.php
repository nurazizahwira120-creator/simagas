<?php

namespace Database\Seeders;

use App\Enums\JenisAgenda;
use App\Models\AgendaAkademik;
use Illuminate\Database\Seeder;

/**
 * Kalender Pendidikan Provinsi Lampung T.A. 2026/2027.
 *
 * SUMBER: Keputusan Kepala Dinas Pendidikan dan Kebudayaan Provinsi Lampung
 * Nomor 400.3.8/1323/V.01/DP.2/2026 tanggal 22 Juni 2026 — Lampiran II
 * "Uraian Kalender Pendidikan", ditambah BAB X "Hari Libur Satuan
 * Pendidikan" dan grid kalender bulanannya.
 *
 * ============ TIGA TANGGAL YANG BERBEDA DI DALAM DOKUMEN SUMBERNYA ============
 * Dokumennya sendiri tidak konsisten di tiga titik. Yang dipakai di sini
 * adalah yang didukung DUA bagian dokumen, dan ketiganya ditandai di bawah
 * supaya bisa dikoreksi cepat kalau sekolah memutuskan lain:
 *
 *   1. Maulid Nabi  -> 25 Agustus 2026 (BAB X + grid Agustus)
 *                      Lampiran II baris 5 menulis 5 September 2026.
 *   2. Nyepi        -> 8 Maret 2027 (Lampiran II + grid Maret)
 *                      BAB X butir 7.d menulis 9 Maret 2027.
 *   3. Waisak       -> 20 Mei 2027, bertabrakan dengan Upacara Harkitnas
 *                      pada tanggal yang sama. Keduanya tetap disimpan;
 *                      yang berjenis 'libur' yang menentukan status harinya.
 * =============================================================================
 *
 * ============ AMAN DIJALANKAN BERULANG ============
 * Baris ber-sumber 'kaldik' untuk tahun ajaran ini DIHAPUS lebih dulu, lalu
 * ditulis ulang. Libur khusus yang diisi sendiri oleh sekolah (sumber
 * 'sekolah') TIDAK disentuh — menjalankan ulang seeder tidak boleh
 * menghapus libur haul atau libur banjir yang sudah susah payah dicatat.
 * ==================================================
 */
class KalenderPendidikanSeeder extends Seeder
{
    private const TAHUN_AJARAN = '2026/2027';

    public function run(): void
    {
        AgendaAkademik::where('sumber', 'kaldik')
            ->where('tahun_ajaran', self::TAHUN_AJARAN)
            ->delete();

        foreach ($this->agenda() as $a) {
            AgendaAkademik::create([
                'judul' => $a[0],
                'tanggal_mulai' => $a[1],
                // Agenda satu hari ditulis dengan tanggal_selesai = null di
                // daftar bawah supaya tidak perlu mengetik tanggalnya dua
                // kali; di sini diisi otomatis.
                'tanggal_selesai' => $a[2] ?? $a[1],
                'jenis' => $a[3]->value,
                'keterangan' => $a[4] ?? null,
                'sumber' => 'kaldik',
                'tahun_ajaran' => self::TAHUN_AJARAN,
            ]);
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: ?string, 3: JenisAgenda, 4?: string}>
     */
    private function agenda(): array
    {
        $L = JenisAgenda::Libur;
        $K = JenisAgenda::Kegiatan;
        $U = JenisAgenda::Ujian;

        return [
            // ================= SEMESTER GASAL 2026 =================
            ['Hari Pertama Masuk Sekolah', '2026-07-13', null, $K, 'Permulaan Tahun Ajaran 2026/2027.'],
            ['Kegiatan MPLS', '2026-07-13', '2026-07-17', $K, 'Masa Pengenalan Lingkungan Sekolah.'],

            ['Upacara HUT ke-81 Kemerdekaan RI', '2026-08-17', null, $L, 'Libur Umum — Hari Kemerdekaan Republik Indonesia.'],
            ['Maulid Nabi Muhammad SAW 1448 H', '2026-08-25', null, $L, 'Libur Umum. Catatan: Lampiran II dokumen sumber menulis 5 September; BAB X dan grid kalender menulis 25 Agustus — yang dipakai di sini yang didukung dua bagian.'],

            ['D-SMART Persiapan TKA SMA/SMK', '2026-09-07', '2026-09-08', $K],
            ['D-SMART Persiapan TKA', '2026-10-05', '2026-10-06', $K],
            ['Perkiraan Tes Kemampuan Akademik (TKA) SMA/SMK', '2026-10-26', '2026-11-05', $U],

            ['Asesmen Sumatif Akhir Semester Gasal', '2026-11-30', '2026-12-05', $U],

            ['Pembagian Rapor Semester Gasal (5 hari sekolah)', '2026-12-18', null, $K],
            ['Pembagian Rapor Semester Gasal (6 hari sekolah)', '2026-12-19', null, $K],

            ['Libur Akhir Semester Gasal', '2026-12-21', '2027-01-04', $L, 'Berlaku untuk satuan pendidikan 5 maupun 6 hari sekolah.'],
            ['Cuti Bersama Hari Raya Natal', '2026-12-24', null, $L],
            ['Hari Raya Natal', '2026-12-25', null, $L, 'Libur Umum.'],

            // ================= SEMESTER GENAP 2027 =================
            ['Tahun Baru Masehi 2027', '2027-01-01', null, $L, 'Libur Umum.'],
            ['Isra Mikraj 1448 H', '2027-01-05', null, $L, 'Libur Umum.'],
            ['Awal Masuk Semester Genap', '2027-01-06', null, $K],
            ['D-SMART (Aksi Jihan) Persiapan UTBK', '2027-01-21', '2027-01-22', $K],

            ['Tahun Baru Imlek 2578', '2027-02-06', null, $L, 'Libur Umum.'],
            ['Libur Perkiraan Awal Ramadan 1448 H', '2027-02-10', null, $L],
            ['D-SMART (Aksi Jihan) Persiapan UTBK', '2027-02-11', '2027-02-12', $K],

            ['Hari Raya Nyepi Tahun Baru Saka 1949', '2027-03-08', null, $L, 'Libur Umum. Catatan: BAB X dokumen sumber menulis 9 Maret; Lampiran II dan grid kalender menulis 8 Maret — yang dipakai di sini yang didukung dua bagian.'],
            ['Perkiraan Libur Hari Raya Idulfitri 1448 H', '2027-03-09', '2027-03-19', $L, 'Hari Raya Idulfitri jatuh pada 10–11 Maret 2027.'],
            ['Wafat Yesus Kristus (Hari Paskah)', '2027-03-26', null, $L, 'Libur Umum.'],
            ['Asesmen Akhir Jenjang SMA', '2027-03-29', '2027-04-02', $U],

            ['Hari Buruh Internasional', '2027-05-01', null, $L, 'Libur Umum.'],
            ['Upacara Peringatan Hari Pendidikan Nasional', '2027-05-02', null, $K],
            ['Perkiraan Pengumuman Kelulusan SMA/SMK', '2027-05-03', null, $K],
            ['Kenaikan Yesus Kristus', '2027-05-06', null, $L, 'Libur Umum.'],
            ['Hari Raya Iduladha 1448 H', '2027-05-17', null, $L, 'Libur Umum.'],
            ['Upacara Peringatan Hari Kebangkitan Nasional', '2027-05-20', null, $K, 'Bertepatan dengan Libur Umum Hari Raya Waisak.'],
            ['Hari Raya Waisak 2571 BE', '2027-05-20', null, $L, 'Libur Umum.'],
            ['Asesmen Sumatif Akhir Semester Genap', '2027-05-24', '2027-05-31', $U],

            ['Hari Lahir Pancasila', '2027-06-01', null, $L, 'Libur Umum.'],
            ['Tahun Baru Islam 1449 H', '2027-06-06', null, $L, 'Libur Umum.'],
            ['Pembagian Rapor Semester Genap (5 hari sekolah)', '2027-06-11', null, $K],
            ['Pembagian Rapor Semester Genap (6 hari sekolah)', '2027-06-12', null, $K],
            ['Libur Akhir Semester Genap / Akhir Tahun Ajaran', '2027-06-14', '2027-07-10', $L],

            ['Permulaan Tahun Ajaran 2027/2028', '2027-07-12', null, $K],
        ];
    }
}
