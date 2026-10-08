<?php

namespace App\Services;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Jobs\KirimPushNotifikasi;
use App\Models\JadwalPelajaran;
use App\Models\PenugasanInval;
use App\Models\User;
use App\Notifications\DitunjukJadiInval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Aturan GURU INVAL — satu tempat untuk siapa boleh menunjuk, siapa boleh
 * ditunjuk, dan kapan seorang calon BENTROK dengan jadwalnya sendiri.
 *
 *   Penunjuk : Kepala Sekolah, Super Admin.
 *   Calon    : guru, wali kelas, guru piket, staf, admin TU — akun aktif,
 *              tidak sedang berhalangan pada tanggal itu.
 *   Pengisi  : HANYA inval yang ditunjuk, ditambah Kepsek & Super Admin
 *              (koreksi). Guru piket/wali kelas tidak lagi mengisi kelas
 *              orang lain tanpa penunjukan.
 *
 * ============ BENTROK ============
 * Calon tidak boleh ditunjuk untuk jam yang tumpang tindih dengan:
 *   1. jadwal mengajarnya sendiri hari itu (ia harus berada di kelasnya), atau
 *   2. tugas inval lain yang sudah ia pegang pada jam yang sama.
 * Diperiksa di server setiap kali menunjuk — opsi yang dinonaktifkan di
 * layar hanyalah kenyamanan, bukan pengaman.
 * =================================
 */
class GuruInval
{
    /** Peran yang boleh menunjuk inval (dan mengoreksi absensinya). */
    public const PERAN_PENUNJUK = [UserRole::SuperAdmin, UserRole::Kepsek];

    /** Peran yang boleh ditunjuk menjadi inval. */
    public const PERAN_CALON = [UserRole::Guru, UserRole::WaliKelas, UserRole::GuruPiket, UserRole::Staff, UserRole::AdminTu];

    /** Penunjukan bisa direncanakan sampai sekian hari ke depan. */
    public const MAKS_HARI_KE_DEPAN = 14;

    public function __construct(private readonly StatusBerhalanganGuru $berhalangan)
    {
    }

    public static function bolehMenunjuk(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::PERAN_PENUNJUK, true);
    }

    /**
     * Jadwal pada $tanggal yang gurunya berhalangan.
     *
     * @return Collection<int, array{jadwal: JadwalPelajaran, alasan: array}> dikunci jadwal_id
     */
    public function jadwalBerhalangan(Carbon $tanggal): Collection
    {
        $jadwal = JadwalPelajaran::query()
            ->with(['kelas:id,nama_kelas', 'guru:id,nama,user_id'])
            ->where('hari', KalenderAkademik::hariDari($tanggal)->value)
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->get();

        $status = $this->berhalangan->pada($jadwal->pluck('guru')->filter(), $tanggal);

        return $jadwal
            ->filter(fn (JadwalPelajaran $j) => $j->guru_id && $status->has($j->guru_id))
            ->mapWithKeys(fn (JadwalPelajaran $j) => [$j->id => ['jadwal' => $j, 'alasan' => $status->get($j->guru_id)]]);
    }

    /**
     * Penugasan pada $tanggal, dikunci jadwal_id.
     *
     * @return Collection<int, PenugasanInval>
     */
    public function penugasanPada(Carbon $tanggal, ?int $invalUserId = null): Collection
    {
        return PenugasanInval::query()
            ->with('inval:id,name,role')
            ->wherePadaTanggal('tanggal', $tanggal)
            ->when($invalUserId, fn ($q) => $q->where('inval_user_id', $invalUserId))
            ->get()
            ->keyBy('jadwal_id');
    }

    /**
     * Akun yang boleh ditunjuk pada $tanggal, beserta jam sibuknya.
     *
     * @return Collection<int, array{user: User, sibuk: array<int, array{mulai: int, selesai: int, ket: string}>}> dikunci user_id
     */
    public function calon(Carbon $tanggal): Collection
    {
        $pengguna = User::query()
            ->with('pegawai:id,user_id,nama')
            ->whereIn('role', array_map(fn (UserRole $r) => $r->value, self::PERAN_CALON))
            ->where('status', StatusAkun::Active->value)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        // Calon yang dirinya sendiri sedang berhalangan tidak bisa menggantikan.
        $tidakHadir = $this->berhalangan->pada($pengguna->pluck('pegawai')->filter(), $tanggal);
        $pengguna = $pengguna->reject(fn (User $u) => $u->pegawai && $tidakHadir->has($u->pegawai->id));

        // Jam mengajar sendiri hari itu — SATU query untuk semua calon.
        $pegawaiKeUser = $pengguna->filter(fn (User $u) => $u->pegawai)->mapWithKeys(fn (User $u) => [$u->pegawai->id => $u->id]);

        $sibuk = [];

        JadwalPelajaran::query()
            ->with('kelas:id,nama_kelas')
            ->where('hari', KalenderAkademik::hariDari($tanggal)->value)
            ->whereIn('guru_id', $pegawaiKeUser->keys())
            ->get()
            ->each(function (JadwalPelajaran $j) use (&$sibuk, $pegawaiKeUser) {
                $sibuk[$pegawaiKeUser[$j->guru_id]][] = self::rentang($j) + [
                    'ket' => 'mengajar ' . ($j->kelas?->nama_kelas ?? '-') . ' ' . $j->rentangJam(),
                    'jadwal_id' => $j->id,
                ];
            });

        // Tugas inval lain yang sudah dipegang hari itu.
        PenugasanInval::query()
            ->with('jadwal.kelas:id,nama_kelas')
            ->wherePadaTanggal('tanggal', $tanggal)
            ->whereIn('inval_user_id', $pengguna->pluck('id'))
            ->get()
            ->each(function (PenugasanInval $p) use (&$sibuk) {
                if ($p->jadwal) {
                    $sibuk[$p->inval_user_id][] = self::rentang($p->jadwal) + [
                        'ket' => 'inval ' . ($p->jadwal->kelas?->nama_kelas ?? '-') . ' ' . $p->jadwal->rentangJam(),
                        'jadwal_id' => $p->jadwal_id,
                    ];
                }
            });

        return $pengguna->mapWithKeys(fn (User $u) => [$u->id => ['user' => $u, 'sibuk' => $sibuk[$u->id] ?? []]]);
    }

    /**
     * Alasan $calon tidak bisa menggantikan $jadwal, atau null bila bisa.
     *
     * @param  array{user: User, sibuk: array}|null  $calon  baris dari calon()
     */
    public static function bentrok(?array $calon, JadwalPelajaran $jadwal): ?string
    {
        if ($calon === null) {
            return 'Akun ini tidak bisa ditunjuk (bukan guru/staf aktif, atau sedang berhalangan).';
        }

        $r = self::rentang($jadwal);

        foreach ($calon['sibuk'] as $s) {
            // Tugas inval di jadwal YANG SAMA bukan bentrok (menunjuk ulang orang yang sama).
            if (($s['jadwal_id'] ?? null) === $jadwal->id) {
                continue;
            }

            if ($r['mulai'] < $s['selesai'] && $s['mulai'] < $r['selesai']) {
                return 'Bentrok: ' . $s['ket'] . '.';
            }
        }

        return null;
    }

    /**
     * Tunjuk $inval untuk sejumlah jadwal pada $tanggal. Jadwal yang bentrok
     * dilewati dan dilaporkan; satu notifikasi ringkasan dikirim ke inval.
     *
     * @param  iterable<JadwalPelajaran>  $daftarJadwal
     * @return array{ditunjuk: array<int, JadwalPelajaran>, dilewati: array<int, string>}
     */
    public function tunjuk(iterable $daftarJadwal, Carbon $tanggal, User $inval, User $penunjuk): array
    {
        $calon = $this->calon($tanggal)->get($inval->id);
        $ditunjuk = [];
        $baru = [];
        $dilewati = [];

        foreach ($daftarJadwal as $jadwal) {
            $alasan = self::bentrok($calon, $jadwal);

            if ($alasan) {
                $dilewati[$jadwal->id] = $jadwal->rentangJam() . ' ' . ($jadwal->kelas?->nama_kelas ?? '') . ' — ' . $alasan;

                continue;
            }

            $berubah = DB::transaction(function () use ($jadwal, $tanggal, $inval, $penunjuk) {
                // Dicari dengan wherePadaTanggal, BUKAN updateOrCreate(['tanggal' => ...]):
                // bentuk simpanan tanggal berbeda antar-database (lihat
                // QueryTanggalServiceProvider), dan updateOrCreate yang gagal
                // menemukan baris lama akan menabrak indeks unik.
                $lama = PenugasanInval::query()
                    ->where('jadwal_id', $jadwal->id)
                    ->wherePadaTanggal('tanggal', $tanggal)
                    ->lockForUpdate()
                    ->first();

                $isi = ['inval_user_id' => $inval->id, 'ditunjuk_oleh' => $penunjuk->id];

                if ($lama) {
                    $sudahOrangItu = $lama->inval_user_id === $inval->id;
                    $lama->fill($isi)->save();

                    return ! $sudahOrangItu;
                }

                PenugasanInval::create($isi + ['jadwal_id' => $jadwal->id, 'tanggal' => $tanggal->toDateString()]);

                return true;
            });

            $ditunjuk[$jadwal->id] = $jadwal;

            // Yang dikabarkan hanya jam yang BARU bagi orang ini — menunjuk
            // ulang orang yang sama tidak membunyikan HP-nya dua kali.
            if ($berubah) {
                $baru[$jadwal->id] = $jadwal;
            }

            // Jam yang baru dipegang ikut dihitung sibuk untuk jadwal berikutnya
            // di perulangan yang sama (menunjuk "semua jam" yang saling tumpang tindih).
            if ($calon) {
                $calon['sibuk'][] = self::rentang($jadwal) + ['ket' => 'inval ' . $jadwal->rentangJam(), 'jadwal_id' => $jadwal->id];
            }
        }

        if ($baru) {
            $this->kabari($inval, collect($baru), $tanggal);
        }

        return ['ditunjuk' => $ditunjuk, 'dilewati' => $dilewati];
    }

    public function batalkan(int $jadwalId, Carbon $tanggal): bool
    {
        return PenugasanInval::query()
            ->where('jadwal_id', $jadwalId)
            ->wherePadaTanggal('tanggal', $tanggal)
            ->delete() > 0;
    }

    /** Lonceng + push ke inval. Gagal mengabari TIDAK membatalkan penunjukan. */
    private function kabari(User $inval, Collection $jadwal, Carbon $tanggal): void
    {
        $jadwal = $jadwal->sortBy(fn (JadwalPelajaran $j) => $j->jam_mulai->format('H:i'))->values();
        $pertama = $jadwal->first();
        $hari = $tanggal->isToday() ? 'hari ini' : $tanggal->translatedFormat('l, d M');

        $pesan = $jadwal->count() === 1
            ? "{$pertama->mata_pelajaran} ({$pertama->kelas?->nama_kelas}) {$hari}, {$pertama->rentangJam()} — menggantikan {$pertama->guru?->nama}."
            : "{$jadwal->count()} jam pelajaran {$hari}, menggantikan {$pertama->guru?->nama}.";

        $cuplikan = $jadwal->map(fn (JadwalPelajaran $j) => $j->rentangJam() . ' ' . $j->mata_pelajaran . ' (' . ($j->kelas?->nama_kelas ?? '-') . ')')->implode('; ');

        try {
            $inval->notify(new DitunjukJadiInval($pesan, $cuplikan));
        } catch (Throwable $e) {
            Log::warning('Guru inval: gagal menulis lonceng.', ['user_id' => $inval->id, 'error' => $e->getMessage()]);
        }

        if (! $inval->bisaMenerimaPush()) {
            return;
        }

        try {
            $prefix = $inval->role?->routePrefix();

            KirimPushNotifikasi::dispatch(
                userId: (int) $inval->id,
                judul: 'Anda ditunjuk menjadi guru inval',
                isi: $pesan,
                data: [
                    'jenis' => 'guru-inval',
                    'url' => $prefix && Route::has("{$prefix}.guru-inval") ? route("{$prefix}.guru-inval") : url('/'),
                ],
            )->onConnection(config('firebase.queue_connection', 'sync'));
        } catch (Throwable $e) {
            Log::warning('Guru inval: gagal mengirim push.', ['user_id' => $inval->id, 'error' => $e->getMessage()]);
        }
    }

    /** @return array{mulai: int, selesai: int} menit sejak tengah malam */
    public static function rentang(JadwalPelajaran $j): array
    {
        return [
            'mulai' => $j->jam_mulai->hour * 60 + $j->jam_mulai->minute,
            'selesai' => $j->jam_selesai->hour * 60 + $j->jam_selesai->minute,
        ];
    }
}
