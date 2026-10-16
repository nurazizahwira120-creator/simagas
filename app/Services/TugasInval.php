<?php

namespace App\Services;

use App\Enums\StatusApproval;
use App\Models\PengajuanIzinGuru;
use App\Models\PenugasanInval;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * TUGAS UNTUK GURU INVAL — menyambungkan izin IDT ke penggantinya.
 *
 * Guru yang mengajukan IDT (Izin Dengan Tugas) menulis deskripsi tugas dan
 * boleh melampirkan berkas. Service ini yang memastikan tugas itu SAMPAI ke
 * guru inval yang ditunjuk, tanpa langkah tambahan dari Kepsek/Admin:
 *   - kartu kelas di halaman Guru Inval (inval & penunjuk),
 *   - notifikasi penunjukan (cuplikan tugas),
 *   - hak membuka lampiran (disk privat).
 *
 * Ditambah CATATAN dari penunjuk (kolom penugasan_inval.catatan) untuk
 * tugas susulan atau izin yang tercatat lewat absensi harian.
 *
 * Semua pengambilan data berjumlah query TETAP (tanpa N+1).
 */
class TugasInval
{
    /** Batas panjang catatan penunjuk untuk inval. */
    public const MAKS_CATATAN = 1000;

    /**
     * Pengajuan izin berdasarkan id — SATU query.
     *
     * @param  iterable<int|null>  $izinId
     * @return Collection<int, PengajuanIzinGuru> dikunci id
     */
    public function izinUntuk(iterable $izinId): Collection
    {
        $id = collect($izinId)->filter()->unique()->values();

        if ($id->isEmpty()) {
            return collect();
        }

        return PengajuanIzinGuru::query()
            ->whereIn('id', $id)
            ->get(['id', 'guru_id', 'jenis_izin', 'detail_tugas', 'file_tugas', 'tanggal_mulai', 'tanggal_selesai', 'status_approval'])
            ->keyBy('id');
    }

    /**
     * Izin DISETUJUI yang berlaku untuk tiap penugasan (dipakai daftar
     * "tugas inval mendatang", yang tanggalnya berbeda-beda). SATU query.
     *
     * Penugasan harus sudah memuat jadwal.guru (dengan user_id). Bila dua
     * izin tumpang tindih, yang TERBARU yang dipakai — aturan yang sama
     * dengan StatusBerhalanganGuru.
     *
     * @param  Collection<int, PenugasanInval>  $penugasan
     * @return Collection<int, PengajuanIzinGuru|null> dikunci penugasan.id
     */
    public function izinUntukPenugasan(Collection $penugasan): Collection
    {
        $akunGuru = $penugasan->map(fn (PenugasanInval $p) => $p->jadwal?->guru?->user_id)->filter()->unique()->values();

        if ($penugasan->isEmpty() || $akunGuru->isEmpty()) {
            return collect();
        }

        $izin = PengajuanIzinGuru::query()
            ->where('status_approval', StatusApproval::Disetujui->value)
            ->whereIn('guru_id', $akunGuru)
            ->whereDate('tanggal_mulai', '<=', $penugasan->max(fn ($p) => $p->tanggal)->toDateString())
            ->whereDate('tanggal_selesai', '>=', $penugasan->min(fn ($p) => $p->tanggal)->toDateString())
            ->orderByDesc('id')
            ->get(['id', 'guru_id', 'jenis_izin', 'detail_tugas', 'file_tugas', 'tanggal_mulai', 'tanggal_selesai']);

        return $penugasan->mapWithKeys(fn (PenugasanInval $p) => [
            $p->id => $izin->first(fn (PengajuanIzinGuru $i) => $i->guru_id === $p->jadwal?->guru?->user_id
                && $p->tanggal->betweenIncluded($i->tanggal_mulai, $i->tanggal_selesai)),
        ]);
    }

    /**
     * Ringkasan tugas satu jam pelajaran, siap ditampilkan.
     *
     * @return array{jenis: \App\Enums\JenisIzinGuru|null, detail: string|null, lampiran_id: int|null, catatan: string|null, ada: bool}
     */
    public static function ringkas(?PengajuanIzinGuru $izin, ?PenugasanInval $penugasan = null): array
    {
        $detail = filled($izin?->detail_tugas) ? trim($izin->detail_tugas) : null;
        $catatan = filled($penugasan?->catatan) ? trim($penugasan->catatan) : null;

        return [
            'jenis' => $izin?->jenis_izin,
            'detail' => $detail,
            // Hanya id-nya; tautan dibuat view lewat rute guru-inval.lampiran,
            // yang memeriksa ulang hak aksesnya di server.
            'lampiran_id' => $izin?->adaLampiran() ? $izin->id : null,
            'catatan' => $catatan,
            'ada' => $detail !== null || $catatan !== null || ($izin?->adaLampiran() ?? false),
        ];
    }

    /**
     * Bolehkah $user membuka lampiran tugas $izin?
     *
     *   - Kepsek / Super Admin  : selalu (mereka yang menunjuk inval).
     *   - Guru pemilik izin     : selalu.
     *   - Guru inval            : hanya bila izinnya DISETUJUI dan ia ditunjuk
     *                             menggantikan guru itu pada salah satu tanggal
     *                             di dalam rentang izinnya.
     *   - Selain itu            : tidak. Lampiran bisa berisi soal ujian.
     */
    public function bolehBukaLampiran(User $user, PengajuanIzinGuru $izin): bool
    {
        if (GuruInval::bolehMenunjuk($user) || $izin->guru_id === $user->id) {
            return true;
        }

        if ($izin->status_approval !== StatusApproval::Disetujui) {
            return false;
        }

        return PenugasanInval::query()
            ->where('inval_user_id', $user->id)
            ->whereAntaraTanggal('tanggal', $izin->tanggal_mulai, $izin->tanggal_selesai)
            ->whereHas('jadwal.guru', fn ($q) => $q->where('user_id', $izin->guru_id))
            ->exists();
    }

    /**
     * Rapikan isian catatan: spasi di ujung dibuang, kosong menjadi null.
     */
    public static function bersihkanCatatan(?string $catatan): ?string
    {
        $catatan = trim((string) $catatan);

        return $catatan === '' ? null : mb_substr($catatan, 0, self::MAKS_CATATAN);
    }

    /**
     * Kalimat pendek untuk notifikasi: tugas guru dan/atau catatan penunjuk.
     */
    public static function kalimatNotifikasi(array $ringkas): ?string
    {
        $bagian = [];

        if ($ringkas['detail']) {
            $bagian[] = 'Tugas dari guru: ' . Str::limit(preg_replace('/\s+/', ' ', $ringkas['detail']), 140);
        } elseif ($ringkas['lampiran_id']) {
            $bagian[] = 'Ada lampiran tugas dari guru.';
        }

        if ($ringkas['catatan']) {
            $bagian[] = 'Catatan: ' . Str::limit(preg_replace('/\s+/', ' ', $ringkas['catatan']), 140);
        }

        return $bagian ? implode(' · ', $bagian) : null;
    }
}
