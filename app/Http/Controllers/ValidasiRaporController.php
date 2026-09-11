<?php

namespace App\Http\Controllers;

use App\Enums\StatusRapor;
use App\Enums\UserRole;
use App\Models\Kelas;
use App\Models\Nilai;
use App\Models\Penghargaan;
use App\Models\TahunAjaran;
use App\Models\ValidasiRapor;
use App\Services\BintangKelasService;
use App\Services\NotifikasiPenghargaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Alur penerbitan rapor: Draft -> Menunggu Persetujuan -> Disetujui.
 *
 * Siapa boleh apa:
 *   - Wali kelas & Admin TU : mengajukan rapor kelasnya.
 *   - Kepala sekolah        : menyetujui atau mengembalikan ke Draft.
 *   - Super Admin           : keduanya (ia pemilik sistem).
 */
class ValidasiRaporController extends Controller
{
    /** Peran yang boleh MENGAJUKAN rapor. */
    public const PERAN_PENGAJU = [UserRole::WaliKelas, UserRole::AdminTu, UserRole::SuperAdmin];

    /** Peran yang boleh MENYETUJUI rapor. */
    public const PERAN_PENYETUJU = [UserRole::Kepsek, UserRole::SuperAdmin];

    /**
     * Daftar kelas beserta status rapornya pada periode aktif.
     */
    public function index(Request $request): View
    {
        $this->pastikanBoleh($request, array_merge(self::PERAN_PENGAJU, self::PERAN_PENYETUJU));

        $periode = TahunAjaran::yangAktif();

        if (! $periode) {
            return view('rapor.validasi', [
                'periode' => null,
                'baris' => collect(),
                'bolehSetujui' => false,
                'bolehAjukan' => false,
            ]);
        }

        /*
         | Tiga query untuk SELURUH halaman, berapa pun jumlah kelasnya:
         |   1. daftar kelas (+ wali kelasnya, eager loaded)
         |   2. status rapor seluruh kelas pada periode ini
         |   3. jumlah nilai & siswa per kelas
         |
         | Kalau ketiganya diambil per kelas di dalam perulangan, halaman ini
         | menjadi 3N+1 query — dan justru halaman inilah yang dibuka kepala
         | sekolah saat semua wali kelas mengajukan rapor bersamaan.
         */
        $kelas = Kelas::query()
            /*
             | 'name', BUKAN 'nama'.
             |
             | Kelas::waliKelas() menunjuk ke User (bukan Pegawai), dan tabel
             | `users` memakai kolom `name`. Salah menyebut kolom di sini
             | TIDAK terlihat saat diuji dengan SQLite: pengenal yang dikutip
             | ganda dan tidak cocok dengan kolom mana pun diperlakukan SQLite
             | sebagai teks biasa, sehingga query-nya "berhasil" dan relasinya
             | cuma jadi null. MySQL menolaknya terang-terangan:
             |
             |   SQLSTATE[42S22]: Unknown column 'nama' in 'SELECT'
             |
             | Akibatnya halaman ini 500 di server padahal mulus di pengujian.
             */
            ->with('waliKelas:id,name')
            ->withCount('siswa')
            ->orderBy('nama_kelas')
            ->get();

        $rapor = ValidasiRapor::query()
            ->with(['penyetuju:id,name', 'pengaju:id,name'])
            ->where('tahun_ajaran', $periode->tahun)
            ->where('semester', $periode->semester)
            ->get()
            ->keyBy('kelas_id');

        // Jumlah nilai yang sudah masuk per kelas — supaya kepala sekolah tidak
        // menyetujui rapor yang isinya masih kosong.
        $jumlahNilai = Nilai::query()
            ->join('siswa', 'siswa.id', '=', 'nilais.siswa_id')
            ->where('nilais.tahun_ajaran', $periode->tahun)
            ->where('nilais.semester', $periode->semester)
            ->groupBy('siswa.kelas_id')
            ->select('siswa.kelas_id')
            ->selectRaw('COUNT(*) as jumlah')
            ->pluck('jumlah', 'kelas_id');

        $baris = $kelas->map(fn (Kelas $k) => [
            'kelas' => $k,
            'rapor' => $rapor->get($k->id),
            'status' => $rapor->get($k->id)?->status ?? StatusRapor::Draft,
            'jumlah_nilai' => (int) ($jumlahNilai[$k->id] ?? 0),
        ]);

        return view('rapor.validasi', [
            'periode' => $periode,
            'baris' => $baris,
            'bolehSetujui' => $this->punyaPeran($request, self::PERAN_PENYETUJU),
            'bolehAjukan' => $this->punyaPeran($request, self::PERAN_PENGAJU),
        ]);
    }

    /**
     * Mengajukan rapor satu kelas: Draft -> Menunggu Persetujuan.
     */
    public function ajukan(Request $request, Kelas $kelas): RedirectResponse
    {
        $this->pastikanBoleh($request, self::PERAN_PENGAJU);

        $periode = $this->periodeWajib();

        $rapor = ValidasiRapor::untuk($kelas->id, $periode->tahun, $periode->semester);

        if ($rapor->status === StatusRapor::Disetujui) {
            return back()->with('gagal', 'Rapor kelas ' . $kelas->nama_kelas . ' sudah disetujui dan tidak bisa diajukan ulang.');
        }

        // Rapor kosong tidak boleh diajukan. Tanpa penjagaan ini, kepala
        // sekolah bisa menyetujui kelas yang nilainya belum diisi sama sekali,
        // dan wali murid melihat rapor kosong yang sudah "resmi terbit".
        $adaNilai = Nilai::query()
            ->whereIn('siswa_id', $kelas->siswa()->select('id'))
            ->where('tahun_ajaran', $periode->tahun)
            ->where('semester', $periode->semester)
            ->exists();

        if (! $adaNilai) {
            return back()->with('gagal', 'Belum ada satu pun nilai di kelas ' . $kelas->nama_kelas . ' untuk periode ini.');
        }

        $rapor->fill([
            'status' => StatusRapor::Draft === $rapor->status || $rapor->status === StatusRapor::Menunggu
                ? StatusRapor::Menunggu
                : $rapor->status,
            'diajukan_oleh' => $request->user()->id,
            'diajukan_pada' => now(),
            'catatan' => null,
        ])->save();

        return back()->with('sukses', 'Rapor kelas ' . $kelas->nama_kelas . ' diajukan untuk persetujuan Kepala Sekolah.');
    }

    /**
     * Menyetujui rapor DAN menetapkan Bintang Kelas.
     *
     * ============ KENAPA SATU TRANSAKSI ============
     * Dua hal terjadi bersamaan: status rapor berubah menjadi Disetujui, dan
     * seorang siswa ditetapkan sebagai Bintang Kelas. Kalau yang kedua gagal
     * di tengah jalan (mis. kehabisan memori saat menghitung peringkat),
     * tanpa transaksi rapornya sudah terbit tetapi penghargaannya hilang —
     * dan tidak ada yang akan menyadarinya sampai orang tua bertanya.
     *
     * Pengiriman notifikasi SENGAJA ditaruh DI LUAR transaksi, sesudah commit:
     * panggilan jaringan ke Firebase bisa memakan waktu beberapa detik, dan
     * menahan transaksi database selama itu mengunci baris lebih lama dari
     * yang diperlukan.
     * ==============================================
     */
    public function setujuiRapor(
        Request $request,
        Kelas $kelas,
        BintangKelasService $bintang,
        NotifikasiPenghargaan $notifikasi,
    ): RedirectResponse {
        $this->pastikanBoleh($request, self::PERAN_PENYETUJU);

        $periode = $this->periodeWajib();

        $rapor = ValidasiRapor::untuk($kelas->id, $periode->tahun, $periode->semester);

        if ($rapor->status !== StatusRapor::Menunggu) {
            return back()->with('gagal',
                'Hanya rapor berstatus "Menunggu Persetujuan" yang bisa disetujui. '
                . 'Status kelas ' . $kelas->nama_kelas . ' saat ini: ' . $rapor->status->label() . '.');
        }

        /** @var Penghargaan|null $penghargaan */
        $penghargaan = null;

        DB::transaction(function () use ($rapor, $request, $kelas, $periode, $bintang, &$penghargaan) {
            $rapor->fill([
                'status' => StatusRapor::Disetujui,
                'disetujui_oleh' => $request->user()->id,
                'tanggal_persetujuan' => now(),
            ])->save();

            $penghargaan = $bintang->tetapkan($kelas, $periode->tahun, $periode->semester);
        });

        if ($penghargaan) {
            $notifikasi->kirim($penghargaan);
        }

        $pesan = 'Rapor kelas ' . $kelas->nama_kelas . ' disetujui dan sudah terbit untuk wali murid.';

        $pesan .= $penghargaan
            ? ' Bintang Kelas: ' . ($penghargaan->siswa?->nama ?? '—')
                . ' (rata-rata ' . number_format((float) $penghargaan->nilai_acuan, 2, ',', '.') . ').'
            : ' Bintang Kelas belum bisa ditetapkan — belum ada siswa dengan minimal '
                . BintangKelasService::MIN_NILAI . ' nilai, atau pemenangnya belum tertaut ke akun wali murid.';

        return back()->with('sukses', $pesan);
    }

    /**
     * Mengembalikan rapor ke Draft supaya nilainya bisa diperbaiki.
     */
    public function kembalikan(Request $request, Kelas $kelas): RedirectResponse
    {
        $this->pastikanBoleh($request, self::PERAN_PENYETUJU);

        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:255'],
        ]);

        $periode = $this->periodeWajib();
        $rapor = ValidasiRapor::untuk($kelas->id, $periode->tahun, $periode->semester);

        $rapor->fill([
            'status' => StatusRapor::Draft,
            'catatan' => $data['catatan'] ?? null,
            'disetujui_oleh' => null,
            'tanggal_persetujuan' => null,
        ])->save();

        return back()->with('sukses', 'Rapor kelas ' . $kelas->nama_kelas . ' dikembalikan ke Draft. Guru bisa memperbaiki nilainya.');
    }

    /* ===================== PEMBANTU ===================== */

    private function periodeWajib(): TahunAjaran
    {
        $periode = TahunAjaran::yangAktif();

        abort_unless($periode, 409, 'Belum ada Tahun Ajaran yang diaktifkan.');

        return $periode;
    }

    /** @param  array<int, UserRole>  $peran */
    private function punyaPeran(Request $request, array $peran): bool
    {
        return in_array($request->user()?->role, $peran, true);
    }

    /**
     * Hak akses diperiksa ULANG di setiap method, bukan hanya diandalkan pada
     * middleware rute: rute ini didaftarkan di beberapa grup peran sekaligus,
     * dan cukup satu grup baru yang lupa memasang middleware-nya untuk membuka
     * tombol "Setujui" bagi orang yang tidak berhak.
     *
     * @param  array<int, UserRole>  $peran
     */
    private function pastikanBoleh(Request $request, array $peran): void
    {
        abort_unless($this->punyaPeran($request, $peran), 403, 'Anda tidak berhak melakukan tindakan ini.');
    }
}
