<?php

namespace App\Http\Controllers;

use App\Livewire\Laporan\RekapKbm;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Services\RekapKbmService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Unduhan PDF untuk Rekap KBM per Jadwal.
 *
 * ============ KENAPA DIBUAT SAAT DIMINTA, BUKAN DIARSIPKAN ============
 * Laporan bulanan otomatis disimpan sebagai berkas karena isinya selalu
 * sama: satu bulan penuh, tanpa saringan. Rekap ini kebalikannya — isinya
 * ditentukan periode + kelas + mapel + guru yang sedang dipilih di layar.
 * Mengarsipkannya berarti menyimpan berkas berbeda untuk setiap kombinasi
 * saringan, dan tidak ada seorang pun yang akan mencarinya lagi nanti.
 *
 * Konsekuensinya: rendering-nya terjadi di dalam satu permintaan web, jadi
 * batas MAKS_JADWAL & MAKS_HARI di RekapKbmService bukan sekadar formalitas.
 * ======================================================================
 */
class RekapKbmController extends Controller
{
    public function unduh(Request $request, RekapKbmService $service): Response
    {
        // Peran diperiksa ULANG di sini. Rute ini didaftarkan di dua grup
        // (kepsek dan super-admin), dan cukup satu grup baru yang lupa
        // memasang middleware-nya untuk membuka rekap seluruh sekolah.
        abort_unless(
            in_array($request->user()?->role, RekapKbm::PERAN_BOLEH, true),
            403,
            'Halaman ini hanya untuk Kepala Sekolah dan Super Admin.',
        );

        $data = $request->validate([
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'kelas' => ['nullable', 'integer', 'exists:kelas,id'],
            'mapel' => ['nullable', 'string', 'max:150'],
            'guru' => ['nullable', 'integer', 'exists:pegawai,id'],
        ]);

        $dari = isset($data['dari']) ? Carbon::createFromFormat('Y-m-d', $data['dari']) : now()->startOfMonth();
        $sampai = isset($data['sampai']) ? Carbon::createFromFormat('Y-m-d', $data['sampai']) : now();

        try {
            $rekap = $service->perJadwal([
                'dari' => $dari,
                'sampai' => $sampai,
                'kelas_id' => $data['kelas'] ?? null,
                'mapel' => $data['mapel'] ?? null,
                'guru_id' => $data['guru'] ?? null,
            ]);
        } catch (\Throwable $e) {
            // Batas rentang / jumlah jadwal. Dikembalikan sebagai 422 dengan
            // kalimat aslinya, bukan 500 — pesannya memang ditulis untuk
            // dibaca pengguna.
            abort(422, $e->getMessage());
        }

        // Label saringan ikut dicetak di kop PDF. Tanpa ini, dua cetakan
        // dengan saringan berbeda terlihat identik di atas meja.
        $rekap['saringan'] = array_values(array_filter([
            isset($data['kelas']) ? 'Kelas: ' . (Kelas::find($data['kelas'])?->nama_kelas ?? '—') : null,
            isset($data['mapel']) ? 'Mapel: ' . $data['mapel'] : null,
            isset($data['guru']) ? 'Guru: ' . (Pegawai::find($data['guru'])?->nama ?? '—') : null,
        ]));

        $isi = $service->render($rekap);

        $nama = 'Rekap-KBM-' . $dari->format('Ymd') . '-' . $sampai->format('Ymd') . '.pdf';

        // Header Content-Disposition dirakit Symfony lewat response()->download()
        // pengganti: di sini isinya sudah di memori, jadi dipakai response
        // biasa dengan header yang dibangun makeDisposition().
        $response = response($isi, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);

        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                $request->boolean('lihat') ? 'inline' : 'attachment',
                $nama,
            ),
        );

        return $response;
    }
}
