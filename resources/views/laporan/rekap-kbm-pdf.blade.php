{{--
    Template PDF Rekap KBM per Jadwal — dirender dompdf, BUKAN browser.

    ============ ATURAN MAIN DOMPDF ============
    Sama seperti laporan.bulanan-pdf: tidak ada flexbox, tidak ada grid,
    tidak ada class Tailwind (CSS-nya tidak dimuat di sini sama sekali),
    tidak ada gambar/font dari internet, dan tidak ada :nth-child.
    Seluruh gayanya CSS lama biasa: table, width persen, warna heksa.
    ============================================
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap KBM {{ $label_periode }}</title>
    <style>
        /* DejaVu Sans satu-satunya font bawaan dompdf dengan cakupan
           karakter lengkap — termasuk – dan ·. Font lain menghasilkan
           kotak kosong untuk karakter itu. */
        * { font-family: "DejaVu Sans", sans-serif; }

        @page { margin: 16mm 10mm 14mm 10mm; }

        body { font-size: 9px; color: #1f2937; margin: 0; }

        h1 { font-size: 15px; margin: 0 0 2px; color: #0f766e; }
        h2 { font-size: 11px; margin: 14px 0 6px; color: #0f766e;
             border-bottom: 1.5px solid #0f766e; padding-bottom: 3px; }

        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 12px; }
        .kop .sub { font-size: 9px; color: #6b7280; line-height: 1.5; }

        table { width: 100%; border-collapse: collapse; }

        .data th, .data td { border: 0.5px solid #d1d5db; padding: 3px 4px; }
        .data th { background: #f0fdfa; color: #115e59; font-size: 7.5px;
                   text-transform: uppercase; letter-spacing: 0.2px; }
        .data td { font-size: 8px; }

        /* Selang-seling lewat class dari PHP — dompdf tidak punya :nth-child. */
        .zebra { background: #f9fafb; }

        .tengah { text-align: center; }
        .kanan { text-align: right; }
        .tebal { font-weight: bold; }
        .merah { color: #b91c1c; font-weight: bold; }
        .abu { color: #9ca3af; }

        .kartu { border: 0.5px solid #d1d5db; padding: 6px 8px; background: #f9fafb; }
        .kartu .angka { font-size: 14px; font-weight: bold; color: #0f766e; }
        .kartu .label { font-size: 7px; color: #6b7280; text-transform: uppercase; }

        .kaki { margin-top: 12px; padding-top: 7px; border-top: 0.5px solid #d1d5db;
                font-size: 7.5px; color: #6b7280; line-height: 1.6; }

        .kosong { text-align: center; color: #9ca3af; padding: 14px; font-style: italic; }
    </style>
</head>
<body>

    <div class="kop">
        <h1>REKAP KEHADIRAN KBM PER JADWAL PELAJARAN</h1>
        <div class="sub">
            SMK Islam Assya'roniyyah &mdash; SIMAGAS (Sistem Absensi Digital)<br>
            Periode: <strong>{{ $label_periode }}</strong>
            &nbsp;&middot;&nbsp; Dicetak {{ $dibuat_pada->translatedFormat('d F Y, H:i') }} WIB
            @if (! empty($saringan))
                <br>Saringan: <strong>{{ implode(' | ', $saringan) }}</strong>
            @endif
        </div>
    </div>

    {{-- Ringkasan pakai <table>, bukan grid — lihat catatan dompdf di atas. --}}
    <table>
        <tr>
            <td width="20%" style="padding-right:5px;">
                <div class="kartu">
                    <div class="angka">{{ $ringkas['jumlah_jadwal'] }}</div>
                    <div class="label">Jadwal</div>
                </div>
            </td>
            <td width="20%" style="padding-right:5px;">
                <div class="kartu">
                    <div class="angka">{{ $ringkas['pertemuan'] }}/{{ $ringkas['pertemuan_mungkin'] }}</div>
                    <div class="label">Pertemuan tercatat</div>
                </div>
            </td>
            <td width="20%" style="padding-right:5px;">
                <div class="kartu">
                    <div class="angka">{{ $ringkas['persen'] === null ? '—' : $ringkas['persen'] . '%' }}</div>
                    <div class="label">Kehadiran di kelas</div>
                </div>
            </td>
            <td width="20%" style="padding-right:5px;">
                <div class="kartu">
                    <div class="angka">{{ number_format($ringkas['alpa'] + $ringkas['bolos'], 0, ',', '.') }}</div>
                    <div class="label">Alpa &amp; bolos</div>
                </div>
            </td>
            <td width="20%">
                <div class="kartu">
                    <div class="angka">{{ $ringkas['belum_terisi'] }}</div>
                    <div class="label">Jadwal belum diabsen</div>
                </div>
            </td>
        </tr>
    </table>

    <h2>Rincian per Jadwal</h2>

    @if (empty($baris))
        <p class="kosong">Tidak ada jadwal yang cocok dengan saringan pada periode ini.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="4%">No</th>
                    <th width="8%">Hari</th>
                    <th width="10%">Jam</th>
                    <th width="17%">Mata Pelajaran</th>
                    <th width="9%">Kelas</th>
                    <th width="16%">Guru</th>
                    <th width="8%" class="tengah">Pertemuan</th>
                    <th width="6%" class="tengah">Hadir</th>
                    <th width="4%" class="tengah">S</th>
                    <th width="4%" class="tengah">I</th>
                    <th width="5%" class="tengah">Alpa</th>
                    <th width="5%" class="tengah">Bolos</th>
                    <th width="6%" class="kanan">% Hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($baris as $i => $b)
                    <tr class="{{ $i % 2 === 1 ? 'zebra' : '' }}">
                        <td class="tengah">{{ $i + 1 }}</td>
                        <td>{{ $b['hari'] }}</td>
                        <td>{{ $b['jam'] }}</td>
                        <td>{{ $b['mapel'] }}</td>
                        <td>{{ $b['kelas'] }}</td>
                        <td>{{ $b['guru'] }}</td>

                        <td class="tengah {{ $b['pertemuan'] === 0 ? 'merah' : '' }}">
                            {{ $b['pertemuan'] }}/{{ $b['pertemuan_mungkin'] }}
                        </td>

                        <td class="tengah">{{ $b['hadir'] }}</td>
                        <td class="tengah">{{ $b['sakit'] }}</td>
                        <td class="tengah">{{ $b['izin'] }}</td>
                        <td class="tengah {{ $b['alpa'] > 0 ? 'merah' : '' }}">{{ $b['alpa'] }}</td>
                        <td class="tengah {{ $b['bolos'] > 0 ? 'merah' : '' }}">{{ $b['bolos'] }}</td>

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
        <strong>% Hadir</strong> membandingkan status "hadir" terhadap seluruh catatan pada jadwal itu — mengukur siswanya.
        <strong>Pertemuan</strong> (mis. 3/4) berarti jurnal terisi 3 kali dari 4 kali pelajaran yang seharusnya berlangsung
        dalam periode ini — mengukur pengisian jurnalnya, bukan kehadiran siswa.
        Jadwal dengan pertemuan <strong>0</strong> berarti jurnalnya belum pernah diisi sama sekali; kolom persentasenya
        sengaja dikosongkan (&mdash;) karena menuliskan 0% akan terbaca seolah seluruh kelas tidak hadir.<br>
        <strong>Bolos</strong> berbeda dari <strong>Alpa</strong>: bolos berarti siswa tercatat masuk gerbang pagi itu,
        tetapi tidak ada di kelas pada jam tersebut.<br>
        Dokumen ini dihasilkan SIMAGAS sesuai saringan yang dipilih saat pencetakan.
        Angkanya dihitung ulang dari jurnal KBM setiap kali dicetak, sehingga koreksi yang dilakukan guru selalu ikut terbawa.
    </div>

</body>
</html>
