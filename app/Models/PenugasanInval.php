<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu jam pelajaran (jadwal) pada satu tanggal yang digantikan seorang
 * guru inval. Lihat migrasi 000044 dan App\Services\GuruInval.
 */
class PenugasanInval extends Model
{
    protected $table = 'penugasan_inval';

    protected $fillable = ['jadwal_id', 'tanggal', 'inval_user_id', 'ditunjuk_oleh'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_id');
    }

    public function inval(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inval_user_id');
    }

    public function penunjuk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditunjuk_oleh');
    }
}
