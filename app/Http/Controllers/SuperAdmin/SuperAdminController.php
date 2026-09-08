<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Exports\KelasExport;
use App\Exports\KelasImportTemplateExport;
use App\Exports\PegawaiExport;
use App\Exports\PegawaiImportTemplateExport;
use App\Exports\SiswaExport;
use App\Exports\SiswaImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\KelasImport;
use App\Imports\PegawaiImport;
use App\Imports\SiswaImport;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Validators\ValidationException;

/**
 * Pusat otomatisasi administrasi Master Data untuk Super Admin: Import
 * massal & Export ke Excel untuk tiga entitas inti (Siswa, Pegawai, Kelas).
 *
 * Sengaja TIDAK berisi CRUD manual (tambah/edit/hapus satu-per-satu) —
 * itu sudah ditangani masing-masing oleh SiswaController, PegawaiController,
 * dan KelasController di bawah panel /super-admin/*. Controller ini murni
 * fokus pada operasi MASSAL: upload satu file Excel -> ratusan baris
 * langsung ter-insert, atau sebaliknya, unduh seluruh data ke Excel.
 */
class SuperAdminController extends Controller
{
    private const MAKS_UKURAN_FILE_KB = 10240; // 10 MB

    /**
     * Halaman "Master Data" — tiga kartu (Siswa/Pegawai/Kelas), masing-
     * masing dengan tombol unduh template, form upload/import, dan tombol
     * export data yang sudah ada.
     */
    public function index()
    {
        return view('super-admin.master-data.index', [
            'daftarEntitas' => [
                [
                    'kunci' => 'siswa',
                    'label' => 'Siswa',
                    'icon' => 'identification',
                    'total' => Siswa::count(),
                    'kolom' => 'nis, nama, kelas, no_hp_wali (opsional), email_wali (opsional)',
                ],
                [
                    'kunci' => 'pegawai',
                    'label' => 'Pegawai',
                    'icon' => 'briefcase',
                    'total' => Pegawai::count(),
                    'kolom' => 'nip, nama, jabatan, no_hp (opsional), email_akun (opsional)',
                ],
                [
                    'kunci' => 'kelas',
                    'label' => 'Kelas',
                    'icon' => 'academic-cap',
                    'total' => Kelas::count(),
                    'kolom' => 'nama_kelas, email_wali_kelas (opsional)',
                ],
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Siswa
    // ------------------------------------------------------------------

    public function templateSiswa()
    {
        return Excel::download(new SiswaImportTemplateExport(), 'template-import-siswa.xlsx');
    }

    public function exportSiswa()
    {
        return Excel::download(new SiswaExport(), 'data-siswa.xlsx');
    }

    /**
     * Terima file Excel/CSV, lalu loop tiap barisnya lewat SiswaImport
     * (ToModel) untuk langsung di-insert ke tabel siswa secara batch.
     * Baris yang gagal (NIS dobel, kelas tidak ditemukan, dst) di-skip dan
     * dilaporkan balik, tidak menggagalkan baris lain yang valid.
     */
    public function importSiswa(Request $request): RedirectResponse
    {
        return $this->prosesImport($request, new SiswaImport(), 'siswa', 'siswa');
    }

    // ------------------------------------------------------------------
    // Pegawai
    // ------------------------------------------------------------------

    public function templatePegawai()
    {
        return Excel::download(new PegawaiImportTemplateExport(), 'template-import-pegawai.xlsx');
    }

    public function exportPegawai()
    {
        return Excel::download(new PegawaiExport(), 'data-pegawai.xlsx');
    }

    public function importPegawai(Request $request): RedirectResponse
    {
        return $this->prosesImport($request, new PegawaiImport(), 'pegawai', 'pegawai');
    }

    // ------------------------------------------------------------------
    // Kelas
    // ------------------------------------------------------------------

    public function templateKelas()
    {
        return Excel::download(new KelasImportTemplateExport(), 'template-import-kelas.xlsx');
    }

    public function exportKelas()
    {
        return Excel::download(new KelasExport(), 'data-kelas.xlsx');
    }

    public function importKelas(Request $request): RedirectResponse
    {
        return $this->prosesImport($request, new KelasImport(), 'kelas', 'kelas');
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    /**
     * Alur import yang sama persis dipakai untuk ketiga entitas: validasi
     * file yang diupload, jalankan importer-nya (yang di dalamnya me-loop
     * & meng-insert per baris secara batch — lihat masing-masing class di
     * app/Imports/), lalu kumpulkan ringkasan hasilnya (berhasil vs
     * dilewati) untuk ditampilkan kembali di halaman Master Data.
     *
     * @param  \Maatwebsite\Excel\Concerns\ToModel&\Maatwebsite\Excel\Concerns\SkipsOnFailure&\Maatwebsite\Excel\Concerns\SkipsOnError  $import
     */
    private function prosesImport(Request $request, $import, string $kunciEntitas, string $namaSatuan): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:' . self::MAKS_UKURAN_FILE_KB],
        ]);

        try {
            Excel::import($import, $request->file('file'));
        } catch (ValidationException $e) {
            // Jaga-jaga untuk kegagalan validasi di luar SkipsOnFailure
            // (mis. konfigurasi validasi yang tidak sinkron) — seharusnya
            // jarang sampai ke sini karena semua importer sudah pakai
            // SkipsOnFailure supaya baris bermasalah di-skip, bukan
            // menggagalkan seluruh proses import.
            return back()->withErrors(['file' => 'File Excel tidak valid: ' . $e->getMessage()]);
        }

        $gagal = collect($import->failures())
            ->map(fn ($f) => [
                'baris' => $f->row(),
                'pesan' => implode(' ', $f->errors()),
            ])
            ->concat(
                collect($import->errors())->map(fn ($e) => [
                    'baris' => null,
                    'pesan' => $e->getMessage(),
                ])
            )
            ->values();

        $pesan = "Import selesai: {$import->jumlahDiproses} {$namaSatuan} berhasil ditambahkan";
        $pesan .= $gagal->isNotEmpty()
            ? ", {$gagal->count()} baris dilewati (lihat rincian di bawah)."
            : '.';

        return redirect()
            ->route($this->panelPrefix() . '.master-data.index')
            ->with('status', $pesan)
            ->with('importGagalEntitas', $kunciEntitas)
            ->with('importGagal', $gagal);
    }
}
