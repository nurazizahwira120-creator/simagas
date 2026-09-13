<?php

namespace App\Models;

use App\Enums\JenisIzin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu baris = satu izin siswa yang dicatat petugas piket/admin di gerbang.
 *
 * ============ SIKLUS HIDUP SATU IZIN ============
 *   1. Orang tua menelepon / menitip surat ke gerbang
 *   2. Petugas mencatatnya di halaman Scan Gerbang  -> baris INI dibuat
 *   3. Status hariannya disisipkan ke `absensi_siswa` (izin/sakit)
 *   4. Guru mata pelajaran membuka Jurnal & Absen Kelas -> status itu
 *      sudah TERISI otomatis, tidak perlu ditanyakan ulang
 *
 * Langkah 3 dan 4 itulah yang membuat fitur ini "satu pintu": petugas
 * mencatat SEKALI, dan seluruh guru di hari itu melihat hasilnya tanpa
 * seorang pun perlu meneruskan kabar.
 * ================================================
 */
class PencatatanIzin extends Model
{
    use HasFactory;

    protected $table = 'pencatatan_izins';

    protected $fillable = [
        'siswa_id',
        'petugas_id',
        'tanggal',
        'status',
        'keterangan',
        'foto_surat',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => JenisIzin::class,
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class, 'siswa_id');
    }

    /**
     * Akun petugas yang mencatat izin ini.
     *
     * CATATAN kolom: tabel `users` memakai kolom "name" (bawaan Laravel),
     * BUKAN "nama" seperti tabel `siswa` dan `pegawai`. Menyebut kolom yang
     * salah saat eager load relasi ini LOLOS diam-diam di SQLite tapi
     * membalas 500 di MySQL produksi. Itu sudah pernah terjadi di project ini
     * pada halaman Persetujuan Rapor; penjaganya ada di
     * tests/Feature/KolomEagerLoadTest.
     */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'petugas_id');
    }

    public function adaSurat(): bool
    {
        return filled($this->foto_surat);
    }

    /**
     * Berkas suratnya masih benar-benar ada di disk?
     *
     * Dipisahkan dari adaSurat() dengan sengaja: kolomnya terisi berarti
     * "dulu ada surat yang diunggah", sementara method ini menjawab "hari ini
     * berkasnya masih bisa dibuka". Keduanya bisa berbeda kalau folder
     * storage sempat terhapus saat deploy.
     */
    public function suratAda(): bool
    {
        return $this->adaSurat() && Storage::disk('local')->exists($this->foto_surat);
    }
}
