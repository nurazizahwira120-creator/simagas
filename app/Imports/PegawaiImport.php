<?php

namespace App\Imports;

use App\Models\Pegawai;
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
        foreach (['nip', 'nama', 'jabatan', 'no_hp', 'email_akun'] as $kolom) {
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
