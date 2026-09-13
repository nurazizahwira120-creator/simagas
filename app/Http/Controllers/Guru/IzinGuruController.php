<?php

namespace App\Http\Controllers\Guru;

use App\Enums\JenisIzinGuru;
use App\Http\Controllers\Controller;
use App\Models\PengajuanIzinGuru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengajuan Izin Khusus Guru — ITT & IDT.
 *
 * ============ KENAPA GURU PUNYA FORM IZIN SENDIRI ============
 * Form izin pegawai yang lama (App\Livewire\Pegawai\FormIzin) tetap dipakai
 * staff, admin TU, dan kepala sekolah. Bentuknya sederhana karena memang
 * cukup: satu tanggal, satu alasan, selesai.
 *
 * Guru berbeda dalam satu hal yang menentukan: ia MEMEGANG KELAS. Saat ia
 * tidak masuk, ada 30 anak yang tetap datang dan menunggu di ruangan. Yang
 * perlu diketahui sekolah bukan sekadar "gurunya izin", melainkan "kelasnya
 * bagaimana" — dan itulah yang dibedakan ITT dan IDT.
 * =============================================================
 */
class IzinGuruController extends Controller
{
    /** Folder lampiran tugas di dalam disk PRIVAT ('local'). */
    private const FOLDER_TUGAS = 'tugas-izin-guru';

    /**
     * Batas kewajaran tanggal pengajuan, dalam hari.
     *
     * MUNDUR_MAKS mencegah "memperbaiki" rekap bulan lampau setelah laporan
     * dikirim; MAJU_MAKS mencegah salah ketik tahun (2027 alih-alih 2026)
     * diam-diam masuk basis data dan mengunci absensi setahun ke depan.
     * Angkanya sengaja sama dengan form izin pegawai yang lama.
     */
    private const MUNDUR_MAKS = 30;

    private const MAJU_MAKS = 90;

    /** Rentang terpanjang satu pengajuan, dalam hari. */
    private const RENTANG_MAKS = 30;

    public function create(Request $request)
    {
        // Riwayat pengajuan guru ini sendiri.
        //
        // Tanpa daftar ini, form-nya jadi lubang tanpa dasar: guru mengirim
        // pengajuan, statusnya Pending, dan tidak ada satu pun cara baginya
        // mengetahui apakah sudah disetujui selain bertanya langsung ke
        // kepala sekolah.
        $riwayat = PengajuanIzinGuru::query()
            ->with('penyetuju:id,name')
            ->where('guru_id', $request->user()->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('guru.create-izin-guru', [
            'jenisIzin' => JenisIzinGuru::semua(),
            'riwayat' => $riwayat,
            'panelPrefix' => $request->user()->role->routePrefix(),
            'mundurMaks' => self::MUNDUR_MAKS,
            'majuMaks' => self::MAJU_MAKS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(
            [
                'tanggal_mulai' => [
                    'required',
                    'date',
                    'after_or_equal:' . now()->subDays(self::MUNDUR_MAKS)->toDateString(),
                    'before_or_equal:' . now()->addDays(self::MAJU_MAKS)->toDateString(),
                ],

                // after_or_equal memakai NAMA FIELD lain, bukan tanggal tetap:
                // aturannya mengikuti apa pun yang diisi pengguna. Tanpa ini,
                // izin "10 s.d. 3 September" tersimpan rapi dan baru terlihat
                // aneh saat rekap bulanan menghasilkan jumlah hari negatif.
                'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],

                'jenis_izin' => ['required', Rule::in(array_column(JenisIzinGuru::cases(), 'value'))],
                'alasan' => ['required', 'string', 'min:10', 'max:1000'],

                /*
                 | ============ INTI ATURAN ITT vs IDT ============
                 | required_if: detail_tugas WAJIB begitu jenisnya IDT, dan
                 | boleh kosong untuk ITT.
                 |
                 | Ditegakkan DI SERVER, bukan hanya disembunyikan di form.
                 | Toggle Alpine di view hanya menyembunyikan elemennya — siapa
                 | pun yang mengirim POST langsung (atau yang JavaScript-nya
                 | gagal dimuat) tetap bisa mengirim IDT tanpa tugas. Dan izin
                 | berlabel "dengan tugas" yang tidak menyebutkan tugasnya
                 | adalah ITT yang menyamar: piket berdiri di depan kelas
                 | tanpa tahu harus menyampaikan apa.
                 |
                 | min:5 dipasang supaya "-" atau "ada" tidak lolos sebagai
                 | tugas — secara teknis terisi, tapi sama tidak bergunanya
                 | dengan kosong.
                 */
                'detail_tugas' => ['nullable', 'required_if:jenis_izin,' . JenisIzinGuru::Idt->value, 'string', 'min:5', 'max:2000'],

                /*
                 | Lampiran tugas — opsional bahkan untuk IDT, karena tugas
                 | sering cukup dituliskan ("LKS halaman 40") tanpa berkas.
                 |
                 | Berbeda dari surat izin siswa yang hanya menerima gambar,
                 | di sini PDF ikut diterima: lembar kerja paling sering
                 | berbentuk PDF. Daftar putihnya tetap ketat — tidak ada
                 | .doc/.zip yang bisa membawa makro atau isi sembarangan.
                 */
                'file_tugas' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:8192'],
            ],
            [
                'detail_tugas.required_if' => 'Deskripsi tugas wajib diisi untuk IDT (Izin Dengan Tugas). Kalau memang tidak ada tugas, pilih ITT.',
                'alasan.min' => 'Tuliskan alasannya lebih jelas — minimal 10 karakter.',
                'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal daripada tanggal mulai.',
            ],
            [
                'tanggal_mulai' => 'tanggal mulai',
                'tanggal_selesai' => 'tanggal selesai',
                'jenis_izin' => 'jenis izin',
                'detail_tugas' => 'deskripsi tugas',
                'file_tugas' => 'lampiran tugas',
            ],
        );

        $mulai = \Illuminate\Support\Carbon::parse($data['tanggal_mulai']);
        $selesai = \Illuminate\Support\Carbon::parse($data['tanggal_selesai']);

        // Rentang terlalu panjang hampir selalu salah ketik tanggal, bukan
        // izin sungguhan. Diperiksa di sini karena tidak ada aturan validasi
        // bawaan yang membandingkan JARAK antara dua tanggal.
        if ($mulai->diffInDays($selesai) + 1 > self::RENTANG_MAKS) {
            return back()->withInput()->withErrors([
                'tanggal_selesai' => 'Rentang izin maksimal ' . self::RENTANG_MAKS . ' hari. Periksa lagi tanggalnya.',
            ]);
        }

        $jenis = JenisIzinGuru::from($data['jenis_izin']);

        try {
            $jalurTugas = $this->simpanLampiran($request);
        } catch (\Throwable $e) {
            Log::error('Gagal menyimpan lampiran tugas izin guru.', [
                'guru_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with(
                'gagal',
                'Lampiran tugasnya GAGAL disimpan, jadi pengajuannya belum dikirim sama sekali. Coba unggah ulang; kalau tetap gagal, hubungi admin.',
            );
        }

        try {
            PengajuanIzinGuru::create([
                'guru_id' => $request->user()->id,
                'tanggal_mulai' => $mulai->toDateString(),
                'tanggal_selesai' => $selesai->toDateString(),
                'jenis_izin' => $jenis->value,
                'alasan' => $data['alasan'],

                // ITT tidak boleh membawa sisa tugas dari isian sebelumnya.
                // Petugas bisa saja mengetik tugas, lalu berpindah ke ITT
                // tanpa mengosongkannya — dan browser tetap mengirim nilainya
                // karena elemennya hanya DISEMBUNYIKAN, bukan dihapus.
                'detail_tugas' => $jenis->butuhTugas() ? ($data['detail_tugas'] ?? null) : null,
                'file_tugas' => $jenis->butuhTugas() ? $jalurTugas : null,

                // status_approval memakai default 'Pending' dari migrasi.
            ]);
        } catch (\Throwable $e) {
            // Barisnya gagal dibuat, tapi berkasnya sudah terlanjur ditulis.
            // Dibuang supaya tidak menumpuk jadi berkas yatim yang tidak
            // tertunjuk baris mana pun dan tidak pernah ada yang membersihkan.
            if ($jalurTugas) {
                Storage::disk('local')->delete($jalurTugas);
            }

            Log::error('Gagal menyimpan pengajuan izin guru.', [
                'guru_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withInput()->with('gagal', 'Terjadi kesalahan saat menyimpan. Pengajuan TIDAK terkirim — silakan coba lagi.');
        }

        // ITT yang sempat mengunggah lampiran: berkasnya dibuang, karena
        // kolomnya sengaja tidak diisi di atas.
        if ($jalurTugas && ! $jenis->butuhTugas()) {
            Storage::disk('local')->delete($jalurTugas);
        }

        return back()->with('sukses', sprintf(
            'Pengajuan %s untuk %s terkirim dan menunggu persetujuan kepala sekolah. '
                . 'Kehadiran Anda BELUM berubah sampai pengajuannya disetujui.',
            $jenis->kode(),
            $mulai->isSameDay($selesai)
                ? $mulai->translatedFormat('d F Y')
                : $mulai->translatedFormat('d M') . ' – ' . $selesai->translatedFormat('d M Y'),
        ));
    }

    /**
     * Menyimpan lampiran tugas, atau null kalau tidak ada yang diunggah.
     *
     * ============ KENAPA DISK PRIVAT ============
     * Lembar kerja dan soal ujian adalah materi internal sekolah. Disk
     * 'public' berarti berkasnya dilayani web server tanpa melewati Laravel
     * sama sekali — siapa pun yang memegang URL-nya bisa mengunduhnya,
     * termasuk siswa yang seharusnya baru menerimanya besok pagi.
     *
     * Nama berkasnya juga diganti UUID: nama asli yang bisa ditebak
     * ("soal-uts-xi-rpl.pdf") membuat berkas kelas lain bisa dibuka dengan
     * menerka jalur.
     *
     * @throws \RuntimeException kalau disk menolak menyimpan
     */
    private function simpanLampiran(Request $request): ?string
    {
        if (! $request->hasFile('file_tugas')) {
            return null;
        }

        $berkas = $request->file('file_tugas');

        $jalur = $berkas->storeAs(
            self::FOLDER_TUGAS,
            (string) Str::uuid() . '.' . $berkas->extension(),
            'local',
        );

        /*
         | MELEMPAR, bukan mengembalikan null.
         |
         | Disk 'local' di project ini diset 'throw' => false, artinya Storage
         | TIDAK melempar apa pun saat gagal menulis — ia hanya mengembalikan
         | false. Mengembalikan null di sini berarti pengajuannya tetap
         | terkirim tanpa lampiran: gurunya yakin sudah melampirkan soal, dan
         | piket keesokan paginya tidak menemukan apa pun.
         */
        if ($jalur === false || blank($jalur)) {
            throw new \RuntimeException('Storage menolak menyimpan lampiran tugas.');
        }

        return $jalur;
    }

    /**
     * Melayani berkas lampiran dari disk privat.
     *
     * Hak aksesnya diperiksa DI SINI, bukan hanya oleh middleware rute:
     * rute ini terdaftar di grup guru, jadi middleware-nya hanya memastikan
     * "yang membuka adalah seorang guru" — bukan "guru yang bersangkutan".
     * Tanpa pemeriksaan di bawah, guru mana pun bisa membuka soal ujian yang
     * dilampirkan guru lain dengan menebak id pengajuan.
     */
    public function lampiran(Request $request, PengajuanIzinGuru $izin): StreamedResponse
    {
        abort_unless($izin->guru_id === $request->user()->id, 403, 'Ini bukan lampiran Anda.');
        abort_unless($izin->lampiranAda(), 404, 'Berkas lampirannya tidak ditemukan.');

        $namaUnduh = Str::slug(sprintf(
            'tugas-%s-%s',
            $izin->jenis_izin->kode(),
            $izin->tanggal_mulai->format('Y-m-d'),
        )) . '.' . pathinfo($izin->file_tugas, PATHINFO_EXTENSION);

        return Storage::disk('local')->response($izin->file_tugas, $namaUnduh, [
            'Content-Disposition' => 'inline; filename="' . $namaUnduh . '"',
        ]);
    }
}
