<?php

namespace App\Models;

use App\Enums\AbsensiStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbsensiPegawai extends Model
{
    use HasFactory;

    protected $table = 'absensi_pegawai';

    protected $fillable = [
        'pegawai_id',
        'tanggal',
        'jam_masuk',
        'status',
        'keterangan',

        // Diisi lewat halaman Pengajuan Izin (App\Livewire\Pegawai\FormIzin).
        // Lihat migration 000019 untuk alasan `alasan` dipisah dari
        // `keterangan` yang sudah ada.
        'alasan',
        'bukti_foto',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'jam_masuk' => 'datetime:H:i',
            'status' => AbsensiStatus::class,
        ];
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pegawai_id');
    }

    /**
     * URL bukti untuk ditampilkan browser, atau null kalau tidak ada.
     *
     * Dibuat sebagai accessor, bukan Storage::url() yang dipanggil di view:
     * kalau `php artisan storage:link` belum dijalankan, berkasnya memang
     * tidak akan terlihat — dan satu-satunya tempat yang perlu diperbaiki
     * nanti cukup di sini, bukan di setiap halaman yang menampilkannya.
     */
    public function getBuktiUrlAttribute(): ?string
    {
        if (! $this->bukti_foto) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->bukti_foto);
    }
}
