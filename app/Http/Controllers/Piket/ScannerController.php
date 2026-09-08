<?php

namespace App\Http\Controllers\Piket;

use App\Enums\AbsensiStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppNotification;
use App\Models\AbsensiPegawai;
use App\Models\AbsensiSiswa;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Services\NotifikasiKehadiran;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScannerController extends Controller
{
    /**
     * Halaman scanner untuk guru piket — satu kamera, satu endpoint AJAX
     * yang otomatis mengenali apakah kode yang di-scan itu NIS siswa atau
     * NIP pegawai (lihat store()). Guru piket tidak perlu memilih mode.
     */
    public function index(Request $request)
    {
        return view('piket.scanner', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Endpoint AJAX TUNGGAL yang dipanggil frontend setiap kali sebuah kode
     * (barcode/QR atau input manual) berhasil ditangkap. Logikanya hybrid:
     *
     *   1. Cari kode itu di tabel `siswa` (kolom nis).
     *      -> Ketemu: catat jam masuk ke `absensi_siswa`, antrekan job
     *         notifikasi WhatsApp ke wali murid, lalu SELESAI (tidak lanjut
     *         dicoba sebagai pegawai).
     *   2. Kalau TIDAK ketemu sebagai siswa, baru dicoba dicari di tabel
     *      `pegawai` (kolom nip).
     *      -> Ketemu: catat jam masuk ke `absensi_pegawai`. TIDAK
     *         mengantrekan notifikasi WhatsApp — job itu khusus memberi
     *         tahu wali murid, tidak relevan untuk kehadiran pegawai.
     *   3. Kalau dua-duanya tidak ketemu -> 404, kode tidak dikenali sama
     *      sekali.
     *
     * Urutan "siswa dulu, baru pegawai" sengaja dipilih: populasi siswa
     * jauh lebih besar daripada pegawai, jadi secara statistik lookup
     * pertama ini yang paling sering langsung cocok.
     *
     * Response JSON selalu membawa `entitas` ('siswa' | 'pegawai' | null)
     * supaya layar HP guru piket bisa menampilkan label yang tepat
     * ("Siswa" atau "Pegawai") di samping nama yang baru saja tercatat.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kode' => ['required', 'string', 'max:50'],
        ]);

        $kode = trim($validated['kode']);

        $siswa = Siswa::where('nis', $kode)->first();

        if ($siswa) {
            return $this->catatKehadiranSiswa($siswa);
        }

        $pegawai = Pegawai::where('nip', $kode)->first();

        if ($pegawai) {
            return $this->catatKehadiranPegawai($pegawai);
        }

        return response()->json([
            'success' => false,
            'status' => 'not_found',
            'entitas' => null,
            'message' => "Kode \"{$kode}\" tidak dikenali — tidak ditemukan sebagai NIS siswa maupun NIP pegawai.",
        ], 404);
    }

    private function catatKehadiranSiswa(Siswa $siswa): JsonResponse
    {
        $absensiHariIni = AbsensiSiswa::where('siswa_id', $siswa->id)
            ->whereDate('tanggal', today())
            ->first();

        if ($absensiHariIni) {
            // jam_masuk bisa NULL kalau catatan hari ini dibuat manual oleh
            // wali kelas (upsert dari dashboard-nya menulis jam_masuk = null),
            // jadi tidak boleh langsung di-format tanpa dicek.
            $jamMasuk = $absensiHariIni->jam_masuk?->format('H:i');

            // Statusnya juga belum tentu "Hadir" — bisa saja wali kelas sudah
            // menandainya Izin/Sakit/Alpa lebih dulu. Pesannya mengikuti status
            // yang sebenarnya supaya guru piket tidak salah paham.
            $message = $absensiHariIni->status === AbsensiStatus::Hadir
                ? "{$siswa->nama} (Siswa) sudah tercatat hadir hari ini" . ($jamMasuk ? " pukul {$jamMasuk}." : '.')
                : "{$siswa->nama} (Siswa) sudah punya catatan absensi hari ini: {$absensiHariIni->status->label()}.";

            return response()->json([
                'success' => false,
                'status' => 'duplicate',
                'entitas' => 'siswa',
                'message' => $message,
                'data' => [
                    'nama' => $siswa->nama,
                    'keterangan' => $siswa->kelas?->nama_kelas,
                    'jam_masuk' => $jamMasuk,
                ],
            ], 409);
        }

        try {
            $absensi = AbsensiSiswa::create([
                'siswa_id' => $siswa->id,
                'tanggal' => today(),
                'jam_masuk' => now()->format('H:i:s'),
                'status' => AbsensiStatus::Hadir,
            ]);
        } catch (QueryException $e) {
            // SQLSTATE 23000 = integrity constraint violation. Paling mungkin
            // dua scan kode yang sama nyaris bersamaan menabrak
            // unique(siswa_id, tanggal) di tabel absensi_siswa.
            if ((string) $e->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'status' => 'duplicate',
                    'entitas' => 'siswa',
                    'message' => "{$siswa->nama} sudah tercatat hadir hari ini.",
                ], 409);
            }

            Log::error('Gagal menyimpan absensi siswa.', [
                'nis' => $siswa->nis,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'entitas' => 'siswa',
                'message' => 'Terjadi kesalahan saat menyimpan absensi. Coba lagi.',
            ], 500);
        }

        // Insert sukses -> antrekan notifikasi WhatsApp ke wali murid lewat
        // queue, tidak menahan respons AJAX ke layar guru piket. Satu panggilan
        // ke gateway bisa memakan beberapa detik; kalau dikerjakan di sini,
        // guru piket menatap layar menunggu tiap kali satu siswa lewat.
        $this->antrekanNotifikasiHadir($siswa, $absensi);

        // Notifikasi HP (Web Push) ke wali murid. Jalur KEDUA yang
        // sepenuhnya terpisah dari WhatsApp di atas: WhatsApp butuh kuota
        // gateway dan nomor yang terdaftar, push tidak butuh keduanya.
        // Keduanya sengaja dipertahankan — wali murid yang belum memasang
        // aplikasi tetap dapat WhatsApp, yang sudah memasang dapat
        // pemberitahuan seketika di layar kuncinya.
        //
        // relationLoaded dicek supaya query wali murid tidak dijalankan
        // dua kali: antrekanNotifikasiHadir() di atas sudah menyentuhnya.
        $siswa->loadMissing('waliMurid');
        app(NotifikasiKehadiran::class)->siswaMasuk($siswa, $absensi);

        return response()->json([
            'success' => true,
            'status' => 'ok',
            'entitas' => 'siswa',
            'message' => "{$siswa->nama} (Siswa) berhasil dicatat hadir.",
            'data' => [
                'nama' => $siswa->nama,
                'keterangan' => $siswa->kelas?->nama_kelas,
                'jam_masuk' => $absensi->jam_masuk->format('H:i'),
            ],
        ]);
    }

    private function catatKehadiranPegawai(Pegawai $pegawai): JsonResponse
    {
        $absensiHariIni = AbsensiPegawai::where('pegawai_id', $pegawai->id)
            ->whereDate('tanggal', today())
            ->first();

        if ($absensiHariIni) {
            // Sama seperti di catatKehadiranSiswa(): jam_masuk bisa NULL dan
            // statusnya belum tentu "Hadir" kalau sudah diinput manual.
            $jamMasuk = $absensiHariIni->jam_masuk?->format('H:i');

            $message = $absensiHariIni->status === AbsensiStatus::Hadir
                ? "{$pegawai->nama} (Pegawai) sudah tercatat hadir hari ini" . ($jamMasuk ? " pukul {$jamMasuk}." : '.')
                : "{$pegawai->nama} (Pegawai) sudah punya catatan absensi hari ini: {$absensiHariIni->status->label()}.";

            return response()->json([
                'success' => false,
                'status' => 'duplicate',
                'entitas' => 'pegawai',
                'message' => $message,
                'data' => [
                    'nama' => $pegawai->nama,
                    'keterangan' => $pegawai->jabatan,
                    'jam_masuk' => $jamMasuk,
                ],
            ], 409);
        }

        try {
            $absensi = AbsensiPegawai::create([
                'pegawai_id' => $pegawai->id,
                'tanggal' => today(),
                'jam_masuk' => now()->format('H:i:s'),
                'status' => AbsensiStatus::Hadir,
            ]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return response()->json([
                    'success' => false,
                    'status' => 'duplicate',
                    'entitas' => 'pegawai',
                    'message' => "{$pegawai->nama} sudah tercatat hadir hari ini.",
                ], 409);
            }

            Log::error('Gagal menyimpan absensi pegawai.', [
                'nip' => $pegawai->nip,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'status' => 'error',
                'entitas' => 'pegawai',
                'message' => 'Terjadi kesalahan saat menyimpan absensi. Coba lagi.',
            ], 500);
        }

        // TIDAK dispatch SendWhatsAppNotification di sini — job itu khusus
        // memberi tahu wali murid, tidak relevan untuk kehadiran pegawai.
        //
        // Notifikasi HP TETAP dikirim, tapi ke pegawainya SENDIRI: bukti
        // terima yang tersimpan di riwayat notifikasi HP-nya, menjawab
        // pertanyaan "tadi absen saya masuk tidak ya?" yang paling sering
        // ditanyakan setelah scan di gerbang.
        $pegawai->loadMissing('user');
        app(NotifikasiKehadiran::class)->pegawaiMasuk($pegawai, $absensi);

        return response()->json([
            'success' => true,
            'status' => 'ok',
            'entitas' => 'pegawai',
            'message' => "{$pegawai->nama} (Pegawai) berhasil dicatat hadir.",
            'data' => [
                'nama' => $pegawai->nama,
                'keterangan' => $pegawai->jabatan,
                'jam_masuk' => $absensi->jam_masuk->format('H:i'),
            ],
        ]);
    }

    /**
     * Antrekan notifikasi "anak Anda sudah tiba" ke wali murid.
     *
     * Nomor tujuannya diambil bertingkat, dari yang paling tepat:
     *   1. siswa.no_hp_wali          — nomor yang khusus didaftarkan sekolah
     *   2. akun wali murid tertaut   — users.no_hp
     * Kalau dua-duanya kosong, notifikasinya dilewati dengan catatan di log,
     * bukan membuat scan-nya gagal: kehadiran anak sudah tercatat, dan itu
     * yang utama.
     */
    private function antrekanNotifikasiHadir(Siswa $siswa, AbsensiSiswa $absensi): void
    {
        $tujuan = $siswa->no_hp_wali ?: $siswa->waliMurid?->no_hp;

        if (blank($tujuan)) {
            Log::warning('Notifikasi WhatsApp dilewati: wali murid tidak punya nomor HP.', [
                'siswa_id' => $siswa->id,
                'absensi_id' => $absensi->id,
            ]);

            return;
        }

        $pesan = sprintf(
            'INFO SIMAGAS: Ananda %s telah HADIR di sekolah pada %s pukul %s. Terima kasih.',
            $siswa->nama,
            $absensi->tanggal->translatedFormat('d F Y'),
            $absensi->jam_masuk->format('H:i'),
        );

        SendWhatsAppNotification::dispatch((string) $tujuan, $pesan);
    }
}
