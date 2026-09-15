<?php

namespace App\Models;

use App\Enums\JenisAgenda;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu agenda kalender pendidikan, untuk satu rentang tanggal.
 *
 * Dibaca lewat App\Services\KalenderAkademik, bukan langsung dari halaman.
 * Alasannya: "apakah tanggal ini hari KBM" bukan hanya soal tabel ini —
 * pola libur mingguan ada di Pengaturan Sistem, dan menggabungkan keduanya
 * di banyak tempat adalah cara tercepat membuat dua halaman menjawab beda.
 */
class AgendaAkademik extends Model
{
    use HasFactory;

    protected $table = 'agenda_akademik';

    protected $fillable = [
        'judul',
        'tanggal_mulai',
        'tanggal_selesai',
        'jenis',
        'keterangan',
        'sumber',
        'tahun_ajaran',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'jenis' => JenisAgenda::class,
        ];
    }

    /**
     * Pembuatnya.
     *
     * CATATAN kolom: tabel `users` memakai "name" (bawaan Laravel), BUKAN
     * "nama" seperti `siswa` dan `pegawai`. Menyebut kolom yang salah saat
     * eager load LOLOS diam-diam di SQLite tapi membalas 500 di MySQL —
     * penjaganya di tests/Feature/KolomEagerLoadTest.
     */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /* ===================== SCOPE ===================== */

    /**
     * Agenda yang MENYENTUH rentang [$awal, $akhir] — termasuk yang mulai
     * sebelum $awal atau berakhir sesudah $akhir.
     *
     * Dua perbandingannya sengaja "terbalik" (mulai <= akhir DAN selesai >=
     * awal). Itu rumus tumpang-tindih dua rentang, dan menuliskannya
     * terbalik — mulai >= awal DAN selesai <= akhir — menghasilkan bug yang
     * sangat halus: libur akhir semester yang membentang dari Desember ke
     * Januari akan HILANG dari tampilan bulan Januari, karena mulainya ada
     * di bulan sebelumnya.
     */
    public function scopeMenyentuh(Builder $q, $awal, $akhir): Builder
    {
        return $q->whereDate('tanggal_mulai', '<=', $akhir)
            ->whereDate('tanggal_selesai', '>=', $awal);
    }

    /** Agenda yang berlaku pada SATU tanggal. */
    public function scopePada(Builder $q, $tanggal): Builder
    {
        return $q->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal);
    }

    public function scopeLibur(Builder $q): Builder
    {
        return $q->where('jenis', JenisAgenda::Libur->value);
    }

    /* ===================== BANTU TAMPILAN ===================== */

    public function satuHari(): bool
    {
        return $this->tanggal_mulai->isSameDay($this->tanggal_selesai);
    }

    public function jumlahHari(): int
    {
        // +1 karena rentangnya inklusif: agenda 10–10 September = satu hari.
        return (int) $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    public function rentangTanggal(): string
    {
        if ($this->satuHari()) {
            return $this->tanggal_mulai->translatedFormat('l, d F Y');
        }

        // Tahun ikut ditulis di kedua ujung HANYA kalau memang berbeda —
        // "21 Des 2026 – 4 Jan 2027" perlu, "9 – 19 Mar 2027" tidak.
        if ($this->tanggal_mulai->year !== $this->tanggal_selesai->year) {
            return $this->tanggal_mulai->translatedFormat('d M Y')
                . ' – ' . $this->tanggal_selesai->translatedFormat('d M Y');
        }

        return $this->tanggal_mulai->translatedFormat('d M')
            . ' – ' . $this->tanggal_selesai->translatedFormat('d M Y');
    }

    public function dariKaldik(): bool
    {
        return $this->sumber === 'kaldik';
    }
}
