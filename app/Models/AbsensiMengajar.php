<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Satu baris = satu kali guru masuk kelas dan men-scan stiker QR ruangan.
 *
 * Berbeda dengan AbsensiPegawai yang hanya satu baris per hari, tabel ini
 * bisa berisi banyak baris per hari untuk orang yang sama — satu per jam
 * mengajar.
 *
 * ============ SIKLUS HIDUP SATU SESI MENGAJAR ============
 *   1. Guru absen kedatangan (Radius GPS) -> AbsensiPegawai hari ini terisi
 *   2. Guru scan QR ruangan               -> baris INI dibuat, waktu_mulai terisi
 *   3. Guru mengisi jurnal & absensi      -> AbsensiKbmSiswa terisi
 *   4. Guru unggah foto bukti mengajar    -> foto_bukti terisi
 *   5. Guru menekan "Akhiri Sesi Kelas"   -> waktu_selesai terisi
 *
 * Langkah 5 TIDAK BISA dilakukan tanpa langkah 4, dan langkah 3 tidak bisa
 * dilakukan tanpa 1 dan 2. Itulah inti "validasi silang": sesi yang tercatat
 * selesai berarti ada jejak kehadiran, jejak ruangan, DAN bukti visualnya —
 * bukan sekadar klaim seseorang yang menekan tombol.
 * =========================================================
 */
class AbsensiMengajar extends Model
{
    use HasFactory;

    protected $table = 'absensi_mengajar';

    protected $fillable = [
        'user_id',
        'kode_kelas',
        'foto_bukti',
        'waktu_mulai',
        'waktu_selesai',
    ];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Sudah ada foto bukti mengajar yang tersimpan? */
    public function adaBukti(): bool
    {
        return filled($this->foto_bukti);
    }

    /** Sesi ini sudah diakhiri guru? */
    public function sudahSelesai(): bool
    {
        return $this->waktu_selesai !== null;
    }

    /**
     * Durasi sesi dalam menit, atau null kalau belum diakhiri.
     *
     * Dipakai laporan bulanan. Dibulatkan ke menit karena satuan yang lebih
     * halus tidak berarti apa-apa untuk satu jam pelajaran.
     */
    public function durasiMenit(): ?int
    {
        if (! $this->waktu_selesai || ! $this->waktu_mulai) {
            return null;
        }

        return max(0, (int) $this->waktu_mulai->diffInMinutes($this->waktu_selesai));
    }

    /**
     * URL foto bukti untuk <img src="...">, atau null.
     *
     * Jalurnya dibuat RELATIF — sama seperti User::getFotoUrlAttribute().
     * Storage::url() merakit URL dari APP_URL di .env; kalau nilainya tidak
     * sama persis dengan alamat yang dipakai membuka aplikasi, SEMUA foto
     * tampil rusak dengan gejala yang menyesatkan: seolah unggahannya gagal
     * padahal berkasnya ada.
     */
    public function urlBukti(): ?string
    {
        if (! $this->foto_bukti) {
            return null;
        }

        $url = Storage::disk('public')->url($this->foto_bukti);

        return parse_url($url, PHP_URL_PATH) ?: $url;
    }
}
