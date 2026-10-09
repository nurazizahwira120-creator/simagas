<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu pertemuan ekskul yang dimulai pembina lewat scan QR ekskul.
 *
 * ============ SIKLUS HIDUP — SAMA DENGAN SESI KBM ============
 *   1. Pembina scan QR ekskul          -> baris INI dibuat (waktu_mulai)
 *   2. Pembina mengisi absensi anggota -> absensi_ekskuls terisi
 *   3. Pembina unggah foto bukti       -> foto_bukti terisi
 *   4. Pembina menekan "Akhiri Sesi"   -> waktu_selesai terisi (+ honor)
 * Langkah 4 ditolak sebelum 2 dan 3 terpenuhi. Lihat AturanSesiEkskul.
 * =============================================================
 */
class SesiEkskul extends Model
{
    protected $table = 'sesi_ekskul';

    protected $fillable = [
        'jadwal_ekskul_id', 'user_id', 'tanggal', 'waktu_mulai', 'waktu_selesai',
        'foto_bukti', 'bukti_dihapus_pada', 'pengingat_akhiri_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'bukti_dihapus_pada' => 'datetime',
            'pengingat_akhiri_pada' => 'datetime',
        ];
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalEkskul::class, 'jadwal_ekskul_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sudahSelesai(): bool
    {
        return $this->waktu_selesai !== null;
    }

    public function adaBukti(): bool
    {
        return filled($this->foto_bukti);
    }

    /** URL foto bukti, atau null kalau belum ada / berkasnya sudah dibuang pembersih. */
    public function urlBukti(): ?string
    {
        if (! $this->adaBukti() || $this->bukti_dihapus_pada !== null) {
            return null;
        }

        return Storage::disk('public')->url($this->foto_bukti);
    }
}
