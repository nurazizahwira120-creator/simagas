<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Kenaikan Kelas — memindahkan siswa dari satu kelas ke kelas lain secara
 * massal, dengan pilihan per siswa.
 *
 * Siswa yang TIDAK dicentang sengaja dibiarkan di kelas lama (kasus tinggal
 * kelas). Jadi tombolnya memindahkan "yang dicentang saja", bukan seluruh
 * isi kelas — perilaku ini ditulis jelas di antarmukanya supaya tidak ada
 * kejutan pada operasi yang mengubah banyak data sekaligus.
 */
class KenaikanKelasController extends Controller
{
    public function index(Request $request)
    {
        $kelasAsalId = $request->query('kelas_asal') ?: null;

        $kelasAsal = $kelasAsalId
            ? Kelas::find($kelasAsalId)
            : null;

        $siswa = $kelasAsal
            ? Siswa::where('kelas_id', $kelasAsal->id)->orderBy('nama')->get()
            : collect();

        return view('super-admin.kenaikan-kelas', [
            'daftarKelas' => Kelas::withCount('siswa')->orderBy('nama_kelas')->get(),
            'kelasAsal' => $kelasAsal,
            'siswa' => $siswa,
        ]);
    }

    public function proses(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kelas_asal' => ['required', Rule::exists('kelas', 'id')],
            'kelas_tujuan' => ['required', Rule::exists('kelas', 'id'), 'different:kelas_asal'],
            'siswa' => ['required', 'array', 'min:1'],
            'siswa.*' => [Rule::exists('siswa', 'id')],
        ], [
            'kelas_tujuan.different' => 'Kelas tujuan harus berbeda dari kelas asal.',
            'siswa.required' => 'Centang minimal satu siswa yang akan dinaikkan.',
        ]);

        // Hanya siswa yang BENAR-BENAR ada di kelas asal yang boleh dipindah.
        // Tanpa penyaringan ini, id siswa dari kelas lain bisa diselipkan
        // lewat request yang diubah manual dan ikut terpindahkan.
        $idSah = Siswa::where('kelas_id', $validated['kelas_asal'])
            ->whereIn('id', $validated['siswa'])
            ->pluck('id');

        if ($idSah->isEmpty()) {
            return back()->withErrors([
                'siswa' => 'Tidak ada siswa sah yang bisa dipindahkan dari kelas tersebut.',
            ]);
        }

        $tujuan = Kelas::find($validated['kelas_tujuan']);

        DB::transaction(function () use ($idSah, $validated) {
            Siswa::whereIn('id', $idSah)->update(['kelas_id' => $validated['kelas_tujuan']]);
        });

        return redirect()
            ->route($this->panelPrefix() . '.kenaikan-kelas', ['kelas_asal' => $validated['kelas_asal']])
            ->with('status', $idSah->count() . ' siswa berhasil dinaikkan ke kelas ' . $tujuan->nama_kelas . '.');
    }
}
