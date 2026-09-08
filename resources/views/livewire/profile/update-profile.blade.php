{{--
    View untuk App\Livewire\Profile\UpdateProfile.

    Komponen Livewire wajib punya tepat satu elemen akar — semuanya dibungkus
    <div> paling luar di bawah ini.
--}}
<div x-data="pemeriksaFoto()">

    @php
        $kelasLabel = 'mb-1.5 block text-sm font-semibold text-navy-700';
        $kelasInput = 'w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-brand-ink ring-1 ring-inset ring-gray-200 transition placeholder:text-brand-faint focus:bg-brand-surface focus:outline-none focus:ring-2 focus:ring-teal-500';
    @endphp

    {{-- Pemberitahuan sukses. Memakai session flash + wire:key supaya Livewire
         benar-benar memasang ulang elemennya setiap kali disimpan — kalau tidak,
         menyimpan dua kali berturut-turut membuat notifikasinya tidak berkedip
         sama sekali dan pengguna mengira tombolnya tidak bekerja. --}}
    @if (session('profil-sukses'))
        <div wire:key="notif-{{ now()->timestamp }}-{{ Str::random(4) }}"
            x-data="{ tampil: true }" x-init="setTimeout(() => tampil = false, 6000)"
            x-show="tampil" x-transition
            class="mb-6 flex items-start gap-2.5 rounded-2xl border border-emerald-200 dark:border-emerald-500/30 bg-emerald-500/10 px-4 py-3.5 text-sm text-emerald-800 dark:text-emerald-400">
            <x-icon name="check-circle" class="mt-0.5 h-5 w-5 shrink-0" />
            <p class="font-semibold">{{ session('profil-sukses') }}</p>
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-6 flex items-start gap-2.5 rounded-2xl border border-rose-200 dark:border-rose-500/30 bg-rose-500/10 px-4 py-3.5 text-sm text-rose-700 dark:text-rose-400">
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


    <form wire:submit.prevent="simpanProfil" class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- ============ KOLOM KIRI — FOTO ============ --}}
        <div class="lg:col-span-1">
            <div class="rounded-2xl border border-brand-border bg-brand-surface p-6 shadow-soft">
                <h2 class="text-base font-bold text-navy-700">Foto Profil</h2>
                <p class="mt-1 text-xs leading-relaxed text-brand-muted">
                    Dipakai di kartu identitas Anda. Rasio 3:4 seperti pas foto.
                </p>

                {{-- Pratinjau 3:4 --}}
                <div class="mt-5 flex justify-center">
                    <div class="relative h-[280px] w-[210px] overflow-hidden rounded-xl border border-brand-border bg-brand-surface-muted">
                        @if ($this->pratinjauFoto)
                            <img src="{{ $this->pratinjauFoto }}" alt="Pratinjau foto profil"
                                class="h-full w-full object-cover">
                        @else
                            <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-brand-faint">
                                <x-icon name="camera" class="h-12 w-12" />
                                <span class="text-xs font-semibold uppercase tracking-widest">Belum ada foto</span>
                            </div>
                        @endif

                        {{-- Tirai "sedang mengunggah" — html2canvas tidak terlibat
                             di sini, ini murni umpan balik unggahan Livewire. --}}
                        <div wire:loading wire:target="foto_baru"
                            class="absolute inset-0 flex flex-col items-center justify-center gap-2 bg-white/85 text-teal-600 dark:text-teal-400">
                            <svg class="h-8 w-8 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                            </svg>
                            <span class="text-xs font-semibold">Mengunggah…</span>
                        </div>
                    </div>
                </div>

                @if ($foto_baru)
                    <p class="mt-3 text-center text-xs font-medium text-teal-700 dark:text-teal-400">
                        Foto baru dipilih — tekan <span class="font-bold">Simpan Perubahan</span> untuk menyimpannya.
                    </p>
                @endif

                {{-- Tombol pilih berkas kustom.
                     <input type="file"> bawaan tidak bisa digaya, jadi input-nya
                     disembunyikan (sr-only, BUKAN display:none supaya tetap bisa
                     dijangkau keyboard & pembaca layar) dan <label> yang bertindak
                     sebagai tombolnya. --}}
                <div class="mt-5 space-y-2">
                    <label for="foto_baru"
                        class="flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl bg-teal-500 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600 focus-within:ring-2 focus-within:ring-teal-500 focus-within:ring-offset-2">
                        <x-icon name="upload" class="h-4 w-4" />
                        {{ $this->pratinjauFoto ? 'Ganti Foto' : 'Pilih Foto' }}
                        <input id="foto_baru" type="file" wire:model="foto_baru"
                            accept="image/jpeg,image/png" class="sr-only"
                            x-on:change="periksa($event)">
                    </label>

                    @if ($pengguna->foto)
                        {{-- data-konfirmasi, bukan wire:confirm: wire:confirm
                             memanggil confirm() bawaan browser dan tidak bisa
                             diarahkan ke SweetAlert2. --}}
                        <button type="button" wire:click="hapusFoto"
                            data-konfirmasi-judul="Hapus Foto Profil?"
                            data-konfirmasi="Foto profil Anda akan dihapus dan diganti inisial nama."
                            data-konfirmasi-ikon="warning"
                            data-konfirmasi-ya="Ya, Hapus!"
                            data-konfirmasi-batal="Batal"
                            class="w-full rounded-xl border border-brand-border px-4 py-2.5 text-sm font-semibold text-brand-muted transition hover:border-rose-300 hover:text-rose-600 hover:dark:text-rose-400">
                            Hapus Foto
                        </button>
                    @endif

                    {{-- Pesan dari sisi browser: muncul SEBELUM berkas dikirim,
                         jadi tetap terbaca walau server menolak diam-diam. --}}
                    <p x-show="pesanKlien" style="display: none"
                        class="rounded-xl bg-rose-500/10 px-3 py-2.5 text-center text-xs font-semibold text-rose-700 dark:text-rose-400"
                        x-text="pesanKlien"></p>

                    <p class="text-center text-[11px] leading-relaxed text-brand-faint">
                        Format JPG atau PNG, maksimal 2 MB.
                        Foto tegak (potret) memberi hasil terbaik.
                    </p>
                </div>
            </div>
        </div>

        {{-- ============ KOLOM KANAN — DATA DIRI ============ --}}
        <div class="space-y-6 lg:col-span-2">

            <div class="rounded-2xl border border-brand-border bg-brand-surface p-6 shadow-soft sm:p-8">
                <h2 class="text-base font-bold text-navy-700">Data Diri</h2>
                <p class="mt-1 text-xs text-brand-muted">
                    Masuk sebagai <span class="font-semibold text-brand-muted">{{ $pengguna->role->label() }}</span>.
                </p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div>
                        <label for="nama" class="{{ $kelasLabel }}">Nama Lengkap</label>
                        <input id="nama" type="text" wire:model="nama" required
                            autocomplete="name" class="{{ $kelasInput }}">
                    </div>
                    <div>
                        <label for="email" class="{{ $kelasLabel }}">Email</label>
                        <input id="email" type="email" wire:model="email" required
                            autocomplete="email" class="{{ $kelasInput }}">
                    </div>
                </div>

                <div class="mt-5">
                    <label for="no_hp" class="{{ $kelasLabel }}">Nomor HP / WhatsApp</label>
                    <input id="no_hp" type="tel" wire:model="no_hp" inputmode="tel"
                        placeholder="08xxxxxxxxxx" autocomplete="tel" class="{{ $kelasInput }}">

                    @if ($pengguna->role === \App\Enums\UserRole::WaliMurid)
                        <p class="mt-1.5 text-xs text-brand-muted">
                            Nomor ini juga dipakai untuk mengirim notifikasi kehadiran anak Anda —
                            mengubahnya di sini ikut memperbarui data anak Anda.
                        </p>
                    @endif
                </div>

                <div class="mt-5">
                    <label for="alamat" class="{{ $kelasLabel }}">Alamat</label>
                    <textarea id="alamat" wire:model="alamat" rows="3"
                        placeholder="Jalan, RT/RW, desa/kelurahan, kecamatan"
                        class="{{ $kelasInput }} resize-y"></textarea>
                </div>
            </div>

            {{-- ---- Ubah kata sandi ---- --}}
            <div class="rounded-2xl border border-brand-border bg-brand-surface p-6 shadow-soft sm:p-8">
                <h2 class="text-base font-bold text-navy-700">Ubah Kata Sandi</h2>
                <p class="mt-1 text-xs text-brand-muted">
                    Kosongkan ketiga kolom ini kalau tidak ingin mengganti kata sandi.
                </p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="password_lama" class="{{ $kelasLabel }}">Kata Sandi Lama</label>
                        <input id="password_lama" type="password" wire:model="password_lama"
                            autocomplete="current-password" placeholder="Diperlukan untuk mengganti sandi"
                            class="{{ $kelasInput }} sm:max-w-sm">
                    </div>
                    <div>
                        <label for="password_baru" class="{{ $kelasLabel }}">Kata Sandi Baru</label>
                        <input id="password_baru" type="password" wire:model="password_baru"
                            autocomplete="new-password" placeholder="Minimal 8 karakter"
                            class="{{ $kelasInput }}">
                    </div>
                    <div>
                        <label for="password_baru_confirmation" class="{{ $kelasLabel }}">Ulangi Kata Sandi Baru</label>
                        <input id="password_baru_confirmation" type="password"
                            wire:model="password_baru_confirmation" autocomplete="new-password"
                            placeholder="Ketik ulang" class="{{ $kelasInput }}">
                    </div>
                </div>
            </div>

            {{-- ---- Tombol simpan ---- --}}
            <div class="flex flex-wrap items-center justify-end gap-3">
                <p wire:loading wire:target="simpanProfil" class="text-xs text-brand-muted">
                    Menyimpan perubahan…
                </p>

                <button type="submit" wire:loading.attr="disabled" wire:target="simpanProfil, foto_baru"
                    class="inline-flex items-center justify-center gap-2 rounded-xl bg-teal-500 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-teal-500/25 transition hover:bg-teal-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-teal-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70">
                    <span wire:loading.remove wire:target="simpanProfil" class="inline-flex items-center gap-2">
                        <x-icon name="check-circle" class="h-4 w-4" />
                        Simpan Perubahan
                    </span>
                    <span wire:loading wire:target="simpanProfil" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none">
                            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2.5" class="opacity-25" />
                            <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" />
                        </svg>
                        Menyimpan…
                    </span>
                </button>
            </div>
        </div>
    </form>

    <script>
        // CATATAN: jangan menulis teks berbentuk tag HTML di blok ini —
        // termasuk di dalam komentar. Livewire memeriksa aturan satu-elemen-akar
        // memakai DOMDocument, dan parser-nya keluar dari script begitu melihat
        // teks semacam itu lalu membuat elemen di luar div akar. Akibatnya
        // halaman jadi error 500. Sudah pernah terjadi di komponen kartu.
        window.pemeriksaFoto = function () {
            return {
                pesanKlien: null,

                periksa(ev) {
                    this.pesanKlien = null;

                    const berkas = ev.target.files && ev.target.files[0];
                    if (! berkas) return;

                    const BATAS = 2 * 1024 * 1024;   // 2 MB, sama dengan aturan di server

                    // KENAPA DIPERIKSA DI SINI JUGA, padahal server sudah
                    // memvalidasi: PHP punya batas sendiri (upload_max_filesize,
                    // sering 2M) yang bekerja SEBELUM Laravel melihat berkasnya.
                    // Kalau batas itu terlampaui, permintaannya ditolak di
                    // tingkat server dan aturan validasi kita tidak pernah
                    // jalan — pengguna cuma melihat tombolnya tidak bereaksi.
                    // Pemeriksaan di browser memastikan alasannya selalu
                    // tersampaikan, berapa pun batas PHP di server sekolah.
                    if (berkas.size > BATAS) {
                        const mb = (berkas.size / 1024 / 1024).toFixed(1);
                        this.pesanKlien = 'Ukuran foto ' + mb + ' MB, melebihi batas 2 MB. Perkecil dulu fotonya lalu coba lagi.';
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

        // Jaring pengaman terakhir: kalau unggahan tetap gagal di tingkat
        // jaringan/server, Livewire memancarkan peristiwa ini. Tanpa
        // mendengarkannya, kegagalan itu benar-benar tidak terlihat.
        document.addEventListener('livewire:init', function () {
            Livewire.hook('upload:error', function () {
                const wadah = document.querySelector('[x-data^="pemeriksaFoto"]');
                if (wadah && wadah._x_dataStack) {
                    wadah._x_dataStack[0].pesanKlien =
                        'Server menolak unggahan foto. Kemungkinan besar ukurannya melebihi batas server. Coba foto yang lebih kecil.';
                }
            });
        });
    </script>
</div>
