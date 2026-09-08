{{--
    View untuk App\Livewire\SuperAdmin\EditDataLengkap.

    Komponen Livewire wajib punya tepat satu elemen akar — semuanya dibungkus
    <div> paling luar di bawah ini.
--}}
<div>

    @if ($terbuka && $this->model)
        @php
            $kelasLabel = 'mb-1.5 block text-xs font-semibold text-navy-700';
            $kelasInput = 'w-full rounded-xl border-0 bg-gray-50 px-3.5 py-2.5 text-sm text-brand-ink ring-1 ring-inset ring-gray-200 transition placeholder:text-brand-faint focus:bg-brand-surface focus:outline-none focus:ring-2 focus:ring-teal-500';

            $adalahSiswa = $jenis === 'siswa';

            $inisial = collect(explode(' ', trim($nama)))
                ->filter()->take(2)
                ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))
                ->implode('');
        @endphp

        <div x-data="pemeriksaFotoEdit()"
            x-on:keydown.escape.window="$wire.tutup()"
            class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/60 p-4 py-8"
            role="dialog" aria-modal="true" aria-label="Edit data lengkap">

            {{-- Klik latar untuk menutup. Elemen terpisah supaya klik DI DALAM
                 modal tidak ikut menutupnya. --}}
            <div class="absolute inset-0" wire:click="tutup" aria-hidden="true"></div>

            <div class="relative w-full max-w-4xl overflow-hidden rounded-2xl bg-brand-surface shadow-2xl">

                {{-- ---------- Kepala modal ---------- --}}
                <div class="flex items-start justify-between gap-4 border-b border-brand-border px-6 py-5 sm:px-8">
                    <div>
                        <h2 class="text-lg font-extrabold tracking-tight text-navy-700">
                            Edit Data {{ $adalahSiswa ? 'Siswa' : 'Pegawai' }}
                        </h2>
                        <p class="mt-0.5 text-sm text-brand-muted">
                            Lengkapi biodata {{ $nama ?: '—' }}.
                        </p>
                    </div>

                    <button type="button" wire:click="tutup"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl text-brand-faint transition hover:bg-brand-surface-muted hover:text-brand-muted"
                        aria-label="Tutup">
                        <x-icon name="x-mark" class="h-5 w-5" />
                    </button>
                </div>

                @if ($errors->any())
                    <div class="mx-6 mt-5 flex items-start gap-2.5 rounded-xl border border-rose-200 dark:border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-700 dark:text-rose-400 sm:mx-8">
                        <x-icon name="exclamation-triangle" class="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p class="font-semibold">Periksa lagi isian berikut:</p>
                            <ul class="mt-1 list-inside list-disc space-y-0.5">
                                @foreach ($errors->all() as $pesan)
                                    <li>{{ $pesan }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <form wire:submit.prevent="simpan">
                    <div class="grid gap-6 px-6 py-6 sm:px-8 md:grid-cols-3">

                        {{-- ============ KOLOM KIRI — FOTO ============ --}}
                        <div class="md:col-span-1">
                            <p class="{{ $kelasLabel }}">Pas Foto</p>

                            <div class="relative mx-auto h-[240px] w-[180px] overflow-hidden rounded-xl border border-brand-border bg-brand-surface-muted md:mx-0">
                                @if ($this->pratinjauFoto)
                                    <img src="{{ $this->pratinjauFoto }}" alt="Pratinjau foto {{ $nama }}"
                                        class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full flex-col items-center justify-center gap-1.5 text-brand-faint">
                                        <span class="text-[32px] font-extrabold leading-none">{{ $inisial ?: '—' }}</span>
                                        <span class="text-[10px] font-semibold uppercase tracking-widest">Belum ada foto</span>
                                    </div>
                                @endif

                                <div wire:loading wire:target="foto_baru"
                                    class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-white/85 text-teal-600 dark:text-teal-400">
                                    <svg class="h-7 w-7 animate-spin" viewBox="0 0 24 24" fill="none">
                                        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                                    </svg>
                                    <span class="text-[11px] font-semibold">Mengunggah…</span>
                                </div>
                            </div>

                            @if ($this->bisaUnggahFoto)
                                <label for="foto_edit"
                                    class="mt-4 flex cursor-pointer items-center justify-center gap-2 rounded-xl bg-teal-500 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600 focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-2">
                                    <x-icon name="upload" class="h-4 w-4" />
                                    {{ $this->pratinjauFoto ? 'Ganti Foto' : 'Unggah Foto' }}
                                    <input id="foto_edit" type="file" wire:model="foto_baru"
                                        accept="image/jpeg,image/png" class="sr-only"
                                        x-on:change="periksa($event)">
                                </label>

                                @if ($foto_baru)
                                    <p class="mt-2 text-center text-[11px] font-medium text-teal-700 dark:text-teal-400 md:text-left">
                                        Foto baru dipilih — tekan Simpan Perubahan.
                                    </p>
                                @endif

                                <p x-show="pesanKlien" style="display: none"
                                    class="mt-2 rounded-lg bg-rose-500/10 px-3 py-2 text-[11px] font-semibold text-rose-700 dark:text-rose-400"
                                    x-text="pesanKlien"></p>

                                <p class="mt-2 text-[11px] leading-relaxed text-brand-faint">
                                    JPG atau PNG, maksimal 2 MB. Foto tegak (potret) memberi hasil terbaik.
                                </p>
                            @else
                                {{-- Foto pegawai disimpan di kolom users.foto, jadi
                                     pegawai yang belum punya akun login tidak punya
                                     tempat menyimpannya. Lebih baik dikatakan
                                     terus terang daripada menyediakan tombol yang
                                     diam-diam tidak menyimpan apa pun. --}}
                                <div class="mt-4 rounded-xl border border-amber-200 dark:border-amber-500/30 bg-amber-500/10 px-3.5 py-3 text-[11px] leading-relaxed text-amber-800 dark:text-amber-400">
                                    Pegawai ini belum punya akun login, jadi fotonya belum bisa disimpan.
                                    Buatkan dulu akunnya lewat menu Manajemen Pengguna, lalu tautkan di kolom
                                    Akun Login.
                                </div>
                            @endif
                        </div>

                        {{-- ============ KOLOM KANAN — BIODATA ============ --}}
                        <div class="space-y-4 md:col-span-2">

                            {{-- Baris identitas --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    {{-- NIS siswa tetap wajib; NIP pegawai opsional,
                                         jadi keterangannya hanya muncul untuk pegawai. --}}
                                    <label for="ed-kode" class="{{ $kelasLabel }}">
                                        {{ $adalahSiswa ? 'NIS' : 'NIP' }}
                                        @unless ($adalahSiswa)
                                            <span class="font-normal text-brand-faint">(Opsional)</span>
                                        @endunless
                                    </label>
                                    <input id="ed-kode" type="text" inputmode="numeric"
                                        wire:model="{{ $adalahSiswa ? 'nis' : 'nip' }}"
                                        class="{{ $kelasInput }} font-mono">
                                </div>
                                <div>
                                    <label for="ed-nama" class="{{ $kelasLabel }}">Nama Lengkap</label>
                                    <input id="ed-nama" type="text" wire:model="nama" class="{{ $kelasInput }}">
                                </div>
                            </div>

                            {{-- Kelas / Jabatan --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                @if ($adalahSiswa)
                                    <div>
                                        <label for="ed-kelas" class="{{ $kelasLabel }}">Kelas</label>
                                        <select id="ed-kelas" wire:model="kelas_id" class="{{ $kelasInput }}">
                                            <option value="">— Pilih kelas —</option>
                                            @foreach ($this->daftarKelas as $kelas)
                                                <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div>
                                        <label for="ed-hp-wali" class="{{ $kelasLabel }}">No. HP Wali</label>
                                        <input id="ed-hp-wali" type="tel" inputmode="tel" wire:model="no_hp_wali"
                                            placeholder="08xxxxxxxxxx" class="{{ $kelasInput }}">
                                    </div>
                                @else
                                    <div>
                                        <label for="ed-jabatan" class="{{ $kelasLabel }}">Jabatan</label>
                                        <input id="ed-jabatan" type="text" wire:model="jabatan"
                                            placeholder="Contoh: Guru Matematika" class="{{ $kelasInput }}">
                                    </div>
                                    <div>
                                        <label for="ed-hp" class="{{ $kelasLabel }}">No. HP</label>
                                        <input id="ed-hp" type="tel" inputmode="tel" wire:model="no_hp"
                                            placeholder="08xxxxxxxxxx" class="{{ $kelasInput }}">
                                    </div>
                                @endif
                            </div>

                            {{-- Tempat & tanggal lahir --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="ed-tempat" class="{{ $kelasLabel }}">Tempat Lahir</label>
                                    <input id="ed-tempat" type="text" wire:model="tempat_lahir"
                                        placeholder="Contoh: Serang" class="{{ $kelasInput }}">
                                </div>
                                <div>
                                    <label for="ed-tanggal" class="{{ $kelasLabel }}">Tanggal Lahir</label>
                                    <input id="ed-tanggal" type="date" wire:model="tanggal_lahir"
                                        max="{{ now()->subDay()->toDateString() }}" class="{{ $kelasInput }}">
                                </div>
                            </div>

                            {{-- Jenis kelamin & agama --}}
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label for="ed-jk" class="{{ $kelasLabel }}">Jenis Kelamin</label>
                                    <select id="ed-jk" wire:model="jenis_kelamin" class="{{ $kelasInput }}">
                                        <option value="">— Pilih —</option>
                                        <option value="L">Laki-laki</option>
                                        <option value="P">Perempuan</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="ed-agama" class="{{ $kelasLabel }}">Agama</label>
                                    <select id="ed-agama" wire:model="agama" class="{{ $kelasInput }}">
                                        <option value="">— Pilih —</option>
                                        @foreach (\App\Livewire\SuperAdmin\EditDataLengkap::DAFTAR_AGAMA as $pilihan)
                                            <option value="{{ $pilihan }}">{{ $pilihan }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Wali murid (khusus siswa) --}}
                            @if ($adalahSiswa)
                                <div>
                                    <label for="ed-wali" class="{{ $kelasLabel }}">Akun Wali Murid <span class="font-normal text-brand-faint">(opsional)</span></label>
                                    <select id="ed-wali" wire:model="wali_murid_id" class="{{ $kelasInput }}">
                                        <option value="">— Belum ditautkan —</option>
                                        @foreach ($this->daftarWaliMurid as $wali)
                                            <option value="{{ $wali->id }}">{{ $wali->name }} ({{ $wali->email }})</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-[11px] text-brand-faint">
                                        Menautkan akun membuat wali murid bisa memantau kehadiran anak ini dari dashboard-nya.
                                    </p>
                                </div>
                            @endif

                            {{-- Alamat --}}
                            <div>
                                <label for="ed-alamat" class="{{ $kelasLabel }}">Alamat</label>
                                <textarea id="ed-alamat" wire:model="alamat" rows="2"
                                    placeholder="Jalan, RT/RW, desa/kelurahan, kecamatan"
                                    class="{{ $kelasInput }} resize-y"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- ---------- Kaki modal ---------- --}}
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-brand-border bg-brand-surface-muted px-6 py-4 sm:px-8">
                        <button type="button" wire:click="tutup"
                            class="rounded-xl border border-brand-border bg-brand-surface px-5 py-2.5 text-sm font-semibold text-brand-muted transition hover:bg-brand-surface-muted">
                            Batal
                        </button>

                        <button type="submit" wire:loading.attr="disabled" wire:target="simpan, foto_baru"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-500 px-6 py-2.5 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600 disabled:cursor-not-allowed disabled:opacity-70">
                            <span wire:loading.remove wire:target="simpan" class="inline-flex items-center gap-2">
                                <x-icon name="check-circle" class="h-4 w-4" />
                                Simpan Perubahan
                            </span>
                            <span wire:loading wire:target="simpan" class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                                    <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                                    <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                                </svg>
                                Menyimpan…
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <script>
        // CATATAN: jangan menulis teks berbentuk tag HTML di blok ini —
        // termasuk di dalam komentar. Livewire memeriksa aturan satu-elemen-akar
        // memakai DOMDocument, dan parser-nya keluar dari script begitu melihat
        // teks semacam itu lalu membuat elemen di luar div akar; halamannya jadi
        // error 500. Sudah pernah terjadi di komponen kartu identitas.
        window.pemeriksaFotoEdit = function () {
            return {
                pesanKlien: null,

                periksa(ev) {
                    this.pesanKlien = null;

                    const berkas = ev.target.files && ev.target.files[0];
                    if (! berkas) return;

                    const BATAS = 2 * 1024 * 1024;

                    // Diperiksa di browser DULU karena PHP punya batas sendiri
                    // (upload_max_filesize, sering 2M) yang bekerja sebelum
                    // Laravel melihat berkasnya — kalau terlampaui, aturan
                    // validasi kita tidak pernah sempat jalan dan pengguna
                    // hanya melihat tombolnya seperti tidak bereaksi.
                    if (berkas.size > BATAS) {
                        this.pesanKlien = 'Ukuran foto ' + (berkas.size / 1024 / 1024).toFixed(1)
                            + ' MB, melebihi batas 2 MB.';
                        ev.target.value = '';
                        return;
                    }

                    if (! ['image/jpeg', 'image/png'].includes(berkas.type)) {
                        this.pesanKlien = 'Format berkas harus JPG atau PNG.';
                        ev.target.value = '';
                    }
                },
            };
        };
    </script>
</div>
