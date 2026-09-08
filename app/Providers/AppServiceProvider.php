<?php

namespace App\Providers;

use App\Models\AbsensiPegawai;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         | Nama hari & bulan dalam bahasa Indonesia.
         |
         | Tanpa baris ini translatedFormat() tetap menghasilkan "Saturday,
         | 29 August 2026" — seluruh antarmuka berbahasa Indonesia tapi
         | tanggalnya Inggris. Disetel di sini, bukan lewat config/app.php,
         | karena berkas config milik Anda tidak ikut di paket ini dan
         | menimpanya berisiko menghapus penyesuaian lain.
         |
         | Ini HANYA mengubah bahasa penulisan tanggal. Zona waktu terpisah —
         | lihat catatan APP_TIMEZONE di BACA-DULU.md.
         */
        /*
         | Locale APLIKASI ikut disetel, bukan cuma Carbon.
         |
         | Carbon::setLocale() saja TIDAK cukup dan itu terbukti: nama hari
         | benar di halaman biasa ("Senin") tapi kembali Inggris ("Monday")
         | di dalam request Livewire. Sebabnya Livewire menyimpan locale
         | aplikasi di memo komponen lalu memulihkannya di setiap permintaan
         | berikutnya — dan yang dipulihkan adalah 'en' dari config bawaan,
         | yang kemudian menarik locale Carbon ikut kembali ke Inggris.
         |
         | Akibatnya bukan cuma soal tampilan: teks notifikasi DISIMPAN ke
         | database apa adanya, jadi tanggal berbahasa Inggris di sana ikut
         | tersimpan permanen dan tidak bisa diperbaiki dengan mengganti
         | setelan belakangan.
         |
         | Pesan validasi bawaan Laravel tetap Inggris (berkas lang/id tidak
         | disertakan) — Laravel otomatis jatuh ke fallback_locale, dan pesan
         | validasi di project ini memang sudah ditulis manual dalam bahasa
         | Indonesia di masing-masing komponen.
         */
        app()->setLocale('id');
        Carbon::setLocale('id');

        // $panelPrefix tersedia di SEMUA view — dipakai lewat route($panelPrefix . '.xxx')
        // di view yang dipakai bersama lintas role (mis. resources/views/kepsek/**,
        // yang dipakai baik oleh role kepsek maupun super_admin lewat dua grup
        // rute berbeda: 'kepsek.*' dan 'super-admin.*'). Didaftarkan sebagai
        // View::composer (bukan di dalam method boot() langsung) supaya
        // auth()->user() dievaluasi saat view benar-benar dirender — setelah
        // middleware 'auth' & 'role' jalan — bukan saat provider di-boot di
        // awal request (waktu session/auth belum tentu siap).
        View::composer('*', function ($view) {
            $view->with('panelPrefix', auth()->user()?->role?->routePrefix() ?? '');
        });

        // Kartu "Absensi Hari Ini" dipasang di tiga dasbor berbeda (Kepsek,
        // Wali Kelas, Guru/Staff). Datanya disuplai di sini supaya ketiga
        // controller-nya tidak perlu mengulang query yang sama.
        View::composer('partials.kartu-kehadiran-hari-ini', function ($view) {
            $pegawai = auth()->user()?->pegawai;

            $view->with([
                'pegawaiSaya' => $pegawai,
                'absensiSayaHariIni' => $pegawai
                    ? AbsensiPegawai::where('pegawai_id', $pegawai->id)
                        ->whereDate('tanggal', today())
                        ->first()
                    : null,
            ]);
        });
    }
}
