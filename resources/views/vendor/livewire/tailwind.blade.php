{{-- Menimpa template pagination bawaan Livewire (livewire::tailwind).
     Livewire mewajibkan SATU elemen akar, karena itu dibungkus <div>.
     Tampilannya sama dengan halaman biasa — lihat partials/pagination. --}}
<div>
    @include('partials.pagination', ['mode' => 'livewire', 'scrollTo' => $scrollTo ?? 'body'])
</div>
