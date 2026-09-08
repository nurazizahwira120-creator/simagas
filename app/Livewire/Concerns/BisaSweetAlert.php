<?php

namespace App\Livewire\Concerns;

/**
 * Pintasan memanggil SweetAlert2 dari komponen Livewire.
 *
 * Sisi JavaScript-nya ada di resources/views/partials/sweetalert.blade.php
 * (dimuat di semua halaman lewat partials/head-assets).
 *
 * ============ KENAPA ADA TRAIT, BUKAN dispatch() LANGSUNG ============
 * Yang dikirim ke browser bukan cuma judul dan teks: ada `komponen`
 * (ID komponen Livewire) yang harus benar supaya jalur baliknya menemukan
 * komponen yang tepat. Menuliskannya manual di tiap komponen berarti
 * mengulang $this->getId() di belasan tempat, dan satu yang salah ketik
 * menghasilkan tombol "Ya" yang ditekan lalu TIDAK TERJADI APA-APA —
 * kegagalan yang tidak melempar error apa pun.
 * ====================================================================
 */
trait BisaSweetAlert
{
    /**
     * Kotak pesan biasa (pengganti alert bawaan browser).
     *
     * @param  string  $ikon  success | error | warning | info | question
     */
    protected function swalPesan(string $judul, string $teks = '', string $ikon = 'success'): void
    {
        $this->dispatch('swal:modal', judul: $judul, teks: $teks, ikon: $ikon);
    }

    /** Notifikasi kecil di pojok kanan atas, hilang sendiri. */
    protected function swalToast(string $judul, string $ikon = 'success'): void
    {
        $this->dispatch('swal:toast', judul: $judul, ikon: $ikon);
    }

    /**
     * Tanya dulu, baru jalankan method komponen ini.
     *
     * Contoh pemakaian di dalam komponen:
     *
     *     public function mintaSetujui(int $id): void
     *     {
     *         $this->swalKonfirmasi('approveAccount', [$id],
     *             judul: 'Setujui Akun Ini?',
     *             teks: 'Akun wali murid ini akan diaktifkan...',
     *             ya: 'Ya, Setujui!');
     *     }
     *
     * PENTING soal keamanan: dialog ini hanya lapisan tampilan. Method
     * tujuannya ($metode) adalah endpoint HTTP tersendiri yang bisa
     * dipanggil langsung dari konsol browser tanpa melewati dialog mana
     * pun — jadi pemeriksaan hak akses tetap WAJIB ditulis di dalam
     * method itu, bukan di sini.
     *
     * @param  array<int, mixed>  $params  Argumen untuk $metode.
     */
    protected function swalKonfirmasi(
        string $metode,
        array $params = [],
        string $judul = 'Yakin?',
        string $teks = '',
        string $ikon = 'warning',
        string $ya = 'Ya, Lanjutkan!',
        string $batal = 'Batal',
    ): void {
        $this->dispatch(
            'swal:confirm',
            judul: $judul,
            teks: $teks,
            ikon: $ikon,
            ya: $ya,
            batal: $batal,
            komponen: $this->getId(),
            metode: $metode,
            params: array_values($params),
        );
    }
}
