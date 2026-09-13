<?php

namespace App\Http\Controllers\Kepsek;

use App\Enums\StatusApproval;
use App\Http\Controllers\Controller;
use App\Models\PengajuanIzinGuru;
use App\Services\PenerapIzinGuru;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Persetujuan Pengajuan Izin Khusus Guru (ITT / IDT).
 *
 * ============ KENAPA HALAMAN INI HARUS ADA ============
 * Halaman ini TIDAK diminta dalam spesifikasi, tapi tanpanya seluruh fitur
 * izin guru menjadi jalan buntu yang merusak data kehadiran.
 *
 * Alurnya: pengajuan dibuat berstatus 'Pending', dan yang menuliskan izin ke
 * `absensi_pegawai` HANYA persetujuan (App\Services\PenerapIzinGuru). Kalau
 * tidak ada satu pun layar yang bisa menyetujui, maka setiap pengajuan guru
 * mengendap selamanya di status Pending — dan absensi guru yang bersangkutan
 * tetap kosong, yang pada akhir bulan terhitung ALPA.
 *
 * Jadi: sebelum fitur ini ada, guru izin lalu tercatat izin. Kalau halaman
 * ini tidak dibuat, guru izin lalu tercatat alpa. Itu bukan fitur baru,
 * itu kemunduran.
 * =====================================================
 */
class PersetujuanIzinGuruController extends Controller
{
    /** Berapa banyak riwayat keputusan yang ikut ditampilkan. */
    private const BATAS_RIWAYAT = 25;

    /**
     * Daftar pengajuan: yang menunggu di atas, riwayat keputusan di bawah.
     */
    public function index(Request $request)
    {
        /*
         | Eager load 'guru:id,name' — kolomnya "name", BUKAN "nama".
         |
         | Tabel `users` memakai penamaan bawaan Laravel, sementara `siswa`
         | dan `pegawai` memakai "nama". Menyebut kolom yang salah di sini
         | LOLOS DIAM-DIAM di SQLite (dianggap literal string, relasinya
         | jadi null tanpa peringatan) tapi membalas 500 di MySQL produksi.
         | Penjaganya: tests/Feature/KolomEagerLoadTest.
         */
        $menunggu = PengajuanIzinGuru::query()
            ->with('guru:id,name')
            ->menunggu()
            ->orderBy('tanggal_mulai')
            ->get();

        $riwayat = PengajuanIzinGuru::query()
            ->with(['guru:id,name', 'penyetuju:id,name'])
            ->whereIn('status_approval', [
                StatusApproval::Disetujui->value,
                StatusApproval::Ditolak->value,
            ])
            ->latest('diputuskan_pada')
            ->limit(self::BATAS_RIWAYAT)
            ->get();

        return view('kepsek.persetujuan-izin-guru', [
            'menunggu' => $menunggu,
            'riwayat' => $riwayat,
            'panelPrefix' => $request->user()->role->routePrefix(),
        ]);
    }

    /**
     * Menyetujui pengajuan DAN menuliskannya ke absensi harian.
     */
    public function setujui(Request $request, PengajuanIzinGuru $izin, PenerapIzinGuru $penerap): RedirectResponse
    {
        $data = $request->validate([
            'catatan_penyetuju' => ['nullable', 'string', 'max:500'],
        ]);

        if ($gagal = $this->tolakKalauSudahDiputuskan($izin)) {
            return $gagal;
        }

        try {
            /*
             | Satu transaksi untuk DUA tulisan yang tidak boleh terpisah:
             | mengubah status pengajuan, dan menandai hari-harinya di
             | `absensi_pegawai`.
             |
             | Kalau keduanya berdiri sendiri lalu yang kedua gagal, hasilnya
             | adalah pengajuan berstatus "Disetujui" yang absensinya tidak
             | pernah berubah — persis keadaan yang paling sulit ditemukan,
             | karena di layar semuanya terlihat beres.
             */
            $hari = DB::transaction(function () use ($izin, $request, $data, $penerap) {
                $izin->forceFill([
                    'status_approval' => StatusApproval::Disetujui->value,
                    'penyetuju_id' => $request->user()->id,
                    'diputuskan_pada' => now(),
                    'catatan_penyetuju' => $data['catatan_penyetuju'] ?? null,
                ])->save();

                // refresh() supaya enum & tanggal di dalam $izin sudah dalam
                // bentuk yang dipakai PenerapIzinGuru (Carbon + enum), bukan
                // string mentah yang baru saja di-forceFill.
                return $penerap->terapkan($izin->refresh());
            });
        } catch (\Throwable $e) {
            Log::error('Gagal menyetujui pengajuan izin guru.', [
                'pengajuan_id' => $izin->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('gagal', 'Terjadi kesalahan saat menyimpan persetujuan. Tidak ada perubahan yang tersimpan — silakan coba lagi.');
        }

        /*
         | $hari === 0 berarti persetujuannya tersimpan tapi absensinya tidak
         | bisa ditulis — akun gurunya belum ditautkan ke data pegawai (lihat
         | PenerapIzinGuru). Dikatakan APA ADANYA, karena kalau tidak,
         | kepala sekolah menutup halaman ini dengan yakin urusannya selesai,
         | dan guru itu tetap terhitung alpa sampai akhir bulan.
         */
        if ($hari === 0) {
            return back()->with('gagal', sprintf(
                'Pengajuan %s DISETUJUI, tetapi absensinya belum bisa ditandai izin karena akun guru tersebut belum ditautkan ke data pegawai. '
                    . 'Minta admin menautkannya lebih dulu, lalu catat absensinya secara manual.',
                $izin->guru?->name ?? 'guru',
            ));
        }

        return back()->with('sukses', sprintf(
            'Pengajuan %s disetujui. %d hari kehadirannya ditandai izin.',
            $izin->guru?->name ?? 'Guru',
            $hari,
        ));
    }

    /**
     * Menolak pengajuan. Absensi TIDAK disentuh sama sekali.
     */
    public function tolak(Request $request, PengajuanIzinGuru $izin): RedirectResponse
    {
        $data = $request->validate(
            [
                // Wajib pada penolakan (berbeda dari persetujuan, yang
                // catatannya opsional): pengajuan yang ditolak tanpa satu
                // kalimat pun memaksa gurunya menebak apa yang salah, dan
                // pengajuan berikutnya kemungkinan besar salah lagi.
                'catatan_penyetuju' => ['required', 'string', 'min:5', 'max:500'],
            ],
            [
                'catatan_penyetuju.required' => 'Tuliskan alasan penolakannya supaya gurunya tahu apa yang harus diperbaiki.',
            ],
        );

        if ($gagal = $this->tolakKalauSudahDiputuskan($izin)) {
            return $gagal;
        }

        $izin->forceFill([
            'status_approval' => StatusApproval::Ditolak->value,
            'penyetuju_id' => $request->user()->id,
            'diputuskan_pada' => now(),
            'catatan_penyetuju' => $data['catatan_penyetuju'],
        ])->save();

        return back()->with('sukses', sprintf(
            'Pengajuan %s ditolak. Kehadirannya tidak diubah.',
            $izin->guru?->name ?? 'guru',
        ));
    }

    /**
     * Melayani lampiran tugas untuk penyetuju.
     *
     * Method terpisah dari milik guru (IzinGuruController::lampiran) dan itu
     * disengaja: yang di sana memeriksa "apakah ini lampiran ANDA SENDIRI",
     * aturan yang justru harus TIDAK berlaku di sini — kepala sekolah memang
     * perlu membuka lampiran milik guru lain untuk menilai pengajuannya.
     * Menggabungkan keduanya berarti salah satu aturan harus dilonggarkan,
     * dan yang dilonggarkan pasti yang di sisi guru.
     */
    public function lampiran(PengajuanIzinGuru $izin): StreamedResponse
    {
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

    /**
     * Penjaga keputusan ganda.
     *
     * Dua kepala sekolah (atau satu orang dengan dua tab terbuka) bisa
     * menekan tombol pada pengajuan yang SAMA. Tanpa pemeriksaan ini,
     * penolakan bisa menimpa persetujuan yang absensinya sudah terlanjur
     * ditulis — status berubah jadi 'Ditolak' sementara hari-harinya tetap
     * tertandai izin di absensi_pegawai.
     */
    private function tolakKalauSudahDiputuskan(PengajuanIzinGuru $izin): ?RedirectResponse
    {
        if ($izin->status_approval->bisaDiputuskan()) {
            return null;
        }

        return back()->with('gagal', sprintf(
            'Pengajuan ini sudah %s sebelumnya (oleh %s). Tidak ada yang diubah.',
            strtolower($izin->status_approval->label()),
            $izin->penyetuju?->name ?? 'pengguna lain',
        ));
    }
}
