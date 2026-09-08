<?php

namespace App\Livewire;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Pengumuman;
use App\Models\PengaturanSistem;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kirim Pengumuman Broadcast — Super Admin & Kepala Sekolah.
 *
 * ================== TENTANG PENGIRIMAN WHATSAPP ==================
 * Ini satu-satunya tempat di aplikasi yang bisa mengirim ratusan pesan
 * WhatsApp sekaligus, dan setiap pesan Fonnte berbiaya nyata. Karena itu ada
 * tiga pengaman yang tidak diminta brief tapi tidak layak dihilangkan:
 *
 *   1. Jumlah penerima dihitung dan DITAMPILKAN sebelum tombol ditekan,
 *      lengkap dengan berapa yang punya nomor HP. "Kirim ke 312 nomor"
 *      adalah informasi yang harus dilihat SEBELUM menekan, bukan sesudah.
 *   2. Tombolnya memakai wire:confirm ketika WhatsApp dicentang.
 *   3. Jumlah yang benar-benar diantrekan disimpan di riwayat, supaya
 *      pertanyaan "kenapa tagihan bulan ini besar" bisa dijawab dari data.
 *
 * Pengumuman TETAP tersimpan dan tetap masuk lonceng walau gateway WA mati —
 * notifikasi dalam aplikasi tidak boleh ikut gagal hanya karena tokennya
 * belum diisi.
 * ================================================================
 */
class KirimPengumuman extends Component
{
    use WithPagination;

    public string $judul = '';

    public string $isi_pesan = '';

    public string $target_role = 'semua';

    public bool $kirim_wa = false;

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    /** Peran yang dianggap "guru & staff". */
    private const PERAN_PEGAWAI = [
        UserRole::Guru,
        UserRole::WaliKelas,
        UserRole::Staff,
        UserRole::AdminTu,
        UserRole::GuruPiket,
        UserRole::Kepsek,
    ];

    protected function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'min:4', 'max:150'],
            'isi_pesan' => ['required', 'string', 'min:10', 'max:2000'],
            'target_role' => ['required', 'in:pegawai,wali_murid,semua'],
            'kirim_wa' => ['boolean'],
        ];
    }

    protected function messages(): array
    {
        return [
            'judul.required' => 'Judul pengumuman wajib diisi.',
            'judul.min' => 'Judul terlalu pendek — tulis minimal 4 karakter.',
            'isi_pesan.required' => 'Isi pesan pengumuman wajib diisi.',
            'isi_pesan.min' => 'Isi pesan terlalu pendek — tulis minimal 10 karakter.',
            'isi_pesan.max' => 'Isi pesan maksimal 2000 karakter.',
            'target_role.required' => 'Pilih dulu siapa penerimanya.',
        ];
    }

    /**
     * Query penerima sesuai target. HANYA akun berstatus aktif — akun yang
     * masih menunggu persetujuan belum tentu benar-benar milik orang sekolah,
     * dan mengirimi mereka pengumuman internal berarti membocorkan isinya ke
     * siapa pun yang sempat mendaftar.
     */
    private function penerima()
    {
        $q = User::query()->where('status', StatusAkun::Active);

        return match ($this->target_role) {
            'pegawai' => $q->whereIn('role', self::PERAN_PEGAWAI),
            'wali_murid' => $q->where('role', UserRole::WaliMurid),
            default => $q->whereIn(
                'role',
                array_merge(self::PERAN_PEGAWAI, [UserRole::WaliMurid])
            ),
        };
    }

    /**
     * Nomor WhatsApp seseorang, dicari di TIGA tempat.
     *
     * Kenapa tidak cukup `users.no_hp`: kolom itu memang diisi lewat halaman
     * Registrasi, tapi TIDAK diisi untuk pegawai yang datanya dimasukkan
     * Super Admin lewat Data Pegawai atau Import Excel — di sana nomornya
     * masuk ke `pegawai.no_hp`. Begitu juga wali murid hasil impor, yang
     * nomornya tersimpan di `siswa.no_hp_wali`.
     *
     * Membaca satu kolom saja terlihat berhasil di data uji lalu mengirim
     * NOL pesan di sekolah sungguhan — kegagalan yang tidak melempar error
     * apa pun, hanya pengumuman yang tidak pernah sampai.
     */
    public static function nomorWa(User $orang): ?string
    {
        $kandidat = [
            $orang->no_hp,
            $orang->pegawai?->no_hp,
            $orang->siswaWali?->first()?->no_hp_wali,
        ];

        foreach ($kandidat as $nomor) {
            if (filled($nomor)) {
                return $nomor;
            }
        }

        return null;
    }

    /** Ringkasan calon penerima — ditampilkan sebelum tombol kirim ditekan. */
    #[Computed]
    public function ringkasanPenerima(): array
    {
        $daftar = $this->penerima()->with(['pegawai:id,user_id,no_hp', 'siswaWali:id,wali_murid_id,no_hp_wali'])->get();

        return [
            'total' => $daftar->count(),
            'ber_nomor' => $daftar->filter(fn (User $u) => self::nomorWa($u) !== null)->count(),
        ];
    }

    #[Computed]
    public function gatewayAktif(): bool
    {
        return (bool) PengaturanSistem::ambil()->wa_gateway_status;
    }

    #[Computed]
    public function riwayat()
    {
        return Pengumuman::query()
            ->with('pembuat')
            ->latest()
            ->paginate(10);
    }

    /** Ringkasan penerima ikut berubah saat targetnya diganti. */
    public function updatedTargetRole(): void
    {
        unset($this->ringkasanPenerima);
    }

    public function kirim(): void
    {
        $this->notif = null;
        $data = $this->validate();

        // Hak akses dihitung ulang DI SINI, bukan dipercayakan ke menu:
        // method Livewire adalah endpoint HTTP tersendiri, jadi siapa pun yang
        // sudah login bisa memanggilnya dari konsol browser. Tanpa baris ini,
        // seorang wali murid bisa mengirim pengumuman atas nama sekolah ke
        // seluruh orang tua.
        $pengirim = auth()->user();

        if (! $pengirim || ! in_array($pengirim->role, [UserRole::SuperAdmin, UserRole::Kepsek], true)) {
            $this->pesan('error', 'Tidak diizinkan',
                'Hanya Super Admin dan Kepala Sekolah yang boleh mengirim pengumuman.');

            return;
        }

        $daftarPenerima = $this->penerima()
            ->with(['pegawai:id,user_id,no_hp', 'siswaWali:id,wali_murid_id,no_hp_wali'])
            ->get();

        if ($daftarPenerima->isEmpty()) {
            $this->pesan('warn', 'Tidak ada penerima',
                'Belum ada akun aktif yang cocok dengan target yang dipilih, jadi pengumuman tidak dikirim.');

            return;
        }

        $pengumuman = Pengumuman::create([
            'user_id' => $pengirim->id,
            'judul' => trim($data['judul']),
            'isi_pesan' => trim($data['isi_pesan']),
            'target_role' => $data['target_role'],
            'is_sent_wa' => false,
            'jumlah_penerima' => $daftarPenerima->count(),
            'jumlah_wa' => 0,
        ]);

        // Notifikasi dalam aplikasi (lonceng). Dikirim lebih dulu supaya
        // pengumumannya sampai walau bagian WhatsApp di bawah gagal.
        Notification::send($daftarPenerima, PengumumanBaru::dari($pengumuman));

        $jumlahWa = 0;

        if ($data['kirim_wa']) {
            $jumlahWa = $this->antrekanWhatsApp($daftarPenerima, $pengumuman);

            $pengumuman->update(['is_sent_wa' => $jumlahWa > 0, 'jumlah_wa' => $jumlahWa]);
        }

        $this->reset(['judul', 'isi_pesan', 'kirim_wa']);
        $this->target_role = 'semua';
        unset($this->riwayat, $this->ringkasanPenerima);

        $ringkas = 'Pengumuman terkirim ke ' . $daftarPenerima->count() . ' pengguna (muncul di lonceng notifikasi mereka).';

        if ($data['kirim_wa']) {
            $ringkas .= $jumlahWa > 0
                ? ' ' . $jumlahWa . ' pesan WhatsApp masuk antrean — pastikan queue worker berjalan.'
                : ' Tidak ada pesan WhatsApp yang dikirim (gateway nonaktif atau tidak ada nomor HP tersimpan).';
        }

        $this->pesan('ok', 'Pengumuman terkirim', $ringkas);
    }

    /**
     * Antrekan pesan WhatsApp satu per satu dengan jeda.
     *
     * Jedanya WAJIB: Fonnte memblokir nomor yang mengirim beruntun tanpa
     * jeda, dan nomor sekolah yang diblokir berarti SELURUH notifikasi
     * (termasuk pemberitahuan ketidakhadiran ke orang tua) ikut mati.
     * Besarnya diambil dari pengaturan wa_delay, minimal 1 detik.
     */
    private function antrekanWhatsApp($penerima, Pengumuman $pengumuman): int
    {
        $pengaturan = PengaturanSistem::ambil();

        if (! $pengaturan->wa_gateway_status) {
            return 0;
        }

        $jeda = max(1, (int) $pengaturan->wa_delay);
        $teks = '*' . $pengumuman->judul . "*\n\n" . $pengumuman->isi_pesan
            . "\n\n_Pengumuman dari " . ($pengaturan->nama_sekolah ?: 'sekolah') . '_';

        $urutan = 0;

        foreach ($penerima as $orang) {
            $nomor = self::nomorWa($orang);

            if (blank($nomor)) {
                continue;
            }

            try {
                SendWhatsAppNotification::dispatch($nomor, $teks)
                    ->delay(now()->addSeconds($urutan * $jeda));

                $urutan++;
            } catch (\Throwable $e) {
                // Satu nomor yang gagal diantrekan tidak boleh menghentikan
                // sisanya — dan tidak boleh menggagalkan pengumumannya, yang
                // sudah tersimpan dan sudah masuk lonceng.
                Log::warning('Gagal mengantre pengumuman WA.', [
                    'pengumuman_id' => $pengumuman->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $urutan;
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.kirim-pengumuman');
    }
}
