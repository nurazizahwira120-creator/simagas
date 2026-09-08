<?php

namespace Database\Factories;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiPegawai;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbsensiPegawaiFactory extends Factory
{
    protected $model = AbsensiPegawai::class;

    public function definition(): array
    {
        // Bobot lebih besar ke Hadir — pegawai jarang absen dibanding siswa.
        $status = fake()->randomElement([
            AbsensiStatus::Hadir, AbsensiStatus::Hadir, AbsensiStatus::Hadir,
            AbsensiStatus::Hadir, AbsensiStatus::Hadir,
            AbsensiStatus::Izin, AbsensiStatus::Sakit,
        ]);

        return [
            'tanggal' => now()->toDateString(),
            'status' => $status,
            'jam_masuk' => $status === AbsensiStatus::Hadir
                ? fake()->dateTimeBetween('06:30:00', '07:45:00')->format('H:i:s')
                : null,
            'keterangan' => match ($status) {
                AbsensiStatus::Izin => fake()->randomElement(['Dinas luar', 'Urusan keluarga', 'Izin pimpinan']),
                AbsensiStatus::Sakit => fake()->randomElement(['Demam', 'Flu', 'Kontrol dokter']),
                default => null,
            },
            // pegawai_id wajib disuplai lewat AbsensiPegawai::factory()->for($pegawai)
            // waktu dipakai.
        ];
    }
}
