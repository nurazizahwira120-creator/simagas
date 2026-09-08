<?php

namespace Database\Factories;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiSiswa;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsensiSiswaFactory extends Factory
{
    protected $model = AbsensiSiswa::class;

    public function definition(): array
    {
        // Bobot lebih besar ke Hadir supaya data contoh terasa realistis.
        $status = fake()->randomElement([
            AbsensiStatus::Hadir, AbsensiStatus::Hadir, AbsensiStatus::Hadir, AbsensiStatus::Hadir,
            AbsensiStatus::Izin, AbsensiStatus::Sakit, AbsensiStatus::Alpha,
        ]);

        return [
            'tanggal' => now()->toDateString(),
            'status' => $status,
            'jam_masuk' => $status === AbsensiStatus::Hadir
                ? fake()->dateTimeBetween('06:00:00', '07:30:00')->format('H:i:s')
                : null,
            'keterangan' => match ($status) {
                AbsensiStatus::Izin => fake()->randomElement(['Acara keluarga', 'Urusan administrasi', 'Izin dari orang tua']),
                AbsensiStatus::Sakit => fake()->randomElement(['Demam', 'Flu', 'Sakit perut']),
                default => null,
            },
            // siswa_id wajib disuplai lewat AbsensiSiswa::factory()->for($siswa)
            // waktu dipakai.
        ];
    }
}
