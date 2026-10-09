<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tarif honor KHUSUS satu orang per JP. Yang tidak punya baris di sini
 * memakai tarif umum — lihat App\Services\AturanHonor.
 */
class TarifHonorGuru extends Model
{
    protected $table = 'tarif_honor_guru';

    protected $fillable = ['user_id', 'tarif_per_jp'];

    protected function casts(): array
    {
        return ['tarif_per_jp' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
