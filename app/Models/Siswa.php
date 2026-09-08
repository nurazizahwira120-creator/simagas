<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswa';

    protected $fillable = [
        'nis',
        'nama',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'foto',
        'no_hp_wali',
        'kelas_id',
        'wali_murid_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_lahir' => 'date',
        ];
    }

    /**
     * URL foto siswa yang siap dipasang di <img src="...">, atau null.
     *
     * Jalurnya dibuat RELATIF (mis. /storage/profile_photos/abc.jpg) — sama
     * seperti User::getFotoUrlAttribute(). Storage::url() merakit URL dari
     * APP_URL di .env; kalau nilainya tidak sama persis dengan alamat yang
     * dipakai membuka aplikasi, semua foto tampil rusak. Jalur relatif selalu
     * menunjuk host yang sedang dibuka.
     */
    public function getFotoUrlAttribute(): ?string
    {
        if (! $this->foto) {
            return null;
        }

        $url = \Illuminate\Support\Facades\Storage::disk('public')->url($this->foto);

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function waliMurid(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_murid_id');
    }

    public function absensi(): HasMany
    {
        return $this->hasMany(AbsensiSiswa::class, 'siswa_id');
    }
}
