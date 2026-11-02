@props(['status', 'anak' => null, 'kontak' => null])

{{--
    Kartu "Kedatangan hari ini" — dipakai Beranda DAN Absensi Kedatangan,
    supaya kedua halaman tidak pernah berbeda pendapat tentang anak yang sama.

    Isinya disusun App\Services\StatusKedatanganAnak. Aturan warnanya:
    merah HANYA untuk Alpa resmi. "Belum ada catatan" tidak pernah merah —
    lihat penjelasan di kepala service tersebut.

    $kontak (opsional): ['nama' => ..., 'url' => 'https://wa.me/...'] dari
    StatusKedatanganAnak::kontakWaliKelas(). Tombolnya hanya muncul bila
    nomor wali kelas tersimpan DAN keadaannya memang perlu ditanyakan.
--}}
@php
    $gaya = match ($status['jenis']) {
        'libur' => 'border-gray-200 bg-gray-100 text-gray-700 dark:border-gray-800 dark:bg-white/[0.04] dark:text-gray-300',
        'belum' => 'border-blue-light-200 bg-blue-light-50 text-blue-light-800 dark:border-blue-light-500/30 dark:bg-blue-light-500/10 dark:text-blue-light-300',
        'telat' => 'border-warning-200 bg-warning-50 text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300',
        'hadir' => 'border-success-200 bg-success-50 text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300',
        'izin', 'sakit' => 'border-theme-purple-500/25 bg-theme-purple-500/[0.07] text-[#4a2fbd] dark:bg-theme-purple-500/10 dark:text-[#c9c0fd]',
        default => 'border-error-200 bg-error-50 text-error-800 dark:border-error-500/30 dark:bg-error-500/10 dark:text-error-300',
    };
@endphp

<div {{ $attributes->merge(['class' => 'muncul rounded-2xl border p-4 sm:p-5 ' . $gaya]) }}
    role="status" data-status-kedatangan="{{ $status['jenis'] }}">
    <div class="flex items-start gap-3.5">

        {{-- Denyut riak HANYA saat anak belum tiba sebelum batas masuk —
             satu-satunya keadaan yang sedang "ditunggu". --}}
        <span class="{{ $status['denyut'] ? 'denyut' : '' }} flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-white/80 dark:bg-white/10">
            <x-icon name="{{ $status['ikon'] }}" class="h-6 w-6" />
        </span>

        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold uppercase tracking-wide opacity-80">
                Kedatangan hari ini
                @if ($anak)
                    <span class="font-semibold normal-case tracking-normal">&middot; {{ $anak->nama }}@if ($anak->kelas?->nama_kelas) ({{ $anak->kelas->nama_kelas }})@endif</span>
                @endif
            </p>
            <p class="mt-0.5 text-lg font-extrabold leading-snug tracking-tight sm:text-xl">{{ $status['judul'] }}</p>
            <p class="mt-1 text-sm leading-relaxed [overflow-wrap:anywhere]">{{ $status['keterangan'] }}</p>

            @if ($status['hubungi'] && $kontak)
                <a href="{{ $kontak['url'] }}" target="_blank" rel="noopener noreferrer"
                    class="mt-3 inline-flex min-h-11 items-center gap-2 rounded-xl border border-current bg-white px-4 py-2 text-sm font-bold transition hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-current dark:bg-gray-900">
                    <x-icon name="phone" class="h-4 w-4" />
                    Hubungi Wali Kelas
                </a>
            @endif
        </div>
    </div>
</div>
