<?php

namespace App\Models;

use App\Enums\Hari;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPelajaran extends Model
{
    use HasFactory;

    protected $table = 'jadwal_pelajaran';

    protected $fillable = [
        'hari',
        'jam_mulai',
        'jam_selesai',
        'mata_pelajaran',
        'ruangan',
        'kelas_id',
        'guru_id',
    ];

    /**
     * Setiap jadwal dibuat/diubah, nama mata pelajarannya didaftarkan ke
     * master `mapels` — kalau tidak, mapel baru tidak pernah muncul di
     * Input Nilai. Lihat Mapel::sinkron().
     *
     * rescue(): pendaftaran mapel adalah pelengkap. Gagalnya TIDAK boleh
     * membatalkan penyimpanan jadwal yang sedang dikerjakan admin; ia
     * tercatat di log, dan diajarOleh() akan menyembuhkannya nanti.
     */
    protected static function booted(): void
    {
        static::saved(function (self $jadwal) {
            if ($jadwal->wasChanged('mata_pelajaran') || $jadwal->wasRecentlyCreated) {
                rescue(fn () => Mapel::sinkron([$jadwal->mata_pelajaran]), report: true);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'hari' => Hari::class,
            // Kolom bertipe TIME — di-cast ke Carbon supaya bisa langsung
            // ->format('H:i') di view, sama seperti jam_masuk di tabel absensi.
            'jam_mulai' => 'datetime:H:i',
            'jam_selesai' => 'datetime:H:i',
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /**
     * Guru pengajar — mengarah ke tabel `pegawai`, bukan `users`.
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'guru_id');
    }

    /**
     * Rentang jam siap tampil, mis. "07:00 – 08:30".
     */
    public function rentangJam(): string
    {
        return $this->jam_mulai->format('H:i') . ' – ' . $this->jam_selesai->format('H:i');
    }
}
