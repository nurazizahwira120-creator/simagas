<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template kosong (berisi header + satu baris contoh) untuk diisi lalu
 * di-upload lagi lewat form Import Siswa. Nama kolom di sini HARUS sama
 * persis dengan yang dibaca SiswaImport::rules()/model().
 */
class SiswaImportTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['2526001', 'Contoh Nama Siswa', 'X RPL 1', '6281234567890', 'wali@contoh.com'],
        ];
    }

    public function headings(): array
    {
        return ['nis', 'nama', 'kelas', 'no_hp_wali', 'email_wali'];
    }
}
