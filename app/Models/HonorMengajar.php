<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu bagian honor untuk satu orang dari satu jam pelajaran pada satu
 * tanggal. Lihat migrasi 000045 dan App\Services\PencatatHonor.
 */
class HonorMengajar extends Model
{
    /** Guru mengajar kelasnya sendiri dan menekan "Akhiri Sesi". */
    public const PERAN_MENGAJAR = 'mengajar';

    /** Guru/staf inval mengisi absensi kelas guru yang berhalangan. */
    public const PERAN_INVAL = 'inval';

    /** Bagian guru yang berhalangan dari jam yang diajar inval. */
    public const PERAN_GURU_ASLI = 'guru_asli';

    /** Pembina ekskul menekan "Akhiri Sesi" ekskul. */
    public const PERAN_EKSKUL = 'ekskul';

    /** Urutan tampil di ringkasan & rekap. */
    public const SEMUA_PERAN = [self::PERAN_MENGAJAR, self::PERAN_INVAL, self::PERAN_GURU_ASLI, self::PERAN_EKSKUL];

    protected $table = 'honor_mengajar';

    protected $fillable = [
        'user_id', 'jadwal_id', 'tanggal', 'peran', 'jp', 'tarif_per_jp',
        'persen', 'nominal', 'rincian', 'absensi_mengajar_id', 'jadwal_ekskul_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jp' => 'integer',
            'tarif_per_jp' => 'integer',
            'persen' => 'integer',
            'nominal' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_id');
    }

    /** Label singkat yang tampil di rincian guru. */
    public function labelPeran(): string
    {
        return match ($this->peran) {
            self::PERAN_INVAL => 'Inval',
            self::PERAN_GURU_ASLI => 'Bagian saat diinval',
            self::PERAN_EKSKUL => 'Ekskul',
            default => 'Mengajar',
        };
    }
}
