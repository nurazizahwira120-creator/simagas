<?php

namespace App\Models;

use App\Enums\StatusKbm;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = kehadiran satu siswa pada satu jam pelajaran, satu tanggal.
 *
 * Diisi guru lewat App\Livewire\Guru\JurnalAbsenKelas dan dibaca orang tua
 * lewat App\Livewire\WaliMurid\PantauanKbm.
 */
class AbsensiKbmSiswa extends Model
{
    use HasFactory;

    protected $table = 'absensi_kbm_siswa';

    protected $fillable = [
        'jadwal_id',
        'siswa_id',
        'tanggal',
        'status',
        'keterangan',

        // Akun yang mengisi (users.id). NULL pada data lama = diisi guru
        // jadwalnya sendiri. Lihat migration 000040.
        'diisi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => StatusKbm::class,
        ];
    }

    public function jadwal(): BelongsTo
    {
        return $this->belongsTo(JadwalPelajaran::class, 'jadwal_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    /** Akun yang mengisi absensi ini — guru jadwalnya, atau pengganti. */
    public function pengisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diisi_oleh');
    }
}
