<?php

namespace App\Models;

use App\Enums\StatusRapor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengunci status rapor satu kelas pada satu semester.
 */
class ValidasiRapor extends Model
{
    protected $table = 'validasi_rapors';

    protected $fillable = [
        'kelas_id',
        'tahun_ajaran',
        'semester',
        'status',
        'diajukan_oleh',
        'diajukan_pada',
        'disetujui_oleh',
        'tanggal_persetujuan',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'status' => StatusRapor::class,
            'diajukan_pada' => 'datetime',
            'tanggal_persetujuan' => 'datetime',
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /**
     * Status rapor sebuah kelas pada satu periode — SELALU mengembalikan
     * objek, walau barisnya belum pernah dibuat.
     *
     * Tanpa ini setiap pemanggil harus menulis `?->status ?? StatusRapor::Draft`
     * sendiri, dan satu tempat yang lupa akan memperlakukan "belum ada baris"
     * sebagai "sudah disetujui".
     */
    public static function untuk(int $kelasId, string $tahunAjaran, string $semester): self
    {
        return static::firstOrNew([
            'kelas_id' => $kelasId,
            'tahun_ajaran' => $tahunAjaran,
            'semester' => $semester,
        ], [
            'status' => StatusRapor::Draft,
        ]);
    }

    public function labelPeriode(): string
    {
        return $this->tahun_ajaran . ' — ' . ucfirst((string) $this->semester);
    }
}
