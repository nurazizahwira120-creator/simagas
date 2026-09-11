{{-- ====================================================================
     ANGKA STATISTIK YANG MENGHITUNG NAIK SAAT DASHBOARD DIBUKA

     Pemakaian:
         <x-angka-naik :nilai="$totalSiswa" class="text-3xl font-bold" />
         <x-angka-naik :nilai="$persen" :desimal="1" akhiran="%" />
         <x-angka-naik :nilai="null" kosong="—" />   (tidak dianimasikan)

     ============ ANGKANYA DIRENDER SERVER, BUKAN JAVASCRIPT ============
     Isi elemennya sudah berupa angka final lengkap dengan pemisah ribuan.
     JavaScript hanya meminjamnya untuk dihitung naik lalu mengembalikannya
     (lihat partials/gerak.blade.php).

     Akibatnya, kalau JavaScript mati total: angkanya tetap benar, hanya
     tidak beranimasi. Kalau urutannya dibalik — 0 di HTML, diisi JS —
     kegagalan sekecil apa pun berarti kepala sekolah membaca angka nol
     pada dashboard kehadiran. Itu bukan risiko yang sepadan dengan sebuah
     efek.
     ====================================================================

     @param int|float|null $nilai    angka yang ditampilkan; null = tidak ada data
     @param int            $desimal  jumlah angka di belakang koma
     @param string         $akhiran  satuan yang menempel di belakang (mis. '%')
     @param string         $kosong   yang ditampilkan bila $nilai null --}}

@props([
    'nilai' => null,
    'desimal' => 0,
    'akhiran' => '',
    'kosong' => '—',
])

@if ($nilai === null)
    {{-- Tidak ada data. Sengaja TIDAK dirender sebagai 0: "belum ada
         siswa terdaftar" berbeda arti dari "tidak ada yang hadir", dan
         menampilkannya sebagai nol besar di dashboard membuat pembacanya
         salah menyimpulkan keadaan sekolah. --}}
    <span {{ $attributes->merge(['class' => 'angka-naik']) }}>{{ $kosong }}</span>
@else
    {{-- data-akhiran dibaca skrip saat menggambar angka antara, supaya tanda
         persen tidak berkedip hilang selama hitungan berjalan lalu muncul
         lagi di akhir. --}}
    <span {{ $attributes->merge(['class' => 'angka-naik']) }}
        data-akhiran="{{ $akhiran }}"
        x-data
        x-init="window.hitungNaik?.($el, {{ (float) $nilai }}, {{ (int) $desimal }})">{{ number_format((float) $nilai, (int) $desimal, ',', '.') }}{{ $akhiran }}</span>
@endif
