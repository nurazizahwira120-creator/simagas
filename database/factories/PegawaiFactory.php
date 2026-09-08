<?php

namespace Database\Factories;

use App\Models\Pegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

class PegawaiFactory extends Factory
{
    protected $model = Pegawai::class;

    public function definition(): array
    {
        return [
            'nip' => fake()->unique()->numerify('##################'),
            'nama' => fake()->name(),
            'jabatan' => fake()->randomElement(['Guru Mapel', 'Staff Tata Usaha', 'Staff Perpustakaan', 'Satpam']),
            'no_hp' => fake()->numerify('62812#######'),
            // user_id opsional — disuplai lewat Pegawai::factory()->for($user, 'user')
            // kalau pegawai ini juga punya akun login.
        ];
    }
}
