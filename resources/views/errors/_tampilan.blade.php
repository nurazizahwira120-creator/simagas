{{--
    Tampilan bersama untuk SELURUH halaman galat.

    ============ KENAPA TIDAK MEMAKAI layouts/app ============
    Halaman ini muncul justru ketika ada yang rusak. Kalau ia memakai layout
    aplikasi, ia ikut memuat sidebar — dan sidebar membaca database untuk
    mengetahui peran pengguna, menu, serta jumlah notifikasi.

    Artinya: kalau yang rusak adalah databasenya, halaman galatnya ikut
    melempar galat, dan yang dilihat pengguna kembali menjadi layar putih
    "500 Server Error" bawaan — persis keadaan yang ingin dihilangkan.

    Maka halaman ini berdiri sendiri sepenuhnya:
      - TIDAK @extends layout mana pun
      - TIDAK menyentuh database (config() membaca .env, bukan tabel)
      - TIDAK memakai Livewire, Alpine, atau berkas JS apa pun
      - CSS-nya DITANAM di dalam berkas ini, tidak memanggil simagas.css
        yang mungkin belum tersalin ke public_html setelah deploy gagal
      - TIDAK memanggil route(), karena cache rute yang rusak justru salah
        satu penyebab galat yang mungkin membawa pengguna ke sini
    ==========================================================

    Variabel yang dikirim tiap halaman: $kode, $judul, $pesan, $saran,
    $nada ('merah' | 'kuning' | 'netral'), $tampilkanWaktu.
--}}
@php
    $nada = $nada ?? 'netral';

    $warna = [
        'merah' => ['#b42318', '#fef3f2', '#fecdca'],
        'kuning' => ['#b54708', '#fffaeb', '#fedf89'],
        'netral' => ['#344054', '#f9fafb', '#e4e7ec'],
    ][$nada];

    $aplikasi = config('sekolah.aplikasi', 'SIMAGAS');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $judul }} — {{ $aplikasi }}</title>

    <style>
        /* Ditanam di sini dengan sengaja — lihat catatan di atas berkas. */
        :root {
            --aksen: #0d9488;
            --tinta: #101828;
            --redup: #475467;
            --garis: #e4e7ec;
            --latar: #f2f4f7;
            --kartu: #ffffff;
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --tinta: #f9fafb;
                --redup: #98a2b3;
                --garis: #333741;
                --latar: #0c111d;
                --kartu: #161b26;
            }
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: var(--latar);
            color: var(--tinta);
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .kotak {
            width: 100%;
            max-width: 440px;
            background: var(--kartu);
            border: 1px solid var(--garis);
            border-radius: 20px;
            padding: 32px 24px;
            text-align: center;
            box-shadow: 0 12px 32px rgba(16, 24, 40, .08);
        }

        .logo { width: 56px; height: 56px; object-fit: contain; margin-bottom: 18px; }

        .lencana {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
            color: {{ $warna[0] }};
            background: {{ $warna[1] }};
            border: 1px solid {{ $warna[2] }};
        }

        h1 { margin: 16px 0 8px; font-size: 21px; line-height: 1.35; font-weight: 800; }

        p.pesan { margin: 0; color: var(--redup); font-size: 15px; }

        ul {
            margin: 18px 0 0;
            padding: 14px 16px 14px 32px;
            text-align: left;
            background: var(--latar);
            border-radius: 12px;
            color: var(--redup);
            font-size: 14px;
        }

        ul li + li { margin-top: 6px; }

        .tombol { margin-top: 22px; display: flex; flex-direction: column; gap: 10px; }

        a.aksi, button.aksi {
            display: block;
            width: 100%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            font-family: inherit;
        }

        .utama { background: var(--aksen); color: #fff; }
        .kedua { background: transparent; color: var(--redup); border-color: var(--garis); }

        .kode {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px dashed var(--garis);
            font-size: 12px;
            color: var(--redup);
        }

        .kode strong {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 13px;
            color: var(--tinta);
            letter-spacing: .02em;
        }

        @media (min-width: 480px) {
            .tombol { flex-direction: row-reverse; }
        }
    </style>
</head>
<body>
    <main class="kotak">
        {{-- Logo dilayani sebagai berkas statis dari akar situs, bukan lewat
             helper asset(): satu pemanggilan helper pun berarti satu
             kemungkinan tambahan halaman ini ikut gagal. Kalau berkasnya
             tidak ada, teks alt yang muncul — bukan halaman rusak. --}}
        <img class="logo" src="/logo-mark.png" alt="{{ $aplikasi }}">

        <div class="lencana">{{ $kode }}</div>

        <h1>{{ $judul }}</h1>
        <p class="pesan">{{ $pesan }}</p>

        @if (! empty($saran))
            <ul>
                @foreach ($saran as $butir)
                    <li>{{ $butir }}</li>
                @endforeach
            </ul>
        @endif

        <div class="tombol">
            <button type="button" class="aksi utama" onclick="location.reload()">Coba Lagi</button>
            <a class="aksi kedua" href="/">Kembali ke Beranda</a>
        </div>

        @if ($tampilkanWaktu ?? false)
            {{-- Kode waktu ini yang dicari di storage/logs/laravel.log saat
                 guru melapor. Tanpanya, pertanyaan "error jam berapa?" hampir
                 selalu dijawab "tadi" — dan mencari barisnya jadi menebak. --}}
            <p class="kode">
                Sebutkan kode ini saat melapor:<br>
                <strong>{{ now()->format('d/m H:i') }}</strong>
            </p>
        @endif
    </main>
</body>
</html>
