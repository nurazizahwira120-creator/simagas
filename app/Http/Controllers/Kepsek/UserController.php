<?php

namespace App\Http\Controllers\Kepsek;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Daftar semua akun, bisa difilter per role.
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->query('role'), fn ($query, $role) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('kepsek.users.index', [
            'users' => $users,
            'roleOptions' => UserRole::cases(),
            'roleFilter' => $request->query('role'),
        ]);
    }

    public function create()
    {
        return view('kepsek.users.create', [
            'roleOptions' => UserRole::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validasi($request);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route($this->panelPrefix() . '.users.index')->with('status', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('kepsek.users.edit', [
            'akun' => $user,
            'roleOptions' => UserRole::cases(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validasi($request, $user);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route($this->panelPrefix() . '.users.index')->with('status', 'Akun berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 403, 'Anda tidak bisa menghapus akun Anda sendiri.');

        // FK ke kelas.wali_kelas_id & siswa.wali_murid_id sudah nullOnDelete
        // di migration, jadi aman dihapus — relasinya otomatis dikosongkan,
        // bukan ikut menghapus kelas/siswa.
        $user->delete();

        return redirect()->route($this->panelPrefix() . '.users.index')->with('status', 'Akun berhasil dihapus.');
    }

    private function validasi(Request $request, ?User $user = null): array
    {
        $roleValues = implode(',', array_column(UserRole::cases(), 'value'));

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'role' => ['required', 'string', "in:{$roleValues}"],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
        ]);
    }
}
