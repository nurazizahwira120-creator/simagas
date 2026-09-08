<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PegawaiController extends Controller
{
    public function index(Request $request)
    {
        $pegawai = Pegawai::query()
            ->with('user')
            ->when(
                $request->query('cari'),
                fn ($query, $cari) => $query->where(fn ($q) => $q
                    ->where('nama', 'like', "%{$cari}%")
                    ->orWhere('nip', 'like', "%{$cari}%")
                    ->orWhere('jabatan', 'like', "%{$cari}%"))
            )
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('super-admin.pegawai.index', [
            'daftarPegawai' => $pegawai,
            'cari' => $request->query('cari'),
        ]);
    }

    public function create()
    {
        return view('super-admin.pegawai.create', [
            'daftarUser' => $this->daftarUserBelumTertaut(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Pegawai::create($this->validasi($request));

        return redirect()->route($this->panelPrefix() . '.pegawai.index')->with('status', 'Pegawai berhasil ditambahkan.');
    }

    public function edit(Pegawai $pegawai)
    {
        return view('super-admin.pegawai.edit', [
            'pegawai' => $pegawai,
            'daftarUser' => $this->daftarUserBelumTertaut($pegawai->user_id),
        ]);
    }

    public function update(Request $request, Pegawai $pegawai): RedirectResponse
    {
        $pegawai->update($this->validasi($request, $pegawai));

        return redirect()->route($this->panelPrefix() . '.pegawai.index')->with('status', 'Data pegawai berhasil diperbarui.');
    }

    public function destroy(Pegawai $pegawai): RedirectResponse
    {
        // absensi_pegawai.pegawai_id pakai cascadeOnDelete() — riwayat
        // absensi pegawai ini ikut terhapus. Akun user (kalau ada yang
        // tertaut) TIDAK ikut terhapus, hanya tautannya yang hilang.
        $pegawai->delete();

        return redirect()->route($this->panelPrefix() . '.pegawai.index')->with('status', 'Data pegawai berhasil dihapus.');
    }

    private function validasi(Request $request, ?Pegawai $pegawai = null): array
    {
        return $request->validate([
            // NIP opsional — lihat catatan di App\Livewire\Auth\Register.
            'nip' => [
                'nullable', 'string', 'max:50',
                Rule::unique('pegawai', 'nip')->ignore($pegawai?->id),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'jabatan' => ['required', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'user_id' => [
                'nullable',
                Rule::exists('users', 'id'),
                Rule::unique('pegawai', 'user_id')->ignore($pegawai?->id),
            ],
        ]);
    }

    /**
     * Akun user yang belum ditautkan ke pegawai manapun — supaya dropdown
     * di form tidak menawarkan akun yang sudah "diambil" pegawai lain
     * (constraint unique di tabel pegawai.user_id). Saat edit, akun yang
     * sedang ditautkan ke pegawai ini sendiri tetap disertakan.
     */
    private function daftarUserBelumTertaut(?int $userIdSaatIni = null)
    {
        return User::query()
            ->whereDoesntHave('pegawai')
            ->when($userIdSaatIni, fn ($query, $id) => $query->orWhere('id', $id))
            ->orderBy('name')
            ->get();
    }
}
