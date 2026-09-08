<?php

namespace App\Livewire;

use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal Kartu Identitas (ID Card) untuk Siswa & Pegawai.
 *
 * Dipasang SEKALI per halaman (di daftar Siswa dan daftar Pegawai), lalu
 * dibuka dari tombol "Kartu" di setiap baris tabel lewat:
 *     Livewire.dispatch('buka-kartu', { jenis: 'siswa', id: 12 })
 *
 * Datanya diambil saat modal dibuka, bukan ikut dirender untuk semua baris
 * sekaligus — kalau tidak, satu halaman berisi 50 siswa berarti 50 kartu
 * lengkap dengan barcode-nya ikut dikirim ke browser padahal paling banter
 * satu yang dilihat.
 */
class KartuIdentitas extends Component
{
    public bool $terbuka = false;

    /** 'siswa' | 'pegawai' */
    public string $jenis = '';

    public ?int $id = null;

    #[On('buka-kartu')]
    public function buka(string $jenis, int $id): void
    {
        abort_unless(in_array($jenis, ['siswa', 'pegawai'], true), 404);

        $this->pastikanBerwenang($jenis);

        unset($this->kartu, $this->barcode);

        $this->jenis = $jenis;
        $this->id = $id;
        $this->terbuka = true;
    }

    public function tutup(): void
    {
        unset($this->kartu, $this->barcode);

        $this->reset('terbuka', 'jenis', 'id');
    }

    /**
     * Penjagaan akses tingkat komponen.
     *
     * Halaman yang memuat modal ini memang sudah dijaga middleware role,
     * tapi endpoint Livewire adalah alamat tersendiri (/livewire/update).
     * Sama seperti pelajaran di halaman Login: begitu sebuah aksi pindah ke
     * Livewire, penjagaan yang menempel di rute halaman tidak lagi ikut
     * melindunginya. Jadi kewenangannya diperiksa lagi di sini.
     *
     * Acuannya rute yang benar-benar dimiliki role tersebut — bukan daftar
     * role yang ditulis ulang — supaya kalau nanti pembagian menu berubah,
     * penjagaan ini ikut berubah sendiri.
     */
    private function pastikanBerwenang(string $jenis): void
    {
        $prefix = auth()->user()?->role?->routePrefix();

        abort_if($prefix === null, 403);

        $rute = $jenis === 'siswa'
            ? $prefix . '.siswa.index'
            : $prefix . '.pegawai.index';

        abort_unless(Route::has($rute), 403, 'Anda tidak berwenang melihat kartu ini.');
    }

    /**
     * Data kartu dalam bentuk seragam, supaya view tidak perlu bercabang
     * antara Siswa dan Pegawai di setiap barisnya.
     *
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function kartu(): ?array
    {
        if (! $this->terbuka || $this->id === null) {
            return null;
        }

        if ($this->jenis === 'siswa') {
            $siswa = Siswa::with(['kelas', 'waliMurid'])->find($this->id);

            if (! $siswa) {
                return null;
            }

            return [
                'jenis' => 'siswa',
                'judul' => 'Kartu Pelajar',
                'nama' => $siswa->nama,
                'labelKode' => 'NIS',
                'kode' => $siswa->nis,
                'labelKedua' => 'Kelas',
                'kedua' => $siswa->kelas?->nama_kelas ?? '—',
                // Foto siswa punya kolomnya sendiri (siswa.foto), diunggah
                // Super Admin lewat modal Edit Data Lengkap. Tidak menumpang
                // users.foto milik wali murid — kalau menumpang, wajah anak
                // akan muncul sebagai foto profil orang tuanya.
                'foto' => $siswa->foto_url,
                'berlaku' => TahunAjaran::yangAktif()?->label(),

                // Kontak & alamat di kartu siswa adalah milik WALI MURID —
                // itulah nomor yang dihubungi sekolah kalau terjadi apa-apa,
                // bukan nomor siswanya. no_hp_wali sudah ada di tabel siswa;
                // alamatnya menumpang dari akun wali murid yang tertaut
                // (kolom users.alamat), jadi tidak perlu kolom baru.
                'noHp' => $siswa->no_hp_wali ?: $siswa->waliMurid?->no_hp,
                // Alamat siswa kini punya kolom sendiri; alamat akun wali
                // murid dipakai sebagai cadangan untuk data lama yang belum
                // sempat dilengkapi lewat modal Edit Data Lengkap.
                'alamat' => $siswa->alamat ?: $siswa->waliMurid?->alamat,
            ];
        }

        $pegawai = Pegawai::with('user')->find($this->id);

        if (! $pegawai) {
            return null;
        }

        return [
            'jenis' => 'pegawai',
            'judul' => 'Kartu Pegawai',
            'nama' => $pegawai->nama,
            'labelKode' => 'NIP',
            'kode' => $pegawai->nip,   // bisa null sejak NIP jadi opsional
            'labelKedua' => 'Jabatan',
            'kedua' => $pegawai->jabatan,
            // Foto pegawai diambil dari akun user yang tertaut — itulah yang
            // ia unggah sendiri lewat halaman Profil Saya. Jadi mengganti foto
            // di sana langsung mengubah pas foto di kartunya, tanpa proses
            // terpisah.
            'foto' => $pegawai->user?->foto_url,
            'berlaku' => TahunAjaran::yangAktif()?->label(),
            'noHp' => $pegawai->no_hp ?: $pegawai->user?->no_hp,
            'alamat' => $pegawai->alamat ?: $pegawai->user?->alamat,
        ];
    }

    /*
     * CATATAN: properti terhitung `barcode` DIHAPUS dari sini.
     * Sejak kartu diubah jadi dua sisi, sisi depan tidak lagi memuat barcode
     * dan sisi belakang memakai QR. Kelas App\Support\Kode128 sengaja TIDAK
     * ikut dihapus: ia berdiri sendiri, sudah teruji baca-ulang 300/300, dan
     * langsung bisa dipakai lagi kalau barcode 1D dibutuhkan (mis. untuk
     * pemindai laser lama yang tidak bisa membaca QR).
     */

    public function render()
    {
        return view('livewire.kartu-identitas');
    }
}
