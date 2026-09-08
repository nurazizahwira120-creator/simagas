<?php

namespace Database\Factories;

use App\Enums\Hari;
use App\Models\JadwalPelajaran;
use Illuminate\Database\Eloquent\Factories\Factory;

class JadwalPelajaranFactory extends Factory
{
    protected $model = JadwalPelajaran::class;

    public function definition(): array
    {
        // Slot jam pelajaran khas SMK — dipilih dari daftar tetap supaya
        // jam_selesai selalu masuk akal (setelah jam_mulai), bukan acak
        // bebas yang bisa menghasilkan jam selesai lebih awal dari mulai.
        [$mulai, $selesai] = fake()->randomElement([
            ['07:00', '08:30'],
            ['08:30', '10:00'],
            ['10:15', '11:45'],
            ['12:30', '14:00'],
            ['14:00', '15:30'],
        ]);

        return [
            'hari' => fake()->randomElement(Hari::hariSekolah()),
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'mata_pelajaran' => fake()->randomElement([
                'Matematika', 'Bahasa Indonesia', 'Bahasa Inggris',
                'Pemrograman Dasar', 'Basis Data', 'Pemrograman Web',
                'Pendidikan Agama Islam', 'PPKn', 'Penjaskes', 'Produk Kreatif',
            ]),
            // kelas_id & guru_id wajib disuplai lewat ->for(...) saat dipakai.
        ];
    }
}
