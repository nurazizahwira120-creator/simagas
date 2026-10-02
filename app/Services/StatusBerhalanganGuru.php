<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Models\PengajuanIzinGuru;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * SATU-SATUNYA tempat yang memutuskan "guru ini berhalangan hadir pada
 * tanggal itu".
 *
 * ============ KENAPA DIPUSATKAN ============
 * Pertanyaan yang sama sekarang diajukan empat layar:
 *   - Lembar Paraf Mengajar   -> kolom paraf diisi "GURU IZIN"
 *   - Kelas Pengganti         -> absensi KBM kelasnya dibuka untuk guru lain
 *   - Live Monitoring         -> kelas kosong karena gurunya izin, bukan mangkir
 *   - Rekap Jam Mengajar      -> JP yang tidak terlaksana karena izin
 *
 * Kalau tiap layar menulis aturannya sendiri, cepat atau lambat keempatnya
 * berbeda — dan bentuk perbedaannya menyesatkan: lembar paraf berkata
 * "GURU IZIN" sementara halaman pengganti menolak membuka kelasnya.
 * ===========================================
 *
 * ============ SUMBER & TINGKATANNYA ============
 *   1. Pengajuan izin guru DISETUJUI yang mencakup tanggal itu
 *      -> "GURU IZIN" + jenis ITT/IDT.                      sah = true
 *   2. Absensi harian berstatus izin   -> "GURU IZIN".      sah = true
 *   3. Absensi harian berstatus sakit  -> "GURU SAKIT".     sah = true
 *   4. Absensi harian berstatus alpha  -> "GURU ALPA".      sah = false
 *
 * Pengajuan yang masih MENUNGGU tidak dianggap berhalangan (masih bisa
 * ditolak), tetapi dilaporkan terpisah lewat menunggu().
 *
 * "sah" membedakan dua kebutuhan:
 *   - Lembar paraf hanya boleh mencetak keterangan yang SAH; guru alpa
 *     tidak boleh tercetak seolah izinnya resmi.
 *   - Kelas pengganti justru harus terbuka untuk SEMUA keadaan di atas:
 *     siswanya tetap butuh diabsen, apa pun alasan gurunya tidak datang.
 * ===============================================
 *
 * Setiap method menjalankan jumlah query TETAP berapa pun banyak gurunya.
 * Pemanggil menyerahkan model Pegawai yang SUDAH memuat user_id (biasanya
 * dari eager load jadwal->guru) supaya tidak perlu query tambahan.
 */
class StatusBerhalanganGuru
{
    /**
     * Keadaan berhalangan per guru pada SATU tanggal.
     *
     * @param  iterable<Pegawai>  $pegawai
     * @return Collection<int, array{label:string, rinci:string|null, jenis:string, sah:bool}> dikunci pegawai.id
     */
    public function pada(iterable $pegawai, CarbonInterface $tanggal): Collection
    {
        $hari = Carbon::parse($tanggal)->startOfDay();

        return collect($this->rentang($pegawai, $hari, $hari))
            ->mapWithKeys(fn (array $info, string $kunci) => [(int) explode('|', $kunci)[0] => $info]);
    }

    /**
     * Keadaan berhalangan untuk RENTANG tanggal — dipakai rekap.
     *
     * @param  iterable<Pegawai>  $pegawai
     * @return array<string, array{label:string, rinci:string|null, jenis:string, sah:bool}>
     *         dikunci "pegawai_id|Y-m-d"
     */
    public function rentang(iterable $pegawai, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $pegawai = collect($pegawai)->filter()->unique('id');

        if ($pegawai->isEmpty()) {
            return [];
        }

        $awal = Carbon::parse($dari)->startOfDay();
        $akhir = Carbon::parse($sampai)->startOfDay();

        // users.id -> pegawai.id: pengajuan izin berkunci pada AKUN,
        // sedangkan absensi harian berkunci pada data PEGAWAI.
        $pegawaiDariAkun = $pegawai->filter(fn ($p) => $p->user_id)->pluck('id', 'user_id');

        $hasil = [];

        // (1) Absensi harian lebih dulu, supaya pengajuan izin di bawah
        //     MENIMPANYA: pengajuan membawa jenis ITT/IDT yang dibutuhkan
        //     guru piket (IDT = ada tugas untuk dibagikan ke kelas).
        $harian = AbsensiPegawai::query()
            ->whereIn('pegawai_id', $pegawai->pluck('id'))
            // whereDate, BUKAN whereBetween: kolom `tanggal` bisa tersimpan
            // sebagai '2026-09-24 00:00:00', dan di SQLite teks itu LEBIH
            // BESAR dari batas '2026-09-24' — hari terakhir rentang hilang
            // diam-diam. Lihat juga catatan di PenerapIzinGuru.
            ->whereDate('tanggal', '>=', $awal->toDateString())
            ->whereDate('tanggal', '<=', $akhir->toDateString())
            ->whereIn('status', [AbsensiStatus::Izin->value, AbsensiStatus::Sakit->value, AbsensiStatus::Alpha->value])
            ->get(['pegawai_id', 'tanggal', 'status']);

        foreach ($harian as $a) {
            $info = match ($a->status) {
                AbsensiStatus::Izin => ['label' => 'GURU IZIN', 'rinci' => null, 'jenis' => 'izin', 'sah' => true],
                AbsensiStatus::Sakit => ['label' => 'GURU SAKIT', 'rinci' => null, 'jenis' => 'sakit', 'sah' => true],
                default => ['label' => 'GURU ALPA', 'rinci' => null, 'jenis' => 'alpa', 'sah' => false],
            };

            $hasil[$a->pegawai_id . '|' . $a->tanggal->toDateString()] = $info;
        }

        // (2) Pengajuan izin guru yang DISETUJUI dan bersinggungan dengan rentang.
        if ($pegawaiDariAkun->isNotEmpty()) {
            $pengajuan = PengajuanIzinGuru::query()
                ->where('status_approval', \App\Enums\StatusApproval::Disetujui->value)
                ->whereIn('guru_id', $pegawaiDariAkun->keys())
                ->whereDate('tanggal_mulai', '<=', $akhir->toDateString())
                ->whereDate('tanggal_selesai', '>=', $awal->toDateString())
                // Naik menurut id: kalau dua pengajuan tumpang tindih, yang
                // TERBARU yang menimpa.
                ->orderBy('id')
                ->get(['id', 'guru_id', 'jenis_izin', 'tanggal_mulai', 'tanggal_selesai']);

            foreach ($pengajuan as $p) {
                $idPegawai = $pegawaiDariAkun->get($p->guru_id);

                // Dipotong ke dalam rentang yang diminta. copy() wajib:
                // Carbon bersifat mutable dan tanggal milik model tidak
                // boleh ikut bergeser (lihat catatan di PenerapIzinGuru).
                //
                // BUKAN ->max()/->min(): keduanya mengembalikan SALAH SATU
                // objek aslinya, bukan salinan — addDay() di bawah lalu ikut
                // menggeser $awal milik pemanggil.
                $hari = ($p->tanggal_mulai->gt($awal) ? $p->tanggal_mulai : $awal)->copy();
                $selesai = ($p->tanggal_selesai->lt($akhir) ? $p->tanggal_selesai : $akhir)->copy();

                while ($hari->lte($selesai)) {
                    $hasil[$idPegawai . '|' . $hari->toDateString()] = [
                        'label' => 'GURU IZIN',
                        'rinci' => $p->jenis_izin->label(),
                        'jenis' => 'izin',
                        'sah' => true,
                    ];
                    $hari->addDay();
                }
            }
        }

        return $hasil;
    }

    /**
     * Guru yang pengajuan izinnya MENCAKUP tanggal ini tetapi masih menunggu
     * persetujuan.
     *
     * @param  iterable<Pegawai>  $pegawai
     * @return Collection<int, true> dikunci pegawai.id
     */
    public function menunggu(iterable $pegawai, CarbonInterface $tanggal): Collection
    {
        $pegawaiDariAkun = collect($pegawai)->filter(fn ($p) => $p?->user_id)->pluck('id', 'user_id');

        if ($pegawaiDariAkun->isEmpty()) {
            return collect();
        }

        $hari = Carbon::parse($tanggal)->toDateString();

        return PengajuanIzinGuru::query()
            ->menunggu()
            ->whereIn('guru_id', $pegawaiDariAkun->keys())
            ->whereDate('tanggal_mulai', '<=', $hari)
            ->whereDate('tanggal_selesai', '>=', $hari)
            ->pluck('guru_id')
            ->mapWithKeys(fn ($idAkun) => [$pegawaiDariAkun->get($idAkun) => true]);
    }
}
