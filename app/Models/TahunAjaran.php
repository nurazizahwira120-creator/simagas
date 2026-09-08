<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TahunAjaran extends Model
{
    protected $table = 'tahun_ajaran';

    protected $fillable = ['tahun', 'semester', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    /**
     * Label siap tampil, mis. "2026/2027 — Ganjil".
     */
    public function label(): string
    {
        return $this->tahun . ' — ' . ucfirst($this->semester);
    }

    /**
     * Tahun ajaran yang sedang berjalan (null kalau belum ada yang diaktifkan).
     */
    public static function yangAktif(): ?self
    {
        return static::query()->aktif()->first();
    }
}
