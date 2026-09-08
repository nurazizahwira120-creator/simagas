<?php

namespace App\Exports;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Ekspor seluruh data siswa ke Excel. Dipakai dari menu Master Data (Super
 * Admin) — bukan untuk backup database, tapi supaya data bisa dibuka/diedit
 * di Excel lalu di-import ulang kalau perlu perubahan massal.
 */
class SiswaExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Siswa::query()->with(['kelas', 'waliMurid'])->orderBy('nama');
    }

    public function headings(): array
    {
        return ['nis', 'nama', 'kelas', 'no_hp_wali', 'email_wali'];
    }

    public function map($siswa): array
    {
        return [
            $siswa->nis,
            $siswa->nama,
            $siswa->kelas?->nama_kelas,
            $siswa->no_hp_wali,
            $siswa->waliMurid?->email,
        ];
    }
}
