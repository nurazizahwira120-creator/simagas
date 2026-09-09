<?php

namespace App\Http\Controllers;

use App\Models\Rpp;
use App\Policies\RppPolicy;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manajemen RPP (Rencana Pelaksanaan Pembelajaran) berbasis berkas PDF.
 *
 * SATU controller untuk dua peran, bukan GuruRppController +
 * KepsekRppController. Keduanya membaca tabel yang sama dengan aturan yang
 * sama; yang berbeda hanya CAKUPAN datanya (milik sendiri vs semua) dan APA
 * yang boleh dilakukan. Memecahnya jadi dua berarti menyalin query, validasi,
 * dan pemeriksaan hak akses dua kali — dan perbaikan di satu berkas diam-diam
 * tidak ikut di berkas satunya. Perbedaannya cukup ditangani RppPolicy.
 */
class RppController extends Controller
{
    /*
     | AuthorizesRequests di-use DI SINI, bukan diandalkan dari kelas induk.
     |
     | Sejak Laravel 11 kerangka App\Http\Controllers\Controller dibuat
     | KOSONG — trait ini tidak lagi ikut secara otomatis seperti di Laravel
     | 10. Tanpa baris di bawah, setiap $this->authorize() melempar
     | "Call to undefined method ...::authorize()" begitu halamannya dibuka.
     */
    use AuthorizesRequests;

    /** Batas ukuran unggahan dalam kilobyte (5 MB). */
    private const MAKS_KB = 5120;

    /** Folder di dalam disk 'public'. */
    private const FOLDER = 'rpp';

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Rpp::class);

        $pengguna = $request->user();
        $pengawas = RppPolicy::adalahPengawas($pengguna);

        $daftar = Rpp::query()
            ->with('user:id,name')
            // Pengawas melihat semua; guru DIBATASI DI QUERY, bukan disaring
            // di Blade. Kalau penyaringannya di tampilan, judul RPP guru lain
            // sudah terlanjur terkirim ke browser dan cukup satu salah tulis
            // di view untuk membocorkannya.
            ->when(! $pengawas, fn ($q) => $q->where('user_id', $pengguna->id))
            ->when($request->filled('cari'), function ($q) use ($request) {
                $kunci = trim((string) $request->query('cari'));

                $q->where(function ($w) use ($kunci) {
                    $w->where('judul_rpp', 'like', '%' . $kunci . '%')
                        ->orWhere('mata_pelajaran', 'like', '%' . $kunci . '%')
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%' . $kunci . '%'));
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view($pengawas ? 'rpp.index_kepsek' : 'rpp.index', [
            'daftar' => $daftar,
            'cari' => (string) $request->query('cari', ''),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Rpp::class);

        return view('rpp.create', [
            // Batas PHP di server sering LEBIH KECIL dari batas aplikasi.
            // Ditampilkan ke pengguna karena gejalanya kalau tidak: berkas 4 MB
            // ditolak dengan pesan "file wajib diisi" — yang menyesatkan, sebab
            // berkasnya memang tidak pernah sampai ke PHP.
            'batasServer' => $this->batasUnggahServer(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Rpp::class);

        $data = $request->validate([
            'judul_rpp' => ['required', 'string', 'min:4', 'max:150'],
            'mata_pelajaran' => ['required', 'string', 'min:2', 'max:100'],
            'file_rpp' => ['required', 'file', 'mimes:pdf', 'max:' . self::MAKS_KB],
        ], [
            'judul_rpp.required' => 'Judul RPP wajib diisi.',
            'judul_rpp.min' => 'Judul RPP terlalu pendek — tulis minimal 4 karakter.',
            'mata_pelajaran.required' => 'Mata pelajaran wajib diisi.',
            'file_rpp.required' => 'Berkas PDF wajib dipilih.',
            'file_rpp.file' => 'Unggahan gagal diterima server. Coba berkas yang lebih kecil.',
            'file_rpp.mimes' => 'Berkas harus berformat PDF.',
            'file_rpp.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);

        $berkas = $request->file('file_rpp');

        /*
         | Pemeriksaan TANDA TANGAN berkas, bukan cuma ekstensi.
         |
         | Aturan mimes:pdf mengandalkan ekstensi dan tebakan MIME dari
         | ekstensi PHP fileinfo. Di sebagian hosting cPanel fileinfo tidak
         | aktif dan tebakannya jadi longgar, sehingga berkas apa pun yang
         | dinamai ".pdf" lolos. Empat byte pertama PDF selalu "%PDF", dan
         | membacanya tidak bergantung pada ekstensi PHP apa pun.
         */
        if (! $this->benarBenarPdf($berkas->getRealPath())) {
            return back()
                ->withInput($request->except('file_rpp'))
                ->withErrors(['file_rpp' => 'Berkas ini bukan PDF yang sah, meski namanya berakhiran .pdf.']);
        }

        /*
         | Nama di disk DIACAK, bukan memakai nama asli unggahan.
         |
         | Nama asli datang dari pengguna: bisa mengandung "../", karakter yang
         | bikin repot di Linux, atau sama persis dengan berkas guru lain
         | sehingga saling menimpa. Nama aslinya sendiri tidak hilang — judul
         | RPP-nya tersimpan di kolom tersendiri, dan nama unduhan dirangkai
         | dari judul itu (lihat Rpp::namaUnduhan()).
         */
        $namaDisk = Str::uuid()->toString() . '.pdf';

        $jalur = $berkas->storeAs(self::FOLDER, $namaDisk, 'public');

        if ($jalur === false) {
            return back()
                ->withInput($request->except('file_rpp'))
                ->withErrors(['file_rpp' => 'Berkas gagal disimpan. Pastikan folder storage/ bisa ditulis (chmod 755).']);
        }

        Rpp::create([
            'user_id' => $request->user()->id,
            'judul_rpp' => trim($data['judul_rpp']),
            'mata_pelajaran' => trim($data['mata_pelajaran']),
            'file_path' => $jalur,
        ]);

        return redirect()
            ->route($this->panelPrefix() . '.rpp.index')
            ->with('sukses', 'RPP "' . trim($data['judul_rpp']) . '" berhasil diunggah.');
    }

    public function show(Rpp $rpp): View
    {
        $this->authorize('view', $rpp);

        return view('rpp.show', [
            'rpp' => $rpp->load('user:id,name'),
            'adaBerkas' => Storage::disk('public')->exists($rpp->file_path),
        ]);
    }

    /**
     * Mengalirkan berkas PDF-nya — INI yang dipasang di <embed>/<iframe>,
     * bukan asset('storage/...').
     *
     * ============ KENAPA TIDAK asset('storage/'.$file_path) ============
     * Dua alasan, keduanya nyata di hosting cPanel:
     *
     *   1. TAUTAN PUBLIK ITU TIDAK TERKUNCI. Berkas di public/storage bisa
     *      dibuka siapa pun yang tahu (atau menebak) URL-nya, tanpa login.
     *      RPP adalah dokumen kerja guru; "URL-nya tidak dibagikan" bukan
     *      kontrol akses. Lewat rute ini, setiap permintaan berkas melewati
     *      middleware auth + RppPolicy yang sama dengan halamannya.
     *
     *   2. TIDAK BERGANTUNG PADA storage:link. Symlink public/storage sering
     *      gagal dibuat di cPanel (open_basedir, atau public_html yang
     *      terpisah dari folder aplikasi) dan hasilnya PDF tidak pernah
     *      tampil — layar viewer kosong tanpa pesan apa pun. Rute ini membaca
     *      berkasnya langsung dari storage/app/public.
     * ==================================================================
     */
    public function berkas(Request $request, Rpp $rpp): StreamedResponse
    {
        $this->authorize('view', $rpp);

        $disk = Storage::disk('public');

        abort_unless($disk->exists($rpp->file_path), 404, 'Berkas RPP tidak ditemukan di server.');

        // inline = tampil di viewer; attachment = dipaksa terunduh.
        $mode = $request->boolean('unduh') ? 'attachment' : 'inline';

        // Header Content-Disposition-nya DIBIARKAN DIRAKIT LARAVEL (argumen
        // keempat), bukan dirangkai sendiri dengan tanda kutip.
        //
        // Nama berkasnya dirangkai dari judul RPP yang diketik guru. Begitu di
        // dalamnya ada tanda kutip, koma, atau huruf beraksen, header rakitan
        // tangan menjadi tidak sah — dan browser yang menerima header rusak
        // TIDAK menampilkan pesan error apa pun: ia hanya menolak membuka
        // PDF-nya. makeDisposition() milik Symfony menangani pelolosan
        // karakter dan menyediakan filename* (RFC 5987) untuk nama non-ASCII.
        return $disk->response($rpp->file_path, $rpp->namaUnduhan(), [
            'Content-Type' => 'application/pdf',

            // Berkas bisa diganti dengan yang baru di id yang sama; tanpa ini
            // browser bisa menahan versi lama di cache dan guru mengira
            // unggahannya gagal.
            'Cache-Control' => 'private, max-age=0, must-revalidate',

            // Sebagian proxy hosting memaksa "nosniff". Dengan tipe yang sudah
            // kita nyatakan sendiri di atas, header ini aman dan justru
            // memastikan browser memakai application/pdf apa adanya.
            'X-Content-Type-Options' => 'nosniff',
        ], $mode);
    }

    public function destroy(Rpp $rpp): RedirectResponse
    {
        $this->authorize('delete', $rpp);

        $judul = $rpp->judul_rpp;
        $jalur = $rpp->file_path;

        // Baris database dihapus DULU, berkasnya menyusul.
        //
        // Urutan ini disengaja: kalau penghapusan berkas gagal (izin folder,
        // berkas sudah hilang duluan), yang tertinggal cuma satu berkas yatim
        // di disk — tidak terlihat siapa pun dan tidak merusak apa pun.
        // Urutan sebaliknya menghasilkan baris yang masih tampil di daftar
        // tapi berkasnya sudah tidak ada: guru mengklik "Lihat" dan mendapat
        // 404 tanpa tahu kenapa.
        $rpp->delete();

        try {
            Storage::disk('public')->delete($jalur);
        } catch (\Throwable $e) {
            Log::warning('Berkas RPP gagal dihapus dari disk.', [
                'file_path' => $jalur,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route($this->panelPrefix() . '.rpp.index')
            ->with('sukses', 'RPP "' . $judul . '" telah dihapus.');
    }

    /* ===================== PEMBANTU ===================== */

    // panelPrefix() diwarisi dari App\Http\Controllers\Controller —
    // mengembalikan 'guru', 'kepsek', 'wali-kelas', dst. sesuai peran yang
    // sedang login, supaya redirect selalu pulang ke panel yang benar.

    private function benarBenarPdf(string|false $jalurAsli): bool
    {
        if ($jalurAsli === false || ! is_readable($jalurAsli)) {
            return false;
        }

        $pembuka = file_get_contents($jalurAsli, false, null, 0, 5);

        return $pembuka === '%PDF-';
    }

    /**
     * Batas unggahan yang benar-benar berlaku di server ini, dalam MB.
     * Diambil dari nilai TERKECIL antara upload_max_filesize, post_max_size,
     * dan batas aplikasi — karena yang menolak duluan adalah yang terkecil.
     */
    private function batasUnggahServer(): float
    {
        $keBytes = function (string $nilai): float {
            $nilai = trim($nilai);

            if ($nilai === '' || $nilai === '-1' || $nilai === '0') {
                return INF;
            }

            $satuan = strtolower(substr($nilai, -1));
            $angka = (float) $nilai;

            return match ($satuan) {
                'g' => $angka * 1024 * 1024 * 1024,
                'm' => $angka * 1024 * 1024,
                'k' => $angka * 1024,
                default => $angka,
            };
        };

        $batas = min(
            $keBytes((string) ini_get('upload_max_filesize')),
            $keBytes((string) ini_get('post_max_size')),
            self::MAKS_KB * 1024,
        );

        return round($batas / 1024 / 1024, 1);
    }
}
