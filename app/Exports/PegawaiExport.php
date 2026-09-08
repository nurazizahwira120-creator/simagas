<?php

namespace App\Exports;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class PegawaiExport implements FromQuery, WithHeadings, WithMapping
{
    public function query(): Builder
    {
        return Pegawai::query()->with('user')->orderBy('nama');
    }

    public function headings(): array
    {
        return ['nip', 'nama', 'jabatan', 'no_hp', 'email_akun'];
    }

    public function map($pegawai): array
    {
        return [
            $pegawai->nip,
            $pegawai->nama,
            $pegawai->jabatan,
            $pegawai->no_hp,
            $pegawai->user?->email,
        ];
    }
}
