{{--
    Template PDF Laporan Bulanan Kehadiran Siswa (Gerbang & Kelas) —
    dirender dompdf, BUKAN browser. Aturan main sama dengan
    laporan.rekap-jam-mengajar-pdf: tanpa flexbox/grid, tanpa Tailwind,
    tanpa sumber dari internet, tanpa :nth-child.

    Data dari App\Services\RekapAbsensiSiswaBulanan::hitung() — SAMA dengan
    yang tampil di layar, jadi angka PDF dan layar tidak mungkin berbeda.
--}}
@php
    $kolomGerbang = ['hadir' => 'Hadir', 'terlambat' => 'Telat', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];
    $kolomKelas = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'bolos' => 'Bolos', 'alpa' => 'Alpa'];
    $merah = ['alpa' => true, 'bolos' => true];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Kehadiran Siswa {{ $label }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        @page { margin: 12mm 10mm 14mm 10mm; }
        body { font-size: 8px; color: #1f2937; margin: 0; }
        h1 { font-size: 14px; margin: 0 0 2px; color: #0f766e; }
        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 7px; margin-bottom: 8px; }
        .kop .sub { font-size: 8.5px; color: #6b7280; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        th, td { border: 0.5px solid #9ca3af; padding: 3px 3px; }
        th { background: #f0fdfa; color: #115e59; font-size: 7px; text-transform: uppercase; }
        th.grup { font-size: 7.5px; background: #ccfbf1; }
        td { font-size: 7.5px; }
        tr { page-break-inside: avoid; }
        .zebra { background: #f9fafb; }
        .tengah { text-align: center; }
        .redup { color: #9ca3af; }
        .merah { color: #b91c1c; font-weight: bold; }
        .kuning { color: #b45309; font-weight: bold; }
        .jumlah { background: #f3f4f6; font-weight: bold; }
        .batas { border-left: 1.5px solid #0f766e; }
        .total td { background: #ecfdf5; font-weight: bold; }
        .kecil { font-size: 6.5px; color: #6b7280; }
        .kaki { margin-top: 8px; padding-top: 5px; border-top: 0.5px solid #d1d5db; font-size: 7px; color: #4b5563; line-height: 1.6; }
        .kosong { text-align: center; color: #9ca3af; padding: 14px; font-style: italic; }
    </style>
</head>
<body>

    <div class="kop">
        <h1>LAPORAN BULANAN KEHADIRAN SISWA</h1>
        <div class="sub">
            {{ \App\Models\PengaturanSistem::namaSekolah() }} &mdash; {{ config('sekolah.aplikasi') }}<br>
            Periode: <strong>{{ $label }}</strong>
            &nbsp;&middot;&nbsp; Kelas: <strong>{{ $kelas_terpilih ?? 'Semua Kelas' }}</strong>
            &nbsp;&middot;&nbsp; {{ $baris->count() }} siswa
            <br>Dicetak {{ $dicetak_pada->translatedFormat('d F Y, H:i') }} WIB oleh {{ $dicetak_oleh }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" width="3%">No</th>
                <th rowspan="2" width="20%">Nama Siswa</th>
                @if (! $kelas_terpilih)
                    <th rowspan="2" width="8%">Kelas</th>
                @endif
                <th colspan="6" class="grup batas">Absensi Gerbang (hari)</th>
                <th colspan="6" class="grup batas">Absensi di Kelas (jam pelajaran)</th>
            </tr>
            <tr>
                @foreach ($kolomGerbang as $label)
                    <th class="tengah {{ $loop->first ? 'batas' : '' }}">{{ $label }}</th>
                @endforeach
                <th class="tengah">Jml</th>
                @foreach ($kolomKelas as $label)
                    <th class="tengah {{ $loop->first ? 'batas' : '' }}">{{ $label }}</th>
                @endforeach
                <th class="tengah">Jml</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $b)
                <tr class="{{ $loop->even ? 'zebra' : '' }}">
                    <td class="tengah">{{ $loop->iteration }}</td>
                    <td>{{ $b['siswa']->nama }} <span class="kecil">{{ $b['siswa']->nis }}</span></td>
                    @if (! $kelas_terpilih)
                        <td>{{ $b['siswa']->kelas?->nama_kelas ?? '-' }}</td>
                    @endif
                    @foreach ($kolomGerbang as $kunci => $label)
                        @php $n = $b['gerbang'][$kunci]; @endphp
                        <td class="tengah {{ $loop->first ? 'batas' : '' }} {{ $n === 0 ? 'redup' : ($kunci === 'terlambat' ? 'kuning' : (isset($merah[$kunci]) ? 'merah' : '')) }}">{{ $n }}</td>
                    @endforeach
                    <td class="tengah jumlah">{{ $b['gerbang']['total'] }}</td>
                    @foreach ($kolomKelas as $kunci => $label)
                        @php $n = $b['kelas'][$kunci]; @endphp
                        <td class="tengah {{ $loop->first ? 'batas' : '' }} {{ $n === 0 ? 'redup' : (isset($merah[$kunci]) ? 'merah' : '') }}">{{ $n }}</td>
                    @endforeach
                    <td class="tengah jumlah">{{ $b['kelas']['total'] }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ $kelas_terpilih ? 14 : 15 }}" class="kosong">Tidak ada siswa yang cocok dengan saringan ini.</td></tr>
            @endforelse

            @if ($baris->isNotEmpty())
                <tr class="total">
                    <td colspan="{{ $kelas_terpilih ? 2 : 3 }}">TOTAL</td>
                    @foreach ($kolomGerbang as $kunci => $label)
                        <td class="tengah {{ $loop->first ? 'batas' : '' }}">{{ $total['gerbang'][$kunci] }}</td>
                    @endforeach
                    <td class="tengah">{{ $total['gerbang']['total'] }}</td>
                    @foreach ($kolomKelas as $kunci => $label)
                        <td class="tengah {{ $loop->first ? 'batas' : '' }}">{{ $total['kelas'][$kunci] }}</td>
                    @endforeach
                    <td class="tengah">{{ $total['kelas']['total'] }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <div class="kaki">
        <strong>Absensi Gerbang</strong> dihitung per hari: Hadir = tepat waktu, Telat = scan gerbang setelah pukul {{ $batas_terlambat }},
        Jml = jumlah hari yang tercatat.
        <strong>Absensi di Kelas</strong> dihitung per jam pelajaran (1 JP = {{ $durasi_jp }} menit) dari jurnal guru/guru inval:
        Bolos = tercatat masuk gerbang tetapi tidak ada di kelas; Alpa = tidak hadir tanpa keterangan.
    </div>

</body>
</html>
