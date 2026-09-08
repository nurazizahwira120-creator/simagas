{{--
    Kartu "Absensi Hari Ini" — dipakai di dasbor Kepsek/Super Admin, Wali
    Kelas, dan Guru/Staff/Admin TU.

    DULU bernama kartu-absensi-mandiri.blade.php dan memuat tombol "Absen
    Masuk" sekali klik tanpa verifikasi apa pun. Tombol itu DIHAPUS bersama
    rute POST absensi-mandiri dan PegawaiAbsensiController: selama jalur itu
    ada, kehadiran bisa dicatat dari mana saja tanpa pernah ke sekolah,
    sehingga fitur Absen Radius kehilangan gunanya. Sekarang kartu ini hanya
    MENAMPILKAN status, dan satu-satunya tombol di sini mengarah ke Absen
    Kehadiran (Radius).

    Variabel $pegawaiSaya & $absensiSayaHariIni TIDAK dikirim dari controller
    melainkan diisi otomatis lewat View::composer di AppServiceProvider,
    supaya tiga dasbor yang memakai partial ini tidak perlu mengulang query
    yang sama di masing-masing controller-nya.

    $absensiSayaHariIni membaca tabel absensi_pegawai — tabel yang SAMA yang
    diisi scanner PWA di gerbang. Jadi kalau pegawai sudah scan di gerbang,
    tombol di sini otomatis sudah berubah jadi "Sudah Absen".
--}}
@php
    $adaCatatan = $absensiSayaHariIni !== null;
    $statusHadir = $adaCatatan && $absensiSayaHariIni->status === \App\Enums\AbsensiStatus::Hadir;
@endphp

<div class="rounded-2xl border border-brand-border bg-brand-surface p-5 shadow-soft">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="flex min-w-0 items-center gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $adaCatatan ? 'bg-brand-accent-soft text-brand-accent-text' : 'bg-brand-surface-muted text-brand-muted' }}">
                <x-icon name="{{ $adaCatatan ? 'check-circle' : 'clock' }}" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <p class="text-xs font-medium uppercase tracking-wide text-brand-muted">Absensi Hari Ini</p>

                @if (! $pegawaiSaya)
                    <p class="mt-0.5 text-sm text-brand-muted">
                        Akun Anda belum ditautkan ke data pegawai.
                    </p>
                @elseif ($statusHadir)
                    <p class="mt-0.5 text-base font-semibold text-brand-accent-text">
                        Sudah Absen
                        @if ($absensiSayaHariIni->jam_masuk)
                            <span class="font-normal text-brand-muted">&middot; masuk pukul {{ $absensiSayaHariIni->jam_masuk->format('H:i') }}</span>
                        @endif
                    </p>
                @elseif ($adaCatatan)
                    <p class="mt-0.5 text-base font-semibold text-brand-ink">
                        Tercatat {{ $absensiSayaHariIni->status->shortLabel() }}
                        @if ($absensiSayaHariIni->keterangan)
                            <span class="font-normal text-brand-muted">&middot; {{ $absensiSayaHariIni->keterangan }}</span>
                        @endif
                    </p>
                @else
                    <p class="mt-0.5 text-base font-semibold text-brand-ink">Belum absen</p>
                    <p class="text-xs text-brand-muted">{{ now()->translatedFormat('l, d F Y') }}</p>
                @endif
            </div>
        </div>

        @if ($pegawaiSaya && ! $adaCatatan)
            <div class="flex flex-wrap items-center gap-2">
                {{-- Satu-satunya jalur absen mandiri yang tersisa: GPS.
                     Dipasang dengan Route::has() supaya ikut hilang sendiri di
                     panel yang memang tidak mendaftarkan rutenya (Super
                     Admin). --}}
                @if (Route::has($panelPrefix . '.absen-lokasi'))
                    <a href="{{ route($panelPrefix . '.absen-lokasi') }}"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-accent px-5 py-2.5 text-sm font-semibold text-white shadow-soft transition-colors hover:bg-brand-accent-dark">
                        <x-icon name="map-pin" class="h-5 w-5" />
                        Absen Kehadiran (Radius)
                    </a>
                @endif

            </div>
        @elseif ($adaCatatan)
            {{-- Tombol sengaja diganti badge non-klik: kehadiran hari ini sudah
                 terkunci (unique pegawai_id + tanggal di database). --}}
            <span class="inline-flex items-center gap-2 rounded-xl border border-brand-accent bg-brand-accent-soft px-5 py-2.5 text-sm font-semibold text-brand-accent-text">
                <x-icon name="shield-check" class="h-5 w-5" />
                Sudah Absen
            </span>
        @endif
    </div>
</div>
