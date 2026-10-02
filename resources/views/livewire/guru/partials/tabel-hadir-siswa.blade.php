{{--
    Tabel daftar hadir siswa — dipakai BERSAMA oleh dua komponen:
      - App\Livewire\Guru\JurnalAbsenKelas  (guru pemilik jadwal)
      - App\Livewire\Guru\KelasPengganti    (pengganti saat guru berhalangan)

    Satu berkas, bukan dua salinan: tampilan status, penanda gerbang, dan
    perilaku tombolnya harus sama persis di kedua halaman. Kalau disalin,
    perbaikan tata letak HP di satu halaman diam-diam tidak ikut ke halaman
    lainnya.

    Syarat komponen pemakainya: punya computed daftarSiswa, hadirDiGerbang,
    izinDariGerbang, pilihanStatus, serta properti publik $status dan
    $keterangan yang dikunci siswa_id.
--}}
{{-- ================================================================
     TABEL DI LAYAR BESAR, DAFTAR BERTUMPUK DI HP

     ============ KENAPA min-w-nya HANYA md: ============
     Sebelumnya tabel ini selalu `min-w-[760px]`. Di layar 360px
     (ukuran HP paling umum di sekolah ini) akibatnya terukur:
     tabelnya 760px di dalam wadah 326px, dan kolom "Status
     Kehadiran" mulai pada x=559 — 199px DI LUAR layar.

     Artinya satu-satunya kontrol yang harus disentuh guru justru
     yang paling tidak terjangkau: ia harus menggeser tabel ke
     samping dulu, dan sesudah digeser tombolnya masih terpotong
     di tepi. Yang dirasakan guru: "halamannya geser sendiri ke
     samping dan tombolnya kepotong".

     Di bawah md: seluruh elemen tabel dijadikan block (max-md:*),
     sehingga tiap siswa tampil sebagai satu kartu bertumpuk dan
     TIDAK ada yang perlu digeser ke samping sama sekali.

     Dibuat dengan SATU susunan DOM, bukan dua (versi HP + versi
     desktop yang saling disembunyikan). Dua susunan berarti dua
     set <input> dengan wire:model yang sama untuk satu siswa —
     input yang disembunyikan CSS tetap ada dan tetap terikat,
     dan itu sumber bug yang jauh lebih sulit dilacak daripada
     tata letak yang salah.
     ================================================================ --}}
<div class="overflow-x-auto">
    <table class="w-full table-auto max-md:block md:min-w-[760px]">
        <thead class="max-md:hidden">
            <tr class="bg-gray-50 text-left dark:bg-gray-800">
                <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">No</th>
                <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">NIS</th>
                <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Nama Siswa</th>
                <th class="px-4 py-4 text-xs font-semibold uppercase tracking-wide text-brand-ink dark:text-white">Status Kehadiran</th>
            </tr>
        </thead>
        <tbody class="max-md:block">
            @forelse ($this->daftarSiswa as $i => $siswa)
                <tr wire:key="siswa-{{ $siswa->id }}"
                    class="max-md:block max-md:border-b max-md:border-gray-200 max-md:px-4 max-md:py-4 max-md:dark:border-gray-800">

                    {{-- Nomor urut: berguna di tabel, jadi kebisingan
                         di kartu HP. Namanya sudah cukup menandai baris. --}}
                    <td class="border-b border-gray-200 px-4 py-4 text-sm text-brand-muted max-md:hidden dark:border-gray-800 dark:text-brand-faint">
                        {{ $i + 1 }}
                    </td>

                    {{-- Di HP, NIS ikut turun ke bawah nama (lihat
                         max-md:order-* pada baris) — di sini cukup
                         disembunyikan karena sudah ditampilkan lagi
                         di dalam sel nama. --}}
                    <td class="border-b border-gray-200 px-4 py-4 font-mono text-sm text-brand-muted max-md:hidden dark:border-gray-800 dark:text-brand-faint">
                        {{ $siswa->nis }}
                    </td>

                    <td class="border-b border-gray-200 px-4 py-4 max-md:block max-md:border-0 max-md:p-0 dark:border-gray-800">
                        <p class="font-medium text-brand-ink dark:text-white">{{ $siswa->nama }}</p>

                        {{-- NIS hanya muncul di sini saat layar kecil,
                             menggantikan kolomnya yang disembunyikan. --}}
                        <p class="hidden font-mono text-xs text-brand-muted max-md:block dark:text-brand-faint">
                            {{ $siswa->nis }}
                        </p>

                        {{-- Penanda ini yang membuat "bolos" masuk akal
                             bagi guru: ia bisa melihat siapa yang tadi
                             pagi lewat gerbang tapi tidak ada di kelas. --}}
                        @if ($this->hadirDiGerbang->contains($siswa->id))
                            <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-success-500">
                                <x-icon name="qr-code" class="h-3 w-3" />
                                Masuk gerbang pagi ini
                            </span>
                        @elseif ($this->izinDariGerbang->has($siswa->id))
                            {{-- Penanda KENAPA status anak ini sudah terisi
                                 sebelum guru menyentuh apa pun. Tanpa
                                 kalimat ini, isian yang berubah sendiri
                                 terlihat seperti kesalahan sistem — guru
                                 akan mengembalikannya ke Hadir, dan izin
                                 yang sudah resmi dicatat petugas piket
                                 hilang begitu saja. --}}
                            <span class="mt-1 inline-flex items-center gap-1 text-[11px] font-medium text-warning-500">
                                <x-icon name="clipboard-check" class="h-3 w-3" />
                                Sudah dicatat izin/sakit di gerbang
                            </span>
                        @endif
                    </td>
                    <td class="border-b border-gray-200 px-4 py-4 max-md:block max-md:border-0 max-md:px-0 max-md:pb-0 max-md:pt-3 dark:border-gray-800">
                        {{-- grid 2 kolom di HP: empat tombol status muat
                             dalam dua baris tanpa satu pun terpotong, dan
                             luas sentuhnya tetap lebar. --}}
                        <div class="flex flex-wrap gap-2 max-md:grid max-md:grid-cols-2">
                            @foreach ($this->pilihanStatus as $pilihan)
                                @php $dipilih = ($status[$siswa->id] ?? 'hadir') === $pilihan->value; @endphp

                                <label
                                    class="flex cursor-pointer select-none items-center gap-2 rounded-md border px-3 py-1.5 text-sm font-medium transition
                                           {{ $dipilih
                                                ? $pilihan->kelasTitik()
                                                : 'border-gray-200 text-brand-muted hover:border-brand-500 dark:border-gray-800 dark:text-brand-faint' }}">
                                    {{-- ============ WAJIB .live ============
                                         Radio aslinya disembunyikan (sr-only); yang
                                         dilihat guru adalah lingkaran dan warna yang
                                         DIGAMBAR SERVER dari $status[...].

                                         Dengan `wire:model` biasa, Livewire v3+
                                         menahan nilainya sampai permintaan berikutnya
                                         (deferred). Akibatnya guru mengklik "Alpa",
                                         radio tersembunyinya memang berpindah, tapi
                                         server tidak pernah merender ulang — warna dan
                                         titiknya tetap di "Hadir", dan kolom Keterangan
                                         tidak pernah muncul.

                                         Yang terlihat di layar: mengklik status sama
                                         sekali tidak ada efeknya. Nilainya sebenarnya
                                         TETAP ikut tersimpan saat Simpan ditekan — dan
                                         justru itu yang berbahaya: guru menyerah atau
                                         menekan berulang kali tanpa pernah tahu apa
                                         yang sedang tercatat.
                                         ====================================== --}}
                                    <input type="radio"
                                        wire:model.live="status.{{ $siswa->id }}"
                                        value="{{ $pilihan->value }}"
                                        class="sr-only">

                                    <span class="flex h-4 w-4 items-center justify-center rounded-full border
                                                 {{ $dipilih ? $pilihan->kelasTitik() : 'border-gray-200 dark:border-gray-800' }}">
                                        <span class="h-2 w-2 rounded-full {{ $dipilih ? 'bg-current' : 'bg-transparent' }}"></span>
                                    </span>

                                    {{ $pilihan->label() }}
                                </label>
                            @endforeach
                        </div>

                        {{-- Keterangan hanya muncul untuk status selain
                             Hadir — kolom yang selalu tampil membuat
                             tabel 30 baris jadi dinding input kosong. --}}
                        @if (($status[$siswa->id] ?? 'hadir') !== 'hadir')
                            <input type="text" wire:model="keterangan.{{ $siswa->id }}"
                                maxlength="255" placeholder="Keterangan (opsional)"
                                class="mt-2 w-full max-w-xs rounded-md border border-gray-200 bg-transparent px-3 py-1.5 text-xs text-brand-ink outline-none focus:border-brand-500 dark:border-gray-800 dark:text-white">
                        @endif
                    </td>
                </tr>
            @empty
                <tr class="max-md:block">
                    <td colspan="4" class="px-4 py-12 text-center max-md:block">
                        <p class="text-sm font-semibold text-brand-ink dark:text-white">Kelas ini belum punya siswa</p>
                        <p class="mt-1 text-sm text-brand-muted dark:text-brand-faint">
                            Hubungi Admin TU untuk memasukkan data siswanya lebih dulu.
                        </p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
