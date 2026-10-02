{{--
    Template PDF Rekap Jam Mengajar Guru — dirender dompdf, BUKAN browser.
    Aturan main sama dengan laporan.rekap-kbm-pdf: tanpa flexbox/grid, tanpa
    Tailwind, tanpa sumber dari internet, tanpa :nth-child.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Jam Mengajar Guru {{ $label_periode }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        @page { margin: 14mm 10mm 14mm 10mm; }
        body { font-size: 9px; color: #1f2937; margin: 0; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #0f766e; }
        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 10px; }
        .kop .sub { font-size: 9px; color: #6b7280; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        .kartu { border: 0.5px solid #d1d5db; padding: 6px 8px; background: #f9fafb; }
        .kartu .angka { font-size: 14px; font-weight: bold; color: #0f766e; }
        .kartu .label { font-size: 7px; color: #6b7280; text-transform: uppercase; }
        .data { margin-top: 10px; }
        .data th, .data td { border: 0.5px solid #9ca3af; padding: 4px 5px; }
        .data th { background: #f0fdfa; color: #115e59; font-size: 7.5px; text-transform: uppercase; }
        .data td { font-size: 8.5px; }
        .data tr { page-break-inside: avoid; }
        .zebra { background: #f9fafb; }
        .tengah { text-align: center; }
        .kanan { text-align: right; }
        .tebal { font-weight: bold; }
        .merah { color: #b91c1c; font-weight: bold; }
        .hijau { color: #047857; font-weight: bold; }
        .kecil { font-size: 7px; color: #6b7280; }
        .kaki { margin-top: 10px; padding-top: 6px; border-top: 0.5px solid #d1d5db; font-size: 7.5px; color: #4b5563; line-height: 1.6; }
        .kosong { text-align: center; color: #9ca3af; padding: 14px; font-style: italic; }
    </style>
</head>
<body>

    <div class="kop">
        <h1>REKAP JAM MENGAJAR GURU (BERBASIS JAM PELAJARAN)</h1>
        <div class="sub">
            {{ \App\Models\PengaturanSistem::namaSekolah() }} &mdash; {{ config('sekolah.aplikasi') }}<br>
            Periode: <strong>{{ $label_periode }}</strong> ({{ $hari_kbm }} hari KBM)
            &nbsp;&middot;&nbsp; 1 JP = <strong>{{ $durasi_jp }} menit</strong>
            @if (! empty($saringan_guru))
                &nbsp;&middot;&nbsp; Guru: <strong>{{ $saringan_guru }}</strong>
            @endif
            <br>Dicetak {{ $dicetak_pada->translatedFormat('d F Y, H:i') }} WIB oleh {{ $dicetak_oleh }}
        </div>
    </div>

    <table>
        <tr>
            @foreach ([
                ['JP terjadwal', $ringkas['jp_terjadwal']],
                ['JP terlaksana', $ringkas['jp_terlaksana']],
                ['Keterlaksanaan', $ringkas['persen'] === null ? '—' : $ringkas['persen'] . '%'],
                ['JP berhalangan', $ringkas['jp_berhalangan']],
                ['JP tidak terlaksana', $ringkas['jp_tidak_terlaksana']],
            ] as [$label, $nilai])
                <td width="20%" style="padding-right:5px;">
                    <div class="kartu">
                        <div class="angka">{{ $nilai }}</div>
                        <div class="label">{{ $label }}</div>
                    </div>
                </td>
            @endforeach
        </tr>
    </table>

    @if (empty($baris))
        <p class="kosong">Belum ada jadwal pelajaran yang cocok dengan saringan ini.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="26%">Nama Guru</th>
                    <th width="8%" class="tengah">JP / Minggu</th>
                    <th width="9%" class="tengah">Terjadwal</th>
                    <th width="9%" class="tengah">Terlaksana</th>
                    <th width="9%" class="tengah">Berhalangan</th>
                    <th width="10%" class="tengah">Tidak Terlaksana</th>
                    <th width="9%" class="tengah">Pengganti</th>
                    <th width="8%" class="tengah">Sesi Luar Jadwal</th>
                    <th width="8%" class="kanan">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($baris as $i => $b)
                    <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                        <td class="tengah">{{ $i + 1 }}</td>
                        <td>
                            {{ $b['nama'] }}
                            @if ($b['nip'])
                                <div class="kecil">NIP {{ $b['nip'] }}</div>
                            @endif
                        </td>
                        <td class="tengah">{{ $b['jp_per_minggu'] }}</td>
                        <td class="tengah tebal">{{ $b['jp_terjadwal'] }}</td>
                        <td class="tengah hijau">{{ $b['jp_terlaksana'] }}</td>
                        <td class="tengah">{{ $b['jp_berhalangan'] }}</td>
                        <td class="tengah {{ $b['jp_tidak_terlaksana'] > 0 ? 'merah' : '' }}">{{ $b['jp_tidak_terlaksana'] }}</td>
                        <td class="tengah">{{ $b['jp_pengganti'] ?: '—' }}</td>
                        <td class="tengah">{{ $b['sesi_luar_jadwal'] ?: '—' }}</td>
                        <td class="kanan {{ $b['persen'] !== null && $b['persen'] < 70 ? 'merah' : 'tebal' }}">
                            {{ $b['persen'] === null ? '—' : $b['persen'] . '%' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="kaki">
        <strong>Cara membaca:</strong>
        <strong>JP Terjadwal</strong> = jumlah JP seluruh jadwal guru pada hari KBM di periode ini yang sudah selesai saat dicetak
        (libur mingguan dan libur Kalender Pendidikan tidak dihitung){{ $ringkas['jp_akan_datang'] > 0 ? '; ' . $ringkas['jp_akan_datang'] . ' JP yang belum selesai belum dihitung' : '' }}. Jumlah JP satu jadwal = panjang jadwal &divide; {{ $durasi_jp }} menit, dibulatkan.
        <strong>JP Terlaksana</strong> = JP jadwal yang sesi mengajarnya tuntas (scan QR ruangan, foto bukti, diakhiri).
        <strong>JP Berhalangan</strong> = JP pada hari guru tercatat izin, sakit, atau alpa.
        <strong>JP Tidak Terlaksana</strong> = sisanya: guru tidak berhalangan tetapi tidak ada sesi tuntas yang cocok.
        <strong>Pengganti</strong> = JP kelas guru lain yang absensi siswanya diisi guru ini lewat halaman Kelas Pengganti.
        <strong>Sesi Luar Jadwal</strong> = sesi tuntas yang tidak cocok dengan jadwal mana pun (salah scan ruangan atau hari libur);
        tidak diberi JP.
        <strong>%</strong> = JP Terlaksana &divide; JP Terjadwal.
    </div>

</body>
</html>
