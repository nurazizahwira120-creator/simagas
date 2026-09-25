{{--
    Template PDF Lembar Paraf Guru Mengajar — dirender dompdf, BUKAN browser.

    ============ ATURAN MAIN DOMPDF ============
    Sama seperti laporan.rekap-kbm-pdf: tidak ada flexbox/grid, tidak ada
    class Tailwind, tidak ada gambar dari internet, tidak ada :nth-child.
    Seluruh tata letak memakai <table> dan lebar persen.
    ============================================

    ============ KENAPA KAKI HALAMAN position:fixed ============
    Elemen fixed diulang dompdf di SETIAP halaman. Waktu cetak sengaja
    ditaruh di sana, bukan hanya di kop: lembar yang terdiri dari tiga
    halaman sering dipisah stapler-nya, dan halaman kedua yang berdiri
    sendiri tetap harus bisa menjawab "ini dicetak kapan, oleh siapa".
    ============================================================
--}}
@php
    // Logo ditanam sebagai data URI. isRemoteEnabled dimatikan, dan dompdf
    // tidak membaca berkas di luar chroot-nya — data URI satu-satunya jalan
    // yang pasti berhasil di hosting mana pun. Kalau berkasnya tidak ada,
    // kop tetap tercetak tanpa logo, bukan gagal.
    $jalurLogo = public_path('logo-mark.png');
    $logo = is_file($jalurLogo) ? 'data:image/png;base64,' . base64_encode(file_get_contents($jalurLogo)) : null;

    $namaSekolah = \App\Models\PengaturanSistem::namaSekolah();
    $judulTanggal = $hari . ', ' . $tanggal->translatedFormat('d F Y');
    $waktuCetak = $dicetak_pada->translatedFormat('l, d F Y') . ' pukul ' . $dicetak_pada->format('H.i') . ' WIB';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Paraf Guru Mengajar — {{ $judulTanggal }}</title>
    <style>
        /* DejaVu Sans: satu-satunya font bawaan dompdf yang memuat – dan ·. */
        * { font-family: "DejaVu Sans", sans-serif; }

        /* Margin bawah lebih lebar untuk memberi ruang kaki halaman fixed. */
        @page { margin: 12mm 12mm 18mm 12mm; }

        body { font-size: 8.5px; color: #111827; margin: 0; }

        table { width: 100%; border-collapse: collapse; }

        /* ---------- KOP ---------- */
        .kop td { vertical-align: middle; }
        .kop .logo { width: 46px; height: 46px; }
        .kop .sekolah { font-size: 12px; font-weight: bold; letter-spacing: 0.3px; }
        .kop .judul { font-size: 14px; font-weight: bold; color: #0f766e; margin-top: 2px; letter-spacing: 0.4px; }
        .kop .sub { font-size: 8px; color: #4b5563; margin-top: 2px; }
        .garis-kop { border-bottom: 2.2px solid #0f766e; margin: 6px 0 1.5px; }
        .garis-kop-tipis { border-bottom: 0.6px solid #0f766e; margin-bottom: 8px; }

        /* ---------- IDENTITAS LEMBAR ---------- */
        .identitas td { font-size: 8.5px; padding: 1.5px 0; }
        .identitas .label { width: 22%; color: #4b5563; }
        .identitas .isi { font-weight: bold; }

        .peringatan { margin: 6px 0 2px; padding: 5px 7px; border: 0.8px solid #d97706;
                      background: #fffbeb; color: #92400e; font-size: 8px; }

        /* ---------- TABEL UTAMA ---------- */
        .data { margin-top: 8px; }
        .data th, .data td { border: 0.7px solid #6b7280; }
        .data th { background: #e6f4f1; color: #134e4a; font-size: 7.5px; font-weight: bold;
                   text-transform: uppercase; padding: 5px 3px; text-align: center; }
        .data td { font-size: 8px; padding: 3px 4px; vertical-align: middle; }

        /* Tinggi baris cukup untuk tanda tangan tangan sungguhan (~9 mm). */
        .data tbody tr { height: 34px; }

        /* Satu baris tidak boleh terbelah di dua halaman — paraf yang
           setengahnya di halaman berikut tidak bisa dibaca siapa pun. */
        .data tr { page-break-inside: avoid; }

        .zebra { background: #f9fafb; }
        .tengah { text-align: center; }
        .kecil { font-size: 7px; color: #4b5563; }
        .tebal { font-weight: bold; }

        .sistem-ok { color: #047857; }
        .sistem-kurang { color: #b45309; }
        .sistem-nihil { color: #b91c1c; }
        .sistem-netral { color: #6b7280; }

        /* Kolom paraf: pola daftar hadir sekolah — nomor ganjil menandatangani
           di kiri, nomor genap di kanan, supaya paraf baris yang berdekatan
           tidak saling bertumpuk. Dibuat dengan tabel dua sel 50/50 karena
           padding persen di dalam <td> diabaikan dompdf. */
        .paraf { padding: 0; }
        .paraf table td { border: 0; padding: 2px 4px; vertical-align: top; width: 50%; }
        .paraf .nomor { font-size: 7px; color: #9ca3af; }

        .izin { text-align: center; background: #fef2f2; }
        .izin .cap { display: inline-block; border: 1.2px solid #b91c1c; color: #b91c1c;
                     font-weight: bold; font-size: 9px; letter-spacing: 0.8px; padding: 2px 6px; }
        .izin .rinci { font-size: 6.5px; color: #7f1d1d; margin-top: 2px; }

        .menunggu { font-size: 6.5px; color: #b45309; }

        .kosong { text-align: center; color: #6b7280; padding: 18px; font-style: italic;
                  border: 0.7px dashed #9ca3af; margin-top: 10px; }

        /* ---------- RINGKASAN & TANDA TANGAN ---------- */
        .ringkas { margin-top: 8px; font-size: 7.5px; color: #374151; }
        .ttd { margin-top: 16px; page-break-inside: avoid; }
        .ttd td { width: 50%; text-align: center; font-size: 8.5px; vertical-align: top; }
        .ttd .ruang { height: 48px; }
        .ttd .nama { font-weight: bold; text-decoration: underline; }

        .petunjuk { margin-top: 12px; padding-top: 5px; border-top: 0.6px solid #d1d5db;
                    font-size: 7px; color: #4b5563; line-height: 1.5; }

        /* ---------- KAKI SETIAP HALAMAN ---------- */
        .kaki { position: fixed; bottom: -11mm; left: 0; right: 0; height: 8mm;
                border-top: 0.6px solid #9ca3af; padding-top: 3px; font-size: 7px; color: #4b5563; }
        .kaki td { font-size: 7px; color: #4b5563; }
    </style>
</head>
<body>

    {{-- Kaki halaman ditulis PALING ATAS di body: dompdf hanya mengulang
         elemen fixed mulai dari halaman tempat elemen itu muncul. Kalau
         ditaruh di akhir, halaman pertama tidak mendapatkannya. --}}
    <div class="kaki">
        <table>
            <tr>
                <td style="width:78%;">
                    Dicetak: <strong>{{ $waktuCetak }}</strong> oleh {{ $dicetak_oleh }} ({{ $peran_pencetak }})
                    &middot; {{ config('sekolah.aplikasi') }}
                </td>
                {{-- Nomor "Halaman x dari y" TIDAK ditulis di sini. counter(pages)
                     di CSS menghasilkan "dari 0" pada dompdf, karena jumlah
                     halaman belum diketahui saat elemen ini digambar. Nomornya
                     dicetak sesudah render lewat canvas — lihat
                     LembarParafMengajar::render(). --}}
                <td style="width:22%;"></td>
            </tr>
        </table>
    </div>

    {{-- ============ KOP ============ --}}
    <table class="kop">
        <tr>
            <td style="width:56px;">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="">
                @endif
            </td>
            <td>
                <div class="sekolah">{{ $namaSekolah }}</div>
                <div class="judul">LEMBAR PARAF GURU MENGAJAR</div>
                <div class="sub">Cadangan manual laporan kegiatan belajar mengajar &mdash; {{ config('sekolah.aplikasi') }}</div>
            </td>
        </tr>
    </table>
    <div class="garis-kop"></div>
    <div class="garis-kop-tipis"></div>

    <table class="identitas">
        <tr>
            <td class="label">Hari, tanggal mengajar</td>
            <td class="isi">: {{ $judulTanggal }}</td>
        </tr>
        <tr>
            <td class="label">Tanggal cetak</td>
            <td class="isi">: {{ $waktuCetak }}</td>
        </tr>
        <tr>
            <td class="label">Jumlah jadwal</td>
            <td>: <strong>{{ $ringkas['jadwal'] }}</strong> jam pelajaran &middot; <strong>{{ $ringkas['guru'] }}</strong> guru
                @if ($ringkas['guru_izin'] > 0)
                    &middot; <strong>{{ $ringkas['guru_izin'] }}</strong> guru izin/sakit
                @endif
            </td>
        </tr>
    </table>

    @if ($libur)
        <div class="peringatan">
            <strong>Perhatian:</strong> menurut Kalender Pendidikan, tanggal ini <strong>bukan hari KBM</strong>
            ({{ $libur }}). Jadwal di bawah tetap dicetak sesuai jadwal pelajaran hari {{ $hari }}.
        </div>
    @endif

    {{-- ============ TABEL PARAF ============ --}}
    @if (empty($baris))
        <div class="kosong">Tidak ada jadwal pelajaran pada hari {{ $hari }}.</div>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th style="width:4%;">No</th>
                    <th style="width:11%;">Jam</th>
                    <th style="width:10%;">Kelas</th>
                    <th style="width:19%;">Mata Pelajaran</th>
                    <th style="width:21%;">Nama Guru</th>
                    <th style="width:14%;">Catatan Sistem</th>
                    <th style="width:21%;">Paraf Guru</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($baris as $b)
                    <tr class="{{ $b['no'] % 2 === 0 ? 'zebra' : '' }}">
                        <td class="tengah">{{ $b['no'] }}</td>
                        <td class="tengah">{{ $b['jam'] }}</td>
                        <td class="tengah tebal">{{ $b['kelas'] }}</td>
                        <td>
                            {{ $b['mapel'] }}
                            @if ($b['ruangan'])
                                <div class="kecil">{{ $b['ruangan'] }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $b['guru'] }}
                            @if ($b['nip'])
                                <div class="kecil">NIP {{ $b['nip'] }}</div>
                            @endif
                        </td>
                        <td class="tengah sistem-{{ $b['sistem']['nada'] }}">{{ $b['sistem']['teks'] }}</td>

                        @if ($b['izin'])
                            <td class="izin">
                                <span class="cap">{{ $b['izin']['label'] }}</span>
                                @if ($b['izin']['rinci'])
                                    <div class="rinci">{{ $b['izin']['rinci'] }}</div>
                                @endif
                            </td>
                        @else
                            <td class="paraf">
                                @php $kiri = $b['no'] % 2 === 1; @endphp
                                <table>
                                    <tr>
                                        <td>
                                            @if ($kiri)
                                                <span class="nomor">{{ $b['no'] }}.</span>
                                            @endif
                                        </td>
                                        <td>
                                            @unless ($kiri)
                                                <span class="nomor">{{ $b['no'] }}.</span>
                                            @endunless
                                        </td>
                                    </tr>
                                </table>
                                @if ($b['izin_menunggu'])
                                    <div class="menunggu" style="padding: 0 4px 2px;">izin diajukan, belum disetujui</div>
                                @endif
                            </td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="ringkas">
        Catatan sistem pada saat dicetak: <strong>{{ $ringkas['tercatat'] }}</strong> dari
        <strong>{{ $ringkas['jadwal'] }}</strong> jam pelajaran sudah memiliki scan QR ruangan.
    </div>

    {{-- ============ TANDA TANGAN ============ --}}
    <table class="ttd">
        <tr>
            <td>
                Guru Piket,
                <div class="ruang"></div>
                (....................................................)
            </td>
            <td>
                Mengetahui,<br>Kepala Sekolah
                <div class="ruang" style="height:38px;"></div>
                @if ($kepsek['nama'])
                    <span class="nama">{{ $kepsek['nama'] }}</span>
                    @if ($kepsek['nip'])
                        <br>NIP {{ $kepsek['nip'] }}
                    @endif
                @else
                    (....................................................)
                @endif
            </td>
        </tr>
    </table>

    <div class="petunjuk">
        <strong>Petunjuk:</strong> guru membubuhkan paraf pada baris jam pelajarannya sendiri
        (nomor ganjil di sisi kiri kolom, nomor genap di sisi kanan).
        Kolom paraf yang sudah terisi <strong>GURU IZIN</strong> / <strong>GURU SAKIT</strong> berarti izinnya
        sudah tercatat sah di sistem dan tidak perlu diparaf.
        <strong>Catatan Sistem</strong> menunjukkan hasil scan QR ruangan <em>pada saat lembar ini dicetak</em>
        &mdash; &ldquo;Belum waktunya&rdquo; berarti jam pelajarannya belum dimulai ketika dicetak.
        Bila catatan sistem dan paraf pada lembar ini berbeda, lembar bertanda tangan ini menjadi bahan
        pembanding untuk koreksi laporan.
    </div>

</body>
</html>
