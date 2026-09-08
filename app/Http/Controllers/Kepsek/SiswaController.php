<?php

namespace App\Http\Controllers\Kepsek;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $kelasId = $request->query('kelas_id');

        $siswa = Siswa::query()
            ->with(['kelas', 'waliMurid'])
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('kepsek.siswa.index', [
            'daftarSiswa' => $siswa,
            'daftarKelas' => Kelas::orderBy('nama_kelas')->get(),
            'kelasFilter' => $kelasId ? (int) $kelasId : null,
        ]);
    }

    public function create()
    {
        return view('kepsek.siswa.create', [
            'daftarKelas' => Kelas::orderBy('nama_kelas')->get(),
            'daftarWaliMurid' => $this->daftarWaliMurid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Siswa::create($this->validasi($request));

        return redirect()->route($this->panelPrefix() . '.siswa.index')->with('status', 'Siswa berhasil ditambahkan.');
    }

    public function edit(Siswa $siswa)
    {
        return view('kepsek.siswa.edit', [
            'siswa' => $siswa,
            'daftarKelas' => Kelas::orderBy('nama_kelas')->get(),
            'daftarWaliMurid' => $this->daftarWaliMurid(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $siswa->update($this->validasi($request, $siswa));

        return redirect()->route($this->panelPrefix() . '.siswa.index')->with('status', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa): RedirectResponse
    {
        // absensi.siswa_id pakai cascadeOnDelete() — riwayat absensi siswa
        // ini ikut terhapus. Ini pilihan yang disengaja sejak migration awal.
        $siswa->delete();

        return redirect()->route($this->panelPrefix() . '.siswa.index')->with('status', 'Siswa berhasil dihapus.');
    }

    private function validasi(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => [
                'required', 'string', 'max:50',
                Rule::unique('siswa', 'nis')->ignore($siswa?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'no_hp_wali' => ['nullable', 'string', 'max:20'],
            'kelas_id' => ['required', 'exists:kelas,id'],
            'wali_murid_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::WaliMurid->value),
            ],
        ]);
    }

    private function daftarWaliMurid()
    {
        return User::where('role', UserRole::WaliMurid)->orderBy('name')->get();
    }
}
