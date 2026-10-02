<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Enums\StatusKbm;
use App\Jobs\SendWhatsAppNotification;
use App\Models\AbsensiKbmSiswa;
use App\Models\AbsensiSiswa;
use App\Models\JadwalPelajaran;
use App\Models\Siswa;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Membaca & menyimpan DAFTAR HADIR SISWA per jam pelajaran (absensi KBM).
 *
 * ============ KENAPA DIPINDAH KE SINI ============
 * Logika ini semula tinggal di dalam App\Livewire\Guru\JurnalAbsenKelas,
 * dan itu cukup selama satu-satunya pengisi adalah guru pemilik jadwal.
 *
 * Sekarang ada pengisi kedua: halaman Kelas Pengganti (guru lain / piket
 * mengisi absensi kelas yang gurunya berhalangan). Aturan penyimpanannya
 * HARUS sama persis — alpa + masuk gerbang = bolos, izin gerbang terisi
 * otomatis, peringatan WhatsApp hanya untuk perubahan baru. Kalau disalin,
 * cepat atau lambat keduanya berbeda, dan siswa yang sama bisa tercatat
 * bolos oleh guru aslinya tapi alpa oleh penggantinya.
 *
 * Perilakunya DIJAGA tests/Feature/JurnalAbsenKelasTest, yang ditulis dan
 * dijalankan sebelum pemindahan ini lalu dijalankan lagi sesudahnya.
 * =================================================
 *
 * Yang TIDAK ada di sini: siapa yang BOLEH mengisi. Itu keputusan masing-
 * masing halaman (guru: sudah absen datang + scan QR; pengganti: guru
 * jadwalnya berhalangan). Service ini hanya menyimpan apa yang diserahkan.
 */
class PencatatAbsensiKbm
{
    /** @return Collection<int, Siswa> */
    public function siswaKelas(JadwalPelajaran $jadwal): Collection
    {
        if (! $jadwal->kelas_id) {
            return collect();
        }

        return Siswa::where('kelas_id', $jadwal->kelas_id)->orderBy('nama')->get();
    }

    /**
     * Siswa yang hari ini tercatat HADIR di gerbang.
     *
     * Inilah dasar status bolos: alpa di kelas tapi tercatat masuk gerbang.
     *
     * @return Collection<int, int>
     */
    public function hadirDiGerbang(Collection $idSiswa, CarbonInterface $tanggal): Collection
    {
        if ($idSiswa->isEmpty()) {
            return collect();
        }

        return AbsensiSiswa::whereIn('siswa_id', $idSiswa)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->where('status', AbsensiStatus::Hadir)
            ->pluck('siswa_id');
    }

    /**
     * Siswa yang hari ini sudah dicatat izin/sakit di gerbang, dipetakan ke
     * status KBM-nya.
     *
     * @return Collection<int, string> siswa_id => nilai StatusKbm
     */
    public function izinDariGerbang(Collection $idSiswa, CarbonInterface $tanggal): Collection
    {
        if ($idSiswa->isEmpty()) {
            return collect();
        }

        return AbsensiSiswa::whereIn('siswa_id', $idSiswa)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->whereIn('status', [AbsensiStatus::Izin->value, AbsensiStatus::Sakit->value])
            ->get(['siswa_id', 'status'])
            ->mapWithKeys(fn (AbsensiSiswa $a) => [
                $a->siswa_id => $a->status === AbsensiStatus::Sakit
                    ? StatusKbm::Sakit->value
                    : StatusKbm::Izin->value,
            ]);
    }

    /**
     * Isian awal form daftar hadir.
     *
     * @return array{status: array<int, string>, keterangan: array<int, string>}
     */
    public function statusAwal(JadwalPelajaran $jadwal, CarbonInterface $tanggal, Collection $siswa): array
    {
        $tersimpan = AbsensiKbmSiswa::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', $tanggal->toDateString())
            ->get()
            ->keyBy('siswa_id');

        $izinGerbang = $this->izinDariGerbang($siswa->pluck('id'), $tanggal);

        $status = [];
        $keterangan = [];

        foreach ($siswa as $s) {
            $baris = $tersimpan->get($s->id);

            /*
             | Urutan pengambilan nilainya PENTING dan tidak boleh dibalik:
             |
             |   1. jurnal jam ini yang sudah tersimpan  -> selalu menang
             |   2. izin/sakit dari gerbang hari ini
             |   3. 'hadir' sebagai bawaan
             |
             | Nomor 1 di atas nomor 2 karena guru berada DI RUANGAN dan
             | melihat sendiri siapa yang ada. Kalau seorang anak dicatat
             | izin di gerbang tapi ternyata menyusul masuk, guru mengubahnya
             | jadi Hadir dan menyimpannya — membuka ulang halaman tidak
             | boleh diam-diam mengembalikannya ke Izin lagi.
             */
            $nilai = $baris?->status?->value
                ?? $izinGerbang->get($s->id)
                ?? StatusKbm::Hadir->value;

            // Bolos ditampilkan sebagai Alpa: bolos bukan pilihan guru,
            // melainkan hasil hitungan sistem saat disimpan.
            $status[$s->id] = $nilai === StatusKbm::Bolos->value ? StatusKbm::Alpa->value : $nilai;
            $keterangan[$s->id] = (string) ($baris?->keterangan ?? '');
        }

        return ['status' => $status, 'keterangan' => $keterangan];
    }

    /**
     * Menyimpan daftar hadir satu jam pelajaran.
     *
     * Melempar exception kalau penyimpanan ke database gagal — pemanggil
     * yang memutuskan pesan apa yang ditampilkan.
     *
     * @param  Collection<int, Siswa>  $siswaKelas
     * @param  array<int|string, string>  $status
     * @param  array<int|string, string>  $keterangan
     * @param  int|null  $diisiOleh  users.id pengisi (guru jadwal atau pengganti)
     * @return array{jumlah: int, bolos: int, ringkas: Collection<string, int>}
     */
    public function simpan(
        JadwalPelajaran $jadwal,
        CarbonInterface $tanggal,
        Collection $siswaKelas,
        array $status,
        array $keterangan,
        ?int $diisiOleh,
    ): array {
        $siswaKelas = $siswaKelas->keyBy('id');
        $hari = Carbon::parse($tanggal)->toDateString();
        $hadirGerbang = $this->hadirDiGerbang($siswaKelas->keys(), Carbon::parse($hari));
        $sahStatus = array_map(fn (StatusKbm $s) => $s->value, StatusKbm::pilihanGuru());

        // Status yang SUDAH tersimpan sebelum tombol ini ditekan.
        //
        // Dipakai untuk satu hal penting: notifikasi WhatsApp hanya dikirim
        // untuk siswa yang statusnya BARU BERUBAH jadi alpa/bolos. Tanpa
        // pembanding ini, guru yang menekan Simpan dua kali (mengoreksi satu
        // nama, lalu menyimpan lagi) akan mengirim peringatan yang sama dua
        // kali ke orang tua yang sama — cara tercepat membuat wali murid
        // memblokir nomor sekolah.
        $sebelumnya = AbsensiKbmSiswa::where('jadwal_id', $jadwal->id)
            ->whereDate('tanggal', $hari)
            ->pluck('status', 'siswa_id')
            ->map(fn ($s) => $s instanceof StatusKbm ? $s->value : (string) $s);

        $baris = [];
        $jumlahBolos = 0;

        /** @var array<int, array{siswa: Siswa, status: StatusKbm}> */
        $perluDiberitahu = [];

        foreach ($siswaKelas as $id => $siswa) {
            $dipilih = $status[$id] ?? StatusKbm::Hadir->value;

            // Nilai di luar daftar (dikirim manual dari browser) dianggap
            // Hadir, bukan ditolak semuanya — satu nilai iseng tidak boleh
            // membuang pekerjaan guru untuk seluruh kelas.
            if (! in_array($dipilih, $sahStatus, true)) {
                $dipilih = StatusKbm::Hadir->value;
            }

            // Alpa + tercatat masuk gerbang pagi ini = BOLOS. Perbedaan ini
            // dihitung sistem, bukan diminta ke guru: guru tidak mungkin
            // hafal siapa saja yang tadi pagi lewat gerbang.
            if ($dipilih === StatusKbm::Alpa->value && $hadirGerbang->contains($id)) {
                $dipilih = StatusKbm::Bolos->value;
                $jumlahBolos++;
            }

            // Hanya perubahan BARU yang memicu pesan ke wali murid.
            if (in_array($dipilih, [StatusKbm::Alpa->value, StatusKbm::Bolos->value], true)
                && ($sebelumnya[$id] ?? null) !== $dipilih) {
                $perluDiberitahu[] = ['siswa' => $siswa, 'status' => StatusKbm::from($dipilih)];
            }

            $ket = trim((string) ($keterangan[$id] ?? ''));

            $baris[] = [
                'jadwal_id' => $jadwal->id,
                'siswa_id' => $id,
                'tanggal' => $hari,
                'status' => $dipilih,
                'keterangan' => $ket === '' ? null : Str::limit($ket, 255, ''),
                'diisi_oleh' => $diisiOleh,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! $baris) {
            return ['jumlah' => 0, 'bolos' => 0, 'ringkas' => collect()];
        }

        DB::transaction(function () use ($baris) {
            // upsert, bukan insert: tombol Simpan boleh ditekan berkali-kali
            // tanpa menumpuk baris ganda. Kuncinya unique(jadwal_id,
            // siswa_id, tanggal) dari migrasi 000017.
            //
            // diisi_oleh ikut diperbarui: kalau pengganti mengoreksi daftar
            // yang sudah diisi orang lain, yang tercatat adalah pengisi
            // TERAKHIR — orang yang bertanggung jawab atas isinya sekarang.
            AbsensiKbmSiswa::upsert(
                $baris,
                ['jadwal_id', 'siswa_id', 'tanggal'],
                ['status', 'keterangan', 'diisi_oleh', 'updated_at'],
            );
        });

        // Notifikasi diantrekan SESUDAH transaksi berhasil, bukan di dalamnya.
        // Kalau di dalam dan transaksinya kemudian gagal, pesan sudah terlanjur
        // masuk antrean dan orang tua menerima peringatan tentang data yang
        // tidak pernah tersimpan.
        $this->antrekanPeringatan($perluDiberitahu, $jadwal);

        return [
            'jumlah' => count($baris),
            'bolos' => $jumlahBolos,
            'ringkas' => collect($baris)->countBy('status'),
        ];
    }

    /** Kalimat ringkasan hasil simpan — sama untuk guru dan pengganti. */
    public function kalimatHasil(array $hasil): string
    {
        $r = $hasil['ringkas'];

        return trim(
            'Tercatat ' . $r->get(StatusKbm::Hadir->value, 0) . ' hadir'
            . ', ' . $r->get(StatusKbm::Sakit->value, 0) . ' sakit'
            . ', ' . $r->get(StatusKbm::Izin->value, 0) . ' izin'
            . ', ' . $r->get(StatusKbm::Alpa->value, 0) . ' alpa'
            . ($hasil['bolos'] > 0
                ? ". {$hasil['bolos']} siswa ditandai BOLOS karena tadi pagi tercatat masuk gerbang."
                : '.')
        );
    }

    /**
     * @param  array<int, array{siswa: Siswa, status: StatusKbm}>  $daftar
     */
    private function antrekanPeringatan(array $daftar, JadwalPelajaran $jadwal): void
    {
        if (! $daftar) {
            return;
        }

        $jamKe = $this->jamKe($jadwal);

        foreach ($daftar as $item) {
            $siswa = $item['siswa'];

            // Sama seperti di scan gerbang: nomor khusus wali lebih dulu,
            // baru nomor akun wali murid yang tertaut.
            $tujuan = $siswa->no_hp_wali ?: $siswa->waliMurid?->no_hp;

            if (blank($tujuan)) {
                Log::warning('Peringatan WhatsApp KBM dilewati: wali murid tidak punya nomor HP.', [
                    'siswa_id' => $siswa->id,
                    'jadwal_id' => $jadwal->id,
                ]);

                continue;
            }

            $pesan = sprintf(
                'PERINGATAN SIMAGAS: Ananda %s tercatat %s pada mata pelajaran %s jam ke-%s. Mohon pantau kehadiran putra/putri Anda.',
                $siswa->nama,
                Str::upper($item['status']->label()),
                $jadwal->mata_pelajaran,
                $jamKe,
            );

            SendWhatsAppNotification::dispatch((string) $tujuan, $pesan);
        }
    }

    /**
     * Urutan jam pelajaran ini di antara jadwal kelas yang sama pada hari
     * yang sama — inilah "jam ke-berapa" yang disebut di pesan.
     *
     * Dihitung, bukan dibaca dari kolom: tabel jadwal_pelajaran tidak punya
     * kolom "jam ke". Menambah kolom itu berarti dua sumber kebenaran yang
     * bisa berselisih setiap kali jam mulai diubah.
     */
    public function jamKe(JadwalPelajaran $jadwal): string
    {
        $urut = JadwalPelajaran::where('kelas_id', $jadwal->kelas_id)
            ->where('hari', $jadwal->hari->value)
            ->get()
            ->sortBy(fn (JadwalPelajaran $j) => $j->jam_mulai->format('H:i'))
            ->values()
            ->search(fn (JadwalPelajaran $j) => $j->id === $jadwal->id);

        // Kalau entah kenapa tidak ketemu, jam mulainya lebih berguna
        // daripada angka yang salah.
        return $urut === false
            ? $jadwal->jam_mulai->format('H:i')
            : (string) ($urut + 1);
    }
}
