<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\Request;

/**
 * Pusat Bantuan & Panduan Penggunaan SIMAGAS.
 *
 * ============ KENAPA TANPA TABEL, TAPI TETAP PUNYA CONTROLLER ============
 * Halamannya statis — tidak ada satu pun query di sini. Route::view() saja
 * sebenarnya cukup untuk menampilkannya.
 *
 * Yang membuat controller ini tetap ada adalah SATU hal: tab mana yang
 * terbuka pertama kali. Guru yang membuka bantuan hampir pasti mencari
 * jawaban tentang pekerjaannya sendiri, bukan tentang Master Data. Membuka
 * tab yang tidak relevan berarti setiap orang harus mencari dulu sebelum
 * membaca — dan halaman bantuan yang perlu dipelajari cara membacanya sudah
 * gagal sejak baris pertama.
 * ========================================================================
 */
class PanduanController extends Controller
{
    public function index(Request $request)
    {
        $peran = $request->user()?->role;

        return view('panduan.index', [
            'tabAwal' => $this->tabUntuk($peran),
            'peranAktif' => $peran,
            'panelPrefix' => $peran?->routePrefix() ?? '',
        ]);
    }

    /**
     * Memetakan peran pengguna ke tab yang paling berguna baginya.
     *
     * Nilainya sengaja berupa string sederhana yang sama persis dengan
     * penanda tab di view — bukan enum baru. Menambah enum untuk empat
     * string yang hanya dipakai satu halaman statis menambah berkas yang
     * harus dirawat tanpa menambah satu pun jaminan.
     */
    private function tabUntuk(?UserRole $peran): string
    {
        return match ($peran) {
            UserRole::SuperAdmin, UserRole::Kepsek => 'admin',

            // Wali kelas ikut ke tab Guru: ia mengajar, mengisi jurnal, dan
            // memakai jalur izin ITT/IDT yang sama.
            UserRole::Guru, UserRole::WaliKelas => 'guru',

            UserRole::GuruPiket => 'piket',
            UserRole::WaliMurid => 'ortu',

            /*
             | Staff & Admin TU belum punya tabnya sendiri. Diarahkan ke tab
             | Piket karena keduanya ikut memegang halaman Gerbang — bukan
             | ke 'admin', yang isinya wewenang yang justru tidak mereka
             | punya dan akan membingungkan kalau dibaca sebagai instruksi.
             */
            default => 'piket',
        };
    }
}
