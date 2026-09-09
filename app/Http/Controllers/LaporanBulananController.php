<?php

namespace App\Http\Controllers;

use App\Livewire\Laporan\LaporanBulanan;
use App\Models\MonthlyReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengalirkan berkas PDF laporan bulanan ke pengunduh.
 *
 * ============ KENAPA LEWAT CONTROLLER, BUKAN asset('storage/...') ============
 * Menautkan langsung ke /storage/laporan-bulanan/xxx.pdf terlihat lebih
 * sederhana, tapi punya dua masalah nyata di hosting ini:
 *
 *   1. TIDAK ADA PENJAGAAN. Berkas di bawah public/storage bisa diunduh
 *      siapa pun yang tahu (atau menebak) alamatnya — termasuk orang yang
 *      belum login. Rekap kehadiran seluruh siswa & pegawai sekolah bukan
 *      dokumen publik.
 *   2. SYMLINK public/storage RAPUH. Di server ini folder aplikasi dan
 *      public_html terpisah; symlink hasil `storage:link` gampang hilang
 *      saat berkas disalin ulang, dan gejalanya 404 untuk berkas yang
 *      sebenarnya ada di disk.
 *
 * Controller ini membaca berkasnya langsung dari disk 'public' lewat
 * Storage, jadi symlink-nya tidak diperlukan sama sekali, dan hak aksesnya
 * diperiksa lebih dulu.
 * ============================================================================
 */
class LaporanBulananController extends Controller
{
    public function unduh(Request $request, MonthlyReport $laporan): StreamedResponse
    {
        // Peran diperiksa ULANG di sini, bukan hanya diandalkan pada
        // middleware rute. Rute ini didaftarkan di dua grup (kepsek dan
        // super-admin), dan cukup satu grup baru yang lupa memasang
        // middleware-nya untuk membuka seluruh laporan sekolah.
        abort_unless(
            in_array($request->user()?->role, LaporanBulanan::PERAN_BOLEH, true),
            403,
            'Halaman ini hanya untuk Kepala Sekolah dan Super Admin.',
        );

        $disk = Storage::disk('public');

        abort_unless(
            filled($laporan->file_path) && $disk->exists($laporan->file_path),
            404,
            'Berkas laporan tidak ditemukan di server. Laporan ini mungkin perlu dibuat ulang.',
        );

        // inline = dibuka di viewer PDF browser; attachment = dipaksa
        // terunduh. Bawaannya attachment, sesuai tombol "Download PDF".
        $mode = $request->boolean('lihat') ? 'inline' : 'attachment';
        $nama = $laporan->namaUnduhan();

        return $disk->response($laporan->file_path, $nama, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $mode . '; filename="' . $nama . '"',

            // Laporan bulan yang sama bisa dibuat ulang dengan --paksa;
            // tanpa baris ini browser bisa menyajikan versi lama dari cache
            // dan kepala sekolah mengira laporannya tidak berubah.
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
