<?php

namespace App\Livewire\Honor;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\AturanHonor;
use App\Services\GuruInval;
use App\Services\PencatatHonor;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * HONOR GURU — halaman Kepala Sekolah & Super Admin.
 *
 *   Tab "Rekap Bulanan" : honor semua guru satu bulan + rincian per orang.
 *   Tab "Pengaturan"    : sakelar fitur, tarif umum per JP, bagian guru
 *                         inval (%), dan tarif khusus per orang.
 *
 * Hak akses diperiksa di SETIAP aksi (pastikanPengatur), tidak hanya oleh
 * grup rute — setiap method Livewire adalah endpoint HTTP tersendiri.
 */
class HonorGuru extends Component
{
    use PilihBulan;

    public string $tab = 'rekap';

    /** Orang yang rinciannya sedang dibuka di tab Rekap. */
    public ?int $lihatUserId = null;

    /* ---- Form pengaturan umum ---- */
    public bool $aktif = false;

    public string $tarifUmum = '0';

    public string $persenInval = '50';

    /** Tarif per JP untuk sesi ekskul; kosong = ikut tarif pembinanya. */
    public string $tarifEkskul = '';

    /** @var array<int|string, string>  [user_id => tarif]; kosong = ikut tarif umum */
    public array $tarifKhusus = [];

    public string $cari = '';

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    public function mount(): void
    {
        $this->pastikanPengatur();
        $this->siapkanBulan();
        $this->isiForm();
    }

    public function updatedTab(): void
    {
        $this->tab = in_array($this->tab, ['rekap', 'pengaturan'], true) ? $this->tab : 'rekap';
        $this->notif = null;
    }

    public function updatedBulan(): void
    {
        $this->lihatUserId = null;
    }

    /* ===================== DATA ===================== */

    #[Computed]
    public function aturan(): array
    {
        return app(AturanHonor::class)->umum();
    }

    #[Computed]
    public function rekap(): array
    {
        return app(PencatatHonor::class)->rekapBulan($this->bulanDipakai());
    }

    #[Computed]
    public function orangDilihat(): ?array
    {
        return $this->lihatUserId ? $this->rekap['baris']->firstWhere('user.id', $this->lihatUserId) : null;
    }

    #[Computed]
    public function rincianDilihat(): ?array
    {
        return $this->orangDilihat
            ? app(PencatatHonor::class)->rincian($this->lihatUserId, $this->bulanDipakai())
            : null;
    }

    /**
     * Semua orang yang bisa menerima honor: pengajar, calon inval, dan
     * Kepala Sekolah (ikut mengajar). Hanya akun aktif.
     *
     * @return Collection<int, User>
     */
    #[Computed]
    public function daftarOrang(): Collection
    {
        $peran = array_map(fn (UserRole $r) => $r->value, [...GuruInval::PERAN_CALON, UserRole::Kepsek]);
        $cari = trim($this->cari);

        return User::query()
            ->with('pegawai:id,user_id,nama')
            ->whereIn('role', $peran)
            ->where('status', StatusAkun::Active->value)
            ->when($cari !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', '%' . $cari . '%')
                ->orWhereHas('pegawai', fn ($q) => $q->where('nama', 'like', '%' . $cari . '%'))))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    /* ===================== AKSI ===================== */

    public function lihat(int $userId): void
    {
        $this->pastikanPengatur();
        $this->lihatUserId = $userId;
    }

    public function tutupRincian(): void
    {
        $this->lihatUserId = null;
    }

    public function simpanPengaturan(): void
    {
        $this->pastikanPengatur();

        $data = $this->validate([
            'aktif' => ['boolean'],
            'tarifUmum' => ['required', 'integer', 'min:0', 'max:' . AturanHonor::MAKS_TARIF],
            'persenInval' => ['required', 'integer', 'min:0', 'max:100'],
            'tarifEkskul' => ['nullable', 'integer', 'min:0', 'max:' . AturanHonor::MAKS_TARIF],
        ], [], [
            'tarifUmum' => 'tarif umum per JP',
            'persenInval' => 'bagian guru inval',
            'tarifEkskul' => 'tarif ekskul per JP',
        ]);

        app(AturanHonor::class)->simpanUmum(
            (bool) $data['aktif'],
            (int) $data['tarifUmum'],
            (int) $data['persenInval'],
            filled($data['tarifEkskul'] ?? null) ? (int) $data['tarifEkskul'] : null,
        );

        unset($this->aturan, $this->rekap);
        $this->isiForm();

        $this->pesan('ok', 'Pengaturan tersimpan',
            $this->aktif
                ? 'Honor dicatat otomatis mulai sekarang. Perubahan tarif hanya berlaku untuk jam berikutnya — honor yang sudah tercatat tidak berubah.'
                : 'Fitur honor dimatikan. Tidak ada honor baru yang dicatat. Guru tetap bisa melihat honor yang sudah tercatat di Rincian Pendapatan.');
    }

    public function simpanTarifKhusus(): void
    {
        $this->pastikanPengatur();

        $this->validate([
            'tarifKhusus' => ['array'],
            'tarifKhusus.*' => ['nullable', 'integer', 'min:0', 'max:' . AturanHonor::MAKS_TARIF],
        ], [], ['tarifKhusus.*' => 'tarif khusus']);

        $boleh = $this->daftarOrangSemua()->pluck('id')->flip();
        $aturan = app(AturanHonor::class);
        $berubah = 0;
        $lama = $aturan->tarifKhusus();

        foreach ($this->tarifKhusus as $userId => $nilai) {
            $userId = (int) $userId;

            // Hanya orang yang memang tampil di daftar — id lain dari browser diabaikan.
            if (! $boleh->has($userId)) {
                continue;
            }

            $baru = ($nilai === '' || $nilai === null) ? null : (int) $nilai;

            if (($lama[$userId] ?? null) !== $baru) {
                $aturan->simpanTarifKhusus($userId, $baru);
                $berubah++;
            }
        }

        unset($this->rekap);
        $this->isiForm();

        $this->pesan('ok', 'Tarif khusus tersimpan',
            $berubah ? "{$berubah} tarif diperbarui. Kolom yang dikosongkan kembali memakai tarif umum." : 'Tidak ada tarif yang berubah.');
    }

    public function render()
    {
        return view('livewire.honor.honor-guru');
    }

    /* ===================== PEMBANTU ===================== */

    private function pastikanPengatur(): void
    {
        abort_unless(AturanHonor::bolehMengatur(auth()->user()), 403);
        $this->notif = null;
    }

    private function isiForm(): void
    {
        $umum = app(AturanHonor::class)->umum();

        $this->aktif = $umum['aktif'];
        $this->tarifUmum = (string) $umum['tarif'];
        $this->persenInval = (string) $umum['persen_inval'];
        $this->tarifEkskul = $umum['tarif_ekskul'] !== null ? (string) $umum['tarif_ekskul'] : '';
        $this->tarifKhusus = array_map('strval', app(AturanHonor::class)->tarifKhusus());
    }

    /** Daftar orang TANPA saringan pencarian — untuk validasi id saat menyimpan. */
    private function daftarOrangSemua(): Collection
    {
        $peran = array_map(fn (UserRole $r) => $r->value, [...GuruInval::PERAN_CALON, UserRole::Kepsek]);

        return User::query()->whereIn('role', $peran)->where('status', StatusAkun::Active->value)->get(['id']);
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }
}
