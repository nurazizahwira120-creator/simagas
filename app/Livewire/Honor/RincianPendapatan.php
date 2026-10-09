<?php

namespace App\Livewire\Honor;

use App\Services\AturanHonor;
use App\Services\GuruInval;
use App\Services\PencatatHonor;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * RINCIAN PENDAPATAN — halaman guru/staf untuk melihat honor MILIKNYA
 * SENDIRI per bulan. Angkanya bertambah otomatis setiap kali ia menekan
 * "Akhiri Sesi" atau menyimpan absensi kelas sebagai guru inval.
 *
 * Tidak ada parameter "milik siapa": selalu auth()->id(), jadi seorang guru
 * tidak mungkin melihat honor guru lain dengan memalsukan permintaan.
 */
class RincianPendapatan extends Component
{
    use PilihBulan;

    public function mount(): void
    {
        // Sama dengan grup rute: peran yang bisa mengajar / menjadi inval.
        abort_unless(in_array(auth()->user()?->role, GuruInval::PERAN_CALON, true), 403);

        $this->siapkanBulan();
    }

    /** @return array{aktif: bool, tarif: int, persen_inval: int} */
    #[Computed]
    public function aturan(): array
    {
        return app(AturanHonor::class)->umum();
    }

    #[Computed]
    public function tarifSaya(): int
    {
        return app(AturanHonor::class)->tarifUntuk(auth()->id(), $this->aturan['tarif']);
    }

    #[Computed]
    public function rincian(): array
    {
        return app(PencatatHonor::class)->rincian(auth()->id(), $this->bulanDipakai());
    }

    public function render()
    {
        return view('livewire.honor.rincian-pendapatan');
    }
}
