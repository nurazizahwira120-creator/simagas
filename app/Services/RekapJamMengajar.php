<?php

namespace App\Services;

use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiMengajar;
use App\Models\JadwalPelajaran;
use App\Models\Pegawai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Rekap BEBAN MENGAJAR GURU berbasis JAM PELAJARAN (JP).
 *
 * ============ BEDANYA DENGAN HITUNGAN LAMA ============
 * Laporan sebelumnya menghitung "Sesi KBM" — berapa KALI guru menutup sesi
 * mengajar. Satu sesi 1 JP dan satu sesi 4 JP sama-sama dihitung satu,
 * sehingga guru dengan blok panjang (praktik produktif 4 JP) tampak
 * mengajar jauh lebih sedikit daripada guru dengan banyak blok pendek.
 *
 * Di sini satuannya JP: setiap jadwal bernilai panjangnya ÷ durasi 1 JP
 * (lihat App\Services\JamPelajaran), lalu diakumulasi per guru.
 * ======================================================
 *
 * ============ ARTI SETIAP KOLOM ============
 *  JP Terjadwal   : jumlah JP seluruh jadwal guru pada HARI KBM di periode
 *                   ini yang SUDAH SELESAI saat rekap dibuka (hari libur
 *                   mingguan & libur Kalender Pendidikan tidak dihitung).
 *  JP Akan Datang : jadwal di periode ini yang jamnya belum selesai —
 *                   belum bisa dinilai, jadi tidak masuk persentase.
 *  JP Terlaksana  : JP dari jadwal yang sesi mengajarnya TUNTAS — scan QR
 *                   ruangan + foto bukti + diakhiri. Nilainya mengikuti
 *                   panjang JADWAL (keputusan sekolah), bukan lama nyata
 *                   di kelas.
 *  JP Berhalangan : JP terjadwal pada hari guru izin/sakit/alpa
 *                   (StatusBerhalanganGuru) yang tidak terlaksana.
 *  JP Tidak Terlaksana : sisanya — terjadwal, guru tidak berhalangan,
 *                   tetapi tidak ada sesi tuntas yang cocok.
 *  JP Pengganti   : JP kelas guru LAIN yang absensinya diisi guru ini
 *                   lewat halaman Kelas Pengganti.
 *  Sesi di luar jadwal : sesi tuntas yang tidak cocok dengan jadwal mana
 *                   pun (ruangan salah scan, atau hari libur). Tidak diberi
 *                   JP — tapi ditampilkan supaya tidak hilang diam-diam.
 *  % Keterlaksanaan = JP Terlaksana ÷ JP Terjadwal.
 * ===========================================
 *
 * Jumlah query TETAP berapa pun panjang periodenya: jadwal, pengaturan JP,
 * sesi mengajar, status berhalangan (2), pengisian pengganti, pegawai
 * pengganti, ditambah kalender (pengaturan + agenda per bulan).
 */
class RekapJamMengajar
{
    /** Rentang terpanjang sekali tarik — lebih dari ini berisiko timeout di hosting bersama. */
    public const MAKS_HARI = 400;

    public function __construct(
        private readonly JamPelajaran $jp,
        private readonly PencocokSesiMengajar $pencocok,
        private readonly KalenderAkademik $kalender,
        private readonly StatusBerhalanganGuru $berhalangan,
    ) {
    }

    /**
     * @return array{
     *     dari: Carbon, sampai: Carbon, label_periode: string, durasi_jp: int,
     *     hari_kbm: int, baris: array<int, array<string, mixed>>, ringkas: array<string, mixed>
     * }
     */
    public function hitung(Carbon $dari, Carbon $sampai, ?int $guruId = null): array
    {
        $dari = $dari->copy()->startOfDay();
        $sampai = $sampai->copy()->startOfDay();

        if ($sampai->lt($dari)) {
            throw new RuntimeException('Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
        }

        if ($dari->diffInDays($sampai) + 1 > self::MAKS_HARI) {
            throw new RuntimeException('Rentang tanggalnya terlalu panjang. Maksimal ' . self::MAKS_HARI . ' hari sekali tarik.');
        }

        // (1) Seluruh jadwal (atau jadwal satu guru) beserta guru & kelasnya.
        $jadwal = JadwalPelajaran::query()
            ->with(['guru:id,nama,nip,user_id', 'kelas:id,nama_kelas'])
            ->when($guruId, fn ($q) => $q->where('guru_id', $guruId))
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();

        $jadwalPerGuruHari = $jadwal->groupBy(fn (JadwalPelajaran $j) => $j->guru_id . '|' . $j->hari->value);
        $guru = $jadwal->pluck('guru')->filter()->keyBy('id');

        // (2) Hari KBM di periode ini, dikelompokkan per nama hari.
        $tanggalKbm = $this->tanggalKbm($dari, $sampai);

        // (3) Sesi mengajar TUNTAS di periode ini, per akun per tanggal.
        $idAkun = $guru->pluck('user_id')->filter()->values();
        $sesi = $idAkun->isEmpty() ? collect() : AbsensiMengajar::query()
            ->whereIn('user_id', $idAkun)
            ->whereDate('waktu_mulai', '>=', $dari->toDateString())
            ->whereDate('waktu_mulai', '<=', $sampai->toDateString())
            ->whereNotNull('waktu_selesai')
            ->whereNotNull('foto_bukti')
            ->orderBy('waktu_mulai')
            ->get(['id', 'user_id', 'kode_kelas', 'waktu_mulai', 'waktu_selesai'])
            ->groupBy(fn (AbsensiMengajar $a) => $a->user_id . '|' . $a->waktu_mulai->toDateString());

        // (4) Status berhalangan seluruh guru untuk seluruh periode.
        $halangan = $this->berhalangan->rentang($guru->values(), $dari, $sampai);

        // (5) JP pengganti, dikreditkan ke pegawai pengisinya.
        [$jpPengganti, $guruPengganti] = $this->jpPengganti($dari, $sampai, $guruId);

        $baris = [];

        $sekarang = now();

        foreach ($guru->union($guruPengganti) as $idPegawai => $p) {
            $terjadwal = $terlaksana = $berhalanganJp = $tidakTerlaksana = $akanDatang = 0;
            $sesiDipakai = [];

            foreach ($tanggalKbm as $tanggal => $hari) {
                $hariIni = $jadwalPerGuruHari->get($idPegawai . '|' . $hari, collect());

                if ($hariIni->isEmpty()) {
                    continue;
                }

                $sesiHariIni = $p->user_id ? $sesi->get($p->user_id . '|' . $tanggal, collect()) : collect();
                $halanganHariIni = $halangan[$idPegawai . '|' . $tanggal] ?? null;

                foreach ($hariIni as $j) {
                    $nilai = $this->jp->untukJadwal($j);
                    $cocok = $this->pencocok->pilihDari($sesiHariIni, $j, $sesiDipakai);

                    if ($cocok) {
                        $sesiDipakai[] = $cocok->id;
                        $terjadwal += $nilai;
                        $terlaksana += $nilai;

                        continue;
                    }

                    /*
                     | Jadwal yang BELUM SELESAI saat rekap dibuka (hari ini
                     | yang jamnya belum lewat, atau tanggal yang akan datang)
                     | tidak dihitung terjadwal. Tanpa ini, rekap yang dibuka
                     | pukul 09.00 mencatat seluruh jam siang sebagai "tidak
                     | terlaksana" — menuduh guru yang memang belum waktunya
                     | masuk kelas.
                     */
                    $selesaiPada = Carbon::parse($tanggal)->setTime($j->jam_selesai->hour, $j->jam_selesai->minute);

                    if ($selesaiPada->gt($sekarang)) {
                        $akanDatang += $nilai;
                    } elseif ($halanganHariIni) {
                        $terjadwal += $nilai;
                        $berhalanganJp += $nilai;
                    } else {
                        $terjadwal += $nilai;
                        $tidakTerlaksana += $nilai;
                    }
                }
            }

            // Sesi tuntas milik guru ini yang tidak terpasang ke jadwal mana pun.
            $semuaSesi = $p->user_id
                ? $sesi->filter(fn ($_, string $kunci) => str_starts_with($kunci, $p->user_id . '|'))->flatten()
                : collect();
            $luarJadwal = $semuaSesi->reject(fn (AbsensiMengajar $a) => in_array($a->id, $sesiDipakai, true))->count();

            $baris[] = [
                'pegawai_id' => $idPegawai,
                'nama' => $p->nama,
                'nip' => $p->nip ?: null,
                'jp_per_minggu' => $jadwal->where('guru_id', $idPegawai)->sum(fn (JadwalPelajaran $j) => $this->jp->untukJadwal($j)),
                'jp_terjadwal' => $terjadwal,
                'jp_terlaksana' => $terlaksana,
                'jp_berhalangan' => $berhalanganJp,
                'jp_tidak_terlaksana' => $tidakTerlaksana,
                'jp_pengganti' => (int) ($jpPengganti[$idPegawai] ?? 0),
                'jp_akan_datang' => $akanDatang,
                'sesi_luar_jadwal' => $luarJadwal,

                // null, BUKAN 0, kalau guru tidak punya jadwal di periode
                // ini — 0% akan terbaca "tidak pernah mengajar".
                'persen' => $terjadwal > 0 ? round($terlaksana / $terjadwal * 100, 1) : null,
            ];
        }

        usort($baris, fn ($a, $b) => strcasecmp($a['nama'], $b['nama']));

        return [
            'dari' => $dari,
            'sampai' => $sampai,
            'label_periode' => $this->labelPeriode($dari, $sampai),
            'durasi_jp' => $this->jp->durasiMenit(),
            'hari_kbm' => count($tanggalKbm),
            'baris' => $baris,
            'ringkas' => $this->ringkas($baris),
        ];
    }

    /** Render PDF A4 landscape; mengembalikan isi berkas. */
    public function render(array $data): string
    {
        $kelas = \Barryvdh\DomPDF\Facade\Pdf::class;

        if (! class_exists($kelas)) {
            throw new RuntimeException('Paket PDF belum terpasang. Jalankan di server: composer require barryvdh/laravel-dompdf');
        }

        return $kelas::loadView('laporan.rekap-jam-mengajar-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOption(['isRemoteEnabled' => false])
            ->output();
    }

    /* ===================== PEMBANTU ===================== */

    /**
     * Tanggal KBM di periode ini => nilai enum Hari-nya.
     *
     * @return array<string, string>  ['2026-09-21' => 'senin', ...]
     */
    private function tanggalKbm(Carbon $dari, Carbon $sampai): array
    {
        $hasil = [];
        $kursor = $dari->copy();

        while ($kursor->lte($sampai)) {
            if ($this->kalender->adalahHariKbm($kursor)) {
                $hasil[$kursor->toDateString()] = KalenderAkademik::hariDari($kursor)->value;
            }

            $kursor->addDay();
        }

        return $hasil;
    }

    /**
     * JP kelas guru lain yang diisi seorang pengganti.
     *
     * Satu pertemuan = satu pasangan (jadwal, tanggal) — bukan sebanyak
     * siswanya. Catatan yang diisi guru jadwalnya sendiri, dan catatan lama
     * dengan diisi_oleh NULL, bukan pengganti.
     *
     * @return array{0: array<int, int>, 1: Collection<int, Pegawai>}  [pegawai_id => JP, pegawai pengganti]
     */
    private function jpPengganti(Carbon $dari, Carbon $sampai, ?int $guruId): array
    {
        $pertemuan = AbsensiKbmSiswa::query()
            ->join('jadwal_pelajaran', 'jadwal_pelajaran.id', '=', 'absensi_kbm_siswa.jadwal_id')
            ->leftJoin('pegawai', 'pegawai.id', '=', 'jadwal_pelajaran.guru_id')
            ->whereNotNull('absensi_kbm_siswa.diisi_oleh')
            ->whereDate('absensi_kbm_siswa.tanggal', '>=', $dari->toDateString())
            ->whereDate('absensi_kbm_siswa.tanggal', '<=', $sampai->toDateString())
            ->where(fn ($q) => $q->whereNull('pegawai.user_id')
                ->orWhereColumn('pegawai.user_id', '!=', 'absensi_kbm_siswa.diisi_oleh'))
            ->distinct()
            ->get([
                'absensi_kbm_siswa.jadwal_id',
                'absensi_kbm_siswa.tanggal',
                'absensi_kbm_siswa.diisi_oleh',
                'jadwal_pelajaran.jam_mulai',
                'jadwal_pelajaran.jam_selesai',
            ]);

        if ($pertemuan->isEmpty()) {
            return [[], collect()];
        }

        $pegawai = Pegawai::query()
            ->whereIn('user_id', $pertemuan->pluck('diisi_oleh')->unique())
            ->when($guruId, fn ($q) => $q->whereKey($guruId))
            ->get(['id', 'nama', 'nip', 'user_id'])
            ->keyBy('user_id');

        $jp = [];

        // Pasangan (jadwal, tanggal) bisa muncul dua kali kalau tanggalnya
        // tersimpan dalam dua bentuk teks; dikunci ulang di PHP.
        foreach ($pertemuan->unique(fn ($b) => $b->jadwal_id . '|' . Carbon::parse($b->tanggal)->toDateString() . '|' . $b->diisi_oleh) as $b) {
            $p = $pegawai->get($b->diisi_oleh);

            if ($p) {
                $jp[$p->id] = ($jp[$p->id] ?? 0) + $this->jp->antara($b->jam_mulai, $b->jam_selesai);
            }
        }

        return [$jp, $pegawai->keyBy('id')];
    }

    /** @param  array<int, array<string, mixed>>  $baris */
    private function ringkas(array $baris): array
    {
        $jumlah = fn (string $k) => array_sum(array_column($baris, $k));
        $terjadwal = $jumlah('jp_terjadwal');

        return [
            'jumlah_guru' => count($baris),
            'jp_terjadwal' => $terjadwal,
            'jp_terlaksana' => $jumlah('jp_terlaksana'),
            'jp_berhalangan' => $jumlah('jp_berhalangan'),
            'jp_tidak_terlaksana' => $jumlah('jp_tidak_terlaksana'),
            'jp_pengganti' => $jumlah('jp_pengganti'),
            'jp_akan_datang' => $jumlah('jp_akan_datang'),
            'sesi_luar_jadwal' => $jumlah('sesi_luar_jadwal'),
            'persen' => $terjadwal > 0 ? round($jumlah('jp_terlaksana') / $terjadwal * 100, 1) : null,
        ];
    }

    private function labelPeriode(Carbon $dari, Carbon $sampai): string
    {
        if ($dari->isSameDay($sampai)) {
            return $dari->translatedFormat('d F Y');
        }

        if ($dari->isSameMonth($sampai) && $dari->isSameYear($sampai)) {
            return $dari->translatedFormat('d') . '–' . $sampai->translatedFormat('d F Y');
        }

        return $dari->translatedFormat('d M Y') . ' – ' . $sampai->translatedFormat('d M Y');
    }
}
