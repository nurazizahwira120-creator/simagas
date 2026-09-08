<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

/**
 * Import massal data kelas dari file Excel/CSV.
 * Kolom: nama_kelas, email_wali_kelas (opsional).
 *
 * Lihat SiswaImport untuk penjelasan lengkap pola loop + batch insert-nya.
 */
class KelasImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    WithBatchInserts,
    WithChunkReading,
    SkipsOnError,
    SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    public int $jumlahDiproses = 0;

    public function model(array $row)
    {
        $this->jumlahDiproses++;

        // email_wali_kelas opsional — kalau kosong atau akunnya tidak
        // ditemukan (harus akun dengan role wali_kelas), kelas tetap dibuat
        // tanpa wali kelas. Bisa ditautkan belakangan lewat form edit.
        $waliKelasId = null;
        if (! empty($row['email_wali_kelas'])) {
            $waliKelasId = User::query()
                ->where('email', $row['email_wali_kelas'])
                ->where('role', UserRole::WaliKelas)
                ->value('id');
        }

        return new Kelas([
            'nama_kelas' => trim((string) $row['nama_kelas']),
            'wali_kelas_id' => $waliKelasId,
        ]);
    }

    public function rules(): array
    {
        return [
            'nama_kelas' => ['required', 'string', 'max:255', 'distinct', Rule::unique('kelas', 'nama_kelas')],
            'email_wali_kelas' => ['nullable', 'email'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nama_kelas.distinct' => 'Nama kelas ":input" dobel di dalam file ini sendiri.',
            'nama_kelas.unique' => 'Kelas ":input" sudah ada di database.',
        ];
    }

    public function batchSize(): int
    {
        return 200;
    }

    public function chunkSize(): int
    {
        return 200;
    }
}
