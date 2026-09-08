<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PegawaiImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['198501012010011001', 'Contoh Nama Pegawai', 'Guru Mapel', '6281234567890', 'pegawai@contoh.com'],
        ];
    }

    public function headings(): array
    {
        return ['nip', 'nama', 'jabatan', 'no_hp', 'email_akun'];
    }
}
