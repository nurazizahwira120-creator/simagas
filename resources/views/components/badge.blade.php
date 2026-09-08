@props([
    // Dua cara pakai (tag-nya SENGAJA ditulis tanpa kurung sudut di sini —
    // lihat catatan di bawah):
    //   x-badge status="hadir"     -> warna dipilih otomatis dari statusnya
    //   x-badge warna="warning"    -> warna ditentukan sendiri
    'status' => null,
    'warna' => null,
    'ikon' => null,
])

{{--
    Badge status TailAdmin.

    JANGAN MENULIS TAG KOMPONEN (<x-…>) DI DALAM KOMENTAR BERKAS INI.
    Blade mengompilasi tag komponen SEBELUM membuang komentar, jadi contoh
    pemakaian yang ditulis lengkap dengan kurung sudut tetap diproses — dan
    di berkas ini artinya badge memanggil dirinya sendiri. Gejalanya:
    "syntax error, unexpected token endif" yang menunjuk ke berkas ini,
    padahal @if/@endif-nya sudah berpasangan. Sudah pernah terjadi.

    KENAPA ADA PEMETAAN OTOMATIS DARI `status`:
    warna status absensi sebelumnya ditulis ulang di banyak halaman, dan
    tiap kali salah satunya diperbaiki yang lain tertinggal — "hijau untuk
    hadir" di halaman guru pernah berbeda dengan di halaman wali murid.
    Dengan komponen ini, aturannya hidup di SATU tempat: ubah di sini,
    seluruh aplikasi ikut.

    Peta warnanya mengikuti brief:
      hijau  -> hadir
      kuning -> izin / sakit / terlambat
      merah  -> alpa / alpha / bolos
    Sakit dibedakan jadi ORANYE (bukan kuning) khusus di halaman Daftar Izin
    Kepala Sekolah, lewat prop `warna` — supaya dalam satu tabel yang isinya
    izin dan sakit saja, keduanya masih bisa dibedakan sekilas.
--}}
@php
    $kunci = $status instanceof \BackedEnum ? $status->value : (string) $status;

    $warnaFinal = $warna ?? match (strtolower($kunci)) {
        'hadir' => 'success',
        'izin', 'sakit', 'terlambat' => 'warning',
        'alpa', 'alpha', 'bolos' => 'danger',
        default => 'netral',
    };

    $kelas = match ($warnaFinal) {
        'success' => 'bg-success-500/10 text-success-700 dark:text-success-400 ring-success-500/20',
        'warning' => 'bg-warning-500/10 text-warning-700 dark:text-warning-400 ring-warning-500/20',
        'orange' => 'bg-orange-500/10 text-orange-700 dark:text-orange-400 ring-orange-500/20',
        'danger' => 'bg-error-500/10 text-error-700 dark:text-error-400 ring-error-500/20',
        'info' => 'bg-brand-500/10 text-brand-700 dark:text-brand-400 ring-brand-500/20',
        default => 'bg-gray-500/10 text-gray-700 dark:text-gray-300 ring-gray-500/20',
    };
@endphp

<span {{ $attributes->merge([
    'class' => 'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ' . $kelas,
]) }}>
    @if ($ikon)
        <x-icon :name="$ikon" class="h-3.5 w-3.5" />
    @endif
    {{ $slot }}
</span>
