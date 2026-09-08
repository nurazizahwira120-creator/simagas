<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Support\Collection;
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
 * Import massal data siswa dari file Excel/CSV.
 *
 * Kolom yang diharapkan (baris pertama = header, lihat SiswaImportTemplateExport
 * untuk contoh file-nya): nis, nama, kelas, no_hp_wali (opsional), email_wali
 * (opsional).
 *
 * Cara kerja singkat:
 *  - WithHeadingRow  -> baris pertama dipakai sebagai nama kolom ($row['nis'], dst).
 *  - WithValidation  -> setiap baris divalidasi dulu SEBELUM masuk ke model().
 *    Baris yang gagal validasi otomatis di-skip (lewat SkipsOnFailure), tidak
 *    menggagalkan baris lain di file yang sama.
 *  - ToModel::model() -> dipanggil sekali per baris yang lolos validasi. Inilah
 *    "loop" yang meng-insert tiap baris ke tabel siswa.
 *  - WithChunkReading + WithBatchInserts -> file dibaca & di-INSERT per 200
 *    baris sekaligus (bukan satu query per baris), supaya import ratusan baris
 *    tetap cepat ("instan") dan tidak membebani memori untuk file besar.
 */
class SiswaImport implements
    ToModel,
    WithHeadingRow,
    WithValidation,
    WithBatchInserts,
    WithChunkReading,
    SkipsOnError,
    SkipsOnFailure
{
    use SkipsErrors, SkipsFailures;

    /** Berapa baris yang berhasil dipetakan & diantrekan untuk di-insert. */
    public int $jumlahDiproses = 0;

    /** Cache "nama_kelas" -> id, supaya tidak query ulang di setiap baris. */
    private Collection $petaKelas;

    public function __construct()
    {
        $this->petaKelas = Kelas::pluck('id', 'nama_kelas');
    }

    public function model(array $row)
    {
        $this->jumlahDiproses++;

        $kelasId = $this->petaKelas->get(trim((string) $row['kelas']));

        // email_wali bersifat opsional — kalau kosong atau akunnya tidak
        // ditemukan, siswa tetap dibuat tanpa wali_murid_id (bisa ditautkan
        // belakangan lewat form edit di menu Siswa).
        $waliMuridId = null;
        if (! empty($row['email_wali'])) {
            $waliMuridId = User::query()
                ->where('email', $row['email_wali'])
                ->where('role', UserRole::WaliMurid)
                ->value('id');
        }

        return new Siswa([
            'nis' => (string) $row['nis'],
            'nama' => trim((string) $row['nama']),
            'no_hp_wali' => $row['no_hp_wali'] ?? null,
            'kelas_id' => $kelasId,
            'wali_murid_id' => $waliMuridId,
        ]);
    }

    /**
     * Validasi PER BARIS. 'distinct' menolak NIS yang dobel di dalam file
     * itu sendiri; Rule::unique menolak NIS yang sudah ada di database.
     * Kolom "kelas" harus persis sama dengan salah satu nama_kelas yang
     * sudah terdaftar di menu Kelas — kalau belum ada, buat dulu kelasnya
     * (atau import file Kelas-nya duluan) sebelum import siswa.
     */
    public function rules(): array
    {
        return [
            'nis' => ['required', 'string', 'max:50', 'distinct', Rule::unique('siswa', 'nis')],
            'nama' => ['required', 'string', 'max:255'],
            'kelas' => ['required', 'string', Rule::in($this->petaKelas->keys()->all())],
            'no_hp_wali' => ['nullable', 'string', 'max:20'],
            'email_wali' => ['nullable', 'email'],
        ];
    }

    public function customValidationMessages(): array
    {
        return [
            'nis.distinct' => 'NIS :input dobel di dalam file ini sendiri (baris lain juga memakainya).',
            'nis.unique' => 'NIS :input sudah terdaftar di database.',
            'kelas.in' => 'Kelas ":input" tidak ditemukan. Pastikan namanya sama persis dengan menu Kelas.',
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
