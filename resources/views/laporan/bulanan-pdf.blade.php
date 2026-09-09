{{--
    Template PDF laporan bulanan — dirender dompdf, BUKAN browser.

    ============ ATURAN MAIN DOMPDF ============
    dompdf bukan mesin render modern. Yang TIDAK boleh dipakai di berkas ini:
      - flexbox & grid          -> diabaikan, tata letaknya berantakan
      - class Tailwind          -> CSS-nya tidak dimuat di sini sama sekali
      - <img src="https://...">  -> isRemoteEnabled sengaja false; gambar dari
                                    internet membuat render menggantung sampai
                                    timeout kalau server sekolah tidak punya
                                    akses keluar
      - font eksternal          -> huruf berubah jadi kotak-kotak

    Karena itu seluruh gaya di bawah ditulis inline sebagai CSS lama biasa
    (table, float, width dalam persen). Terlihat kuno; itu memang harganya.
    ============================================
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $label_periode }}</title>
    <style>
        /* DejaVu Sans adalah satu-satunya font bawaan dompdf yang punya
           cakupan karakter lengkap — termasuk é, ×, dan tanda kutip miring.
           Font lain menghasilkan kotak kosong untuk karakter itu. */
        * { font-family: "DejaVu Sans", sans-serif; }

        @page { margin: 18mm 12mm 16mm 12mm; }

        body { font-size: 9px; color: #1f2937; margin: 0; }

        h1 { font-size: 15px; margin: 0 0 2px; color: #0f766e; }
        h2 { font-size: 11px; margin: 16px 0 6px; color: #0f766e;
             border-bottom: 1.5px solid #0f766e; padding-bottom: 3px; }

        .kop { border-bottom: 2px solid #0f766e; padding-bottom: 8px; margin-bottom: 12px; }
        .kop .sub { font-size: 9px; color: #6b7280; }

        table { width: 100%; border-collapse: collapse; }

        .data th, .data td { border: 0.5px solid #d1d5db; padding: 3px 5px; }
        .data th { background: #f0fdfa; color: #115e59; font-size: 8px;
                   text-transform: uppercase; letter-spacing: 0.3px; }
        .data td { font-size: 8.5px; }

        /* Baris selang-seling dibuat lewat class dari PHP, bukan
           :nth-child — dompdf tidak mendukung pseudo-class itu. */
        .zebra { background: #f9fafb; }

        .tengah { text-align: center; }
        .kanan { text-align: right; }
        .tebal { font-weight: bold; }
        .merah { color: #b91c1c; font-weight: bold; }

        .kartu { border: 0.5px solid #d1d5db; padding: 7px 9px; background: #f9fafb; }
        .kartu .angka { font-size: 15px; font-weight: bold; color: #0f766e; }
        .kartu .label { font-size: 7.5px; color: #6b7280; text-transform: uppercase; }

        .kaki { margin-top: 14px; padding-top: 7px; border-top: 0.5px solid #d1d5db;
                font-size: 7.5px; color: #6b7280; line-height: 1.5; }

        .kosong { text-align: center; color: #9ca3af; padding: 14px; font-style: italic; }
    </style>
</head>
<body>

    <div class="kop">
        <h1>LAPORAN KEHADIRAN BULANAN</h1>
        <div class="sub">
            SMK Islam Assya'roniyyah &mdash; SIMAGAS (Sistem Absensi Digital)<br>
            Periode: <strong>{{ $label_periode }}</strong>
            &nbsp;&middot;&nbsp; Hari kerja: <strong>{{ $hari_kerja }} hari</strong>
            &nbsp;&middot;&nbsp; Dibuat otomatis {{ $dibuat_pada->translatedFormat('d F Y, H:i') }} WIB
        </div>
    </div>

    {{-- Ringkasan dibuat dengan <table>, bukan grid: lihat catatan dompdf
         di kepala berkas. --}}
    <table>
        <tr>
            <td width="20%" style="padding-right:6px;">
                <div class="kartu">
                    <div class="angka">{{ $ringkas['jumlah_pegawai'] }}</div>
                    <div class="label">Pegawai</div>
                </div>
            </td>
            <td width="20%" style="padding-right:6px;">
                <div class="kartu">
                    <div class="angka">{{ $ringkas['jumlah_siswa'] }}</div>
                    <div class="label">Siswa</div>
                </div>
            </td>
            <td width="20%" style="padding-right:6px;">
                <div class="kartu">
                    <div class="angka">{{ number_format($ringkas['kehadiran_pegawai'], 0, ',', '.') }}</div>
                    <div class="label">Hari hadir pegawai</div>
                </div>
            </td>
            <td width="20%" style="padding-right:6px;">
                <div class="kartu">
                    <div class="angka">{{ number_format($ringkas['total_sesi_mengajar'], 0, ',', '.') }}</div>
                    <div class="label">Sesi KBM tervalidasi</div>
                </div>
            </td>
            <td width="20%">
                <div class="kartu">
                    <div class="angka">{{ number_format($ringkas['total_bolos'], 0, ',', '.') }}</div>
                    <div class="label">Kejadian bolos</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- ============ REKAP PEGAWAI ============ --}}
    <h2>A. Rekapitulasi Kehadiran Pegawai</h2>

    @if (empty($pegawai))
        <p class="kosong">Belum ada data pegawai.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="3%" class="tengah">No</th>
                    <th width="24%">Nama</th>
                    <th width="14%">NIP</th>
                    <th width="15%">Jabatan</th>
                    <th width="7%" class="tengah">Hadir</th>
                    <th width="7%" class="tengah">Izin</th>
                    <th width="7%" class="tengah">Sakit</th>
                    <th width="7%" class="tengah">Alpa</th>
                    <th width="8%" class="tengah">Sesi KBM</th>
                    <th width="8%" class="tengah">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($pegawai as $i => $p)
                    <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                        <td class="tengah">{{ $i + 1 }}</td>
                        <td>{{ $p['nama'] }}</td>
                        <td>{{ $p['nip'] }}</td>
                        <td>{{ $p['jabatan'] }}</td>
                        <td class="tengah tebal">{{ $p['hadir'] }}</td>
                        <td class="tengah">{{ $p['izin'] }}</td>
                        <td class="tengah">{{ $p['sakit'] }}</td>
                        <td class="tengah {{ $p['alpha'] > 0 ? 'merah' : '' }}">{{ $p['alpha'] }}</td>
                        <td class="tengah">{{ $p['sesi_mengajar'] }}</td>
                        <td class="tengah">{{ $p['persen'] === null ? '—' : $p['persen'] . '%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- ============ REKAP SISWA ============ --}}
    <h2>B. Rekapitulasi Kehadiran Siswa</h2>

    @if (empty($siswa))
        <p class="kosong">Belum ada data siswa.</p>
    @else
        <table class="data">
            <thead>
                <tr>
                    <th width="3%" class="tengah">No</th>
                    <th width="26%">Nama</th>
                    <th width="14%">NIS</th>
                    <th width="12%">Kelas</th>
                    <th width="7%" class="tengah">Hadir</th>
                    <th width="7%" class="tengah">Izin</th>
                    <th width="7%" class="tengah">Sakit</th>
                    <th width="7%" class="tengah">Alpa</th>
                    <th width="8%" class="tengah">Bolos</th>
                    <th width="9%" class="tengah">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($siswa as $i => $s)
                    <tr class="{{ $i % 2 ? 'zebra' : '' }}">
                        <td class="tengah">{{ $i + 1 }}</td>
                        <td>{{ $s['nama'] }}</td>
                        <td>{{ $s['nis'] }}</td>
                        <td>{{ $s['kelas'] }}</td>
                        <td class="tengah tebal">{{ $s['hadir'] }}</td>
                        <td class="tengah">{{ $s['izin'] }}</td>
                        <td class="tengah">{{ $s['sakit'] }}</td>
                        <td class="tengah {{ $s['alpha'] > 0 ? 'merah' : '' }}">{{ $s['alpha'] }}</td>
                        <td class="tengah {{ $s['bolos'] > 0 ? 'merah' : '' }}">{{ $s['bolos'] }}</td>
                        <td class="tengah">{{ $s['persen'] === null ? '—' : $s['persen'] . '%' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="kaki">
        <strong>Cara membaca angka di laporan ini:</strong><br>
        &bull; <strong>Hari kerja</strong> dihitung sebagai seluruh hari Senin&ndash;Sabtu dalam bulan ini.
        Hari libur nasional dan libur sekolah <strong>belum dikecualikan</strong>, karena sistem belum
        memiliki kalender akademik. Persentase pada bulan yang banyak liburnya karena itu akan
        tampak lebih rendah daripada keadaan sebenarnya.<br>
        &bull; <strong>Sesi KBM tervalidasi</strong> adalah sesi mengajar yang lengkap: guru sudah absen
        kedatangan, sudah men-scan QR ruangan, sudah mengunggah foto bukti, dan sudah menutup sesinya.
        Sesi yang hanya di-scan lalu ditinggalkan tidak ikut dihitung.<br>
        &bull; <strong>Bolos</strong> adalah siswa yang tercatat masuk gerbang pada pagi harinya namun
        ditandai tidak hadir oleh guru di jam pelajaran &mdash; berbeda dari <strong>Alpa</strong>, yang
        berarti tidak hadir sejak dari rumah.<br><br>
        Dokumen ini dihasilkan otomatis oleh SIMAGAS dan tidak memerlukan tanda tangan basah.
    </div>

</body>
</html>
