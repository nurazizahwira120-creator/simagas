@extends('layouts.app')

@section('title', 'Pengaturan')

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink">Pengaturan</h1>
        <p class="mt-1 text-sm text-brand-muted">Aturan waktu absensi, persetujuan akun baru, dan tahun ajaran aktif.</p>
    </div>

    @php
        // Tab dikendalikan lewat query string (?tab=...), bukan JavaScript.
        // Alasannya: kalau validasi form gagal, Laravel melakukan redirect
        // balik — dengan tab di URL, pengguna kembali ke tab yang sama, bukan
        // terlempar ke tab pertama dan kehilangan konteks kesalahannya.
        $tabs = [
            'waktu' => ['label' => 'Aturan Absensi', 'icon' => 'clock'],
            'lokasi' => ['label' => 'Lokasi & Radius GPS', 'icon' => 'building'],
            'approval' => ['label' => 'Approval Akun Baru', 'icon' => 'shield-check'],
            'tahun' => ['label' => 'Tahun Ajaran', 'icon' => 'calendar'],
            'sistem' => ['label' => 'Identitas & WhatsApp', 'icon' => 'cog'],
        ];
    @endphp

    {{-- Navigasi tab --}}
    <div class="mb-6 overflow-x-auto">
        <nav class="inline-flex gap-1 rounded-2xl bg-brand-surface p-1.5 ring-1 ring-brand-border" aria-label="Tab pengaturan">
            @foreach ($tabs as $kunci => $tab)
                @php
                    $aktif = $tabAktif === $kunci;
                @endphp
                <a href="{{ route($panelPrefix . '.pengaturan', ['tab' => $kunci]) }}"
                    @if ($aktif) aria-current="page" @endif
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl px-4 py-2.5 text-sm transition-colors motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 {{ $aktif ? 'bg-brand-500 font-bold text-white shadow-lg shadow-brand-500/25' : 'font-medium text-brand-muted hover:bg-brand-surface-muted hover:text-brand-ink' }}">
                    <x-icon name="{{ $tab['icon'] }}" class="h-4 w-4" />
                    {{ $tab['label'] }}
                    @if ($kunci === 'approval' && $akunPending->isNotEmpty())
                        <span class="ml-0.5 rounded-full px-2 py-0.5 text-[11px] font-bold {{ $aktif ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-700 dark:text-amber-400' }}">
                            {{ $akunPending->count() }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ================= TAB 1: ATURAN ABSENSI ================= --}}
    @if ($tabAktif === 'waktu')
        <form method="POST" action="{{ route($panelPrefix . '.pengaturan.waktu') }}"
            class="max-w-3xl rounded-2xl bg-brand-surface p-6 ring-1 ring-brand-border">
            @csrf
            @method('PUT')

            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                    <x-icon name="clock" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-brand-ink">Aturan Waktu Absensi</h2>
                    <p class="text-xs text-brand-muted">Kehadiran yang tercatat setelah batas terlambat akan dihitung terlambat.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                <fieldset class="rounded-2xl bg-brand-surface-muted p-4">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wide text-brand-faint">Siswa</legend>
                    <div class="mt-2 space-y-4">
                        <div>
                            <label for="jam_masuk_siswa" class="mb-1.5 block text-xs font-semibold text-brand-muted">Jam Masuk</label>
                            <input id="jam_masuk_siswa" name="jam_masuk_siswa" type="time" required
                                value="{{ old('jam_masuk_siswa', $waktu['jam_masuk_siswa']) }}"
                                class="w-full rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="batas_terlambat_siswa" class="mb-1.5 block text-xs font-semibold text-brand-muted">Batas Terlambat</label>
                            <input id="batas_terlambat_siswa" name="batas_terlambat_siswa" type="time" required
                                value="{{ old('batas_terlambat_siswa', $waktu['batas_terlambat_siswa']) }}"
                                class="w-full rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                    </div>
                </fieldset>

                <fieldset class="rounded-2xl bg-brand-surface-muted p-4">
                    <legend class="px-1 text-xs font-bold uppercase tracking-wide text-brand-faint">Guru / Staff</legend>
                    <div class="mt-2 space-y-4">
                        <div>
                            <label for="jam_masuk_pegawai" class="mb-1.5 block text-xs font-semibold text-brand-muted">Jam Masuk</label>
                            <input id="jam_masuk_pegawai" name="jam_masuk_pegawai" type="time" required
                                value="{{ old('jam_masuk_pegawai', $waktu['jam_masuk_pegawai']) }}"
                                class="w-full rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                        <div>
                            <label for="batas_terlambat_pegawai" class="mb-1.5 block text-xs font-semibold text-brand-muted">Batas Terlambat</label>
                            <input id="batas_terlambat_pegawai" name="batas_terlambat_pegawai" type="time" required
                                value="{{ old('batas_terlambat_pegawai', $waktu['batas_terlambat_pegawai']) }}"
                                class="w-full rounded-xl border-0 bg-brand-surface px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </div>
                    </div>
                </fieldset>
            </div>

            <button type="submit"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-500/25 transition-colors hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 motion-reduce:transition-none">
                <x-icon name="check-circle" class="h-5 w-5" />
                Simpan Pengaturan
            </button>
        </form>
    @endif

    {{-- ================= TAB 2: LOKASI & RADIUS GPS ================= --}}
    @if ($tabAktif === 'lokasi')
        <form method="POST" action="{{ route($panelPrefix . '.pengaturan.lokasi') }}"
            class="max-w-3xl rounded-2xl bg-brand-surface p-6 ring-1 ring-brand-border">
            @csrf
            @method('PUT')

            <div class="mb-5 flex items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                    <x-icon name="building" class="h-5 w-5" />
                </span>
                <div>
                    <h2 class="text-base font-bold text-brand-ink">Titik Koordinat Sekolah &amp; Radius GPS</h2>
                    <p class="text-xs text-brand-muted">Dipakai fitur Absen Radius untuk memastikan guru benar-benar berada di sekolah.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="lat_sekolah" class="mb-1.5 block text-xs font-semibold text-brand-muted">Latitude</label>
                    <input id="lat_sekolah" name="lat_sekolah" type="text" inputmode="decimal" required
                        value="{{ old('lat_sekolah', $lokasi['lat_sekolah']) }}"
                        placeholder="-6.175392"
                        class="w-full rounded-xl border-0 bg-brand-surface-muted px-3.5 py-2.5 font-mono text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
                <div>
                    <label for="lng_sekolah" class="mb-1.5 block text-xs font-semibold text-brand-muted">Longitude</label>
                    <input id="lng_sekolah" name="lng_sekolah" type="text" inputmode="decimal" required
                        value="{{ old('lng_sekolah', $lokasi['lng_sekolah']) }}"
                        placeholder="106.827153"
                        class="w-full rounded-xl border-0 bg-brand-surface-muted px-3.5 py-2.5 font-mono text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                </div>
            </div>

            <div class="mt-4 max-w-xs">
                <label for="radius_gps" class="mb-1.5 block text-xs font-semibold text-brand-muted">Radius Toleransi (meter)</label>
                <input id="radius_gps" name="radius_gps" type="number" min="10" max="5000" step="5" required
                    value="{{ old('radius_gps', $lokasi['radius_gps']) }}"
                    class="w-full rounded-xl border-0 bg-brand-surface-muted px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                <p class="mt-1.5 text-xs text-brand-faint">
                    Jangan diisi terlalu kecil — GPS ponsel sendiri punya galat sekitar 5–20 meter,
                    jadi radius di bawah 30 m sering menolak guru yang sebenarnya sudah di sekolah.
                </p>
            </div>

            {{-- ---- Peta pemilih titik ---------------------------------
                 Ditaruh DI TAB INI, bukan di tab Identitas & WhatsApp.
                 Versi pertama salah menaruhnya di sana dan hasilnya persis
                 seperti yang bisa ditebak: admin membuka tab yang namanya
                 "Lokasi & Radius GPS", tidak menemukan peta apa pun, dan
                 menyimpulkan fiturnya tidak jadi dibuat.

                 Skripnya JavaScript biasa — halaman ini form POST biasa,
                 bukan komponen Livewire, jadi peta cukup menulis nilainya
                 ke tiga kotak input di atas. --}}
            <div class="mt-5">
                <p class="mb-2 text-xs font-semibold text-brand-muted">
                    Klik peta atau geser penanda untuk menentukan titiknya.
                </p>
                <div id="peta-sekolah"
                    class="z-0 h-96 w-full overflow-hidden rounded-xl bg-brand-surface-muted ring-1 ring-brand-border"></div>
                <p id="peta-gagal" class="mt-2 hidden text-xs text-amber-700 dark:text-amber-400"></p>
            </div>

            <script>
                (function () {
                    var wadah = document.getElementById('peta-sekolah');
                    if (!wadah || wadah.dataset.petaSiap) return;
                    wadah.dataset.petaSiap = '1';

                    var pesanGagal = document.getElementById('peta-gagal');
                    var isiLat = document.getElementById('lat_sekolah');
                    var isiLng = document.getElementById('lng_sekolah');
                    var isiRadius = document.getElementById('radius_gps');

                    var angka = function (nilai, cadangan) {
                        var n = parseFloat(String(nilai).replace(',', '.'));
                        return isFinite(n) ? n : cadangan;
                    };

                    window.muatLeaflet().then(function (L) {
                        var lat = angka(isiLat.value, -6.175392);
                        var lng = angka(isiLng.value, 106.827153);
                        var radius = angka(isiRadius.value, 50);

                        var peta = L.map(wadah).setView([lat, lng], 17);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap',
                        }).addTo(peta);

                        // Penanda dibuat dari divIcon (HTML + CSS), BUKAN
                        // L.marker bawaan. Ikon bawaan Leaflet memuat dua
                        // berkas gambar (marker-icon.png & marker-shadow.png)
                        // yang alamatnya dihitung relatif terhadap letak
                        // leaflet.css — dua permintaan jaringan tambahan yang
                        // bisa gagal sendiri dan meninggalkan penanda tak
                        // terlihat. divIcon tidak memuat apa pun.
                        var ikonPin = L.divIcon({
                            className: '',
                            html: '<div style="width:22px;height:22px;border-radius:50% 50% 50% 0;'
                                + 'background:#0d9488;border:3px solid #fff;'
                                + 'box-shadow:0 2px 6px rgba(0,0,0,.35);transform:rotate(-45deg)"></div>',
                            iconSize: [22, 22],
                            iconAnchor: [11, 22],
                        });

                        var penanda = L.marker([lat, lng], { draggable: true, icon: ikonPin }).addTo(peta);

                        var lingkaran = L.circle([lat, lng], {
                            radius: radius,
                            color: '#0d9488',
                            fillColor: '#0d9488',
                            fillOpacity: 0.15,
                            weight: 2,
                        }).addTo(peta);

                        // Enam angka di belakang koma setara ketelitian ~11 cm.
                        var pakai = function (titik) {
                            penanda.setLatLng(titik);
                            lingkaran.setLatLng(titik);
                            isiLat.value = titik.lat.toFixed(6);
                            isiLng.value = titik.lng.toFixed(6);
                        };

                        peta.on('click', function (e) { pakai(e.latlng); });
                        penanda.on('dragend', function () { pakai(penanda.getLatLng()); });

                        // Mengetik koordinat manual (mis. salinan dari Google
                        // Maps) harus ikut menggeser peta, supaya titiknya bisa
                        // dipastikan benar sebelum disimpan.
                        var dariInput = function () {
                            var t = L.latLng(angka(isiLat.value, lat), angka(isiLng.value, lng));
                            penanda.setLatLng(t);
                            lingkaran.setLatLng(t);
                            peta.panTo(t);
                        };

                        isiLat.addEventListener('change', dariInput);
                        isiLng.addEventListener('change', dariInput);

                        isiRadius.addEventListener('input', function () {
                            lingkaran.setRadius(angka(isiRadius.value, 50));
                        });

                        setTimeout(function () { peta.invalidateSize(); }, 200);
                    }).catch(function () {
                        wadah.classList.add('hidden');
                        if (!pesanGagal) return;
                        pesanGagal.textContent = 'Peta gagal dimuat (butuh koneksi internet). '
                            + 'Koordinat tetap bisa diisi manual di kotak di atas.';
                        pesanGagal.classList.remove('hidden');
                    });
                })();
            </script>

            <div class="mt-5 flex items-start gap-2 rounded-xl bg-amber-500/10 p-4 text-xs text-amber-900 ring-1 ring-amber-200">
                <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <span>
                    <span class="font-bold">Nilai bawaan di atas hanya contoh (Monas, Jakarta).</span>
                    Ganti dengan koordinat sekolah yang sebenarnya, kalau tidak fitur Absen Radius akan menolak semua guru.
                    Cara termudah: buka Google Maps, klik kanan tepat di gedung sekolah, lalu salin dua angka yang muncul.
                </span>
            </div>

            <button type="submit"
                class="mt-6 inline-flex items-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-500/25 transition-colors hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 motion-reduce:transition-none">
                <x-icon name="check-circle" class="h-5 w-5" />
                Simpan Lokasi
            </button>
        </form>
    @endif

    {{-- ================= TAB 3: APPROVAL AKUN BARU ================= --}}
    @if ($tabAktif === 'approval')
        <div class="overflow-hidden rounded-2xl bg-brand-surface ring-1 ring-brand-border">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-brand-border px-6 py-4">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-500/10 text-brand-accent-text dark:text-brand-accent-text">
                        <x-icon name="shield-check" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-bold text-brand-ink">Approval Akun Baru</h2>
                        <p class="text-xs text-brand-muted">Akun berstatus menunggu belum bisa login sampai disetujui.</p>
                    </div>
                </div>
                <span class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-bold text-amber-700 dark:text-amber-400 ring-1 ring-amber-200">
                    {{ $akunPending->count() }} menunggu
                </span>
            </div>

            @if ($akunPending->isEmpty())
                <div class="flex flex-col items-center gap-3 px-6 py-14 text-center">
                    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-surface-muted text-brand-faint">
                        <x-icon name="inbox" class="h-6 w-6" />
                    </span>
                    <p class="max-w-md text-sm text-brand-muted">
                        Tidak ada akun yang menunggu persetujuan.
                    </p>
                    <p class="max-w-md text-xs text-brand-faint">
                        Akun yang dibuat lewat menu <span class="font-semibold">Master Pengguna</span> langsung berstatus aktif.
                        Antrean di sini hanya terisi kalau ada halaman pendaftaran mandiri &mdash; lihat catatan di bawah.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-brand-border text-[11px] uppercase tracking-wide text-brand-faint">
                                <th class="px-6 py-3 font-semibold">Nama</th>
                                <th class="px-6 py-3 font-semibold">Email</th>
                                <th class="px-6 py-3 font-semibold">Peran Diminta</th>
                                <th class="px-6 py-3 font-semibold">Mendaftar</th>
                                <th class="px-6 py-3 text-right font-semibold">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-brand-border">
                            @foreach ($akunPending as $akun)
                                <tr class="hover:bg-brand-surface-muted/60">
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-surface-muted text-[11px] font-bold text-brand-muted">
                                                {{ Str::of($akun->name)->explode(' ')->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                            </span>
                                            <span class="font-semibold text-brand-ink">{{ $akun->name }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-brand-muted">{{ $akun->email }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center rounded-full bg-brand-500/10 px-2.5 py-1 text-xs font-semibold text-brand-accent-text dark:text-brand-accent-text">
                                            {{ $akun->role->label() }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-brand-muted">{{ $akun->created_at?->translatedFormat('d M Y') ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            {{-- Konfirmasi SweetAlert2 lewat atribut data-konfirmasi.

                                                 Aksinya TETAP form POST biasa, bukan pemanggilan method
                                                 Livewire seperti pada contoh brief: halaman Pengaturan
                                                 Sistem ini bukan komponen Livewire, dan persetujuannya
                                                 dikerjakan SettingController::approveAkun() lewat rute
                                                 bernama '<panel>.pengaturan.approve'. Mengubahnya jadi
                                                 Livewire semata-mata demi kotak dialog berarti membuang
                                                 token CSRF, method spoofing PATCH, dan jaminan bahwa tombol
                                                 ini tetap berfungsi walau JavaScript gagal dimuat —
                                                 padahal kotak dialognya sudah didapat tanpa itu semua. --}}
                                            <form method="POST" action="{{ route($panelPrefix . '.pengaturan.approve', $akun->id) }}"
                                                data-konfirmasi-judul="Setujui Akun Ini?"
                                                data-konfirmasi="Akun {{ $akun->name }} akan diaktifkan dan diberi akses ke sistem."
                                                data-konfirmasi-ikon="question"
                                                data-konfirmasi-ya="Ya, Setujui!"
                                                data-konfirmasi-batal="Batal">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 ring-1 ring-emerald-200 transition-colors hover:bg-emerald-600 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 motion-reduce:transition-none"
                                                    aria-label="Setujui {{ $akun->name }}" title="Setujui">
                                                    <x-icon name="check" class="h-5 w-5" />
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route($panelPrefix . '.pengaturan.tolak', $akun->id) }}"
                                                data-konfirmasi-judul="Tolak Pendaftaran Ini?"
                                                data-konfirmasi="Pendaftaran {{ $akun->name }} akan ditolak dan akunnya tidak bisa dipakai login."
                                                data-konfirmasi-ikon="warning"
                                                data-konfirmasi-ya="Ya, Tolak!"
                                                data-konfirmasi-batal="Batal">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                    class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 ring-1 ring-rose-200 transition-colors hover:bg-rose-600 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-500 motion-reduce:transition-none"
                                                    aria-label="Tolak {{ $akun->name }}" title="Tolak">
                                                    <x-icon name="x-mark" class="h-5 w-5" />
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <p class="mt-4 flex items-start gap-2 rounded-2xl bg-brand-surface p-4 text-xs text-brand-muted ring-1 ring-brand-border">
            <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" />
            <span>
                Aplikasi ini belum punya halaman pendaftaran mandiri, jadi akun berstatus
                <span class="font-semibold">menunggu</span> hanya bisa muncul kalau dibuat lewat seeder
                atau diubah manual di database. Kalau memang ingin calon pengguna bisa mendaftar sendiri,
                halaman registrasi publiknya perlu dibuat lebih dulu.
            </span>
        </p>
    @endif

    {{-- ================= TAB 3: TAHUN AJARAN ================= --}}
    @if ($tabAktif === 'tahun')
        <div class="grid max-w-4xl grid-cols-1 gap-5 lg:grid-cols-2">

            {{-- Kartu tahun ajaran aktif --}}
            <div class="rounded-2xl bg-brand-500 p-6 text-white shadow-lg shadow-brand-500/25">
                <div class="flex items-start justify-between gap-3">
                    <p class="text-xs font-bold uppercase tracking-wide text-white/70">Tahun Ajaran Aktif</p>
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon name="calendar" class="h-5 w-5" />
                    </span>
                </div>

                @if ($tahunAktif)
                    <p class="mt-5 text-3xl font-extrabold tracking-tight">{{ $tahunAktif->tahun }}</p>
                    <p class="mt-1 text-sm font-medium text-white/85">Semester {{ ucfirst($tahunAktif->semester) }}</p>
                @else
                    <p class="mt-5 text-2xl font-extrabold tracking-tight">Belum diatur</p>
                    <p class="mt-1 text-sm text-white/85">Pilih salah satu tahun ajaran di samping untuk mengaktifkannya.</p>
                @endif
            </div>

            {{-- Ganti tahun ajaran — DIKETIK MANUAL --}}
            <div class="rounded-2xl bg-brand-surface p-6 ring-1 ring-brand-border">
                <h2 class="text-base font-bold text-brand-ink">Ganti Tahun Ajaran</h2>
                <p class="mt-1 text-xs text-brand-muted">
                    Ketik tahun ajarannya langsung — tidak dibatasi daftar tetap, jadi tetap bisa dipakai
                    bertahun-tahun ke depan tanpa mengubah kode.
                </p>

                <form method="POST" action="{{ route($panelPrefix . '.pengaturan.tahun-ajaran') }}" class="mt-5">
                    @csrf
                    @method('PUT')

                    <label for="tahun" class="mb-1.5 block text-xs font-semibold text-brand-muted">Tahun Ajaran</label>
                    <input id="tahun" name="tahun" type="text" required
                        value="{{ old('tahun', $tahunAktif?->tahun ?? (now()->year . '/' . (now()->year + 1))) }}"
                        placeholder="2026/2027" pattern="\d{4}/\d{4}"
                        class="w-full rounded-xl border-0 bg-brand-surface-muted px-3.5 py-2.5 font-mono text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <p class="mt-1.5 text-xs text-brand-faint">Format: 2026/2027 (tahun kedua harus tepat satu tahun setelahnya).</p>

                    <label for="semester" class="mb-1.5 mt-4 block text-xs font-semibold text-brand-muted">Semester</label>
                    <div class="relative">
                        <select id="semester" name="semester" required
                            class="w-full appearance-none rounded-xl border-0 bg-brand-surface-muted px-3.5 py-2.5 pr-10 text-sm text-brand-ink ring-1 ring-brand-border focus:outline-none focus:ring-2 focus:ring-brand-500">
                            <option value="ganjil" @selected(old('semester', $tahunAktif?->semester) === 'ganjil')>Ganjil</option>
                            <option value="genap" @selected(old('semester', $tahunAktif?->semester) === 'genap')>Genap</option>
                        </select>
                        <span class="pointer-events-none absolute inset-y-0 right-3.5 flex items-center text-brand-faint">
                            <x-icon name="chevron-down" class="h-4 w-4" />
                        </span>
                    </div>

                    <button type="submit"
                        class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-brand-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-brand-500/25 transition-colors hover:bg-brand-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 motion-reduce:transition-none">
                        <x-icon name="swap" class="h-5 w-5" />
                        Aktifkan Tahun Ajaran
                    </button>
                </form>
            </div>
        </div>

        {{-- Riwayat tahun ajaran yang pernah dipakai --}}
        @if ($daftarTahunAjaran->isNotEmpty())
            <div class="mt-5 max-w-4xl overflow-hidden rounded-2xl bg-brand-surface ring-1 ring-brand-border">
                <div class="border-b border-brand-border px-6 py-4">
                    <h2 class="text-base font-bold text-brand-ink">Riwayat Tahun Ajaran</h2>
                    <p class="text-xs text-brand-muted">Tahun ajaran yang pernah dibuat. Mengetik yang sama tidak membuat data ganda.</p>
                </div>
                <ul class="divide-y divide-brand-border">
                    @foreach ($daftarTahunAjaran as $ta)
                        <li class="flex items-center justify-between gap-3 px-6 py-3">
                            <span class="font-semibold text-brand-ink">{{ $ta->label() }}</span>
                            @if ($ta->aktif)
                                <span class="rounded-full bg-emerald-500/10 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-200">Aktif</span>
                            @else
                                <span class="text-xs text-brand-faint">tidak aktif</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif

    {{-- ============ TAB 5: IDENTITAS SEKOLAH & GATEWAY WHATSAPP ============
         Berbeda dari empat tab di atas yang form-nya diproses SettingController,
         tab ini sepenuhnya ditangani komponen Livewire — termasuk validasi dan
         penyimpanannya. Karena itu tidak ada method updateSistem() di
         controller, dan itu memang disengaja, bukan yang terlupa. --}}
    @if ($tabAktif === 'sistem')
        <livewire:super-admin.pengaturan-sistem />
    @endif
@endsection
