<?php

namespace App\Exports;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class KelasExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Kelas::query()->with('waliKelas')->withCount('siswa')->orderBy('nama_kelas');
    }

    public function headings(): array
    {
        return ['nama_kelas', 'email_wali_kelas', 'jumlah_siswa'];
    }

    public function map($kelas): array
    {
        return [
            $kelas->nama_kelas,
            $kelas->waliKelas?->email,
            $kelas->siswa_count,
        ];
    }
}
