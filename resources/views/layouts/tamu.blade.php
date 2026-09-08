<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f766e">
    <title>{{ $title ?? 'Masuk' }} — SIMAGAS</title>

    {{-- Halaman ini tidak boleh diindeks mesin pencari. --}}
    <meta name="robots" content="noindex, nofollow">

    @include('partials.head-assets')

    @livewireStyles
</head>
<body class="min-h-screen bg-brand-surface-muted font-sans text-brand-ink antialiased">

    {{-- CATATAN: splash screen SENGAJA tidak dipasang di layout tamu.

         Splash-nya menahan layar ~1 detik. Di dalam aplikasi itu terasa
         seperti transisi merek; di halaman login ia cuma menunda orang yang
         sedang buru-buru absen. Merek SIMAGAS di sini sudah diwakili logo
         besar di dalam kartunya sendiri. --}}

    {{ $slot }}

    @livewireScripts
    @stack('scripts')
</body>
</html>
