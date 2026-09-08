<?php

namespace Database\Seeders;

use App\Models\PengaturanSistem;
use Illuminate\Database\Seeder;

/**
 * Baris pengaturan sistem bawaan.
 *
 * Jalankan:  php artisan db:seed --class=PengaturanSistemSeeder
 *
 * Aman dijalankan berkali-kali: memakai firstOrCreate, jadi kalau barisnya
 * sudah ada (apalagi kalau tokennya sudah diisi Super Admin), isinya TIDAK
 * ditimpa. Seeder yang menimpa akan menghapus token produksi setiap kali
 * seseorang menjalankan db:seed — kesalahan yang baru ketahuan saat
 * notifikasi berhenti terkirim tanpa sebab yang jelas.
 */
class PengaturanSistemSeeder extends Seeder
{
    public function run(): void
    {
        $pengaturan = PengaturanSistem::query()->first();

        if ($pengaturan) {
            $this->command?->info('Pengaturan sistem sudah ada — tidak ditimpa.');

            return;
        }

        PengaturanSistem::query()->create([
            'nama_sekolah' => "SMK Islam Assya'roniyyah",
            'wa_gateway_status' => false,
            'fonnte_token' => null,
            'wa_delay' => 2,

            // Titik sekolah & radius Absen Radius. Nilai ini PLACEHOLDER
            // (Monas, Jakarta) dan wajib diganti lewat Pengaturan Sistem >
            // Lokasi & Radius GPS. Diisi di sini, bukan dibiarkan null,
            // supaya peta pemilih titik punya tempat untuk membuka dan tidak
            // memulai di koordinat 0,0 (tengah Samudra Atlantik).
            'latitude' => '-6.175392',
            'longitude' => '106.827153',
            'radius_meter' => 50,
        ]);

        $this->command?->info('Pengaturan sistem bawaan dibuat (notifikasi WA masih NONAKTIF).');
        $this->command?->warn('  Koordinat sekolah masih placeholder Monas — ganti lewat Pengaturan Sistem > Lokasi & Radius GPS.');
    }
}
