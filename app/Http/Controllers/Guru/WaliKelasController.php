<?php

namespace App\Http\Controllers\Guru;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsensiSiswa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WaliKelasController extends Controller
{
    /**
     * Dashboard wali kelas: daftar siswa di kelas yang ia ampu, lengkap
     * dengan status absensi hari ini (hasil scan piket kalau sudah ada).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $kelas = $user->kelasWali()
            ->with([
                'siswa' => fn ($query) => $query->orderBy('nama'),
                'siswa.absensi' => fn ($query) => $query->whereDate('tanggal', today()),
            ])
            ->first();

        return view('wali-kelas.dashboard', [
            'user' => $user,
            'kelas' => $kelas,
            'statusOptions' => AbsensiStatus::cases(),
        ]);
    }

    /**
     * Upsert absensi manual: satu submit menyimpan status semua siswa di
     * kelas sekaligus. Kalau siswa itu sudah punya baris absensi hari ini
     * (mis. dari hasil scan piket), statusnya di-update; kalau belum,
     * dibuatkan baru. `jam_masuk` yang berasal dari scan tidak ikut ditimpa.
     */
    public function simpanAbsensi(Request $request): RedirectResponse
    {
        $user = $request->user();
        $kelas = $user->kelasWali;

        abort_if(! $kelas, 403, 'Anda belum ditugaskan sebagai wali kelas mana pun.');

        $statusValid = implode(',', array_column(AbsensiStatus::cases(), 'value'));

        $validated = $request->validate([
            'absensi' => ['required', 'array', 'min:1'],
            'absensi.*' => ['required', 'string', "in:{$statusValid}"],
        ]);

        // Hanya siswa yang benar-benar ada di kelas ini yang boleh diubah —
        // mencegah id siswa dari kelas lain "diselundupkan" lewat request
        // yang dimodifikasi manual.
        $siswaIdsDiKelasIni = $kelas->siswa()->pluck('id')->all();

        $rows = collect($validated['absensi'])
            ->only($siswaIdsDiKelasIni)
            ->map(function (string $status, int $siswaId) {
                return [
                    'siswa_id' => $siswaId,
                    'tanggal' => today(),
                    'status' => $status,
                    'jam_masuk' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->values()
            ->all();

        if (! empty($rows)) {
            AbsensiSiswa::upsert(
                $rows,
                uniqueBy: ['siswa_id', 'tanggal'],
                update: ['status', 'updated_at'],
            );
        }

        return back()->with('status', 'Absensi kelas berhasil disimpan.');
    }
}
