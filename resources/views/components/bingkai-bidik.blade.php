{{-- ====================================================================
     KOTAK KAMERA + BINGKAI BIDIK

     Dipakai ketiga layar scan (Piket, Siswa, dan QR Ruangan guru) supaya
     tampilan kameranya sama di mana pun — dan supaya perbaikan di satu
     tempat berlaku untuk ketiganya.

     ============ SIKUNYA DIGAMBAR DI ATAS KOTAK MILIK LIBRARY ============
     Bingkai ini TIDAK digambar di posisi yang ditebak sendiri. Ia menempel
     pada elemen #qr-shaded-region — kotak yang dibuat html5-qrcode sendiri
     dan yang posisinya PERSIS sama dengan area yang benar-benar dipindai.

     Ini keputusan yang disengaja. Menggambar bingkai sendiri di tengah kotak
     kamera akan terlihat sama bagusnya, tapi menyesatkan: area pindai
     sebenarnya dihitung dari ukuran video, yang berbeda-beda tiap HP. Begitu
     keduanya bergeser, pengguna menaruh kartu tepat di dalam bingkai dan
     kodenya tidak pernah terbaca — kesalahan yang mustahil ditebak sebabnya
     oleh orang yang memakainya.

     Gayanya sendiri ada di resources/css/app.css (cari "BINGKAI BIDIK").
     ====================================================================

     @param string $id        id elemen yang diserahkan ke Html5Qrcode()
     @param string $petunjuk  teks pengarah di bawah kotak kamera --}}

@props([
    'id',
    'petunjuk' => 'Arahkan kode ke dalam bingkai',
])

<div {{ $attributes->merge(['class' => 'bingkai-bidik relative overflow-hidden bg-black']) }}>

    {{-- Elemen yang diisi html5-qrcode dengan video kamera.

         min-height-nya dipesan lewat CSS (.bingkai-bidik), bukan dibiarkan
         nol: tanpa itu kotaknya setinggi 0px selagi kamera menyala, lalu
         mendadak setinggi ~270px — dan seluruh isi halaman di bawahnya
         meloncat tepat saat pengguna hendak menekan sesuatu. --}}
    <div id="{{ $id }}" class="w-full"></div>

    {{-- Teks pengarah, menempel di dasar kotak.

         Diberi latar gradasi gelap supaya tetap terbaca di atas gambar kamera
         apa pun — teks putih polos akan hilang begitu kameranya menghadap
         dinding yang terang. pointer-events-none supaya tidak pernah
         menghalangi sentuhan ke area kamera. --}}
    <p class="pointer-events-none absolute inset-x-0 bottom-0 z-20 bg-gradient-to-t from-black/75 via-black/40 to-transparent px-4 pb-3 pt-10 text-center text-xs font-medium text-white/90">
        {{ $petunjuk }}
    </p>
</div>
