<?php

namespace App\Http\Controllers;

use App\Models\PengajuanIzinGuru;
use App\Services\TugasInval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Lampiran tugas IDT untuk GURU INVAL (dan penunjuknya) dari halaman Guru Inval.
 *
 * Rute ini terdaftar di grup setiap peran yang bisa menjadi inval, jadi
 * middleware hanya memastikan "yang membuka boleh membuka halaman Guru
 * Inval". Hak atas BERKAS INI diperiksa di sini lewat
 * TugasInval::bolehBukaLampiran(): hanya inval yang benar-benar ditunjuk
 * menggantikan guru itu, di dalam rentang izinnya. Tanpa pemeriksaan ini,
 * guru mana pun bisa membuka soal ujian guru lain dengan menebak id.
 */
class GuruInvalLampiranController extends Controller
{
    public function __invoke(Request $request, PengajuanIzinGuru $izin, TugasInval $tugas): StreamedResponse
    {
        abort_unless($tugas->bolehBukaLampiran($request->user(), $izin), 403, 'Anda tidak ditugaskan menggantikan guru ini.');
        abort_unless($izin->lampiranAda(), 404, 'Berkas lampirannya tidak ditemukan.');

        $namaUnduh = Str::slug(sprintf(
            'tugas-%s-%s',
            $izin->jenis_izin->kode(),
            $izin->tanggal_mulai->format('Y-m-d'),
        )) . '.' . pathinfo($izin->file_tugas, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($izin->file_tugas, $namaUnduh, [
            'Content-Disposition' => 'inline; filename="' . $namaUnduh . '"',
        ]);
    }
}
