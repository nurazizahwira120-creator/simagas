<?php

namespace App\Http\Controllers\Kepsek;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KelasController extends Controller
{
    public function index()
    {
        $kelas = Kelas::query()
            ->with('waliKelas')
            ->withCount('siswa')
            ->orderBy('nama_kelas')
            ->paginate(15);

        return view('kepsek.kelas.index', [
            'daftarKelas' => $kelas,
        ]);
    }

    public function create()
    {
        return view('kepsek.kelas.create', [
            'daftarWaliKelas' => $this->daftarWaliKelas(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validasi($request);

        Kelas::create($validated);

        return redirect()->route($this->panelPrefix() . '.kelas.index')->with('status', 'Kelas berhasil ditambahkan.');
    }

    public function edit(Kelas $kelas)
    {
        return view('kepsek.kelas.edit', [
            'kelas' => $kelas,
            'daftarWaliKelas' => $this->daftarWaliKelas(),
        ]);
    }

    public function update(Request $request, Kelas $kelas): RedirectResponse
    {
        $kelas->update($this->validasi($request));

        return redirect()->route($this->panelPrefix() . '.kelas.index')->with('status', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Kelas $kelas): RedirectResponse
    {
        // siswa.kelas_id pakai restrictOnDelete() di migration, jadi kalau
        // masih ada siswa di kelas ini, query di bawah akan gagal dengan
        // QueryException — kita tangkap supaya pesannya jelas, bukan 500.
        if ($kelas->siswa()->exists()) {
            return back()->withErrors([
                'kelas' => "Kelas \"{$kelas->nama_kelas}\" masih punya siswa terdaftar — pindahkan atau hapus siswanya dulu sebelum menghapus kelas ini.",
            ]);
        }

        $kelas->delete();

        return redirect()->route($this->panelPrefix() . '.kelas.index')->with('status', 'Kelas berhasil dihapus.');
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama_kelas' => ['required', 'string', 'max:255'],
            'wali_kelas_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', UserRole::WaliKelas->value),
            ],
        ]);
    }

    private function daftarWaliKelas()
    {
        return User::where('role', UserRole::WaliKelas)->orderBy('name')->get();
    }
}
