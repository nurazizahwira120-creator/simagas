<?php

namespace App\Models;

use App\Enums\JenisIzinGuru;
use App\Enums\StatusApproval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu baris = satu pengajuan izin seorang guru, untuk satu rentang tanggal.
 *
 * ============ SIKLUS HIDUPNYA ============
 *   1. Guru mengisi form                  -> baris INI dibuat, status Pending
 *   2. Kepala sekolah menyetujui/menolak  -> status_approval berubah
 *   3. Kalau DISETUJUI                    -> absensi_pegawai hari-hari itu
 *                                            ditandai 'izin'
 *                                            (App\Services\PenerapIzinGuru)
 *
 * Selama masih Pending, kehadiran gurunya BELUM berubah sama sekali — dan
 * itu memang keadaan yang benar: belum ada yang mengizinkan.
 * =========================================
 */
class PengajuanIzinGuru extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_izin_gurus';

    protected $fillable = [
        'guru_id',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis_izin',
        'alasan',
        'detail_tugas',
        'file_tugas',
        'status_approval',
        'penyetuju_id',
        'diputuskan_pada',
        'catatan_penyetuju',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jenis_izin' => JenisIzinGuru::class,
            'status_approval' => StatusApproval::class,
            'diputuskan_pada' => 'datetime',
        ];
    }

    /**
     * Guru yang mengajukan.
     *
     * CATATAN kolom: tabel `users` memakai kolom "name" (bawaan Laravel),
     * BUKAN "nama" seperti tabel `siswa` dan `pegawai`. Menyebut kolom yang
     * salah saat eager load relasi ini LOLOS diam-diam di SQLite tapi
     * membalas 500 di MySQL produksi — penjaganya ada di
     * tests/Feature/KolomEagerLoadTest.
     */
    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    /** Atasan yang menyetujui atau menolak. */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penyetuju_id');
    }

    /* ===================== SCOPE ===================== */

    public function scopeMenunggu(Builder $q): Builder
    {
        return $q->where('status_approval', StatusApproval::Pending->value);
    }

    /**
     * Pengajuan yang BERLAKU pada suatu tanggal.
     *
     * Dipakai Live Monitoring untuk menjawab "guru ini kenapa tidak ada?".
     * Dua syaratnya harus berjalan bersama: sudah disetujui DAN tanggalnya
     * masuk rentang. Melewatkan syarat pertama berarti pengajuan yang belum
     * diizinkan siapa pun ikut tampil sebagai izin resmi di layar piket.
     */
    public function scopeBerlakuPada(Builder $q, $tanggal = null): Builder
    {
        $tanggal = $tanggal ?: today();

        return $q->where('status_approval', StatusApproval::Disetujui->value)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal);
    }

    /* ===================== BANTU TAMPILAN ===================== */

    public function jumlahHari(): int
    {
        // +1 karena rentangnya inklusif: izin 10-10 September = satu hari,
        // bukan nol.
        return (int) $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    /** Rentang siap tampil, mis. "10 – 12 September 2026" atau satu tanggal. */
    public function rentangTanggal(): string
    {
        if ($this->tanggal_mulai->isSameDay($this->tanggal_selesai)) {
            return $this->tanggal_mulai->translatedFormat('l, d F Y');
        }

        return $this->tanggal_mulai->translatedFormat('d M')
            . ' – ' . $this->tanggal_selesai->translatedFormat('d M Y');
    }

    public function adaLampiran(): bool
    {
        return filled($this->file_tugas);
    }

    /**
     * Berkas lampirannya masih benar-benar ada di disk?
     *
     * Dipisahkan dari adaLampiran() dengan sengaja: kolomnya terisi berarti
     * "dulu ada berkas yang diunggah", sementara method ini menjawab "hari
     * ini berkasnya masih bisa dibuka". Keduanya bisa berbeda kalau folder
     * storage sempat terhapus saat deploy.
     */
    public function lampiranAda(): bool
    {
        return $this->adaLampiran() && Storage::disk('local')->exists($this->file_tugas);
    }
}
