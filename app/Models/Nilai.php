<?php

namespace App\Models;

use App\Enums\JenisPenilaian;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu angka yang masuk rapor (satu siswa, satu mata pelajaran,
 * satu jenis penilaian, satu semester).
 */
class Nilai extends Model
{
    protected $table = 'nilais';

    protected $fillable = [
        'siswa_id',
        'mapel_id',
        'guru_id',
        'tahun_ajaran',
        'semester',
        'jenis_penilaian',
        'skor',
    ];

    protected function casts(): array
    {
        return [
            // decimal:2 -> selalu string "85.00", bukan float 85.0. Disengaja:
            // float membuat 0.1+0.2 dan pembulatan rata-rata tidak konsisten
            // antar mesin.
            'skor' => 'decimal:2',
            'jenis_penilaian' => JenisPenilaian::class,
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    public function mapel(): BelongsTo
    {
        return $this->belongsTo(Mapel::class, 'mapel_id');
    }

    /** Guru penginput -> tabel `pegawai`, bukan `users`. */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'guru_id');
    }

    /** Predikat huruf sederhana untuk tampilan rapor. */
    public function predikat(): string
    {
        $s = (float) $this->skor;

        return match (true) {
            $s >= 90 => 'A',
            $s >= 80 => 'B',
            $s >= 70 => 'C',
            $s >= 60 => 'D',
            default => 'E',
        };
    }
}
