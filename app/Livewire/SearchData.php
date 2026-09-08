<?php

namespace App\Livewire;

use App\Models\Siswa;
use Livewire\Component;

/**
 * Pencarian siswa real-time (tanpa reload, tanpa tombol submit).
 *
 * Komponen ini sengaja dibuat SEDERHANA: fungsinya sebagai bukti bahwa
 * pemasangan Livewire sudah benar. Kalau mengetik di kotak pencarian
 * langsung memunculkan hasil, berarti fondasinya beres dan komponen yang
 * lebih rumit (GPS & kamera) aman dibangun di atasnya.
 *
 * Catatan Livewire v3:
 *   - Namespace-nya App\Livewire (di v2 dulu App\Http\Livewire).
 *   - View-nya di resources/views/livewire/search-data.blade.php.
 *   - Di Blade, reaktivitas butuh wire:model.live (di v2 cukup wire:model).
 */
class SearchData extends Component
{
    /** Kata kunci yang diketik pengguna; terikat ke input lewat wire:model.live. */
    public string $cari = '';

    /** Batas jumlah hasil supaya query tetap ringan saat kata kunci sangat umum. */
    public int $batas = 15;

    public function render()
    {
        $kunci = trim($this->cari);

        // Belum mengetik apa pun -> jangan query sama sekali. Menampilkan
        // seluruh siswa saat kotak masih kosong hanya membebani database
        // tanpa memberi informasi apa pun.
        $hasil = $kunci === ''
            ? collect()
            : Siswa::query()
                ->with('kelas')
                ->where(fn ($q) => $q
                    ->where('nama', 'like', "%{$kunci}%")
                    ->orWhere('nis', 'like', "%{$kunci}%"))
                ->orderBy('nama')
                ->limit($this->batas)
                ->get();

        return view('livewire.search-data', [
            'hasil' => $hasil,
            'kunci' => $kunci,
        ]);
    }
}
