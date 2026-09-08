<?php

namespace App\Models;

use App\Enums\AbsensiStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiEkskul extends Model
{
    protected $table = 'absensi_ekskuls';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status_kehadiran' => AbsensiStatus::class,
        ];
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalEkskul::class, 'jadwal_ekskul_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }
}
