<?php

namespace App\Http\Controllers\Ortu;

use App\Enums\JenisPenilaian;
use App\Http\Controllers\Controller;
use App\Models\Nilai;
use App\Models\Penghargaan;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\ValidasiRapor;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Rapor anak untuk wali murid.
 *
 * ============ SATU ATURAN YANG MENENTUKAN SEGALANYA ============
 * Nilai hanya ditampilkan kalau rapor kelas anak sudah berstatus DISETUJUI
 * pada semester itu. Selama masih Draft atau Menunggu Persetujuan, halaman ini
 * berkata "rapor belum terbit" — bukan menampilkan angka setengah jadi.
 *
 * Kalau aturan ini dilewat, orang tua akan melihat nilai yang masih diubah
 * guru, menyimpan tangkapan layarnya, lalu mempertanyakan angka yang berbeda
 * saat rapor resmi terbit. Itu masalah kepercayaan, bukan masalah teknis.
 * ===============================================================
 */
class RaporWaliMuridController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        /*
         | Eager load kelas sekaligus: tanpa `with('kelas')`, setiap kali view
         | menyebut $anak->kelas->nama_kelas akan lahir satu query baru — dan
         | orang tua dengan tiga anak mendapat tiga query tambahan hanya untuk
         | menulis nama kelas.
         */
        $daftarAnak = Siswa::query()
            ->with('kelas:id,nama_kelas')
            ->where('wali_murid_id', $user->id)
            ->orderBy('nama')
            ->get(['id', 'nis', 'nama', 'kelas_id']);

        $anak = $daftarAnak->firstWhere('id', (int) $request->query('anak'))
            ?? $daftarAnak->first();

        // Pilihan semester diambil dari tabel tahun_ajaran (sumber tunggal),
        // bukan dirangkai sendiri dari tahun berjalan.
        $daftarPeriode = TahunAjaran::query()
            ->orderByDesc('tahun')
            ->orderBy('semester')
            ->get();

        $periode = $daftarPeriode->firstWhere('id', (int) $request->query('periode'))
            ?? TahunAjaran::yangAktif()
            ?? $daftarPeriode->first();

        if (! $anak || ! $periode) {
            return view('wali-murid.rapor', [
                'daftarAnak' => $daftarAnak,
                'anak' => $anak,
                'daftarPeriode' => $daftarPeriode,
                'periode' => $periode,
                'rapor' => null,
                'baris' => collect(),
                'rataKeseluruhan' => null,
                'penghargaan' => null,
                'jenisPenilaian' => JenisPenilaian::urut(),
            ]);
        }

        $rapor = ValidasiRapor::untuk($anak->kelas_id, $periode->tahun, $periode->semester);

        $baris = collect();
        $rataKeseluruhan = null;

        if ($rapor->status->terbitKeWaliMurid()) {
            /*
             | SATU query untuk seluruh nilai anak pada semester ini, lengkap
             | dengan mata pelajarannya (eager load) — lalu dikelompokkan di
             | PHP menjadi satu baris per mata pelajaran.
             |
             | Tanpa `with('mapel')`, tabel berisi 12 mata pelajaran akan
             | memicu 12 query tambahan hanya untuk menuliskan namanya.
             */
            $nilai = Nilai::query()
                ->with('mapel:id,nama')
                ->where('siswa_id', $anak->id)
                ->where('tahun_ajaran', $periode->tahun)
                ->where('semester', $periode->semester)
                ->get();

            $baris = $nilai
                ->groupBy('mapel_id')
                ->map(function ($perMapel) {
                    $skor = $perMapel->pluck('skor', 'jenis_penilaian.value')
                        ->map(fn ($s) => (float) $s);

                    return [
                        'mapel' => $perMapel->first()->mapel?->nama ?? '(mata pelajaran terhapus)',
                        'skor' => $skor,
                        'rata' => round($perMapel->avg(fn (Nilai $n) => (float) $n->skor), 2),
                    ];
                })
                ->sortBy('mapel')
                ->values();

            $rataKeseluruhan = $nilai->isEmpty()
                ? null
                : round($nilai->avg(fn (Nilai $n) => (float) $n->skor), 2);
        }

        /*
         | Apresiasi hanya diambil kalau rapornya sudah terbit — sama seperti
         | nilainya. Banner "Bintang Kelas" yang muncul sebelum rapor resmi
         | keluar akan membocorkan hasil peringkat lebih dulu.
         */
        $penghargaan = $rapor->status->terbitKeWaliMurid()
            ? Penghargaan::query()
                ->with('siswa:id,nama')
                ->where('user_id', $user->id)
                ->where('siswa_id', $anak->id)
                ->where('periode', Penghargaan::periodeSemester($periode->tahun, $periode->semester))
                ->first()
            : null;

        return view('wali-murid.rapor', [
            'daftarAnak' => $daftarAnak,
            'anak' => $anak,
            'daftarPeriode' => $daftarPeriode,
            'periode' => $periode,
            'rapor' => $rapor,
            'baris' => $baris,
            'rataKeseluruhan' => $rataKeseluruhan,
            'penghargaan' => $penghargaan,
            'jenisPenilaian' => JenisPenilaian::urut(),
        ]);
    }
}
