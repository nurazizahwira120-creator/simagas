<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu kali guru masuk kelas dan men-scan stiker QR ruangan.
 *
 * Berbeda dengan AbsensiPegawai yang hanya satu baris per hari, tabel ini
 * bisa berisi banyak baris per hari untuk orang yang sama — satu per jam
 * mengajar.
 */
class AbsensiMengajar extends Model
{
    use HasFactory;

    protected $table = 'absensi_mengajar';

    protected $fillable = [
        'user_id',
        'kode_kelas',
        'waktu_mulai',
    ];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
