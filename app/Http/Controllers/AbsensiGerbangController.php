<?php

namespace App\Http\Controllers;

use App\Enums\JenisIzin;
use App\Models\PencatatanIzin;
use App\Models\Siswa;
use App\Services\PemampatFoto;
use App\Services\PencatatIzin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Halaman Gerbang — scan kehadiran DAN pencatatan izin dalam satu layar.
 *
 * ============ KENAPA KEDUANYA DI SATU HALAMAN ============
 * Siswa di sekolah ini tidak membawa HP, jadi tidak ada jalur pengajuan izin
 * mandiri. Yang terjadi setiap pagi di gerbang: antrean anak men-scan kartu,
 * dan di sela-selanya seorang wali murid datang menyerahkan surat sakit.
 *
 * Kalau dua pekerjaan itu ada di dua halaman berbeda, petugas harus
 * berpindah halaman — dan selama perpindahan itu kameranya mati, antreannya
 * menumpuk. Satu layar berarti ia mencatat surat tanpa kehilangan scanner.
 * =========================================================
 */
class AbsensiGerbangController extends Controller
{
    /** Folder surat izin di dalam disk PRIVAT ('local'). */
    private const FOLDER_SURAT = 'surat-izin';

    /**
     * Halaman gerbang: area scan + form izin + daftar izin hari ini.
     */
    public function index(Request $request)
    {
        /*
         | Daftar siswa dan daftar izin TIDAK lagi dikirim dari sini.
         |
         | Keduanya kini milik komponen Livewire App\Livewire\Gerbang\FormIzin,
         | yang mencarinya sendiri dengan kata kunci dan membatasi hasilnya.
         | Mengirimkan ratusan siswa dari sini hanya akan membuat payload
         | Livewire membawa seluruh daftar itu bolak-balik pada SETIAP
         | interaksi berikutnya — bukan sekali saja saat halaman dibuka.
         */
        return view('piket.scan-gerbang', [
            'panelPrefix' => $request->user()->role->routePrefix(),
        ]);
    }

    /**
     * Menyimpan satu pencatatan izin dari petugas.
     */
    public function storeIzin(Request $request, PencatatIzin $pencatat): RedirectResponse
    {
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', 'exists:siswa,id'],
            'status' => ['required', 'string', Rule::in(array_column(JenisIzin::cases(), 'value'))],
            'keterangan' => ['nullable', 'string', 'max:500'],

            /*
             | ============ PENJAGAAN BERKAS UNGGAHAN ============
             | Empat lapis, dan tidak ada satu pun yang boleh dihapus:
             |
             |   file      -> harus benar-benar berkas terunggah
             |   image     -> Laravel memeriksa ISI berkas lewat getimagesize(),
             |                bukan sekadar ekstensinya. Berkas .php yang
             |                dinamai ulang jadi .jpg tertangkap di sini.
             |   mimes     -> daftar putih ekstensi. Sengaja TIDAK memuat svg:
             |                SVG adalah XML yang boleh berisi <script>, dan ia
             |                lolos pemeriksaan "image" di banyak konfigurasi.
             |   max:4096  -> 4 MB. Foto kamera HP setelah dipampatkan jauh di
             |                bawah ini; batas ini menahan unggahan yang
             |                dipakai untuk memenuhi kuota hosting.
             */
            'foto_surat' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [], [
            'siswa_id' => 'siswa',
            'foto_surat' => 'bukti surat',
        ]);

        $jenis = JenisIzin::from($data['status']);
        $siswa = Siswa::findOrFail($data['siswa_id']);
        $tanggal = today();

        // Sudah pernah dicatat hari ini? Ditolak di sini dengan pesan yang
        // jelas, supaya petugas tidak bertemu error constraint database yang
        // tidak berarti apa-apa baginya. Unique index di migrasi tetap ada
        // sebagai penjaga terakhir untuk dua petugas yang menyimpan bersamaan.
        if ($pencatat->sudahAda($siswa, $tanggal)) {
            return back()
                ->withInput()
                ->with('gagal', "{$siswa->nama} sudah punya catatan izin hari ini. Hapus atau ubah catatan lamanya lebih dulu.");
        }

        /*
         | ============ KEGAGALAN UNGGAH TIDAK BOLEH SENYAP ============
         | Disk 'local' di project ini diset 'throw' => false, artinya
         | Storage TIDAK melempar apa pun saat gagal menulis — ia hanya
         | mengembalikan false. Penyebab paling mungkin di hosting: folder
         | storage/app/private belum ada atau izinnya salah sesudah deploy.
         |
         | Tanpa pemeriksaan ini, akibatnya adalah kegagalan paling buruk
         | yang bisa terjadi pada fitur ini: petugas melampirkan foto surat
         | dokter, layarnya menjawab "tersimpan", dan buktinya tidak pernah
         | ada di mana pun. Baru ketahuan berbulan-bulan kemudian saat
         | seseorang mempertanyakan izin itu — dan saat itu suratnya sudah
         | lama dikembalikan ke orang tuanya.
         |
         | Lebih baik menolak seluruh pencatatannya dan meminta petugas
         | mengulang, daripada menyimpan izin tanpa bukti yang ia kira ada.
         */
        try {
            $jalurSurat = $this->simpanSurat($request);
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan berkas surat izin.', [
                'petugas_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('gagal', 'Berkas suratnya GAGAL disimpan, jadi izinnya belum dicatat sama sekali. Coba unggah ulang; kalau tetap gagal, hubungi admin (folder penyimpanan server kemungkinan bermasalah).');
        }

        try {
            // Seluruh logika simpan ada di App\Services\PencatatIzin —
            // dipakai bersama jalur Livewire. Dua salinan logika berarti
            // izin yang dicatat lewat satu pintu sampai ke guru, lewat pintu
            // lain tidak, tanpa error apa pun yang menjelaskan bedanya.
            $pencatat->catat($siswa, $request->user(), $jenis, $data['keterangan'] ?? null, $jalurSurat, $tanggal);
        } catch (\Throwable $e) {
            // Transaksinya batal, jadi tidak ada baris yang tersimpan — tapi
            // berkas suratnya sudah terlanjur ditulis ke disk. Dibuang di sini
            // supaya tidak menumpuk jadi berkas yatim yang tidak tertunjuk
            // baris mana pun dan tidak pernah ada yang membersihkan.
            if ($jalurSurat) {
                Storage::disk('local')->delete($jalurSurat);
            }

            Log::error('Gagal menyimpan pencatatan izin.', [
                'siswa_id' => $siswa->id,
                'petugas_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->with('gagal', 'Terjadi kesalahan saat menyimpan. Data TIDAK tersimpan — silakan coba lagi.');
        }

        return back()->with('sukses', sprintf(
            'Izin %s untuk %s tercatat. Status hari ini otomatis menjadi "%s" dan sudah terlihat oleh guru mata pelajaran.',
            $jenis->label(),
            $siswa->nama,
            $jenis->statusAbsensi()->label(),
        ));
    }

    /**
     * Menyimpan berkas surat, atau null kalau petugas tidak mengunggah apa pun.
     *
     * ============ KENAPA DISK PRIVAT, BUKAN 'public' ============
     * Berkas ini seringkali SURAT KETERANGAN DOKTER: berisi nama anak,
     * diagnosis, dan kop klinik. Itu data kesehatan seorang anak di bawah umur.
     *
     * Disk 'public' berarti berkasnya dilayani langsung oleh web server tanpa
     * melewati Laravel sama sekali — siapa pun yang memegang URL-nya bisa
     * membukanya, termasuk orang yang tidak pernah punya akun. Nama berkas
     * acak memang membuatnya sulit ditebak, tapi "sulit ditebak" bukan kendali
     * akses: satu tautan yang tidak sengaja tersalin ke grup WhatsApp sudah
     * cukup untuk menyebarkannya selamanya.
     *
     * Disk 'local' tidak bisa dijangkau dari web. Satu-satunya jalan membuka
     * berkasnya adalah lewat suratIzin() di bawah, yang berjalan SESUDAH
     * middleware auth & role.
     *
     * ============ KENAPA NAMANYA DIGANTI ============
     * Nama asli dari HP orang tua tidak pernah dipakai. Dua alasan:
     * nama asli bisa memuat karakter yang menyulitkan di server, dan yang
     * lebih penting — nama yang bisa ditebak membuat surat milik anak lain
     * bisa dibuka dengan menerka jalur.
     */
    private function simpanSurat(Request $request): ?string
    {
        if (! $request->hasFile('foto_surat')) {
            return null;
        }

        $berkas = $request->file('foto_surat');
        $ekstensi = $berkas->extension();

        /*
         | Dipampatkan SELAGI MASIH BERKAS SEMENTARA, sebelum dipindah ke
         | penyimpanan tetap. Berkas sementara memang dirancang untuk dibuang,
         | jadi menulisinya di tempat tidak berisiko; kalau pemampatannya
         | gagal, yang tersimpan tinggal foto aslinya.
         |
         | getRealPath() hanya masuk akal untuk disk lokal, dan ia melempar
         | kalau suatu saat disk unggahan sementara dipindah ke S3 — itu tidak
         | boleh ikut menggagalkan unggahannya.
         */
        try {
            $jalurSementara = $berkas->getRealPath();
        } catch (\Throwable $e) {
            $jalurSementara = null;
        }

        if (is_string($jalurSementara) && PemampatFoto::keJpeg($jalurSementara)) {
            // Isinya sekarang JPEG, jadi ekstensinya harus ikut berubah —
            // berkas .png yang isinya JPEG akan dilayani dengan Content-Type
            // yang salah saat diunduh.
            $ekstensi = 'jpg';
        }

        $jalur = $berkas->storeAs(
            self::FOLDER_SURAT,
            (string) Str::uuid() . '.' . $ekstensi,
            'local',
        );

        // MELEMPAR, bukan mengembalikan null. Mengembalikan null di sini akan
        // membuat izinnya tetap tersimpan tanpa bukti — lihat catatan panjang
        // di storeIzin(). Pemanggilnya yang memutuskan pesannya.
        if ($jalur === false || blank($jalur)) {
            throw new \RuntimeException('Storage menolak menyimpan berkas surat izin.');
        }

        return $jalur;
    }

    /**
     * Melayani berkas surat dari disk privat.
     *
     * Rutenya berada di dalam grup middleware auth + role, jadi sampai di
     * sini pemanggilnya sudah pasti petugas/admin yang berwenang. Yang tersisa
     * hanyalah memastikan berkasnya memang ada.
     *
     * file() memakai $jalurPenuh dan bukan jalur relatif karena response ini
     * membaca langsung dari filesystem, di luar abstraksi Storage.
     */
    public function suratIzin(PencatatanIzin $izin): StreamedResponse
    {
        abort_unless($izin->suratAda(), 404, 'Berkas suratnya tidak ditemukan.');

        // Nama unduhan dibuat berarti bagi manusia — "surat-izin-Budi-2026-09-13.jpg"
        // jauh lebih berguna di folder Unduhan daripada UUID acak.
        $namaUnduh = Str::slug(sprintf(
            'surat-%s-%s-%s',
            $izin->status->value,
            $izin->siswa?->nama ?? 'siswa',
            $izin->tanggal->format('Y-m-d'),
        )) . '.' . pathinfo($izin->foto_surat, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($izin->foto_surat, $namaUnduh, [
            // inline: dibuka di tab browser, bukan langsung terunduh —
            // petugas biasanya hanya ingin MELIHAT suratnya sekilas.
            'Content-Disposition' => 'inline; filename="' . $namaUnduh . '"',
        ]);
    }
}
