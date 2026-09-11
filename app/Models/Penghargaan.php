<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Riwayat apresiasi untuk siswa maupun guru.
 */
class Penghargaan extends Model
{
    public const PERAN_SISWA = 'siswa';
    public const PERAN_GURU = 'guru';

    public const KATEGORI_BINTANG_KELAS = 'Bintang Kelas (Peringkat 1)';
    public const KATEGORI_GURU_TELADAN = 'Guru Teladan & Tertib Administrasi';

    protected $fillable = [
        'user_id',
        'siswa_id',
        'kelas_id',
        'peran',
        'kategori',
        'periode',
        'pesan_apresiasi',
        'nilai_acuan',
    ];

    protected function casts(): array
    {
        return ['nilai_acuan' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /* ===================== PERIODE ===================== */

    /** Periode penghargaan siswa: "2026/2027-ganjil". */
    public static function periodeSemester(string $tahunAjaran, string $semester): string
    {
        return $tahunAjaran . '-' . $semester;
    }

    /** Periode penghargaan guru: "2026-08". */
    public static function periodeBulan(Carbon $bulan): string
    {
        return $bulan->format('Y-m');
    }

    /* ===================== SCOPE ===================== */

    public function scopeUntukUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }

    public function scopePeriode(Builder $q, string $periode): Builder
    {
        return $q->where('periode', $periode);
    }

    /**
     * Label periode siap tampil. Bentuk periodenya berbeda antara siswa
     * ("2026/2027-ganjil") dan guru ("2026-08"), jadi dibedakan di sini —
     * bukan di setiap view yang menampilkannya.
     */
    public function labelPeriode(): string
    {
        if ($this->peran === self::PERAN_GURU) {
            try {
                return Carbon::createFromFormat('Y-m', $this->periode)
                    ->locale('id')
                    ->isoFormat('MMMM Y');
            } catch (\Throwable $e) {
                return $this->periode;
            }
        }

        // "2026/2027-ganjil" -> "2026/2027 — Ganjil"
        $bagian = explode('-', $this->periode);
        $semester = array_pop($bagian);

        return implode('-', $bagian) . ' — ' . ucfirst((string) $semester);
    }
}
