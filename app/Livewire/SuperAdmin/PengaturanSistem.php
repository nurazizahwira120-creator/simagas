<?php

namespace App\Livewire\SuperAdmin;

use App\Models\PengaturanSistem as ModelPengaturan;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Identitas sekolah & konfigurasi gateway WhatsApp.
 *
 * Dipasang sebagai TAB di halaman Super Admin > Pengaturan Sistem, bukan
 * halaman tersendiri — supaya tidak ada dua menu bernama "Pengaturan Sistem"
 * di panel yang sama.
 */
class PengaturanSistem extends Component
{
    public string $nama_sekolah = '';

    public bool $wa_gateway_status = false;

    public string $fonnte_token = '';

    public int $wa_delay = 2;

    /** @var array{tipe: string, judul: string, pesan: string}|null */
    public ?array $notif = null;

    public function mount(): void
    {
        $p = ModelPengaturan::ambil();

        $this->nama_sekolah = (string) $p->nama_sekolah;
        $this->wa_gateway_status = (bool) $p->wa_gateway_status;
        $this->fonnte_token = (string) ($p->fonnte_token ?? '');
        $this->wa_delay = (int) $p->wa_delay;
    }

    protected function rules(): array
    {
        return [
            'nama_sekolah' => ['required', 'string', 'min:3', 'max:150'],
            'wa_gateway_status' => ['boolean'],

            // Token wajib HANYA kalau gateway-nya dinyalakan. Menyalakan
            // sakelar tanpa token membuat sistem terlihat aktif padahal setiap
            // pesan gagal diam-diam di dalam queue — kegagalan yang baru
            // ketahuan berminggu-minggu kemudian saat ada orang tua bertanya
            // kenapa tidak pernah menerima notifikasi.
            'fonnte_token' => [$this->wa_gateway_status ? 'required' : 'nullable', 'string', 'max:255'],

            // Batas atas 60 detik: di atas itu antrean menumpuk lebih cepat
            // daripada terkirim, dan pesan "anak Anda sudah tiba" baru sampai
            // setelah anaknya pulang.
            'wa_delay' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }

    protected function messages(): array
    {
        return [
            'nama_sekolah.required' => 'Nama sekolah wajib diisi.',
            'fonnte_token.required' => 'Token Fonnte wajib diisi kalau notifikasi WhatsApp dinyalakan.',
            'wa_delay.required' => 'Jeda pengiriman wajib diisi.',
            'wa_delay.min' => 'Jeda pengiriman tidak boleh negatif.',
            'wa_delay.max' => 'Jeda pengiriman maksimal 60 detik.',

        ];
    }

    /** Peringatan lembut kalau jedanya di luar rentang yang disarankan. */
    #[Computed]
    public function delayBerisiko(): bool
    {
        return $this->wa_delay < 2;
    }

    public function simpan(): void
    {
        $this->notif = null;

        $data = $this->validate();

        try {
            $p = ModelPengaturan::ambil();

            $p->fill([
                'nama_sekolah' => trim($data['nama_sekolah']),
                'wa_gateway_status' => (bool) $data['wa_gateway_status'],
                // Token kosong disimpan NULL, bukan string kosong, supaya
                // pengecekan blank() di WhatsAppService punya satu bentuk saja.
                'fonnte_token' => filled($data['fonnte_token']) ? trim($data['fonnte_token']) : null,
                'wa_delay' => (int) $data['wa_delay'],
            ])->save();
        } catch (\Throwable $e) {
            // Pesan error TIDAK ditampilkan mentah ke layar: isinya bisa
            // memuat potongan query beserta tokennya.
            Log::error('Gagal menyimpan pengaturan sistem.', ['error' => $e->getMessage()]);

            $this->pesan('error', 'Gagal menyimpan',
                'Terjadi kesalahan saat menyimpan pengaturan. Coba lagi sebentar.');

            return;
        }

        $this->pesan('ok', 'Pengaturan tersimpan',
            $this->wa_gateway_status
                ? 'Notifikasi WhatsApp AKTIF. Pastikan queue worker berjalan (php artisan queue:work), karena pesan dikirim lewat antrean.'
                : 'Notifikasi WhatsApp NONAKTIF — tidak ada pesan yang akan dikirim ke wali murid.');
    }

    /**
     * Kirim satu pesan uji ke nomor Super Admin sendiri.
     *
     * Ada karena kegagalan gateway hampir selalu diam: token salah atau
     * kuota habis tidak menimbulkan gejala apa pun di layar — pesannya
     * cuma tidak pernah sampai. Tombol ini memindahkan kegagalan itu ke
     * tempat yang kelihatan, sebelum ratusan wali murid bergantung padanya.
     */
    public function kirimUji(): void
    {
        $this->notif = null;

        $nomor = auth()->user()?->no_hp;

        if (blank($nomor)) {
            $this->pesan('warn', 'Nomor Anda belum diisi',
                'Isi dulu nomor HP di halaman Profil Pribadi, supaya pesan uji ada tujuannya.');

            return;
        }

        if (! $this->wa_gateway_status) {
            $this->pesan('warn', 'Gateway masih nonaktif',
                'Nyalakan dulu sakelar notifikasi lalu simpan, baru kirim pesan uji.');

            return;
        }

        // Dikirim LANGSUNG (bukan lewat antrean) supaya hasilnya bisa
        // dilaporkan saat itu juga. Untuk satu pesan uji, menunggu beberapa
        // detik justru itulah yang diinginkan.
        try {
            $berhasil = WhatsAppService::send($nomor, 'Tes koneksi SIMAGAS. Kalau pesan ini sampai, gateway WhatsApp sekolah sudah berfungsi.');
        } catch (\Throwable $e) {
            $this->pesan('error', 'Gagal menghubungi gateway',
                'Tidak bisa terhubung ke Fonnte. Periksa koneksi internet server, lalu coba lagi.');

            return;
        }

        $berhasil
            ? $this->pesan('ok', 'Pesan uji terkirim',
                'Periksa WhatsApp di nomor ' . $nomor . '. Kalau tidak sampai dalam semenit, biasanya tokennya salah atau kuota Fonnte habis.')
            : $this->pesan('error', 'Pesan uji ditolak gateway',
                'Fonnte menolak pengiriman. Penyebab paling sering: token salah, kuota habis, atau nomor tujuan belum terdaftar di WhatsApp. Rinciannya ada di storage/logs/laravel.log.');
    }

    private function pesan(string $tipe, string $judul, string $pesan): void
    {
        $this->notif = ['tipe' => $tipe, 'judul' => $judul, 'pesan' => $pesan];
    }

    public function render()
    {
        return view('livewire.super-admin.pengaturan-sistem');
    }
}
