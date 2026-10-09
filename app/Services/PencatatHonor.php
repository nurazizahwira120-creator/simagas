<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AbsensiMengajar;
use App\Models\HonorMengajar;
use App\Models\JadwalEkskul;
use App\Models\JadwalPelajaran;
use App\Models\SesiEkskul;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PENCATAT HONOR MENGAJAR (uji coba).
 *
 * ============ KAPAN HONOR BERTAMBAH ============
 *  1. Guru menekan "Akhiri Sesi" di Jurnal & Absen Kelas
 *       -> catatMengajar(): JP jadwal × tarif guru itu, 100%.
 *  2. Guru inval menyimpan absensi kelas yang ia gantikan
 *       -> catatInval(): honor jam itu = JP × tarif GURU ASLI, lalu dibagi:
 *            inval      : X%  (Pengaturan "bagian guru inval")
 *            guru asli  : sisanya (100 − X)%
 *  3. Penunjukan inval dibatalkan Kepsek/Admin
 *       -> batalkanInval(): kedua bagian di atas dihapus.
 *  4. Pembina menekan "Akhiri Sesi" ekskul
 *       -> catatEkskul(): JP ekskul × tarif ekskul (atau tarif pembina).
 * ===============================================
 *
 * KENAPA HONOR INVAL MEMAKAI TARIF GURU ASLI: yang dibagi adalah honor
 * SATU jam pelajaran yang sama. Dengan begitu jumlah bagian inval + bagian
 * guru asli selalu persis sama dengan honor jam itu seandainya diajar
 * gurunya sendiri — tidak ada uang yang "muncul" atau "hilang" karena
 * tarif keduanya berbeda.
 *
 * JP mengikuti panjang JADWAL (App\Services\JamPelajaran), sama dengan
 * Rekap Jam Mengajar — bukan lamanya guru benar-benar di kelas.
 *
 * Semua pencatatan IDEMPOTEN: menekan tombol dua kali atau menyimpan ulang
 * absensi mengganti baris yang sama (unique jadwal+tanggal+peran).
 */
class PencatatHonor
{
    /** Peran yang ikut tampil di rekap walau bulan itu belum punya honor. */
    private const PERAN_PENGAJAR = [UserRole::Guru, UserRole::WaliKelas];

    public function __construct(
        private readonly AturanHonor $aturan,
        private readonly JamPelajaran $jp,
    ) {
    }

    /* ===================== MENCATAT ===================== */

    /** Dipanggil SESUDAH sesi berhasil diakhiri. null = fitur mati. */
    public function catatMengajar(AbsensiMengajar $sesi, JadwalPelajaran $jadwal): ?HonorMengajar
    {
        $umum = $this->aturan->umum();

        if (! $umum['aktif']) {
            return null;
        }

        $jp = $this->jpJadwal($jadwal);
        $tarif = $this->aturan->tarifUntuk($sesi->user_id, $umum['tarif']);

        return $this->simpanBaris($jadwal, $sesi->waktu_mulai->copy()->startOfDay(), HonorMengajar::PERAN_MENGAJAR, [
            'user_id' => $sesi->user_id,
            'jp' => $jp,
            'tarif_per_jp' => $tarif,
            'persen' => 100,
            'nominal' => $jp * $tarif,
            'rincian' => $this->rincianJadwal($jadwal),
            'absensi_mengajar_id' => $sesi->id,
        ]);
    }

    /**
     * Dipanggil SESUDAH absensi kelas inval berhasil disimpan.
     *
     * @return array{inval?: HonorMengajar, guru_asli?: HonorMengajar}  kosong = fitur mati
     */
    public function catatInval(JadwalPelajaran $jadwal, Carbon $tanggal, User $inval): array
    {
        $umum = $this->aturan->umum();

        if (! $umum['aktif']) {
            return [];
        }

        $jadwal->loadMissing(['kelas:id,nama_kelas', 'guru:id,nama,user_id']);
        $guruAsliId = $jadwal->guru?->user_id;

        $jp = $this->jpJadwal($jadwal);
        $tarif = $this->aturan->tarifUntuk($guruAsliId, $umum['tarif']);
        $total = $jp * $tarif;

        // Inval dibulatkan, guru asli mendapat SISANYA — jumlah keduanya
        // selalu tepat sama dengan $total, tanpa selisih pembulatan.
        $persenInval = $umum['persen_inval'];
        $bagianInval = (int) round($total * $persenInval / 100);
        $rincian = $this->rincianJadwal($jadwal);

        $hasil = [];

        $hasil['inval'] = $this->simpanBaris($jadwal, $tanggal, HonorMengajar::PERAN_INVAL, [
            'user_id' => $inval->id,
            'jp' => $jp,
            'tarif_per_jp' => $tarif,
            'persen' => $persenInval,
            'nominal' => $bagianInval,
            'rincian' => $this->potong($rincian . ' — menggantikan ' . ($jadwal->guru?->nama ?? 'guru')),
            'absensi_mengajar_id' => null,
        ]);

        $adaBagianAsli = $persenInval < 100 && $guruAsliId && $guruAsliId !== $inval->id;

        if ($adaBagianAsli) {
            $hasil['guru_asli'] = $this->simpanBaris($jadwal, $tanggal, HonorMengajar::PERAN_GURU_ASLI, [
                'user_id' => $guruAsliId,
                'jp' => $jp,
                'tarif_per_jp' => $tarif,
                'persen' => 100 - $persenInval,
                'nominal' => $total - $bagianInval,
                'rincian' => $this->potong($rincian . ' — diinval ' . $inval->name),
                'absensi_mengajar_id' => null,
            ]);
        } else {
            // Persentase inval kini 100%: bagian guru asli dari penyimpanan
            // sebelumnya (kalau ada) tidak berlaku lagi.
            $this->bagianSlot($jadwal->id, $tanggal, [HonorMengajar::PERAN_GURU_ASLI])->delete();
        }

        return $hasil;
    }

    /**
     * Dipanggil SESUDAH sesi ekskul berhasil diakhiri. null = fitur mati.
     *
     * JP: kolom jp_honor di Jadwal Ekskul kalau diisi admin; kosong =
     * dihitung dari panjang jadwal dengan rumus yang sama seperti KBM.
     * Tarif: "tarif ekskul per JP" di Pengaturan; kosong = tarif pembinanya.
     */
    public function catatEkskul(SesiEkskul $sesi, JadwalEkskul $jadwal): ?HonorMengajar
    {
        $umum = $this->aturan->umum();

        if (! $umum['aktif']) {
            return null;
        }

        $jp = $this->jpEkskul($jadwal);
        $tarif = $umum['tarif_ekskul'] ?? $this->aturan->tarifUntuk($sesi->user_id, $umum['tarif']);
        $tanggal = $sesi->tanggal->copy()->startOfDay();

        return DB::transaction(function () use ($sesi, $jadwal, $jp, $tarif, $tanggal) {
            $isi = [
                'user_id' => $sesi->user_id,
                'jadwal_id' => null,
                'jp' => $jp,
                'tarif_per_jp' => $tarif,
                'persen' => 100,
                'nominal' => $jp * $tarif,
                'rincian' => $this->potong('Ekskul ' . $jadwal->nama_ekskul . ' · ' . $jadwal->hari . ' · ' . $jadwal->rentangJam()),
                'absensi_mengajar_id' => null,
            ];

            $lama = HonorMengajar::query()
                ->where('jadwal_ekskul_id', $jadwal->id)
                ->wherePadaTanggal('tanggal', $tanggal)
                ->where('peran', HonorMengajar::PERAN_EKSKUL)
                ->lockForUpdate()
                ->first();

            if ($lama) {
                $lama->fill($isi)->save();

                return $lama;
            }

            return HonorMengajar::create($isi + [
                'jadwal_ekskul_id' => $jadwal->id,
                'tanggal' => $tanggal->toDateString(),
                'peran' => HonorMengajar::PERAN_EKSKUL,
            ]);
        });
    }

    /** JP ekskul untuk honor (dipakai juga layar Jadwal Ekskul sebagai pratinjau). */
    public function jpEkskul(JadwalEkskul $jadwal): int
    {
        $manual = (int) ($jadwal->jp_honor ?? 0);

        return $manual > 0
            ? min(255, $manual)
            : max(1, min(255, $this->jp->antara((string) $jadwal->jam_mulai, (string) $jadwal->jam_selesai)));
    }

    /** Penunjukan inval dibatalkan -> kedua bagian honornya ikut batal. */
    public function batalkanInval(int $jadwalId, Carbon $tanggal): int
    {
        return $this->bagianSlot($jadwalId, $tanggal, [HonorMengajar::PERAN_INVAL, HonorMengajar::PERAN_GURU_ASLI])->delete();
    }

    /* ===================== MEMBACA ===================== */

    /**
     * Rincian honor satu orang dalam satu bulan, terbaru di atas.
     *
     * @return array{daftar: Collection<int, HonorMengajar>, total: int, jp: int, per_peran: array<string, array{jp: int, nominal: int, jumlah: int}>}
     */
    public function rincian(int $userId, Carbon $bulan): array
    {
        $daftar = HonorMengajar::query()
            ->where('user_id', $userId)
            ->whereAntaraTanggal('tanggal', $bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth())
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->get();

        $perPeran = [];
        foreach (HonorMengajar::SEMUA_PERAN as $peran) {
            $bagian = $daftar->where('peran', $peran);
            $perPeran[$peran] = ['jp' => (int) $bagian->sum('jp'), 'nominal' => (int) $bagian->sum('nominal'), 'jumlah' => $bagian->count()];
        }

        return [
            'daftar' => $daftar,
            'total' => (int) $daftar->sum('nominal'),
            // JP yang benar-benar DIAJAR orang ini: bagian guru asli tidak
            // dihitung — jam itu diajar orang lain.
            'jp' => $perPeran[HonorMengajar::PERAN_MENGAJAR]['jp'] + $perPeran[HonorMengajar::PERAN_INVAL]['jp']
                + $perPeran[HonorMengajar::PERAN_EKSKUL]['jp'],
            'per_peran' => $perPeran,
        ];
    }

    /**
     * Rekap honor SEMUA orang dalam satu bulan — satu query agregat + satu
     * query akun, berapa pun jumlah gurunya.
     *
     * @return array{baris: Collection<int, array<string, mixed>>, total: int}
     */
    public function rekapBulan(Carbon $bulan): array
    {
        $agregat = HonorMengajar::query()
            ->whereAntaraTanggal('tanggal', $bulan->copy()->startOfMonth(), $bulan->copy()->endOfMonth())
            ->groupBy('user_id', 'peran')
            ->select('user_id', 'peran')
            ->selectRaw('SUM(jp) as jp, SUM(nominal) as nominal, COUNT(*) as jumlah')
            ->toBase()
            ->get()
            ->groupBy('user_id');

        // Guru & wali kelas tetap tampil walau belum punya honor bulan ini —
        // angka nol juga informasi ("kenapa Pak X belum ada honornya?").
        $orang = User::query()
            ->with('pegawai:id,user_id,nama')
            ->where(fn ($q) => $q->whereIn('id', $agregat->keys())
                ->orWhereIn('role', array_map(fn (UserRole $r) => $r->value, self::PERAN_PENGAJAR)))
            ->get(['id', 'name', 'role']);

        $tarif = $this->aturan->tarifUntukBanyak($orang->pluck('id')->all());
        $khusus = $this->aturan->tarifKhusus($orang->pluck('id')->all());

        $baris = $orang->map(function (User $u) use ($agregat, $tarif, $khusus) {
            $r = $agregat->get($u->id, collect())->keyBy('peran');
            $ambil = fn (string $peran, string $kolom) => (int) ($r->get($peran)?->{$kolom} ?? 0);

            $mengajar = $ambil(HonorMengajar::PERAN_MENGAJAR, 'nominal');
            $inval = $ambil(HonorMengajar::PERAN_INVAL, 'nominal');
            $asli = $ambil(HonorMengajar::PERAN_GURU_ASLI, 'nominal');
            $ekskul = $ambil(HonorMengajar::PERAN_EKSKUL, 'nominal');

            return [
                'user' => $u,
                'nama' => $u->pegawai?->nama ?? $u->name,
                'tarif' => $tarif[$u->id],
                'tarif_khusus' => isset($khusus[$u->id]),
                'jp_mengajar' => $ambil(HonorMengajar::PERAN_MENGAJAR, 'jp'),
                'jp_inval' => $ambil(HonorMengajar::PERAN_INVAL, 'jp'),
                'honor_mengajar' => $mengajar,
                'honor_inval' => $inval,
                'honor_guru_asli' => $asli,
                'jp_ekskul' => $ambil(HonorMengajar::PERAN_EKSKUL, 'jp'),
                'honor_ekskul' => $ekskul,
                'total' => $mengajar + $inval + $asli + $ekskul,
            ];
        })
            ->sortBy([['total', 'desc'], ['nama', 'asc']])
            ->values();

        return ['baris' => $baris, 'total' => (int) $baris->sum('total')];
    }

    /* ===================== PEMBANTU ===================== */

    /** Satu jadwal minimal 1 JP — jurnal yang benar-benar ada tidak boleh bernilai nol. */
    private function jpJadwal(JadwalPelajaran $jadwal): int
    {
        return max(1, min(255, $this->jp->untukJadwal($jadwal)));
    }

    private function rincianJadwal(JadwalPelajaran $jadwal): string
    {
        $jadwal->loadMissing('kelas:id,nama_kelas');

        return $this->potong(($jadwal->mata_pelajaran ?? 'Mapel') . ' · ' . ($jadwal->kelas?->nama_kelas ?? '-') . ' · ' . $jadwal->rentangJam());
    }

    private function potong(string $teks): string
    {
        return mb_substr($teks, 0, 255);
    }

    /** @param  array<int, string>  $peran */
    private function bagianSlot(int $jadwalId, Carbon $tanggal, array $peran)
    {
        return HonorMengajar::query()
            ->where('jadwal_id', $jadwalId)
            ->wherePadaTanggal('tanggal', $tanggal)
            ->whereIn('peran', $peran);
    }

    /**
     * Buat atau timpa SATU baris honor untuk (jadwal, tanggal, peran).
     *
     * Dicari dengan wherePadaTanggal, BUKAN updateOrCreate(['tanggal' => ...]):
     * bentuk simpanan tanggal berbeda antar-database (lihat
     * QueryTanggalServiceProvider) — updateOrCreate yang gagal menemukan
     * baris lama akan menabrak indeks unik.
     */
    private function simpanBaris(JadwalPelajaran $jadwal, Carbon $tanggal, string $peran, array $isi): HonorMengajar
    {
        return DB::transaction(function () use ($jadwal, $tanggal, $peran, $isi) {
            $lama = $this->bagianSlot($jadwal->id, $tanggal, [$peran])->lockForUpdate()->first();

            if ($lama) {
                $lama->fill($isi)->save();

                return $lama;
            }

            return HonorMengajar::create($isi + [
                'jadwal_id' => $jadwal->id,
                'tanggal' => $tanggal->toDateString(),
                'peran' => $peran,
            ]);
        });
    }
}
