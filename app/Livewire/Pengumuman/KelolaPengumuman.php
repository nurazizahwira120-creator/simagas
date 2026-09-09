<?php

namespace App\Livewire\Pengumuman;

use App\Enums\StatusAkun;
use App\Enums\UserRole;
use App\Events\PengumumanDisiarkan;
use App\Jobs\SendWhatsAppNotification;
use App\Livewire\Concerns\BisaSweetAlert;
use App\Models\Pengumuman;
use App\Models\PengaturanSistem;
use App\Models\User;
use App\Notifications\PengumumanBaru;
use App\Services\NotifikasiPengumuman;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Kelola Pengumuman — Super Admin & Kepala Sekolah.
 * Buat, ubah, hapus, dan kirim ulang; sekaligus dorongan real-time Pusher.
 *
 * ================== TENTANG PENGIRIMAN WHATSAPP ==================
 * Ini satu-satunya tempat di aplikasi yang bisa mengirim ratusan pesan
 * WhatsApp sekaligus, dan setiap pesan Fonnte berbiaya nyata. Karena itu ada
 * tiga pengaman yang tidak diminta brief tapi tidak layak dihilangkan:
 *
 *   1. Jumlah penerima dihitung dan DITAMPILKAN sebelum tombol ditekan,
 *      lengkap dengan berapa yang punya nomor HP.
 *   2. Tombolnya memakai konfirmasi SweetAlert2 ketika WhatsApp dicentang.
 *   3. Jumlah yang benar-benar diantrekan disimpan di riwayat, supaya
 *      pertanyaan "kenapa tagihan bulan ini besar" bisa dijawab dari data.
 *
 * Pengumuman TETAP tersimpan dan tetap masuk lonceng walau gateway WA mati
 * atau Pusher tidak bisa dihubungi — notifikasi dalam aplikasi tidak boleh
 * ikut gagal hanya karena layanan luar sedang bermasalah.
 * ================================================================
 */
class KelolaPengumuman extends Component
{
    use BisaSweetAlert, WithPagination;

    public string $judul = '';

    public string $isi_pesan = '';

    public string $target_role = 'semua';

    public bool $kirim_wa = false;

    /** Id pengumuman yang sedang diubah; null berarti sedang membuat baru. */
    public ?int $editId = null;

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

    /**
     * Pusher menolak lebih dari 100 channel dalam satu panggilan trigger.
     * Penerima dipecah per angka ini; 300 wali murid = 3 panggilan, bukan 300.
     */
    private const CHANNEL_PER_SIARAN = 100;

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
     * Hak akses dihitung ulang di SETIAP aksi, bukan dipercayakan ke menu:
     * method Livewire adalah endpoint HTTP tersendiri, jadi siapa pun yang
     * sudah login bisa memanggilnya dari konsol browser. Tanpa pemeriksaan
     * ini, seorang wali murid bisa mengirim pengumuman atas nama sekolah.
     */
    private function bolehKelola(): bool
    {
        $peran = auth()->user()?->role;

        return $peran !== null
            && in_array($peran, [UserRole::SuperAdmin, UserRole::Kepsek], true);
    }

    private function tolak(): void
    {
        $this->pesan('error', 'Tidak diizinkan',
            'Hanya Super Admin dan Kepala Sekolah yang boleh mengelola pengumuman.');
    }

    /**
     * Query penerima sesuai target. HANYA akun berstatus aktif — akun yang
     * masih menunggu persetujuan belum tentu benar-benar milik orang sekolah,
     * dan mengirimi mereka pengumuman internal berarti membocorkan isinya ke
     * siapa pun yang sempat mendaftar.
     */
    private function penerima(?string $target = null)
    {
        $target ??= $this->target_role;

        $q = User::query()->where('status', StatusAkun::Active);

        return match ($target) {
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
        $daftar = $this->penerima()
            ->with(['pegawai:id,user_id,no_hp', 'siswaWali:id,wali_murid_id,no_hp_wali'])
            ->get();

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

    /* ================= CREATE & UPDATE ================= */

    public function simpan(): void
    {
        $this->notif = null;

        if (! $this->bolehKelola()) {
            $this->tolak();

            return;
        }

        $data = $this->validate();

        // MENGUBAH pengumuman lama: tidak mengirim ulang notifikasi apa pun.
        // Baris di tabel `notifications` adalah SALINAN teks saat dikirim —
        // memperbaiki typo lalu diam-diam membanjiri ulang lonceng 300 orang
        // adalah kejutan yang tidak diminta siapa pun. Kalau memang ingin
        // dikirim ulang, ada tombol "Kirim Ulang" tersendiri.
        if ($this->editId !== null) {
            $pengumuman = Pengumuman::find($this->editId);

            if (! $pengumuman) {
                $this->pesan('warn', 'Tidak ditemukan',
                    'Pengumuman yang diubah sudah tidak ada — mungkin dihapus dari perangkat lain.');
                $this->batalEdit();

                return;
            }

            $pengumuman->update([
                'judul' => trim($data['judul']),
                'isi_pesan' => trim($data['isi_pesan']),
                'target_role' => $data['target_role'],
            ]);

            $this->batalEdit();
            unset($this->riwayat);

            $this->pesan('ok', 'Perubahan tersimpan',
                'Notifikasi yang sudah terkirim TIDAK ikut berubah. Pakai tombol "Kirim Ulang" bila perlu dikirim lagi.');

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
            'user_id' => auth()->id(),
            'judul' => trim($data['judul']),
            'isi_pesan' => trim($data['isi_pesan']),
            'target_role' => $data['target_role'],
            'is_sent_wa' => false,
            'jumlah_penerima' => $daftarPenerima->count(),
            'jumlah_wa' => 0,
        ]);

        Notification::send($daftarPenerima, PengumumanBaru::dari($pengumuman));
        $this->siarkan($pengumuman, $daftarPenerima->pluck('id')->all());
        $hasilPush = $this->dorongKeHp($pengumuman, $daftarPenerima);

        $jumlahWa = 0;

        if ($data['kirim_wa']) {
            $jumlahWa = $this->antrekanWhatsApp($daftarPenerima, $pengumuman);
            $pengumuman->update(['is_sent_wa' => $jumlahWa > 0, 'jumlah_wa' => $jumlahWa]);
        }

        $this->reset(['judul', 'isi_pesan', 'kirim_wa']);
        $this->target_role = 'semua';
        $this->resetValidation();
        unset($this->riwayat, $this->ringkasanPenerima);

        $ringkas = 'Pengumuman terkirim ke ' . $daftarPenerima->count()
            . ' pengguna (langsung berbunyi di lonceng mereka).'
            . app(NotifikasiPengumuman::class)->ringkasan($hasilPush);

        if ($data['kirim_wa']) {
            $ringkas .= $jumlahWa > 0
                ? ' ' . $jumlahWa . ' pesan WhatsApp masuk antrean — pastikan queue worker berjalan.'
                : ' Tidak ada pesan WhatsApp yang dikirim (gateway nonaktif atau tidak ada nomor HP tersimpan).';
        }

        $this->pesan('ok', 'Pengumuman terkirim', $ringkas);
    }

    public function edit(int $id): void
    {
        if (! $this->bolehKelola()) {
            $this->tolak();

            return;
        }

        $pengumuman = Pengumuman::find($id);

        if (! $pengumuman) {
            $this->pesan('warn', 'Tidak ditemukan', 'Pengumuman itu sudah tidak ada.');

            return;
        }

        $this->editId = $pengumuman->id;
        $this->judul = $pengumuman->judul;
        $this->isi_pesan = $pengumuman->isi_pesan;
        $this->target_role = $pengumuman->target_role;
        $this->kirim_wa = false;
        $this->notif = null;
        $this->resetValidation();
        unset($this->ringkasanPenerima);

        $this->dispatch('gulir-ke-form');
    }

    public function batalEdit(): void
    {
        $this->reset(['editId', 'judul', 'isi_pesan', 'kirim_wa']);
        $this->target_role = 'semua';
        $this->resetValidation();
        unset($this->ringkasanPenerima);
    }

    /* ================= DELETE ================= */

    public function hapus(int $id): void
    {
        $this->notif = null;

        if (! $this->bolehKelola()) {
            $this->tolak();

            return;
        }

        $pengumuman = Pengumuman::find($id);

        if (! $pengumuman) {
            $this->pesan('warn', 'Tidak ditemukan', 'Pengumuman itu sudah tidak ada.');

            return;
        }

        $judul = $pengumuman->judul;

        // Notifikasi turunannya ikut dibersihkan supaya lonceng tidak
        // menyisakan pengumuman yang sudah ditarik kembali. Dibungkus
        // try/catch: json_extract butuh MySQL 5.7+/SQLite dengan JSON1, dan
        // kegagalan di sini tidak boleh membatalkan penghapusan itu sendiri.
        try {
            DB::table('notifications')
                ->where('type', PengumumanBaru::class)
                ->where('data->pengumuman_id', $id)
                ->delete();
        } catch (\Throwable $e) {
            Log::warning('Notifikasi turunan pengumuman tidak bisa dibersihkan.', [
                'pengumuman_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        $pengumuman->delete();

        unset($this->riwayat);

        if ($this->editId === $id) {
            $this->batalEdit();
        }

        $this->pesan('ok', 'Pengumuman dihapus',
            '"' . Str::limit($judul, 60) . '" dihapus dari riwayat, termasuk dari lonceng penerimanya.');
    }

    /* ================= RESEND ================= */

    public function kirimUlang(int $id): void
    {
        $this->notif = null;

        if (! $this->bolehKelola()) {
            $this->tolak();

            return;
        }

        $pengumuman = Pengumuman::with('pembuat')->find($id);

        if (! $pengumuman) {
            $this->pesan('warn', 'Tidak ditemukan', 'Pengumuman itu sudah tidak ada.');

            return;
        }

        $daftarPenerima = $this->penerima($pengumuman->target_role)->get();

        if ($daftarPenerima->isEmpty()) {
            $this->pesan('warn', 'Tidak ada penerima',
                'Tidak ada akun aktif yang cocok dengan target pengumuman ini.');

            return;
        }

        Notification::send($daftarPenerima, PengumumanBaru::dari($pengumuman));
        $this->siarkan($pengumuman, $daftarPenerima->pluck('id')->all());
        $hasilPush = $this->dorongKeHp($pengumuman, $daftarPenerima);

        // Kiriman ulang TIDAK menyentuh WhatsApp. Pesan WA berbiaya dan tidak
        // bisa ditarik; mengulangnya harus keputusan sadar, bukan efek samping
        // dari tombol yang namanya "kirim ulang notifikasi".
        $pengumuman->update(['jumlah_penerima' => $daftarPenerima->count()]);
        unset($this->riwayat);

        $this->pesan('ok', 'Notifikasi dikirim ulang',
            'Dikirim ulang ke ' . $daftarPenerima->count()
            . ' pengguna. WhatsApp TIDAK ikut dikirim ulang.'
            . app(NotifikasiPengumuman::class)->ringkasan($hasilPush));
    }

    /* ================= NOTIFIKASI HP (Web Push) ================= */

    /**
     * Dorong pengumuman ke layar kunci HP penerimanya.
     *
     * Isinya dipisah ke App\Services\NotifikasiPengumuman, bukan ditulis di
     * sini, karena kelas ini sudah memegang terlalu banyak urusan: validasi,
     * lonceng, Pusher, WhatsApp, dan riwayat. Satu lagi yang dijejalkan
     * membuat simpan() mustahil dibaca sekali jalan.
     *
     * Daftar penerimanya SENGAJA dioper apa adanya — bukan di-query ulang di
     * dalam service. Query kedua bisa memberi hasil berbeda (ada akun yang
     * baru disetujui sedetik sebelumnya), dan hasilnya adalah orang yang
     * menerima notifikasi HP tanpa pernah punya loncengnya — atau sebaliknya.
     *
     * @param  \Illuminate\Support\Collection<int, User>  $penerima
     * @return array{terkirim: int, punya_hp: int, dilewati: int}
     */
    private function dorongKeHp(Pengumuman $pengumuman, $penerima): array
    {
        try {
            return app(NotifikasiPengumuman::class)->siarkan($pengumuman, $penerima);
        } catch (\Throwable $e) {
            // Sama seperti Pusher dan WhatsApp: pengumumannya SUDAH tersimpan
            // dan SUDAH masuk lonceng. Gangguan di layanan luar tidak boleh
            // berubah jadi layar error untuk pekerjaan yang sudah selesai.
            Log::warning('Push pengumuman gagal — pengumuman tetap tersimpan.', [
                'pengumuman_id' => $pengumuman->id,
                'error' => $e->getMessage(),
            ]);

            return ['terkirim' => 0, 'punya_hp' => 0, 'dilewati' => 0];
        }
    }

    /* ================= PUSHER ================= */

    /**
     * Dorong ke Pusher, dipecah per 100 channel.
     *
     * Dibungkus try/catch dengan sengaja: token Pusher salah, kuota habis,
     * atau internet server sedang putus TIDAK BOLEH menggagalkan pengumuman
     * yang sudah tersimpan dan sudah masuk lonceng. Yang hilang hanya
     * "bunyi seketika"-nya; penerima tetap melihatnya saat berpindah halaman.
     *
     * @param  array<int, int>  $userIds
     */
    private function siarkan(Pengumuman $pengumuman, array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        $cuplikan = Str::limit($pengumuman->isi_pesan, 120);

        foreach (array_chunk($userIds, self::CHANNEL_PER_SIARAN) as $gelombang) {
            try {
                PengumumanDisiarkan::dispatch($gelombang, $pengumuman->judul, $cuplikan);
            } catch (\Throwable $e) {
                Log::warning('Siaran Pusher gagal — pengumuman tetap tersimpan.', [
                    'pengumuman_id' => $pengumuman->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /* ================= WHATSAPP ================= */

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
        return view('livewire.pengumuman.kelola-pengumuman');
    }
}
