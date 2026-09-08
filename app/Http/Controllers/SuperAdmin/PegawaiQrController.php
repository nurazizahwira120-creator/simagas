<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;

class PegawaiQrController extends Controller
{
    /**
     * Kartu QR satu pegawai (QR berisi NIP-nya), dipakai Guru Piket untuk
     * absen-masuk lewat mode "Pegawai" di halaman scanner.
     */
    public function show(Pegawai $pegawai)
    {
        return view('super-admin.pegawai.qr', [
            'daftarPegawai' => collect([$pegawai]),
            'judul' => $pegawai->nama,
        ]);
    }

    /**
     * Cetak massal kartu QR untuk seluruh pegawai sekaligus.
     */
    public function semua()
    {
        return view('super-admin.pegawai.qr', [
            'daftarPegawai' => Pegawai::orderBy('nama')->get(),
            'judul' => 'Semua Pegawai',
        ]);
    }
}
