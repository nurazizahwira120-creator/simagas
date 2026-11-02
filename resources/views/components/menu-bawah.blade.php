{{--
    BILAH MENU BAWAH — khusus layar HP & tablet (disembunyikan mulai lg).

    Dibuat untuk wali murid: orang tua terbiasa dengan aplikasi seperti
    WhatsApp dan m-banking yang menunya selalu ada di bawah jempol. Sebelum
    ini, setiap pindah halaman harus membuka menu ☰ lebih dulu dan memilih
    dari sepuluh butir berisi istilah sekolah.

    Lima butir saja; halaman lainnya tetap bisa dibuka dari menu ☰.
    "Kehadiran" menyala juga di Pantauan KBM & Rekap Akademik, dan "Akun"
    menyala di Kalender & Panduan, supaya orang tua selalu tahu ia sedang
    berada di bagian mana.

    Peran lain belum memakainya — cukup tambahkan daftarnya di $menu di
    bawah, lalu nyalakan $adaMenuBawah di layouts/app.blade.php.
--}}
@php
    $peran = auth()->user()?->role;
    $prefix = $peran?->routePrefix() ?? '';

    $menu = match ($peran) {
        \App\Enums\UserRole::WaliMurid => [
            ['label' => 'Beranda',   'ikon' => 'home',         'rute' => '.dashboard',          'nyala' => ['.dashboard']],
            ['label' => 'Kehadiran', 'ikon' => 'check-circle', 'rute' => '.absensi-kedatangan', 'nyala' => ['.absensi-kedatangan', '.pantauan-kbm', '.rekap-akademik']],
            ['label' => 'Jadwal',    'ikon' => 'calendar',     'rute' => '.jadwal-pelajaran',   'nyala' => ['.jadwal-pelajaran', '.ekskul*']],
            ['label' => 'Rapor',     'ikon' => 'academic-cap', 'rute' => '.rapor',              'nyala' => ['.rapor']],
            ['label' => 'Akun',      'ikon' => 'user',         'rute' => '.profil',             'nyala' => ['.profil*', '.kalender-pendidikan', '.panduan']],
        ],
        default => [],
    };

    // Butir yang rutenya tidak terdaftar dilewati, bukan menjadi tautan mati.
    $menu = array_values(array_filter($menu, fn ($m) => $prefix !== '' && Route::has($prefix . $m['rute'])));
@endphp

@if ($menu !== [])
    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-gray-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden dark:border-gray-800 dark:bg-gray-900/95 print:hidden"
        aria-label="Menu utama">
        <ul class="mx-auto flex max-w-xl">
            @foreach ($menu as $m)
                @php
                    $aktif = request()->routeIs(...array_map(fn ($pola) => $prefix . $pola, $m['nyala']));
                @endphp
                <li class="flex-1">
                    <a href="{{ route($prefix . $m['rute']) }}"
                        @if ($aktif) aria-current="page" @endif
                        class="flex min-h-14 flex-col items-center justify-center gap-0.5 px-1 pb-1.5 pt-2 text-[11px] font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-brand-500 {{ $aktif ? 'text-brand-700 dark:text-brand-300' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">
                        <span class="flex h-7 w-12 items-center justify-center rounded-full transition-colors {{ $aktif ? 'bg-brand-100 dark:bg-brand-500/20' : '' }}">
                            <x-icon name="{{ $m['ikon'] }}" class="h-5 w-5" />
                        </span>
                        {{ $m['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
@endif
