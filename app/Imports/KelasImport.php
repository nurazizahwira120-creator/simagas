<?php

namespace App\Imports;

use App\Enums\UserRole;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Dipanggil sekali per baris yang lolos validasi.
     *
     * ============ SOAL RETURN TYPE ": Model|array|null" ============
     * Tanda tangannya WAJIB persis seperti ini. Sejak maatwebsite/excel 3.1.60
     * interface ToModel mendeklarasikan return type-nya, dan PHP menolak kelas
     * yang tanda tangannya lebih longgar:
     *
     *   Declaration of ...::model(array $row) must be compatible with
     *   Maatwebsite\Excel\Concerns\ToModel::model(array $row):
     *   Illuminate\Database\Eloquent\Model|array|null
     *
     * Itu FatalError saat kelasnya dimuat, bukan saat import dijalankan — jadi
     * halamannya mati total, bukan sekadar importnya gagal. Kalau suatu saat
     * Anda menurunkan versi paketnya ke bawah 3.1.60, return type ini tetap
     * aman: menambah tipe yang lebih ketat dari interface lama yang tidak
     * bertipe selalu diperbolehkan PHP.
     * ================================================================
     */
    public function model(array $row): Model|array|null
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

    /**
     * Menyeragamkan isi baris SEBELUM divalidasi.
     *
     * ============ KENAPA INI WAJIB ADA ============
     * Pembaca Excel/CSV mengembalikan sel yang isinya angka sebagai int/float,
     * bukan string. Padahal NIS, NIP, dan nomor HP semuanya divalidasi dengan
     * aturan 'string'. Akibatnya SETIAP baris ditolak dengan pesan yang
     * membingungkan:
     *
     *   The nis field must be a string.
     *
     * Gejalanya: importnya "berhasil" tanpa error, tapi 0 baris masuk dan
     * semua baris muncul di daftar ditolak. Nyaris tidak ada yang menduga
     * penyebabnya ada di tipe data, bukan di isi filenya.
     *
     * number_format() dipakai untuk float, bukan (string) langsung: NIS
     * belasan digit dibaca sebagai float dan (string) menghasilkan notasi
     * ilmiah "9.9E+15" — yang lolos validasi tapi tersimpan sebagai NIS
     * yang salah, dan itu jauh lebih sulit ditemukan daripada baris ditolak.
     * =============================================
     */
    public function prepareForValidation(array $data, int $index): array
    {
        foreach (['nama_kelas', 'email_wali_kelas'] as $kolom) {
            if (! array_key_exists($kolom, $data) || $data[$kolom] === null) {
                continue;
            }

            $nilai = $data[$kolom];

            if (is_float($nilai)) {
                $nilai = number_format($nilai, 0, '.', '');
            }

            $data[$kolom] = trim((string) $nilai);
        }

        return $data;
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
