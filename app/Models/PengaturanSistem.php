<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan sistem — identitas sekolah & kredensial gateway WhatsApp.
 *
 * Tabelnya sengaja hanya berisi SATU baris. Selalu ambil lewat
 * PengaturanSistem::ambil(), bukan ::first(), supaya aplikasi tidak pernah
 * berhadapan dengan null di tengah proses (lihat catatan di method itu).
 *
 * JANGAN dikacaukan dengan App\Models\Pengaturan — itu tabel kunci-nilai
 * untuk aturan absensi (jam masuk, radius GPS). Pembagian tugasnya
 * dijelaskan di migrasi 000018.
 */
class PengaturanSistem extends Model
{
    protected $table = 'pengaturan_sistem';

    protected $fillable = [
        'nama_sekolah',

        // Titik pusat sekolah & radius toleransi Absen Radius. Lihat
        // migration 000020: ini SATU-SATUNYA sumber koordinat sekarang —
        // kunci lat_sekolah/lng_sekolah/radius_gps di tabel `pengaturan`
        // sudah tidak dibaca lagi oleh fitur absensi.
        'latitude',
        'longitude',
        'radius_meter',
        'wa_gateway_status',
        'fonnte_token',
        'wa_delay',
    ];

    protected function casts(): array
    {
        return [
            'wa_gateway_status' => 'boolean',
            'wa_delay' => 'integer',
            'radius_meter' => 'integer',

            // Token disimpan TERENKRIPSI di database. Alasannya sederhana:
            // siapa pun yang bisa membaca isi tabel — hasil backup yang
            // bocor, akses phpMyAdmin, dump dari hosting — otomatis bisa
            // mengirim WhatsApp atas nama sekolah kalau tokennya polos.
            //
            // KONSEKUENSI YANG HARUS DIINGAT: nilainya terikat ke APP_KEY.
            // Kalau APP_KEY di .env diganti, token lama TIDAK bisa dibaca
            // lagi dan harus diisi ulang lewat halaman Pengaturan Sistem.
            'fonnte_token' => 'encrypted',
        ];
    }

    /**
     * Baris pengaturan yang berlaku. Dibuat otomatis dengan nilai bawaan
     * kalau tabelnya masih kosong.
     *
     * Ini penting karena WhatsAppService dipanggil dari dalam queue worker,
     * jauh dari layar siapa pun. Kalau seeder belum dijalankan, ::first()
     * mengembalikan null dan job-nya gagal dengan "Call to a member function
     * on null" — kegagalan yang membingungkan padahal sebabnya cuma satu
     * baris data yang belum ada.
     */
    public static function ambil(): self
    {
        return static::query()->first() ?? static::query()->create([
            // Diambil dari config/sekolah.php, BUKAN dipaku di sini: satu kode
            // ini dipakai beberapa sekolah, masing-masing dengan .env sendiri.
            // Kalau dipaku, sekolah yang baru dipasang akan menampilkan nama
            // sekolah lain di seluruh kop laporannya sampai ada yang sadar.
            'nama_sekolah' => config('sekolah.nama'),
            'wa_gateway_status' => false,
            'wa_delay' => 2,

            // Placeholder Monas — WAJIB diganti lewat peta di halaman
            // Pengaturan Sistem, kalau tidak Absen Radius akan menolak
            // semua pegawai karena mereka memang jauh dari Jakarta Pusat.
            'latitude' => '-6.175392',
            'longitude' => '106.827153',
            'radius_meter' => 50,
        ]);
    }

    /**
     * Nama sekolah yang berlaku — dipakai kop laporan, kartu siswa, dan
     * halaman login.
     *
     * ============ KENAPA DIINGAT DI MEMORI ============
     * Kop laporan bulanan memanggilnya sekali per halaman PDF, dan sidebar
     * memanggilnya di setiap permintaan. Tanpa penyimpanan sementara ini,
     * satu PDF 12 halaman berarti 12 query untuk membaca satu teks yang
     * tidak mungkin berubah di tengah permintaan yang sama.
     *
     * Disimpan per-permintaan (properti statis), BUKAN di cache Laravel:
     * cache yang basi sesudah Super Admin mengganti nama sekolah akan
     * membuatnya seolah tidak tersimpan, dan itu laporan bug yang mahal
     * untuk ditelusuri.
     * =================================================
     */
    public static function namaSekolah(): string
    {
        static $nama = null;

        if ($nama !== null) {
            return $nama;
        }

        // Kalau tabelnya belum ada sama sekali (mis. sebelum migrate pertama
        // di server baru), jangan menjatuhkan halaman hanya karena namanya
        // belum bisa dibaca — pakai cadangan dari .env.
        try {
            $nama = trim((string) static::ambil()->nama_sekolah);
        } catch (\Throwable) {
            $nama = '';
        }

        return $nama = $nama !== '' ? $nama : (string) config('sekolah.nama');
    }
}
