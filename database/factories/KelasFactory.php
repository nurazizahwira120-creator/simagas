<?php

namespace Database\Factories;

use App\Models\Kelas;
use Illuminate\Database\Eloquent\Factories\Factory;

class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    public function definition(): array
    {
        $tingkat = fake()->randomElement(['X', 'XI', 'XII']);
        $jurusan = fake()->randomElement(['RPL', 'TKJ', 'AKL', 'OTKP']);
        $rombel = fake()->numberBetween(1, 3);

        return [
            'nama_kelas' => "{$tingkat} {$jurusan} {$rombel}",
        ];
    }
}
