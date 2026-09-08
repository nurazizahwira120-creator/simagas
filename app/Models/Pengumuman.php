<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pengumuman extends Model
{
    /**
     * Laravel akan menebak nama tabelnya "pengumumen" dari bentuk jamak
     * bahasa Inggris. Ditulis eksplisit supaya tidak ada kejutan.
     */
    protected $table = 'pengumuman';

    protected $fillable = [
        'user_id',
        'judul',
        'isi_pesan',
        'target_role',
        'is_sent_wa',
        'jumlah_penerima',
        'jumlah_wa',
    ];

    protected function casts(): array
    {
        return [
            'is_sent_wa' => 'boolean',
            'jumlah_penerima' => 'integer',
            'jumlah_wa' => 'integer',
        ];
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Label target yang bisa dibaca manusia. */
    public function labelTarget(): string
    {
        return match ($this->target_role) {
            'pegawai' => 'Seluruh Guru & Staff',
            'wali_murid' => 'Seluruh Wali Murid',
            default => 'Semua Pengguna',
        };
    }
}
