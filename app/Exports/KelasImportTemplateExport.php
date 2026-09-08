<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class KelasImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['X RPL 2', 'wali.kelas@contoh.com'],
        ];
    }

    public function headings(): array
    {
        return ['nama_kelas', 'email_wali_kelas'];
    }
}
