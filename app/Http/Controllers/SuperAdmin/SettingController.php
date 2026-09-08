<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\StatusAkun;
use App\Http\Controllers\Controller;
use App\Models\Pengaturan;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pengaturan Sistem: aturan waktu absensi, titik koordinat & radius GPS,
 * persetujuan akun baru, dan tahun ajaran aktif.
 */
class SettingController extends Controller
{
    /** Kunci pengaturan waktu beserta nilai bawaannya. */
    private const KUNCI_WAKTU = [
        'jam_masuk_siswa' => '07:00',
        'batas_terlambat_siswa' => '07:15',
        'jam_masuk_pegawai' => '06:45',
        'batas_terlambat_pegawai' => '07:00',
    ];

    /**
     * Titik pusat sekolah & radius toleransi GPS.
     *
     * PENYIMPANANNYA SUDAH PINDAH. Sejak migration 000020, koordinat sekolah
     * tinggal di kolom latitude/longitude/radius_meter pada tabel
     * `pengaturan_sistem` — tempat yang sama yang ditulis peta pemilih titik
     * di tab "Identitas & WhatsApp", dan tempat yang dibaca AbsenGuru.
     *
     * Tab ini SENGAJA dibiarkan hidup dan diarahkan ke penyimpanan yang sama,
     * bukan dibiarkan menulis ke tabel `pengaturan` seperti dulu. Kalau
     * dibiarkan, admin yang menyimpan dari tab ini akan melihat pesan
     * "berhasil disimpan" sementara Absen Radius tetap memakai titik lama —
     * kegagalan tanpa satu pun tanda di layar.
     *
     * Nilai bawaan di bawah HANYA placeholder (Monas, Jakarta). WAJIB diganti
     * dengan koordinat sekolah yang sebenarnya, kalau tidak fitur Absen
     * Radius akan menolak semua pegawai.
     */
    private const KUNCI_LOKASI = [
        'lat_sekolah' => '-6.175392',
        'lng_sekolah' => '106.827153',
        'radius_gps' => '50',
    ];

    /**
     * Baca koordinat dari sumber yang berlaku (pengaturan_sistem), dengan
     * bentuk array yang sama seperti dulu supaya view-nya tidak perlu diubah.
     *
     * @return array{lat_sekolah: string, lng_sekolah: string, radius_gps: string}
     */
    private function lokasiSekolah(): array
    {
        $p = \App\Models\PengaturanSistem::ambil();

        return [
            'lat_sekolah' => (string) ($p->latitude ?: self::KUNCI_LOKASI['lat_sekolah']),
            'lng_sekolah' => (string) ($p->longitude ?: self::KUNCI_LOKASI['lng_sekolah']),
            'radius_gps' => (string) ($p->radius_meter ?: self::KUNCI_LOKASI['radius_gps']),
        ];
    }

    // Tab 'sistem' dilayani komponen Livewire App\Livewire\SuperAdmin\PengaturanSistem
    // dan TIDAK punya method update di controller ini — form-nya menyimpan
    // sendiri lewat Livewire. Kunci-nya tetap harus terdaftar di sini supaya
    // ?tab=sistem tidak dianggap tidak sah lalu dilempar balik ke tab pertama.
    private const TAB_SAH = ['waktu', 'lokasi', 'approval', 'tahun', 'sistem'];

    public function index(Request $request)
    {
        $tab = in_array($request->query('tab'), self::TAB_SAH, true)
            ? $request->query('tab')
            : 'waktu';

        return view('super-admin.pengaturan', [
            'tabAktif' => $tab,
            'waktu' => Pengaturan::ambilBanyak(self::KUNCI_WAKTU),
            'lokasi' => $this->lokasiSekolah(),

            'akunPending' => User::query()
                ->where('status', StatusAkun::Pending)
                ->orderBy('created_at')
                ->get(),

            'daftarTahunAjaran' => TahunAjaran::query()
                ->orderByDesc('tahun')
                ->orderBy('semester')
                ->get(),
            'tahunAktif' => TahunAjaran::yangAktif(),
        ]);
    }

    /**
     * Tab 1 — jam masuk & batas terlambat.
     */
    public function updateWaktu(Request $request): RedirectResponse
    {
        // <input type="time"> umumnya mengirim "HH:MM", sebagian browser
        // menyertakan detik. Dipangkas dulu supaya date_format tidak menolak
        // input yang sebenarnya sah.
        $request->merge(
            collect(self::KUNCI_WAKTU)
                ->keys()
                ->mapWithKeys(fn ($kunci) => [
                    $kunci => Str::substr((string) $request->input($kunci), 0, 5),
                ])
                ->all()
        );

        $validated = $request->validate([
            'jam_masuk_siswa' => ['required', 'date_format:H:i'],
            'batas_terlambat_siswa' => ['required', 'date_format:H:i', 'after_or_equal:jam_masuk_siswa'],
            'jam_masuk_pegawai' => ['required', 'date_format:H:i'],
            'batas_terlambat_pegawai' => ['required', 'date_format:H:i', 'after_or_equal:jam_masuk_pegawai'],
        ], [
            'batas_terlambat_siswa.after_or_equal' => 'Batas terlambat siswa tidak boleh lebih awal daripada jam masuknya.',
            'batas_terlambat_pegawai.after_or_equal' => 'Batas terlambat guru/staff tidak boleh lebih awal daripada jam masuknya.',
        ]);

        foreach ($validated as $kunci => $nilai) {
            Pengaturan::simpan($kunci, $nilai);
        }

        return $this->kembali('waktu', 'Aturan waktu absensi berhasil disimpan.');
    }

    /**
     * Tab 2 — titik koordinat sekolah & radius toleransi GPS.
     * Dipakai oleh fitur Absen Radius (menghitung jarak HP guru ke sekolah).
     */
    public function updateLokasi(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lat_sekolah' => ['required', 'numeric', 'between:-90,90'],
            'lng_sekolah' => ['required', 'numeric', 'between:-180,180'],
            'radius_gps' => ['required', 'integer', 'between:10,5000'],
        ], [
            'lat_sekolah.between' => 'Latitude harus di antara -90 sampai 90.',
            'lng_sekolah.between' => 'Longitude harus di antara -180 sampai 180.',
            'radius_gps.between' => 'Radius harus di antara 10 sampai 5000 meter. Di bawah 10 m tidak realistis karena GPS ponsel sendiri punya galat sekitar 5–20 m.',
        ]);

        // Ditulis ke pengaturan_sistem — sumber yang sama dengan peta di tab
        // "Identitas & WhatsApp" dan yang dibaca AbsenGuru.
        \App\Models\PengaturanSistem::ambil()->fill([
            'latitude' => (string) $validated['lat_sekolah'],
            'longitude' => (string) $validated['lng_sekolah'],
            'radius_meter' => (int) $validated['radius_gps'],
        ])->save();

        return $this->kembali('lokasi', 'Titik koordinat sekolah dan radius GPS berhasil disimpan.');
    }

    /**
     * Tab 3 — setujui akun: 'pending' -> 'active'.
     */
    public function approveAkun(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->status !== StatusAkun::Pending) {
            return redirect()
                ->route($this->panelPrefix() . '.pengaturan', ['tab' => 'approval'])
                ->withErrors(['approval' => "Akun {$user->name} tidak sedang menunggu persetujuan."]);
        }

        $user->update(['status' => StatusAkun::Active]);

        return $this->kembali('approval', "Akun {$user->name} disetujui dan sekarang bisa login.");
    }

    /**
     * Tab 3 — tolak akun: 'pending' -> 'rejected'.
     * Barisnya sengaja tidak dihapus supaya ada jejak siapa yang ditolak.
     */
    public function tolakAkun(int $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        if ($user->status !== StatusAkun::Pending) {
            return redirect()
                ->route($this->panelPrefix() . '.pengaturan', ['tab' => 'approval'])
                ->withErrors(['approval' => "Akun {$user->name} tidak sedang menunggu persetujuan."]);
        }

        $user->update(['status' => StatusAkun::Rejected]);

        return $this->kembali('approval', "Pendaftaran {$user->name} ditolak.");
    }

    /**
     * Tab 4 — tahun ajaran, DIKETIK MANUAL.
     *
     * Sengaja bukan dropdown dari daftar tetap: sekolah dipakai bertahun-tahun,
     * dan daftar yang di-seed sekali akan habis. Dengan diketik, tahun ajaran
     * baru bisa dibuat kapan saja tanpa perlu mengubah kode atau seeder.
     * Kalau kombinasi tahun+semester sudah pernah ada, barisnya dipakai ulang
     * (tidak menumpuk duplikat).
     */
    public function updateTahunAjaran(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Format "YYYY/YYYY", mis. 2026/2027.
            'tahun' => ['required', 'string', 'regex:/^\d{4}\/\d{4}$/'],
            'semester' => ['required', 'in:ganjil,genap'],
        ], [
            'tahun.regex' => 'Format tahun ajaran harus seperti 2026/2027.',
        ]);

        [$awal, $akhir] = explode('/', $validated['tahun']);

        if ((int) $akhir !== (int) $awal + 1) {
            return back()->withInput()->withErrors([
                'tahun' => 'Tahun kedua harus tepat satu tahun setelah yang pertama, mis. 2026/2027.',
            ]);
        }

        // Menonaktifkan semua lalu mengaktifkan satu adalah dua query; dibungkus
        // transaksi supaya tidak berakhir tanpa tahun ajaran aktif sama sekali
        // kalau query kedua gagal.
        $baru = DB::transaction(function () use ($validated) {
            $ta = TahunAjaran::firstOrCreate(
                ['tahun' => $validated['tahun'], 'semester' => $validated['semester']],
                ['aktif' => false],
            );

            TahunAjaran::query()->where('aktif', true)->update(['aktif' => false]);
            $ta->update(['aktif' => true]);

            return $ta;
        });

        return $this->kembali('tahun', 'Tahun ajaran aktif sekarang ' . $baru->label() . '.');
    }

    private function kembali(string $tab, string $pesan): RedirectResponse
    {
        return redirect()
            ->route($this->panelPrefix() . '.pengaturan', ['tab' => $tab])
            ->with('status', $pesan);
    }
}
