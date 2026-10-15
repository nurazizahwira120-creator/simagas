<?php

namespace App\Http\Controllers;

use App\Enums\Hari;
use App\Enums\UserRole;
use App\Models\AbsensiMengajar;
use App\Models\SesiEkskul;
use App\Services\MonitoringKbmHarian;
use App\Services\PenutupSesi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Monitoring KBM HARI INI (dulu "Live Monitoring" yang hanya menampilkan
 * jam yang sedang berjalan) + "Bantu Akhiri Sesi".
 *
 * Eksklusif Kepala Sekolah & Super Admin. Rutenya dibatasi middleware role
 * di routes/web.php, dan aksi "Bantu Akhiri Sesi" memeriksa perannya
 * SEKALI LAGI di sini — aksi itu menentukan honor guru.
 *
 * Seluruh perhitungan status ada di App\Services\MonitoringKbmHarian;
 * penutupan sesinya di App\Services\PenutupSesi — jalur yang SAMA dengan
 * tombol "Akhiri Sesi" milik guru, jadi waktu selesai, JP, dan honornya
 * dihitung dengan rumus yang sama.
 */
class MonitoringController extends Controller
{
    public function index(Request $request, MonitoringKbmHarian $monitoring)
    {
        $sekarang = Carbon::now();
        $data = $monitoring->hariIni($sekarang);

        return view('monitoring.live', [
            'baris' => $data['baris'],
            'ringkas' => $data['ringkas'],
            'menggantung' => $monitoring->menggantung($sekarang),
            'hariIni' => Hari::hariIni(),
            'sekarang' => $sekarang,
            'toleransi' => MonitoringKbmHarian::TOLERANSI_MENIT,
            'bolehAkhiri' => $this->bolehMengakhiri($request),
        ]);
    }

    /**
     * BANTU AKHIRI SESI — menutup sesi kelas/ekskul yang lupa diakhiri.
     *
     * Hanya sesi yang sudah di-scan QR dan belum diakhiri, dari hari ini
     * sampai 7 hari ke belakang. Alasan wajib diisi; tercatat bersama nama
     * penutupnya, dan guru/pembina menerima notifikasi.
     */
    public function akhiriSesi(Request $request, string $jenis, int $id, MonitoringKbmHarian $monitoring, PenutupSesi $penutup): RedirectResponse
    {
        abort_unless($this->bolehMengakhiri($request), 403);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'alasan.required' => 'Tulis alasan singkat mengapa sesi ini diakhiri.',
            'alasan.min' => 'Alasan terlalu singkat (minimal 5 huruf).',
        ]);

        $palingAwal = now()->subDays(MonitoringKbmHarian::HARI_MENGGANTUNG)->startOfDay();
        $oleh = $request->user();

        if ($jenis === 'ekskul') {
            $sesi = SesiEkskul::with('jadwal')->findOrFail($id);
            abort_if($sesi->jadwal === null || $sesi->waktu_mulai->lt($palingAwal), 404);

            $hasil = $sesi->sudahSelesai()
                ? ['berhasil' => false]
                : $penutup->akhiriEkskul($sesi, $sesi->jadwal, $oleh, $data['alasan']);
            $judul = 'Ekskul ' . $sesi->jadwal->nama_ekskul;
        } else {
            $sesi = AbsensiMengajar::with('user:id,name')->findOrFail($id);
            abort_if($sesi->waktu_mulai->lt($palingAwal), 404);

            $jadwal = $monitoring->jadwalUntukSesi($sesi);
            $hasil = $sesi->sudahSelesai()
                ? ['berhasil' => false]
                : $penutup->akhiriKbm($sesi, $jadwal, $oleh, $data['alasan']);
            $judul = $jadwal ? $jadwal->mata_pelajaran . ' (' . ($jadwal->kelas?->nama_kelas ?? '-') . ')' : 'Sesi ' . $sesi->kode_kelas;
        }

        if (! $hasil['berhasil']) {
            return back()->with('notif_monitoring', ['tipe' => 'warn', 'judul' => 'Sudah diakhiri',
                'pesan' => "{$judul} ternyata sudah diakhiri sebelumnya. Tidak ada yang diubah."]);
        }

        return back()->with('notif_monitoring', ['tipe' => 'ok', 'judul' => 'Sesi diakhiri',
            'pesan' => "{$judul} ditutup pukul " . $hasil['waktu_selesai']->format('H:i')
                . ($hasil['honor'] ? ' · honor ' . $hasil['honor']->jp . ' JP tercatat' : '')
                . '. Guru/pembina sudah diberi tahu.']);
    }

    private function bolehMengakhiri(Request $request): bool
    {
        return in_array($request->user()?->role, [UserRole::SuperAdmin, UserRole::Kepsek], true);
    }
}
