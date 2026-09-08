<?php

namespace Database\Factories;

use App\Models\Siswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiswaFactory extends Factory
{
    protected $model = Siswa::class;

    public function definition(): array
    {
        return [
            'nis' => fake()->unique()->numerify('##########'),
            'nama' => fake()->name(),
            'no_hp_wali' => fake()->numerify('62812#######'),
            // kelas_id wajib disuplai lewat Siswa::factory()->for($kelas)
            // waktu dipakai — tidak ada kelas default yang masuk akal di sini.
        ];
    }
}
