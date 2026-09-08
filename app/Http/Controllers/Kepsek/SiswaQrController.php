<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;

class SiswaQrController extends Controller
{
    /**
     * Kartu QR satu siswa (QR berisi NIS-nya).
     */
    public function show(Siswa $siswa)
    {
        $siswa->loadMissing('kelas');

        return view('kepsek.siswa.qr', [
            'daftarSiswa' => collect([$siswa]),
            'judul' => $siswa->nama,
        ]);
    }

    /**
     * Cetak massal kartu QR untuk satu kelas sekaligus.
     */
    public function kelas(Kelas $kelas)
    {
        $daftarSiswa = $kelas->siswa()->orderBy('nama')->get();
        $daftarSiswa->each->setRelation('kelas', $kelas);

        return view('kepsek.siswa.qr', [
            'daftarSiswa' => $daftarSiswa,
            'judul' => $kelas->nama_kelas,
        ]);
    }
}
