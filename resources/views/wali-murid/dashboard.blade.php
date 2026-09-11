@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')

    @php
        use App\Enums\AbsensiStatus;

        $statusHariIni = $absensiHariIni?->status;
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">
            Halo, {{ auth()->user()->name }}
        </h1>
        <p class="mt-1 text-sm text-brand-muted">{{ now()->translatedFormat('l, d F Y') }}</p>
    </div>

    <div class="mx-auto w-full max-w-3xl">

        @if (! $anak)

            <div class="flex flex-col items-center gap-3 rounded-sm border border-dashed border-gray-200 bg-brand-surface p-10 text-center shadow-theme-sm">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-50 text-brand-muted">
                    <x-icon name="inbox" class="h-6 w-6" />
                </span>
                <p class="font-semibold text-brand-ink">Belum ada anak yang tertaut ke akun Anda</p>
                <p class="max-w-sm text-sm text-brand-muted">
                    Hubungi Admin TU sekolah agar data anak Anda dihubungkan ke akun ini.
                    Sesudah itu, seluruh menu di sebelah kiri akan terisi.
                </p>
            </div>

        @else

            {{-- ============ STATUS HARI INI ============
                 SENGAJA tanpa kelas .kilau. Sapuan cahayanya berwarna putih,
                 sedangkan kartu ini berlatar putih — efeknya tidak akan
                 terlihat sama sekali dan hanya menyisakan satu elemen semu
                 yang dianimasikan percuma di HP orang tua.

                 Kartu berwarna seperti status kehadiran guru adalah tempat
                 yang tepat untuk .kilau; kartu ini cukup dengan .muncul. --}}
            <div class="muncul rounded-sm border border-gray-200 bg-brand-surface shadow-theme-sm">
                <div class="flex flex-wrap items-center gap-5 px-6 py-5">
                    @php
                        $gaya = match (true) {
                            $statusHariIni === AbsensiStatus::Hadir => ['latar' => 'bg-success-500/10', 'teks' => 'text-success-500', 'ikon' => 'check-circle'],
                            $statusHariIni === AbsensiStatus::Alpha => ['latar' => 'bg-error-500/10', 'teks' => 'text-error-500', 'ikon' => 'x-circle'],
                            $statusHariIni !== null => ['latar' => 'bg-warning-500/10', 'teks' => 'text-[#9D5425]', 'ikon' => 'inbox'],
                            default => ['latar' => 'bg-gray-200', 'teks' => 'text-brand-muted', 'ikon' => 'clock'],
                        };
                    @endphp

                    {{-- Denyut riak HANYA saat belum ada catatan kedatangan —
                         satu-satunya keadaan di kartu ini yang membuat orang tua
                         perlu bertindak. Gerak yang muncul di setiap keadaan
                         berhenti menyampaikan apa pun. --}}
                    <span class="{{ $statusHariIni === null ? 'denyut' : '' }} flex h-12 w-12 shrink-0 items-center justify-center rounded-full {{ $gaya['latar'] }} {{ $gaya['teks'] }}">
                        <x-icon name="{{ $gaya['ikon'] }}" class="h-6 w-6" />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium uppercase tracking-wide text-brand-muted">Kedatangan hari ini</p>
                        <p class="mt-0.5 text-lg font-bold text-brand-ink">
                            @if ($statusHariIni === AbsensiStatus::Hadir)
                                Sudah tiba di sekolah
                                @if ($absensiHariIni->jam_masuk)
                                    <span class="text-base font-normal text-brand-muted">&middot; {{ $absensiHariIni->jam_masuk->format('H:i') }}</span>
                                @endif
                            @elseif ($statusHariIni)
                                Tercatat {{ $statusHariIni->shortLabel() }}
                            @else
                                Belum ada catatan kedatangan
                            @endif
                        </p>
                        <p class="mt-0.5 text-sm text-brand-muted">
                            {{ $anak->nama }} &middot; {{ $anak->kelas?->nama_kelas ?? 'tanpa kelas' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- ============ PINTASAN ============
                 Dashboard ini sengaja hanya ringkasan; isinya yang panjang ada
                 di tiga halaman berikut, supaya tidak ada tabel yang sama
                 ditampilkan di dua tempat. --}}
            <div class="tampil-berurutan mt-5 grid gap-4 sm:grid-cols-3">
                @php
                    $pintasan = [
                        ['rute' => '.absensi-kedatangan', 'judul' => 'Absensi Kedatangan', 'ket' => 'Riwayat scan gerbang bulan ini', 'ikon' => 'qr-code'],
                        ['rute' => '.pantauan-kbm',       'judul' => 'Pantauan KBM',       'ket' => 'Kehadiran per jam pelajaran hari ini', 'ikon' => 'eye'],
                        ['rute' => '.rekap-akademik',     'judul' => 'Rekap Akademik',     'ket' => 'Persentase kehadiran per bulan', 'ikon' => 'document-report'],
                    ];
                @endphp

                @foreach ($pintasan as $p)
                    @if (Route::has($panelPrefix . $p['rute']))
                        <a href="{{ route($panelPrefix . $p['rute']) }}"
                            class="kartu-angkat rounded-sm border border-gray-200 bg-brand-surface px-5 py-5 shadow-theme-sm hover:border-brand-500">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-500/10 text-brand-500">
                                <x-icon name="{{ $p['ikon'] }}" class="h-5 w-5" />
                            </span>
                            <p class="mt-3 font-semibold text-brand-ink">{{ $p['judul'] }}</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-brand-muted">{{ $p['ket'] }}</p>
                        </a>
                    @endif
                @endforeach
            </div>

            @if ($anakAnak->count() > 1)
                <p class="mt-4 text-center text-xs text-brand-muted">
                    Anda punya {{ $anakAnak->count() }} anak terdaftar. Pilih anak yang ingin
                    dilihat di masing-masing halaman.
                </p>
            @endif

        @endif
    </div>

@endsection
