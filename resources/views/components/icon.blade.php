@props(['name', 'class' => 'w-5 h-5'])

{{--
    Satu set ikon garis (line icon) sederhana, dipakai lewat <x-icon name="..." />.
    Semua di-draw manual dalam viewBox 24x24 tanpa dependency eksternal — supaya
    tidak nambah request/CDN baru dan konsisten gaya "outline" di semua halaman.
--}}
@php
    $icons = [
        'home' => '<path d="M3.5 10.5 12 3l8.5 7.5" /><path d="M5.5 9.5V20a1 1 0 0 0 1 1h11a1 1 0 0 0 1-1V9.5" /><path d="M9.5 21v-6h5v6" />',

        'chart-bar' => '<path d="M4 19h16" /><rect x="6" y="12" width="3" height="7" rx="0.6" /><rect x="10.5" y="7.5" width="3" height="11.5" rx="0.6" /><rect x="15" y="14.5" width="3" height="4.5" rx="0.6" />',

        'document-report' => '<rect x="6" y="3.2" width="12" height="17.6" rx="2" /><path d="M9 8.2h6" /><path d="M9 11.8h6" /><path d="M9 15.4h3.5" />',

        'users' => '<circle cx="8.7" cy="8" r="3" /><circle cx="16.3" cy="9.3" r="2.3" /><path d="M2.8 20c.3-3.5 2.8-6.2 5.9-6.2s5.6 2.7 5.9 6.2" /><path d="M15 14.3c2.2.4 3.9 2.4 4.2 5.7" />',

        'academic-cap' => '<path d="M12 4 2.5 8.5 12 13l9.5-4.5L12 4Z" /><path d="M6.5 10.8V16c0 1.2 2.5 3 5.5 3s5.5-1.8 5.5-3v-5.2" /><path d="M21 9v6" />',

        'identification' => '<rect x="3" y="5" width="18" height="14" rx="2" /><circle cx="8.5" cy="11" r="2" /><path d="M6 15.8c.4-1.5 1.4-2.4 2.5-2.4s2.1.9 2.5 2.4" /><path d="M14 9.3h4" /><path d="M14 12.7h4" />',

        'logout' => '<path d="M9 4H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h4" /><path d="M13 16l4-4-4-4" /><path d="M17 12H9" />',

        'mail' => '<rect x="3" y="5.5" width="18" height="13" rx="2" /><path d="M4 7l8 6 8-6" />',

        'lock' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2" /><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />',

        'clipboard-check' => '<path d="M9 4.5h6a1 1 0 0 1 1 1V7H8V5.5a1 1 0 0 1 1-1Z" /><path d="M16 6h1.5A1.5 1.5 0 0 1 19 7.5v11A1.5 1.5 0 0 1 17.5 20h-11A1.5 1.5 0 0 1 5 18.5v-11A1.5 1.5 0 0 1 6.5 6H8" /><path d="m9.3 13.4 1.9 1.9 3.6-3.6" />',

        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" /><circle cx="12" cy="12" r="2.6" />',

        'eye-off' => '<path d="M3 3l18 18" /><path d="M10.6 5.6A10 10 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a15.6 15.6 0 0 1-3.2 4" /><path d="M6.3 7.3C4 9 2.5 12 2.5 12s3.5 6.5 9.5 6.5c1.3 0 2.5-.3 3.6-.8" /><path d="M9.9 10a2.6 2.6 0 0 0 3.6 3.6" />',

        'building' => '<path d="M3 21h18" /><path d="M4.5 21V9l7.5-5 7.5 5v12" /><path d="M9.25 21v-5.5h5.5V21" /><path d="M9 12h.01" /><path d="M15 12h.01" /><path d="M9 8.6h.01" /><path d="M15 8.6h.01" />',

        'map-pin' => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11Z" /><circle cx="12" cy="10" r="2.6" />',

        'camera' => '<path d="M4 8.5A1.5 1.5 0 0 1 5.5 7h2l1-2h7l1 2h2A1.5 1.5 0 0 1 20 8.5v9A1.5 1.5 0 0 1 18.5 19h-13A1.5 1.5 0 0 1 4 17.5v-9Z" /><circle cx="12" cy="13" r="3.4" />',

        'qr-code' => '<rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1" /><rect x="14" y="3.5" width="6.5" height="6.5" rx="1" /><rect x="3.5" y="14" width="6.5" height="6.5" rx="1" /><path d="M14 14h3v3h-3z" /><path d="M20.5 14v3.2" /><path d="M14 20.5h3.2" /><path d="M20.5 20.5h.01" />',

        'check-circle' => '<circle cx="12" cy="12" r="9" /><path d="M8.3 12.3l2.5 2.5 4.9-5.4" />',

        'x-circle' => '<circle cx="12" cy="12" r="9" /><path d="M9.5 9.5l5 5" /><path d="M14.5 9.5l-5 5" />',

        'exclamation-triangle' => '<path d="M12 3.5 21.5 20h-19L12 3.5Z" /><path d="M12 10v4.2" /><path d="M12 17.2h.01" />',

        'keyboard' => '<rect x="2.5" y="6" width="19" height="12" rx="2" /><path d="M6 10h.01M9.5 10h.01M13 10h.01M16.5 10h.01" /><path d="M6 13.5h11" />',

        'clock' => '<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" />',

        'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2" /><path d="M8 3v4M16 3v4M3.5 10h17" />',

        'printer' => '<path d="M6.5 9V4a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v5" /><rect x="3.5" y="9" width="17" height="8" rx="1.5" /><rect x="7" y="13.5" width="10" height="6.5" rx="1" />',

        'plus' => '<path d="M12 5v14M5 12h14" />',

        'pencil' => '<path d="M4 20l1-4.3L15.3 5.4a1.6 1.6 0 0 1 2.3 0l1 1a1.6 1.6 0 0 1 0 2.3L8.3 19l-4.3 1Z" /><path d="M13.7 6.8l3.5 3.5" />',

        'trash' => '<path d="M4.5 7h15" /><path d="M9.5 7V5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1v2" /><path d="M6.5 7l1 12.5a1.5 1.5 0 0 0 1.5 1.4h6a1.5 1.5 0 0 0 1.5-1.4L17.5 7" /><path d="M10 11v6M14 11v6" />',

        'arrow-left' => '<path d="M19 12H5" /><path d="M11 6l-6 6 6 6" />',

        'arrow-right' => '<path d="M5 12h14" /><path d="M13 6l6 6-6 6" />',

        'chevron-down' => '<path d="M6 9l6 6 6-6" />',

        'funnel' => '<path d="M3.5 4h17l-6.5 8v6l-4 2v-8L3.5 4Z" />',

        'phone' => '<path d="M6 3.5h3l1.5 4-2 1.6a11 11 0 0 0 5.4 5.4l1.6-2 4 1.5v3a1.5 1.5 0 0 1-1.6 1.5A16.5 16.5 0 0 1 4.5 5.1 1.5 1.5 0 0 1 6 3.5Z" />',

        'hashtag' => '<path d="M5 9h14M5 15h14M10 4 8 20M16 4l-2 16" />',

        'shield-check' => '<path d="M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3Z" /><path d="M9 12l2.2 2.2L15.5 9.7" />',

        'inbox' => '<path d="M3.5 12h4.7l1.3 2.5h4.9l1.3-2.5h4.8" /><path d="M5 6h14l1.5 6v6.5A1.5 1.5 0 0 1 19 20H5a1.5 1.5 0 0 1-1.5-1.5V12L5 6Z" />',

        'search' => '<circle cx="10.5" cy="10.5" r="6.5" /><path d="M20 20l-4.5-4.5" />',

        'menu' => '<path d="M4 6h16M4 12h16M4 18h16" />',

        'briefcase' => '<rect x="3" y="7.5" width="18" height="12" rx="2" /><path d="M8.5 7.5V6a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v1.5" /><path d="M3 13h18" /><path d="M10.5 13v1.5h3V13" />',

        'swap' => '<path d="M4 8h13" /><path d="M14 4l3 4-3 4" /><path d="M20 16H7" /><path d="M10 20l-3-4 3-4" />',

        'upload' => '<path d="M12 15.5V4" /><path d="M7.5 8.5 12 4l4.5 4.5" /><path d="M4.5 15.5V18a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-2.5" />',

        'download' => '<path d="M12 4v11.5" /><path d="M7.5 11l4.5 4.5 4.5-4.5" /><path d="M4.5 15.5V18a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-2.5" />',

        'bell' => '<path d="M18 8.5a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16s-2-1.5-2-6.5Z" /><path d="M13.7 18.5a2 2 0 0 1-3.4 0" />',

        'cog' => '<circle cx="12" cy="12" r="3.2" /><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09A1.7 1.7 0 0 0 8.9 19.3a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.7 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.7 8.9a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1.03-1.56V3a2 2 0 1 1 4 0v.09A1.7 1.7 0 0 0 15.1 4.7a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9v.09A1.7 1.7 0 0 0 21 10.1a2 2 0 1 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1.03Z" />',

        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5" />',

        'x-mark' => '<path d="M6 6l12 12" /><path d="M18 6L6 18" />',

        'chart-pie' => '<path d="M12 3v9h9" /><path d="M21 12a9 9 0 1 1-9-9" />',

        // Pasangan matahari/bulan untuk tombol ganti tema di topbar.
        'sun' => '<circle cx="12" cy="12" r="4" /><path d="M12 2.5v2" /><path d="M12 19.5v2" /><path d="M4.6 4.6 6 6" /><path d="M18 18l1.4 1.4" /><path d="M2.5 12h2" /><path d="M19.5 12h2" /><path d="M4.6 19.4 6 18" /><path d="M18 6l1.4-1.4" />',

        'moon' => '<path d="M20 13.5A8.5 8.5 0 0 1 10.5 4a8.5 8.5 0 1 0 9.5 9.5Z" />',

        // Panah ganda untuk menciutkan sidebar jadi ikon saja.
        'chevron-double-left' => '<path d="m11 7-5 5 5 5" /><path d="m17 7-5 5 5 5" />',
    ];
@endphp

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
    stroke-linecap="round" stroke-linejoin="round" class="{{ $class }}" aria-hidden="true">
    {!! $icons[$name] ?? '' !!}
</svg>
