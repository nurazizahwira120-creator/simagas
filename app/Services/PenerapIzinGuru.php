<?php

namespace App\Services;

use App\Enums\AbsensiStatus;
use App\Models\AbsensiPegawai;
use App\Models\PengajuanIzinGuru;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Menerapkan pengajuan izin guru yang SUDAH DISETUJUI ke absensi harian.
 *
 * ============ KENAPA BARU DITERAPKAN SAAT DISETUJUI ============
 * Pengajuan yang masih Pending sengaja TIDAK menyentuh `absensi_pegawai`
 * sama sekali. Kalau ia langsung berlaku begitu dikirim, persetujuannya
 * tinggal formalitas: gurunya sudah tercatat izin sebelum ada satu pun
 * atasan melihatnya, dan menolak pengajuan berarti harus MEMBATALKAN
 * catatan yang terlanjur dibuat.
 *
 * Arah datanya SATU ARAH dan hanya lewat sini.
 * ===============================================================
 */
class PenerapIzinGuru
{
    /**
     * Menandai seluruh hari dalam rentang pengajuan sebagai 'izin'.
     *
     * @return int jumlah hari yang benar-benar ditulis
     */
    public function terapkan(PengajuanIzinGuru $izin): int
    {
        $guru = $izin->guru()->with('pegawai:id,user_id')->first();
        $pegawai = $guru?->pegawai;

        if (! $pegawai) {
            /*
             | Akunnya belum ditautkan ke data pegawai.
             |
             | TIDAK melempar: persetujuannya sendiri sah dan sudah tercatat.
             | Yang tidak bisa dilakukan hanyalah menuliskannya ke absensi,
             | karena tabel itu berkunci pada pegawai_id — bukan user_id.
             |
             | Dicatat ke log supaya bisa ditelusuri kalau suatu hari ada yang
             | bertanya "izinnya disetujui, kenapa absensinya masih alpa?".
             */
            Log::warning('Izin guru disetujui tapi tidak bisa diterapkan ke absensi: akun belum tertaut ke data pegawai.', [
                'pengajuan_id' => $izin->id,
                'guru_id' => $izin->guru_id,
            ]);

            return 0;
        }

        $ditulis = 0;

        DB::transaction(function () use ($izin, $pegawai, &$ditulis) {
            /*
             | Ditulis HARI PER HARI, karena `absensi_pegawai` berbentuk satu
             | baris per (pegawai, tanggal) — bukan per rentang.
             |
             | copy() dipakai di setiap langkah: Carbon bersifat mutable, dan
             | memanggil addDay() langsung pada $izin->tanggal_mulai akan
             | MENGUBAH nilai di dalam modelnya. Akibatnya tanggal mulai
             | pengajuan ikut bergeser maju setiap kali diterapkan — bug yang
             | baru terlihat berhari-hari kemudian di halaman riwayat.
             */
            $tanggal = $izin->tanggal_mulai->copy();
            $akhir = $izin->tanggal_selesai->copy();

            while ($tanggal->lte($akhir)) {
                /*
                 | ============ KENAPA BUKAN updateOrCreate() ============
                 | Ini jebakan yang SUDAH pernah menggigit di project ini
                 | (lihat catatan sama di App\Livewire\Pegawai\FormIzin dan
                 | App\Livewire\Ekskul\AbsensiEkskul).
                 |
                 | updateOrCreate mencocokkan baris dengan perbandingan PERSIS
                 | (`where tanggal = '2026-09-13'`), sedangkan kolom `tanggal`
                 | di-cast 'date' dan tersimpan sebagai '2026-09-13 00:00:00'.
                 | Keduanya tidak pernah cocok, jadi updateOrCreate menyangka
                 | barisnya belum ada lalu INSERT — dan menabrak
                 | unique(pegawai_id, tanggal).
                 |
                 | Gejalanya di lapangan: guru yang PAGI ITU SUDAH SCAN MASUK
                 | lalu izinnya disetujui -> persetujuannya gagal total dengan
                 | pesan "Integrity constraint violation". Guru yang belum
                 | absen sama sekali justru berhasil. Persis kebalikan dari
                 | yang diduga orang saat melaporkannya.
                 |
                 | whereDate() membandingkan bagian TANGGALNYA saja, jadi ia
                 | menemukan baris yang sudah ada apa pun bentuk simpanannya.
                 */
                $baris = AbsensiPegawai::where('pegawai_id', $pegawai->id)
                    ->whereDate('tanggal', $tanggal->toDateString())
                    ->first();

                /*
                 | jam_masuk SENGAJA tidak ikut ditulis.
                 |
                 | Kalau gurunya sempat absen datang pagi lalu izin pulang
                 | lebih awal, jam kedatangannya adalah fakta yang benar-benar
                 | terjadi. Statusnya berubah; jam masuknya tidak dihapus.
                 */
                $isi = [
                    'status' => AbsensiStatus::Izin,
                    'alasan' => $izin->alasan,

                    // Jenisnya ikut dicatat supaya rekap absensi tetap
                    // bisa membedakan ITT dari IDT tanpa harus membuka
                    // tabel pengajuan.
                    'keterangan' => sprintf(
                        'Izin %s (disetujui) — pengajuan #%d',
                        $izin->jenis_izin->kode(),
                        $izin->id,
                    ),
                ];

                if ($baris) {
                    $baris->fill($isi)->save();
                } else {
                    AbsensiPegawai::create($isi + [
                        'pegawai_id' => $pegawai->id,
                        'tanggal' => $tanggal->toDateString(),
                    ]);
                }

                $ditulis++;
                $tanggal->addDay();
            }
        });

        return $ditulis;
    }
}
