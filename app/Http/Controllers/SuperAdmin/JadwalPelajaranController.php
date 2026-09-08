<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\Hari;
use App\Http\Controllers\Controller;
use App\Models\JadwalPelajaran;
use App\Models\Kelas;
use App\Models\Pegawai;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * CRUD Jadwal Pelajaran untuk Super Admin.
 *
 * Sengaja dipisah dari SuperAdminController (yang isinya khusus
 * import/export Excel Master Data) supaya konsisten dengan pola yang sudah
 * dipakai di panel ini: satu entitas = satu controller resource
 * (SiswaController, KelasController, PegawaiController, dst).
 */
class JadwalPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $kelasId = $request->query('kelas_id') ?: null;
        $hariInput = $request->query('hari') ?: null;
        $hariTerpilih = $hariInput ? Hari::tryFrom($hariInput) : null;

        $jadwal = JadwalPelajaran::query()
            ->with(['kelas', 'guru'])
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when($hariTerpilih, fn ($query) => $query->where('hari', $hariTerpilih))
            ->orderBy('jam_mulai')
            ->get()
            // Urutan hari TIDAK bisa diserahkan ke ORDER BY: kolomnya string
            // ('senin', 'selasa', ...) sehingga database akan mengurutkannya
            // secara alfabetis. Jadi diurutkan di PHP pakai Hari::urutan().
            ->sortBy(fn (JadwalPelajaran $item) => [$item->hari->urutan(), $item->jam_mulai->format('H:i')])
            ->values()
            ->groupBy(fn (JadwalPelajaran $item) => $item->hari->value);

        return view('super-admin.jadwal.index', [
            'jadwalPerHari' => $jadwal,
            'daftarKelas' => Kelas::orderBy('nama_kelas')->get(),
            'daftarHari' => Hari::cases(),
            'kelasFilter' => $kelasId ? (int) $kelasId : null,
            'hariFilter' => $hariTerpilih,
            'totalJadwal' => $jadwal->flatten()->count(),
        ]);
    }

    public function create()
    {
        return view('super-admin.jadwal.create', $this->dataForm());
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validasi($request);

        if ($pesanBentrok = $this->cariBentrok($validated)) {
            return back()->withInput()->withErrors(['jam_mulai' => $pesanBentrok]);
        }

        JadwalPelajaran::create($validated);

        return redirect()
            ->route($this->panelPrefix() . '.jadwal.index')
            ->with('status', 'Jadwal pelajaran berhasil ditambahkan.');
    }

    public function edit(JadwalPelajaran $jadwal)
    {
        return view('super-admin.jadwal.edit', $this->dataForm() + ['jadwal' => $jadwal]);
    }

    public function update(Request $request, JadwalPelajaran $jadwal): RedirectResponse
    {
        $validated = $this->validasi($request);

        if ($pesanBentrok = $this->cariBentrok($validated, $jadwal)) {
            return back()->withInput()->withErrors(['jam_mulai' => $pesanBentrok]);
        }

        $jadwal->update($validated);

        return redirect()
            ->route($this->panelPrefix() . '.jadwal.index')
            ->with('status', 'Jadwal pelajaran berhasil diperbarui.');
    }

    public function destroy(JadwalPelajaran $jadwal): RedirectResponse
    {
        $jadwal->delete();

        return redirect()
            ->route($this->panelPrefix() . '.jadwal.index')
            ->with('status', 'Jadwal pelajaran berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function validasi(Request $request): array
    {
        // <input type="time"> mengirim "HH:MM", tapi sebagian browser bisa
        // mengirim "HH:MM:SS". Dipangkas dulu ke "HH:MM" supaya aturan
        // date_format di bawah tidak menolak input yang sebenarnya valid.
        $request->merge([
            'jam_mulai' => Str::substr((string) $request->input('jam_mulai'), 0, 5),
            'jam_selesai' => Str::substr((string) $request->input('jam_selesai'), 0, 5),
        ]);

        return $request->validate([
            'hari' => ['required', Rule::in(array_column(Hari::cases(), 'value'))],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
            'mata_pelajaran' => ['required', 'string', 'max:255'],
            'ruangan' => ['nullable', 'string', 'max:50'],
            'kelas_id' => ['required', Rule::exists('kelas', 'id')],
            'guru_id' => ['required', Rule::exists('pegawai', 'id')],
        ], [
            'jam_selesai.after' => 'Jam selesai harus lebih akhir daripada jam mulai.',
        ]);
    }

    /**
     * Cegah dua kesalahan input yang paling merepotkan di jadwal sekolah:
     * satu guru dijadwalkan mengajar di dua kelas pada jam yang beririsan,
     * atau satu kelas kebagian dua pelajaran sekaligus.
     *
     * Dua rentang waktu dianggap beririsan jika:
     *     mulai_lama < selesai_baru  DAN  selesai_lama > mulai_baru
     * Jam dinormalkan ke "HH:MM:SS" lebih dulu karena kolomnya bertipe TIME
     * dan di SQLite disimpan sebagai teks — tanpa normalisasi, slot yang
     * bersambung (07:00–08:30 lalu 08:30–10:00) akan salah dibaca sebagai
     * bentrok gara-gara "08:30:00" > "08:30" secara perbandingan string.
     */
    private function cariBentrok(array $data, ?JadwalPelajaran $abaikan = null): ?string
    {
        $mulai = $data['jam_mulai'] . ':00';
        $selesai = $data['jam_selesai'] . ':00';

        $dasar = JadwalPelajaran::query()
            ->where('hari', $data['hari'])
            ->where('jam_mulai', '<', $selesai)
            ->where('jam_selesai', '>', $mulai)
            ->when($abaikan, fn ($query) => $query->whereKeyNot($abaikan->getKey()));

        $bentrokGuru = (clone $dasar)->where('guru_id', $data['guru_id'])->with('kelas')->first();

        if ($bentrokGuru) {
            return sprintf(
                'Guru tersebut sudah mengajar %s di kelas %s pada jam %s. Pilih guru atau jam lain.',
                $bentrokGuru->mata_pelajaran,
                $bentrokGuru->kelas?->nama_kelas ?? '-',
                $bentrokGuru->rentangJam(),
            );
        }

        $bentrokKelas = (clone $dasar)->where('kelas_id', $data['kelas_id'])->with('guru')->first();

        if ($bentrokKelas) {
            return sprintf(
                'Kelas tersebut sudah ada pelajaran %s (%s) pada jam %s. Pilih jam lain.',
                $bentrokKelas->mata_pelajaran,
                $bentrokKelas->guru?->nama ?? '-',
                $bentrokKelas->rentangJam(),
            );
        }

        return null;
    }

    /**
     * Data yang dibutuhkan form create & edit.
     *
     * Dropdown guru dipisah jadi dua kelompok, BUKAN disaring keras hanya ke
     * jabatan yang mengandung "Guru". Alasannya: kolom `jabatan` itu teks
     * bebas — menyaring keras akan ikut membuang wali kelas (jabatannya
     * "Wali Kelas X RPL 1") dan kepala sekolah, padahal mereka juga mengajar.
     * Jadi yang relevan ditaruh di grup atas, sisanya tetap bisa dipilih.
     */
    private function dataForm(): array
    {
        $semuaPegawai = Pegawai::orderBy('nama')->get();

        $adalahPengajar = fn (Pegawai $p) => Str::contains(
            Str::lower($p->jabatan ?? ''),
            ['guru', 'wali kelas', 'kepala sekolah'],
        );

        return [
            'daftarKelas' => Kelas::orderBy('nama_kelas')->get(),
            'daftarHari' => Hari::cases(),
            'guruPengajar' => $semuaPegawai->filter($adalahPengajar)->values(),
            'pegawaiLain' => $semuaPegawai->reject($adalahPengajar)->values(),
        ];
    }
}
