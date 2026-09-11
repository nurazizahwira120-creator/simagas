<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Nilai;
use App\Models\Penghargaan;
use App\Models\Siswa;

/**
 * Memilih "Bintang Kelas (Peringkat 1)" satu kelas pada satu semester.
 *
 * Dipisah dari ValidasiRaporController dengan sengaja: aturan siapa yang
 * berhak menang adalah keputusan sekolah yang akan berubah (bobot, syarat
 * minimal, penanganan seri). Menaruhnya di controller berarti aturan itu
 * hanya bisa diuji lewat permintaan HTTP.
 */
class BintangKelasService
{
    /**
     * Jumlah nilai minimal sebelum seorang siswa boleh ikut diperingkat.
     *
     * ============ KENAPA ADA SYARAT MINIMAL ============
     * Rata-rata tanpa syarat jumlah bisa dimenangkan siswa yang nilainya baru
     * satu. Anak yang punya satu nilai 95 akan mengalahkan anak yang punya
     * dua belas nilai dengan rata-rata 92 — dan yang kedua jelas lebih layak.
     * ===================================================
     */
    public const MIN_NILAI = 3;

    /**
     * Menghitung peringkat kelas. Baris pertama adalah pemenangnya.
     *
     * @return array<int, array{siswa: Siswa, rata: float, jumlah: int}>
     */
    public function peringkat(int $kelasId, string $tahunAjaran, string $semester): array
    {
        /*
         | SATU query agregat untuk seluruh kelas.
         |
         | Alternatifnya — mengambil siswa lalu menghitung rata-rata per siswa
         | di dalam perulangan — adalah N+1 yang klasik: 30 siswa berarti 31
         | query untuk satu kali klik "Setujui".
         |
         | AVG dan COUNT dihitung di database, bukan di PHP, supaya yang
         | berpindah lewat jaringan hanya satu baris per siswa.
         */
        $agregat = Nilai::query()
            ->join('siswa', 'siswa.id', '=', 'nilais.siswa_id')
            ->where('siswa.kelas_id', $kelasId)
            ->where('nilais.tahun_ajaran', $tahunAjaran)
            ->where('nilais.semester', $semester)
            ->groupBy('nilais.siswa_id')
            ->select('nilais.siswa_id')
            ->selectRaw('AVG(nilais.skor) as rata')
            ->selectRaw('COUNT(*) as jumlah')
            ->havingRaw('COUNT(*) >= ?', [self::MIN_NILAI])
            ->get();

        if ($agregat->isEmpty()) {
            return [];
        }

        // Satu query lagi untuk data siswanya — bukan di dalam perulangan.
        $siswa = Siswa::query()
            ->whereIn('id', $agregat->pluck('siswa_id'))
            ->get(['id', 'nis', 'nama', 'kelas_id', 'wali_murid_id'])
            ->keyBy('id');

        $baris = $agregat
            ->map(function ($a) use ($siswa) {
                $s = $siswa->get($a->siswa_id);

                return $s ? [
                    'siswa' => $s,
                    'rata' => round((float) $a->rata, 2),
                    'jumlah' => (int) $a->jumlah,
                ] : null;
            })
            ->filter()
            ->values();

        /*
         | Urutan pemenang, berjenjang:
         |   1. rata-rata tertinggi
         |   2. kalau seri -> yang nilainya lebih LENGKAP
         |   3. kalau masih seri -> nama menurut abjad
         |
         | Langkah ketiga terlihat sepele tapi penting: tanpa aturan terakhir
         | yang pasti, dua kali menjalankan perhitungan yang sama bisa
         | menghasilkan pemenang berbeda, tergantung urutan baris dari
         | database. Penghargaan yang berpindah orang saat halaman dimuat ulang
         | adalah cacat yang mustahil dijelaskan ke orang tua.
         */
        $hasil = $baris->all();

        usort($hasil, function (array $a, array $b) {
            // 1. Rata-rata tertinggi menang.
            if ($a['rata'] !== $b['rata']) {
                return $b['rata'] <=> $a['rata'];
            }

            // 2. Seri -> yang nilainya lebih lengkap.
            if ($a['jumlah'] !== $b['jumlah']) {
                return $b['jumlah'] <=> $a['jumlah'];
            }

            // 3. Masih seri -> abjad nama, A lebih dulu.
            return strcasecmp($a['siswa']->nama, $b['siswa']->nama);
        });

        return $hasil;
    }

    /**
     * Menetapkan Bintang Kelas dan menyimpannya ke tabel penghargaans.
     *
     * @return Penghargaan|null null kalau kelasnya belum punya nilai yang cukup
     */
    public function tetapkan(Kelas $kelas, string $tahunAjaran, string $semester): ?Penghargaan
    {
        $peringkat = $this->peringkat($kelas->id, $tahunAjaran, $semester);

        if ($peringkat === []) {
            return null;
        }

        $juara = $peringkat[0];
        /** @var Siswa $siswa */
        $siswa = $juara['siswa'];

        /*
         | Penghargaan siswa dikirim ke akun WALI MURID-nya — siswa tidak punya
         | akun login di sistem ini. Tanpa wali murid yang tertaut, tidak ada
         | akun yang bisa menerima apresiasinya.
         */
        if (! $siswa->wali_murid_id) {
            return null;
        }

        $periode = Penghargaan::periodeSemester($tahunAjaran, $semester);

        $pesan = sprintf(
            'Selamat! %s meraih Peringkat 1 di kelas %s pada semester %s %s '
                . 'dengan nilai rata-rata %s dari %d penilaian. Terima kasih atas '
                . 'kerja keras dan dukungan keluarga di rumah.',
            $siswa->nama,
            $kelas->nama_kelas,
            ucfirst($semester),
            $tahunAjaran,
            number_format($juara['rata'], 2, ',', '.'),
            $juara['jumlah'],
        );

        /*
         | updateOrCreate pada kunci uniknya (user + kategori + periode).
         |
         | Kepala sekolah bisa mengembalikan rapor lalu menyetujuinya lagi
         | sesudah nilai diperbaiki. Tanpa ini, riwayat apresiasi terisi baris
         | kembar dan HP orang tua berbunyi dua kali untuk hal yang sama.
         */
        return Penghargaan::updateOrCreate(
            [
                'user_id' => $siswa->wali_murid_id,
                'kategori' => Penghargaan::KATEGORI_BINTANG_KELAS,
                'periode' => $periode,
            ],
            [
                'siswa_id' => $siswa->id,
                'kelas_id' => $kelas->id,
                'peran' => Penghargaan::PERAN_SISWA,
                'pesan_apresiasi' => $pesan,
                'nilai_acuan' => $juara['rata'],
            ],
        );
    }

}
