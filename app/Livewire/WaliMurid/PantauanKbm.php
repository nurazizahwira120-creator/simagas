<?php

namespace App\Livewire\WaliMurid;

use App\Enums\Hari;
use App\Enums\StatusKbm;
use App\Models\AbsensiKbmSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Pantauan KBM Harian — orang tua melihat kehadiran anaknya JAM PER JAM.
 *
 * Ini ujung dari rantai: guru scan QR ruangan -> guru mengisi jurnal kelas ->
 * apa yang ia catat muncul di sini. Kalau anak masuk gerbang pagi tapi tidak
 * ada di kelas jam ke-3, badge jam itulah yang berubah merah.
 */
class PantauanKbm extends Component
{
    /** Anak yang sedang dilihat (untuk wali murid dengan lebih dari satu anak). */
    public ?int $anak_id = null;

    public function mount(): void
    {
        $this->anak_id = $this->daftarAnak->first()?->id;
    }

    /**
     * Anak-anak milik akun yang login.
     *
     * @return Collection<int, Siswa>
     */
    #[Computed]
    public function daftarAnak(): Collection
    {
        return auth()->user()->siswaWali()->with('kelas')->orderBy('nama')->get();
    }

    /**
     * Anak yang dipilih — DICARI DI DALAM koleksi anak sendiri.
     *
     * anak_id adalah properti publik Livewire, jadi bisa diisi id siswa mana
     * pun dari browser. Karena pencariannya dibatasi ke koleksi milik user,
     * id keluarga lain tidak akan ketemu dan otomatis jatuh ke anak pertama —
     * bukan menampilkan data anak orang lain.
     */
    #[Computed]
    public function anak(): ?Siswa
    {
        return $this->daftarAnak->firstWhere('id', $this->anak_id)
            ?? $this->daftarAnak->first();
    }

    /**
     * Jadwal anak hari ini, digabung dengan catatan absensi KBM hari ini.
     *
     * Digabung di PHP, bukan lewat leftJoin SQL. Alasannya: leftJoin
     * mengembalikan baris array datar yang kolomnya bertabrakan (`id` jadwal
     * vs `id` absensi, `keterangan` di dua tabel), sehingga hasilnya harus
     * di-alias satu per satu dan gampang salah diam-diam. Dengan keyBy di
     * PHP, relasi Eloquent-nya tetap utuh dan jumlah query-nya sama-sama dua.
     *
     * @return Collection<int, array{jadwal: JadwalPelajaran, absensi: ?AbsensiKbmSiswa}>
     */
    #[Computed]
    public function timeline(): Collection
    {
        $anak = $this->anak;

        if (! $anak || ! $anak->kelas_id) {
            return collect();
        }

        $jadwal = JadwalPelajaran::with('guru')
            ->where('kelas_id', $anak->kelas_id)
            ->where('hari', Hari::hariIni()->value)
            ->get()
            // Kolom jam berupa teks, jadi diurutkan di PHP — ORDER BY di
            // database akan menaruh "10:00" sebelum "07:00".
            ->sortBy(fn (JadwalPelajaran $j) => $j->jam_mulai->format('H:i'))
            ->values();

        $absensi = AbsensiKbmSiswa::where('siswa_id', $anak->id)
            ->whereDate('tanggal', today())
            ->whereIn('jadwal_id', $jadwal->pluck('id'))
            ->get()
            ->keyBy('jadwal_id');

        return $jadwal->map(fn (JadwalPelajaran $j) => [
            'jadwal' => $j,
            'absensi' => $absensi->get($j->id),
        ]);
    }

    /**
     * Tiga metrik di atas timeline.
     *
     * @return array{hadir: int, sakitIzin: int, alpa: int, belum: int}
     */
    #[Computed]
    public function ringkasan(): array
    {
        $status = $this->timeline
            ->map(fn (array $b) => $b['absensi']?->status)
            ->filter();

        return [
            'hadir' => $status->filter(fn (StatusKbm $s) => $s === StatusKbm::Hadir)->count(),
            'sakitIzin' => $status->filter(fn (StatusKbm $s) => in_array($s, [StatusKbm::Sakit, StatusKbm::Izin], true))->count(),
            // Bolos ikut dihitung sebagai alpa di ringkasan: bagi orang tua
            // keduanya sama-sama berarti "anak saya tidak ada di kelas".
            // Bedanya tetap terlihat di badge masing-masing jam.
            'alpa' => $status->filter(fn (StatusKbm $s) => in_array($s, [StatusKbm::Alpa, StatusKbm::Bolos], true))->count(),
            'belum' => $this->timeline->count() - $status->count(),
        ];
    }

    public function render()
    {
        return view('livewire.wali-murid.pantauan-kbm');
    }
}
