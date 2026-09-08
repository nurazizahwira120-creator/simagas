<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pegawai extends Model
{
    use HasFactory;

    protected $table = 'pegawai';

    protected $fillable = [
        'nip',
        'nama',
        'jabatan',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'no_hp',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }

    /**
     * Akun login terkait (opsional — tidak semua pegawai punya akun user).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(AbsensiPegawai::class, 'pegawai_id');
    }

    /**
     * Jadwal mengajar milik pegawai ini (kalau ia mengajar). FK-nya
     * `guru_id`, bukan `pegawai_id`, mengikuti penamaan kolom di tabel
     * jadwal_pelajaran.
     */
    public function jadwalMengajar(): HasMany
    {
        return $this->hasMany(JadwalPelajaran::class, 'guru_id');
    }
}
