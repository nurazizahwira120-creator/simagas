<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Satu baris = satu laporan bulanan PDF yang sudah dihasilkan Cron Job.
 *
 * Lihat App\Console\Commands\BuatLaporanBulanan untuk pembuatannya dan
 * App\Livewire\Laporan\LaporanBulanan untuk tampilannya di dasbor.
 */
class MonthlyReport extends Model
{
    protected $table = 'monthly_reports';

    protected $fillable = [
        'periode',
        'judul',
        'file_path',
        'jumlah_hari_kerja',
        'jumlah_pegawai',
        'jumlah_siswa',
        'kehadiran_pegawai',
        'kehadiran_siswa',
        'dibuat_pada',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'dibuat_pada' => 'datetime',
            'jumlah_hari_kerja' => 'integer',
            'jumlah_pegawai' => 'integer',
            'jumlah_siswa' => 'integer',
            'kehadiran_pegawai' => 'integer',
            'kehadiran_siswa' => 'integer',
        ];
    }

    /** "Agustus 2026" */
    public function labelPeriode(): string
    {
        return $this->periode->translatedFormat('F Y');
    }

    /**
     * Nama berkas saat diunduh — bukan nama acak di disk.
     *
     * Kepala sekolah bisa mengunduh 12 laporan sekaligus; kalau semuanya
     * bernama "laporan.pdf (1)", "laporan.pdf (2)", folder Unduhan-nya jadi
     * tidak berguna.
     */
    public function namaUnduhan(): string
    {
        return 'Laporan-Kehadiran-' . Str::slug($this->labelPeriode()) . '.pdf';
    }

    /** Berkas PDF-nya masih benar-benar ada di disk? */
    public function berkasAda(): bool
    {
        return filled($this->file_path) && Storage::disk('public')->exists($this->file_path);
    }

    /**
     * Persentase kehadiran pegawai sepanjang bulan itu.
     *
     * Pembaginya jumlah_pegawai x hari kerja. Dikembalikan null kalau
     * pembaginya nol — MENAMPILKAN "0%" untuk bulan yang tidak punya hari
     * kerja sama sekali akan terbaca sebagai "tidak ada yang masuk",
     * padahal artinya "tidak ada yang bisa dihitung".
     */
    public function persenPegawai(): ?float
    {
        $pembagi = $this->jumlah_pegawai * $this->jumlah_hari_kerja;

        return $pembagi > 0 ? round($this->kehadiran_pegawai / $pembagi * 100, 1) : null;
    }

    public function persenSiswa(): ?float
    {
        $pembagi = $this->jumlah_siswa * $this->jumlah_hari_kerja;

        return $pembagi > 0 ? round($this->kehadiran_siswa / $pembagi * 100, 1) : null;
    }
}
