<?php

namespace App\Http\Controllers\Guru;

use App\Enums\JenisPenilaian;
use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Mapel;
use App\Models\Nilai;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\ValidasiRapor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Input nilai oleh guru: satu kelas, satu mata pelajaran, satu semester.
 *
 * ============ BENTUK FORMNYA: GRID, BUKAN SATU-SATU ============
 * Guru memilih kelas + mata pelajaran, lalu mendapat SATU tabel berisi seluruh
 * siswa kelas itu dengan tiga kolom nilai (Formatif, Sumatif, Praktik). Semua
 * disimpan sekali tekan.
 *
 * Alternatifnya — satu form per siswa — berarti 30 kali memuat halaman untuk
 * satu mata pelajaran. Di jaringan sekolah itu bukan ketidaknyamanan kecil,
 * itu alasan fiturnya tidak akan dipakai.
 * ==============================================================
 */
class NilaiController extends Controller
{
    /** Batas atas skor yang diterima. */
    private const SKOR_MAKS = 100;

    /**
     * Halaman input nilai.
     */
    public function index(Request $request): View
    {
        $pegawai = $request->user()->pegawai;

        // Guru tanpa data pegawai tidak punya jadwal, jadi tidak punya mata
        // pelajaran. Ditangani di awal supaya sisa method ini tidak perlu
        // memeriksa null berulang kali.
        if (! $pegawai) {
            return view('guru.nilai', $this->kosong('Akun Anda belum tertaut ke data pegawai. Hubungi Admin TU.'));
        }

        $periode = $this->periodeAktif();

        // Hanya mata pelajaran yang benar-benar diajar guru ini.
        $daftarMapel = Mapel::diajarOleh($pegawai->id);

        // Hanya kelas yang benar-benar ada di jadwal guru ini — bukan seluruh
        // kelas sekolah. Guru Matematika kelas X tidak berkepentingan menilai
        // kelas XII yang tidak diajarnya.
        $daftarKelas = Kelas::query()
            ->whereIn('id', $pegawai->jadwalMengajar()->distinct()->pluck('kelas_id'))
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas']);

        $kelasId = (int) $request->query('kelas_id', $daftarKelas->first()->id ?? 0);
        $mapelId = (int) $request->query('mapel_id', $daftarMapel->first()->id ?? 0);

        $kelas = $daftarKelas->firstWhere('id', $kelasId);
        $mapel = $daftarMapel->firstWhere('id', $mapelId);

        if (! $kelas || ! $mapel || ! $periode) {
            return view('guru.nilai', $this->kosong(
                $periode ? null : 'Belum ada Tahun Ajaran yang diaktifkan. Hubungi Super Admin.',
                $daftarKelas, $daftarMapel, $periode, $kelas, $mapel,
            ));
        }

        $rapor = ValidasiRapor::untuk($kelas->id, $periode->tahun, $periode->semester);

        $siswa = Siswa::query()
            ->where('kelas_id', $kelas->id)
            ->orderBy('nama')
            ->get(['id', 'nis', 'nama']);

        /*
         | SATU query untuk seluruh nilai yang sudah ada, lalu dipetakan di PHP
         | menjadi [siswa_id][jenis] => skor.
         |
         | Tanpa ini, view akan memanggil relasi per sel tabel — 30 siswa x 3
         | jenis = 90 query untuk satu halaman. Inilah N+1 yang paling mudah
         | terjadi pada form berbentuk grid.
         */
        $nilaiTersimpan = Nilai::query()
            ->whereIn('siswa_id', $siswa->pluck('id'))
            ->where('mapel_id', $mapel->id)
            ->where('tahun_ajaran', $periode->tahun)
            ->where('semester', $periode->semester)
            ->get(['siswa_id', 'jenis_penilaian', 'skor'])
            ->groupBy('siswa_id')
            ->map(fn ($baris) => $baris->keyBy(fn (Nilai $n) => $n->jenis_penilaian->value)
                ->map(fn (Nilai $n) => $n->skor));

        return view('guru.nilai', [
            'daftarKelas' => $daftarKelas,
            'daftarMapel' => $daftarMapel,
            'kelas' => $kelas,
            'mapel' => $mapel,
            'periode' => $periode,
            'rapor' => $rapor,
            'siswa' => $siswa,
            'nilaiTersimpan' => $nilaiTersimpan,
            'jenisPenilaian' => JenisPenilaian::urut(),
            'pesanKosong' => null,
            'skorMaks' => self::SKOR_MAKS,
        ]);
    }

    /**
     * Menyimpan seluruh grid sekaligus.
     */
    public function simpan(Request $request): RedirectResponse
    {
        $pegawai = $request->user()->pegawai;

        abort_unless($pegawai, 403, 'Akun Anda belum tertaut ke data pegawai.');

        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mapel_id' => ['required', 'integer', 'exists:mapels,id'],

            // nilai[siswa_id][jenis] = skor. Boleh kosong: sel yang dikosongkan
            // berarti "nilai ini belum ada", bukan "nilainya nol".
            'nilai' => ['array'],
            'nilai.*.*' => ['nullable', 'numeric', 'min:0', 'max:' . self::SKOR_MAKS],
        ], [
            'nilai.*.*.numeric' => 'Nilai harus berupa angka.',
            'nilai.*.*.min' => 'Nilai tidak boleh kurang dari 0.',
            'nilai.*.*.max' => 'Nilai tidak boleh lebih dari ' . self::SKOR_MAKS . '.',
        ]);

        $periode = $this->periodeAktif();

        abort_unless($periode, 409, 'Belum ada Tahun Ajaran yang aktif.');

        /*
         | Dua penjagaan yang WAJIB ada di sisi server, bukan hanya di tampilan:
         |
         |   1. Guru hanya boleh menilai kelas & mapel yang benar-benar
         |      diajarnya. Tanpa ini, siapa pun yang sudah login sebagai guru
         |      bisa mengirim kelas_id mana pun lewat alat seperti curl.
         |   2. Rapor yang sudah diajukan/disetujui TIDAK boleh diubah lagi.
         |      Tombolnya memang disembunyikan di layar, tapi tombol yang
         |      disembunyikan bukan kontrol akses.
         */
        $bolehKelas = $pegawai->jadwalMengajar()->where('kelas_id', $data['kelas_id'])->exists();
        abort_unless($bolehKelas, 403, 'Anda tidak mengajar di kelas tersebut.');

        $mapel = Mapel::findOrFail($data['mapel_id']);
        $bolehMapel = Mapel::diajarOleh($pegawai->id)->contains('id', $mapel->id);
        abort_unless($bolehMapel, 403, 'Anda tidak mengajar mata pelajaran tersebut.');

        $rapor = ValidasiRapor::untuk($data['kelas_id'], $periode->tahun, $periode->semester);

        if (! $rapor->status->bolehUbahNilai()) {
            return back()->with('gagal',
                'Rapor kelas ini berstatus "' . $rapor->status->label() . '", jadi nilainya sudah terkunci.');
        }

        // Siswa yang sah untuk kelas ini — dipakai menyaring id yang dikirim
        // browser, supaya nilai tidak bisa dititipkan ke siswa kelas lain.
        $siswaSah = Siswa::where('kelas_id', $data['kelas_id'])->pluck('id')->flip();

        $jumlahSimpan = 0;
        $jumlahHapus = 0;

        DB::transaction(function () use ($data, $periode, $mapel, $pegawai, $siswaSah, &$jumlahSimpan, &$jumlahHapus) {
            foreach ($data['nilai'] ?? [] as $siswaId => $perJenis) {
                if (! $siswaSah->has((int) $siswaId)) {
                    continue;
                }

                foreach ($perJenis as $jenis => $skor) {
                    $jenisEnum = JenisPenilaian::tryFrom((string) $jenis);

                    if (! $jenisEnum) {
                        continue;
                    }

                    $kunci = [
                        'siswa_id' => (int) $siswaId,
                        'mapel_id' => $mapel->id,
                        'tahun_ajaran' => $periode->tahun,
                        'semester' => $periode->semester,
                        'jenis_penilaian' => $jenisEnum->value,
                    ];

                    // Sel dikosongkan -> nilainya DIHAPUS, bukan disimpan 0.
                    // Menyimpan 0 akan menyeret rata-rata anak ke bawah untuk
                    // penilaian yang sebenarnya belum pernah dilakukan.
                    if ($skor === null || $skor === '') {
                        $jumlahHapus += Nilai::where($kunci)->delete();

                        continue;
                    }

                    // updateOrCreate aman ditekan berkali-kali berkat kunci
                    // unik di migrasi 000032.
                    Nilai::updateOrCreate($kunci, [
                        'guru_id' => $pegawai->id,
                        'skor' => round((float) $skor, 2),
                    ]);

                    $jumlahSimpan++;
                }
            }
        });

        return back()->with('sukses', sprintf(
            'Nilai %s untuk kelas tersebut tersimpan (%d nilai disimpan%s).',
            $mapel->nama,
            $jumlahSimpan,
            $jumlahHapus > 0 ? ', ' . $jumlahHapus . ' dikosongkan' : '',
        ));
    }

    /* ===================== PEMBANTU ===================== */

    private function periodeAktif(): ?TahunAjaran
    {
        return TahunAjaran::yangAktif();
    }

    /**
     * Data untuk halaman yang belum bisa menampilkan grid (belum ada tahun
     * ajaran aktif / guru tidak punya jadwal). Dikumpulkan di satu tempat
     * supaya view-nya tidak perlu tahu ada berapa macam keadaan kosong.
     */
    private function kosong(
        ?string $pesan = null,
        $daftarKelas = null,
        $daftarMapel = null,
        $periode = null,
        $kelas = null,
        $mapel = null,
    ): array {
        return [
            'daftarKelas' => $daftarKelas ?? collect(),
            'daftarMapel' => $daftarMapel ?? collect(),
            'kelas' => $kelas,
            'mapel' => $mapel,
            'periode' => $periode,
            'rapor' => null,
            'siswa' => collect(),
            'nilaiTersimpan' => collect(),
            'jenisPenilaian' => JenisPenilaian::urut(),
            'pesanKosong' => $pesan ?? 'Pilih kelas dan mata pelajaran untuk mulai menilai.',
            'skorMaks' => self::SKOR_MAKS,
        ];
    }
}
