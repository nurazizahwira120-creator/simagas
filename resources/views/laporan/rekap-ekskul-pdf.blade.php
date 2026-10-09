{{--
    PDF Rekap Absensi Ekskul — dirender dompdf. Aturan main sama dengan
    laporan.rekap-absensi-siswa-pdf: tanpa flexbox/grid, tanpa Tailwind,
    tanpa sumber dari internet.

    $ringkasan : null (satu ekskul) atau hasil RekapEkskulBulanan::ringkasan()
    $rincian   : koleksi hasil RekapEkskulBulanan::rincian(), satu per ekskul
--}}
@php
    use App\Enums\AbsensiStatus;
    use App\Services\RekapEkskulBulanan;
    $kode = [AbsensiStatus::Hadir->value => 'H', AbsensiStatus::Izin->value => 'I', AbsensiStatus::Sakit->value => 'S', AbsensiStatus::Alpha->value => 'A'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Absensi Ekskul {{ $label }}</title>
    <style>
        * { font-family: "DejaVu Sans", sans-serif; }
        @page { margin: 12mm 10mm 14mm 10mm; }
        body { font-size: 8px; color: #1f2937; margin: 0; }
        h1 { font-size: 14px; margin: 0 0 2px; color: #0f766e; }
        h2 { font-size: 11px; margin: 0 0 2px; color: #115e59; }
        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 7px; margin-bottom: 8px; }
        .sub { font-size: 8.5px; color: #6b7280; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        thead { display: table-header-group; }
        th, td { border: 0.5px solid #9ca3af; padding: 3px 3px; }
        th { background: #f0fdfa; color: #115e59; font-size: 7px; text-transform: uppercase; }
        td { font-size: 7.5px; }
        tr { page-break-inside: avoid; }
        .tengah { text-align: center; }
        .redup { color: #9ca3af; }
        .merah { color: #b91c1c; font-weight: bold; }
        .jumlah { background: #f3f4f6; font-weight: bold; }
        .kecil { font-size: 6.5px; color: #6b7280; }
        .kotak { border: 0.5px solid #fca5a5; background: #fef2f2; color: #b91c1c; padding: 4px 6px; margin-bottom: 6px; font-size: 7.5px; }
        .halaman-baru { page-break-before: always; }
        .kaki { margin-top: 6px; font-size: 7px; color: #4b5563; line-height: 1.6; }
    </style>
</head>
<body>

    <div class="kop">
        <h1>REKAP ABSENSI EKSTRAKURIKULER</h1>
        <div class="sub">
            {{ \App\Models\PengaturanSistem::namaSekolah() }} &mdash; {{ config('sekolah.aplikasi') }}<br>
            Periode: <strong>{{ $label }}</strong>
            &nbsp;&middot;&nbsp; Dicetak {{ $dicetak_pada->translatedFormat('d F Y, H:i') }} WIB oleh {{ $dicetak_oleh }}
        </div>
    </div>

    @if ($ringkasan)
        <h2>Ringkasan Semua Ekskul</h2>
        <table>
            <thead>
                <tr>
                    <th width="3%">No</th><th>Ekskul</th><th>Jadwal</th><th>Pembina</th>
                    <th class="tengah">Anggota</th><th class="tengah">Terjadwal</th><th class="tengah">Terlaksana</th><th class="tengah">% Hadir</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ringkasan['baris'] as $b)
                    <tr>
                        <td class="tengah">{{ $loop->iteration }}</td>
                        <td>{{ $b['ekskul']->nama_ekskul }}</td>
                        <td>{{ $b['ekskul']->hari }} {{ $b['ekskul']->rentangJam() }}</td>
                        <td>{{ $b['ekskul']->namaPembina() }}</td>
                        <td class="tengah">{{ $b['anggota'] }}</td>
                        <td class="tengah">{{ $b['terjadwal'] }}</td>
                        <td class="tengah {{ $b['terjadwal'] > 0 && $b['terlaksana'] < $b['terjadwal'] ? 'merah' : '' }}">{{ $b['terlaksana'] }}</td>
                        <td class="tengah">{{ $b['persen_hadir'] !== null ? $b['persen_hadir'] . '%' : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach ($rincian as $r)
        @php $e = $r['ekskul']; $t = $r['total']; @endphp
        <div class="{{ ($ringkasan || ! $loop->first) ? 'halaman-baru' : '' }}">
            <h2>{{ $e->nama_ekskul }}</h2>
            <div class="sub" style="margin-bottom:5px">
                {{ $e->hari }} {{ $e->rentangJam() }} &middot; Pembina: {{ $e->namaPembina() }}
                &middot; Terjadwal {{ $t['terjadwal'] }} &middot; Sesi terlaksana {{ $t['terlaksana'] }}
                &middot; Pertemuan diisi {{ $t['pertemuan'] }}
                &middot; Hadir {{ $t['persen_hadir'] !== null ? $t['persen_hadir'] . '%' : '-' }}
            </div>

            @if ($r['kosong'])
                <div class="kotak">Terjadwal tetapi tidak ada sesi maupun absensi:
                    {{ collect($r['kosong'])->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->translatedFormat('d M'))->join(', ') }}</div>
            @endif

            <table>
                <thead>
                    <tr>
                        <th width="3%">No</th>
                        <th width="22%">Nama</th>
                        <th width="9%">Kelas</th>
                        @foreach ($r['pertemuan'] as $p)
                            <th class="tengah">{{ $p['tanggal']->format('d/m') }}<br><span class="kecil">{{ $p['sesi']?->sudahSelesai() ? 'sesi' : ($p['sesi'] ? 'terbuka' : 'susulan') }}</span></th>
                        @endforeach
                        @foreach (RekapEkskulBulanan::STATUS as $s)
                            <th class="tengah">{{ $kode[$s->value] }}</th>
                        @endforeach
                        <th class="tengah">%</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['siswa'] as $b)
                        <tr>
                            <td class="tengah">{{ $loop->iteration }}</td>
                            <td>{{ $b['siswa']->nama }}@unless ($b['anggota_aktif']) <span class="kecil">(keluar)</span>@endunless</td>
                            <td>{{ $b['siswa']->kelas?->nama_kelas ?? '-' }}</td>
                            @foreach ($r['pertemuan'] as $p)
                                @php $st = $b['per_tanggal'][$p['kunci']] ?? null; @endphp
                                <td class="tengah {{ $st === AbsensiStatus::Alpha ? 'merah' : '' }}">{{ $st ? $kode[$st->value] : '' }}</td>
                            @endforeach
                            @foreach (RekapEkskulBulanan::STATUS as $s)
                                @php $n = $b['jumlah'][$s->value]; @endphp
                                <td class="tengah jumlah {{ $n === 0 ? 'redup' : '' }}">{{ $n }}</td>
                            @endforeach
                            <td class="tengah jumlah">{{ $b['persen'] !== null ? $b['persen'] : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 8 + $r['pertemuan']->count() }}" class="tengah redup">Belum ada anggota.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="kaki">
        H = Hadir, I = Izin, S = Sakit, A = Alpa. <strong>Terjadwal</strong> = hari ekskul sampai tanggal cetak, tanpa hari libur Kalender Pendidikan.
        <strong>sesi</strong> = pertemuan dimulai dengan scan QR dan diakhiri pembina; <strong>susulan</strong> = absensi diisi tanpa sesi.
    </div>

</body>
</html>
