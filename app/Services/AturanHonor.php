<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Pengaturan;
use App\Models\TarifHonorGuru;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * ATURAN HONOR MENGAJAR (uji coba) — satu tempat untuk semua angka yang
 * diatur Kepala Sekolah / Super Admin.
 *
 * ============ TIGA PENGATURAN UMUM (tabel `pengaturan`) ============
 *   honor_aktif         '1' / '0' — sakelar fitur. Selama mati, TIDAK ADA
 *                       honor BARU yang dicatat (yang lama tetap terlihat).
 *   honor_tarif_per_jp  tarif umum dalam rupiah per 1 JP.
 *   honor_persen_inval  bagian guru inval (0–100%) dari honor jam yang ia
 *                       gantikan. Sisanya untuk guru aslinya.
 *   honor_tarif_ekskul_per_jp  tarif per JP untuk sesi ekskul. Kosong =
 *                       ekskul memakai tarif pembinanya sendiri.
 * ===================================================================
 *
 * Tarif KHUSUS per orang ada di tabel tarif_honor_guru dan selalu
 * mengalahkan tarif umum. Kosong = ikut tarif umum.
 */
class AturanHonor
{
    public const KUNCI_AKTIF = 'honor_aktif';

    public const KUNCI_TARIF = 'honor_tarif_per_jp';

    public const KUNCI_PERSEN_INVAL = 'honor_persen_inval';

    /** Tarif per JP khusus ekskul. Kosong = ikut tarif pembinanya (umum/khusus). */
    public const KUNCI_TARIF_EKSKUL = 'honor_tarif_ekskul_per_jp';

    /** Bawaan sebelum diatur: fitur mati, tarif nol, inval dapat separuh. */
    public const BAWAAN_PERSEN_INVAL = 50;

    /** Batas atas masuk akal untuk tarif 1 JP — mencegah salah ketik nol berlebih. */
    public const MAKS_TARIF = 5_000_000;

    /** Hanya dua peran ini yang boleh mengatur tarif & melihat honor semua guru. */
    public const PERAN_PENGATUR = [UserRole::SuperAdmin, UserRole::Kepsek];

    public static function bolehMengatur(?User $user): bool
    {
        return $user !== null && in_array($user->role, self::PERAN_PENGATUR, true);
    }

    /**
     * Ketiga pengaturan umum sekaligus — SATU query.
     *
     * @return array{aktif: bool, tarif: int, persen_inval: int, tarif_ekskul: int|null}
     */
    public function umum(): array
    {
        $nilai = Pengaturan::ambilBanyak([
            self::KUNCI_AKTIF => '0',
            self::KUNCI_TARIF => '0',
            self::KUNCI_PERSEN_INVAL => (string) self::BAWAAN_PERSEN_INVAL,
            self::KUNCI_TARIF_EKSKUL => null,
        ]);

        return [
            'aktif' => $nilai[self::KUNCI_AKTIF] === '1',
            'tarif' => $this->rapikanTarif($nilai[self::KUNCI_TARIF]),
            'persen_inval' => max(0, min(100, (int) $nilai[self::KUNCI_PERSEN_INVAL])),
            'tarif_ekskul' => filled($nilai[self::KUNCI_TARIF_EKSKUL]) ? $this->rapikanTarif($nilai[self::KUNCI_TARIF_EKSKUL]) : null,
        ];
    }

    public function aktif(): bool
    {
        return Pengaturan::ambil(self::KUNCI_AKTIF, '0') === '1';
    }

    /**
     * @param  int|null|false  $tarifEkskul  null = ikut tarif pembina; false = tidak diubah
     */
    public function simpanUmum(bool $aktif, int $tarif, int $persenInval, int|null|false $tarifEkskul = false): void
    {
        DB::transaction(function () use ($aktif, $tarif, $persenInval, $tarifEkskul) {
            Pengaturan::simpan(self::KUNCI_AKTIF, $aktif ? '1' : '0');
            Pengaturan::simpan(self::KUNCI_TARIF, (string) $tarif);
            Pengaturan::simpan(self::KUNCI_PERSEN_INVAL, (string) $persenInval);

            if ($tarifEkskul !== false) {
                Pengaturan::simpan(self::KUNCI_TARIF_EKSKUL, $tarifEkskul === null ? null : (string) $tarifEkskul);
            }
        });
    }

    /**
     * Tarif per JP untuk banyak orang sekaligus — satu query, tanpa N+1.
     *
     * @param  array<int, int>  $userIds
     * @return array<int, int>  [user_id => tarif]
     */
    public function tarifUntukBanyak(array $userIds, ?int $tarifUmum = null): array
    {
        $tarifUmum ??= $this->umum()['tarif'];
        $khusus = $this->tarifKhusus($userIds);

        $hasil = [];
        foreach (array_unique($userIds) as $id) {
            $hasil[$id] = $khusus[$id] ?? $tarifUmum;
        }

        return $hasil;
    }

    public function tarifUntuk(?int $userId, ?int $tarifUmum = null): int
    {
        $tarifUmum ??= $this->umum()['tarif'];

        return $userId ? ($this->tarifKhusus([$userId])[$userId] ?? $tarifUmum) : $tarifUmum;
    }

    /**
     * @param  array<int, int>  $userIds  kosong = semua
     * @return array<int, int>  [user_id => tarif] — HANYA yang punya tarif khusus
     */
    public function tarifKhusus(array $userIds = []): array
    {
        return TarifHonorGuru::query()
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->pluck('tarif_per_jp', 'user_id')
            ->map(fn ($t) => (int) $t)
            ->all();
    }

    /** null / kosong = hapus tarif khusus (kembali ikut tarif umum). */
    public function simpanTarifKhusus(int $userId, ?int $tarif): void
    {
        if ($tarif === null) {
            TarifHonorGuru::where('user_id', $userId)->delete();

            return;
        }

        TarifHonorGuru::updateOrCreate(['user_id' => $userId], ['tarif_per_jp' => $tarif]);
    }

    /** 25000 -> "Rp 25.000" — satu format untuk layar guru & kepsek. */
    public static function rupiah(int $nominal): string
    {
        return 'Rp ' . number_format($nominal, 0, ',', '.');
    }

    private function rapikanTarif(?string $nilai): int
    {
        return max(0, min(self::MAKS_TARIF, (int) $nilai));
    }
}
