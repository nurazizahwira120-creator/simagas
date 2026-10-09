<?php

namespace App\Livewire\Honor;

use Illuminate\Support\Carbon;

/**
 * Pemilih bulan bersama untuk halaman honor: 12 bulan terakhir, bulan ini
 * paling atas. Nilai dari browser selalu divalidasi ulang — bulan yang
 * dipalsukan (format salah / masa depan) kembali ke bulan ini.
 */
trait PilihBulan
{
    public string $bulan = '';

    public const JUMLAH_BULAN = 12;

    protected function siapkanBulan(): void
    {
        $this->bulan = now()->format('Y-m');
    }

    public function bulanDipakai(): Carbon
    {
        $pilihan = $this->pilihanBulan();

        return Carbon::createFromFormat('!Y-m', isset($pilihan[$this->bulan]) ? $this->bulan : now()->format('Y-m'));
    }

    /** @return array<string, string>  ['2026-10' => 'Oktober 2026', ...] */
    public function pilihanBulan(): array
    {
        $hasil = [];
        $bulan = now()->startOfMonth();

        for ($i = 0; $i < self::JUMLAH_BULAN; $i++) {
            $hasil[$bulan->format('Y-m')] = $bulan->translatedFormat('F Y');
            $bulan->subMonthNoOverflow();
        }

        return $hasil;
    }

    public function bulanIni(): bool
    {
        return $this->bulanDipakai()->format('Y-m') === now()->format('Y-m');
    }
}
