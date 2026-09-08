<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Rpp extends Model
{
    protected $fillable = [
        'user_id',
        'judul_rpp',
        'mata_pelajaran',
        'file_path',
    ];

    /** Guru pemilik RPP ini. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Nama berkas yang enak dibaca saat diunduh.
     *
     * Nama di disk sengaja acak (lihat RppController::store), jadi tanpa ini
     * guru mengunduh berkas bernama "9f2c1a4e....pdf" dan tidak tahu itu RPP
     * yang mana begitu ada tiga di folder Unduhan.
     */
    public function namaUnduhan(): string
    {
        $judul = Str::of($this->judul_rpp)->trim()->limit(60, '')->slug(' ')->title();

        return 'RPP - ' . ($judul ?: 'Dokumen') . '.pdf';
    }
}
