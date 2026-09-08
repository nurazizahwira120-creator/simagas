<?php

namespace App\Imports;

use App\Models\Pegawai;
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
 * Import massal data pegawai dari file Excel/CSV.
 * Kolom: nip, nama, jabatan, no_hp (opsional), email_akun (opsional).
 *
 * Lihat SiswaImport untuk penjelasan lengkap pola loop + batch insert-nya —
 * strukturnya sengaja dibuat identik supaya konsisten & gampang dirawat.
 */
class PegawaiImport implements
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

        // email_akun opsional — kalau kosong, tidak ditemukan, atau akun itu
        // sudah ditautkan ke pegawai lain (unique di kolom pegawai.user_id),
        // pegawai tetap dibuat tanpa akun login. Bisa ditautkan belakangan
        // lewat form edit di menu Pegawai.
        $userId = null;
        if (! empty($row['email_akun'])) {
            $user = User::where('email', $row['email_akun'])->first();

            if ($user && ! Pegawai::where('user_id', $user->id)->exists()) {
                $userId = $user->id;
            }
        }

        return new Pegawai([
            'nip' => filled($row['nip'] ?? null) ? (string) $row['nip'] : null,
            'nama' => trim((string) $row['nama']),
            'jabatan' => trim((string) $row['jabatan']),
            'no_hp' => $row['no_hp'] ?? null,
            'user_id' => $userId,
        ]);
    }

    public function rules(): array
    {
        return [
            // NIP opsional — lihat catatan di App\Livewire\Auth\Register.
            // 'distinct' tetap dipasang supaya NIP yang DIISI tidak dobel di
            // dalam satu file; baris tanpa NIP tidak saling dianggap kembar.
            'nip' => ['nullable', 'string', 'max:50', 'distinct', Rule::unique('pegawai', 'nip')],
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'email_akun' => ['nullable', 'email'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nip.distinct' => 'NIP :input dobel di dalam file ini sendiri (baris lain juga memakainya).',
            'nip.unique' => 'NIP :input sudah terdaftar di database.',
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
