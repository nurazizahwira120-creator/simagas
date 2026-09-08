<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JadwalEkskul extends Model
{
    /**
     * $guarded = [] membuka mass assignment untuk SEMUA kolom. Aman di sini
     * karena satu-satunya jalan masuk adalah App\Livewire\Ekskul\KelolaEkskul,
     * dan yang disimpan bukan request mentah melainkan hasil validate().
     */
    protected $guarded = [];

    /**
     * TIDAK ada cast ke datetime untuk jam_mulai / jam_selesai — disengaja.
     * Kolom TIME menghasilkan "07:30:00", dan cast 'datetime' memaksa Carbon
     * mengurainya sebagai tanggal: di MySQL jadi tanggal hari ini jam segitu
     * (kelihatan benar sampai ada perbandingan tanggal), di SQLite melempar
     * InvalidFormatException dan halamannya mati.
     */
    protected function casts(): array
    {
        return [];
    }

    /* ===================== RELASI ===================== */

    /** Pembina ekskul — satu baris di tabel pegawai. */
    public function pembina(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'pembina_id');
    }

    /**
     * Siswa yang tergabung, lewat pivot ekskul_siswa.
     *
     * withTimestamps() dipasang supaya kolom created_at/updated_at di pivot
     * benar-benar terisi saat attach(). Tanpa itu kolomnya ada tapi selalu
     * NULL, dan pertanyaan "sejak kapan anak ini ikut" tidak bisa dijawab.
     */
    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(Siswa::class, 'ekskul_siswa', 'jadwal_ekskul_id', 'siswa_id')
            ->withTimestamps();
    }

    /** Seluruh catatan kehadiran pertemuan ekskul ini. */
    public function absensi(): HasMany
    {
        return $this->hasMany(AbsensiEkskul::class, 'jadwal_ekskul_id');
    }

    /* ===================== TAMPILAN ===================== */

    /**
     * Nama pembina yang siap ditampilkan.
     *
     * Membaca DUA sumber: relasi pegawai (jalur normal) dan kolom
     * `pembina_luar` (nama teks dari sebelum ada relasi, atau pelatih dari
     * luar sekolah yang tidak punya baris pegawai). Tanpa cadangan kedua itu,
     * jadwal lama yang namanya tidak cocok saat migrasi akan tampil kosong —
     * terlihat seperti data hilang, padahal namanya masih tersimpan.
     */
    public function namaPembina(): string
    {
        /*
         | getRelationValue(), BUKAN $this->pembina.
         |
         | Sebelum migration 000024 dijalankan, tabelnya masih punya KOLOM
         | bernama `pembina` berisi teks — dan Eloquent mendahulukan atribut
         | di atas relasi, jadi $this->pembina mengembalikan string. Baris
         | berikutnya lalu membaca ->nama pada string itu dan halamannya mati
         | dengan TypeError. getRelationValue() hanya melihat relasi, jadi
         | model ini aman di kedua bentuk skema.
         */
        if ($this->pembina_id !== null) {
            $orang = $this->getRelationValue('pembina');

            if ($orang instanceof Pegawai && filled($orang->nama)) {
                return $orang->nama;
            }
        }

        // Cadangan: nama teks dari sebelum ada relasi (`pembina_luar`), atau
        // kolom `pembina` lama kalau migrationnya memang belum dijalankan.
        foreach (['pembina_luar', 'pembina'] as $kolom) {
            $teks = $this->getAttributeValue($kolom);

            if (is_string($teks) && filled($teks)) {
                return $teks;
            }
        }

        return '-';
    }

    /** True kalau pembinanya masih nama teks, belum ditautkan ke pegawai. */
    public function pembinaBelumTertaut(): bool
    {
        return $this->pembina_id === null && $this->namaPembina() !== '-';
    }

    /** "07:30:00" -> "07:30". Aman untuk nilai yang sudah "07:30". */
    public static function jam(?string $waktu): string
    {
        return $waktu ? substr($waktu, 0, 5) : '-';
    }

    public function rentangJam(): string
    {
        return self::jam($this->jam_mulai) . ' – ' . self::jam($this->jam_selesai);
    }

    public function durasiMenit(): int
    {
        [$h1, $m1] = array_pad(explode(':', (string) $this->jam_mulai), 2, 0);
        [$h2, $m2] = array_pad(explode(':', (string) $this->jam_selesai), 2, 0);

        return max(0, ((int) $h2 * 60 + (int) $m2) - ((int) $h1 * 60 + (int) $m1));
    }
}
