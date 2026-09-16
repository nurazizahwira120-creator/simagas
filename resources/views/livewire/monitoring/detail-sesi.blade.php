{{--
    Panel detail sesi KBM. Dirender SELALU (sebagai wadah kosong) supaya
    Livewire punya satu akar yang tetap, dan isinya baru muncul saat ada
    jadwal yang dipilih.
--}}
<div>
    @if ($this->jadwal)
        @php
            $j = $this->jadwal;
            $st = $this->statusSesi;
            $sesi = $this->sesi;
            $gaya = [
                'belum' => ['border-red-300 bg-red-500/10 text-brand-danger-text', 'x-circle'],
                'jalan' => ['border-sky-300 bg-sky-500/10 text-sky-700 dark:text-sky-400', 'clock'],
                'selesai' => ['border-emerald-300 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', 'check-circle'],
            ][$st['kunci']];
        @endphp

        {{-- Latar gelap: menutup panel saat diklik. Tidak memakai <button>
             membentang karena panelnya sendiri harus tetap bisa diklik.

             ---------- KENAPA z-[100000], bukan z-50 ----------
             Ajakan "Aktifkan notifikasi di HP ini?" (partials/firebase-push)
             memakai z-[99999] supaya selalu terlihat. Dengan z-50, kotak itu
             mengambang DI ATAS panel ini dan menutupi bagian bawahnya —
             di layar HP yang tertutup justru catatan "halaman ini hanya
             menampilkan data", yaitu kalimat yang mencegah kepala sekolah
             mengira absensi bisa dikoreksi dari sini.

             Panel ini dibuka dengan sengaja oleh penggunanya, sedangkan
             ajakan notifikasi muncul sendiri; yang dibuka sengaja harus
             menang. Angkanya satu tingkat di atas z-[99999] — kalau suatu
             saat ada lapisan baru yang lebih tinggi, naikkan DI SINI dan
             sebutkan alasannya, jangan tambahkan !important di tempat lain.
             ---------------------------------------------------- --}}
        <div class="fixed inset-0 z-[100000] flex justify-end bg-black/50"
            wire:click.self="tutup"
            x-data
            @keydown.escape.window="$wire.tutup()"
            role="dialog" aria-modal="true" aria-labelledby="judul-detail-sesi">

            <div class="flex h-full w-full max-w-lg flex-col overflow-y-auto bg-brand-surface shadow-xl dark:bg-gray-900">

                {{-- ---------- KEPALA ---------- --}}
                <div class="sticky top-0 z-10 border-b border-brand-border bg-brand-surface px-5 py-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 id="judul-detail-sesi" class="truncate text-lg font-bold text-brand-ink dark:text-white">
                                {{ $j->kelas?->nama_kelas ?? 'Kelas —' }}
                            </h2>
                            <p class="mt-0.5 truncate text-sm font-medium text-brand-accent-text">{{ $j->mata_pelajaran }}</p>
                        </div>

                        <button type="button" wire:click="tutup"
                            class="shrink-0 rounded-lg p-1.5 text-brand-muted hover:bg-brand-surface-muted"
                            aria-label="Tutup detail">
                            <x-icon name="x-mark" class="h-5 w-5" />
                        </button>
                    </div>

                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex min-w-0 items-center gap-2">
                            <dt class="sr-only">Guru pengampu</dt>
                            <x-icon name="briefcase" class="h-4 w-4 shrink-0 text-brand-muted" />
                            <dd class="truncate text-brand-ink dark:text-gray-200">{{ $j->guru?->nama ?? '— belum ditetapkan —' }}</dd>
                        </div>
                        <div class="flex min-w-0 items-center gap-2">
                            <dt class="sr-only">Waktu</dt>
                            <x-icon name="clock" class="h-4 w-4 shrink-0 text-brand-muted" />
                            {{-- rentangJam() dari model, BUKAN memotong string sendiri:
                                 jam_mulai di-cast ke Carbon, jadi memperlakukannya
                                 sebagai teks menghasilkan tanggalnya, bukan jamnya. --}}
                            <dd class="tabular-nums text-brand-muted">{{ $j->rentangJam() }}</dd>
                        </div>
                        @if ($j->ruangan)
                            <div class="flex min-w-0 items-center gap-2">
                                <dt class="sr-only">Ruangan</dt>
                                <x-icon name="building" class="h-4 w-4 shrink-0 text-brand-muted" />
                                <dd class="truncate text-brand-muted">{{ $j->ruangan }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="space-y-5 px-5 py-5">

                    {{-- ---------- 1. STATUS SESI MENGAJAR ---------- --}}
                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">Status Sesi</h3>

                        <div class="rounded-xl border px-4 py-3 {{ $gaya[0] }}">
                            <p class="flex items-center gap-2 text-sm font-bold">
                                <x-icon name="{{ $gaya[1] }}" class="h-4 w-4 shrink-0" />
                                {{ $st['label'] }}
                            </p>
                            <p class="mt-1 text-xs leading-relaxed opacity-90">{{ $st['rincian'] }}</p>
                        </div>
                    </section>

                    {{-- ---------- 2. DAFTAR HADIR ---------- --}}
                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">Daftar Hadir Siswa</h3>

                        @if ($this->barisSiswa->isEmpty())
                            {{-- Ini pesan yang paling penting di panel ini. Kartu di
                                 layar utama sudah menandainya merah, tapi di sini
                                 disebut sebabnya secara langsung. --}}
                            <p class="flex items-start gap-2 rounded-xl border border-red-300 bg-red-500/10 px-4 py-3 text-sm leading-relaxed text-brand-danger-text">
                                <x-icon name="exclamation-triangle" class="mt-0.5 h-4 w-4 shrink-0" />
                                <span class="min-w-0">
                                    <strong>Belum diisi sama sekali.</strong>
                                    Tidak ada satu pun kehadiran siswa yang tercatat untuk jam pelajaran ini.
                                </span>
                            </p>
                        @else
                            @php $r = $this->ringkasKehadiran; @endphp

                            <p class="mb-2 text-sm text-brand-muted">
                                Sudah diisi — <strong class="text-brand-ink dark:text-white">{{ $this->barisSiswa->count() }} siswa</strong> tercatat.
                            </p>

                            <div class="flex flex-wrap gap-2">
                                @foreach (\App\Enums\StatusKbm::cases() as $s)
                                    @if ($r[$s->value] > 0)
                                        <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $s->kelasBadge() }}">
                                            {{ $s->label() }}
                                            <span class="tabular-nums">{{ $r[$s->value] }}</span>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </section>

                    {{-- ---------- 3. SISWA TIDAK HADIR ---------- --}}
                    @if ($this->siswaTidakHadir->isNotEmpty())
                        <section>
                            <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">
                                Siswa Tidak Hadir ({{ $this->siswaTidakHadir->count() }})
                            </h3>

                            <ul class="divide-y divide-brand-border rounded-xl border border-brand-border dark:divide-gray-800 dark:border-gray-800">
                                @foreach ($this->siswaTidakHadir as $a)
                                    @php $surat = $this->suratPerSiswa->get($a->siswa_id); @endphp

                                    <li class="min-w-0 px-4 py-3" wire:key="tidak-hadir-{{ $a->id }}">
                                        <div class="flex flex-wrap items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-brand-ink dark:text-white">
                                                    {{ $a->siswa?->nama ?? 'Siswa tidak ditemukan' }}
                                                </p>
                                                <p class="truncate text-xs text-brand-muted">NIS {{ $a->siswa?->nis ?? '—' }}</p>
                                            </div>

                                            <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $a->status->kelasBadge() }}">
                                                {{ $a->status->label() }}
                                            </span>
                                        </div>

                                        @if ($a->keterangan)
                                            <p class="mt-1.5 text-xs leading-relaxed text-brand-muted">
                                                <span class="font-semibold">Catatan guru:</span> {{ $a->keterangan }}
                                            </p>
                                        @endif

                                        {{-- Surat izin dari gerbang. Berkasnya ada di disk
                                             PRIVAT dan dilayani lewat controller, bukan URL
                                             statis — isinya sering surat keterangan dokter
                                             milik anak di bawah umur. --}}
                                        @if ($surat)
                                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                                <span class="inline-flex items-center gap-1 rounded-lg bg-brand-surface-muted px-2.5 py-1 text-[11px] font-medium text-brand-muted">
                                                    <x-icon name="shield-check" class="h-3 w-3" />
                                                    Tercatat di gerbang: {{ $surat->status->label() }}
                                                </span>

                                                @if ($surat->suratAda())
                                                    <a href="{{ route($this->panelPrefix . '.gerbang.surat', $surat) }}"
                                                        target="_blank" rel="noopener"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-brand-border px-2.5 py-1 text-[11px] font-semibold text-brand-accent-text hover:bg-brand-surface-muted dark:border-gray-800">
                                                        <x-icon name="eye" class="h-3 w-3" />
                                                        Lihat Surat
                                                    </a>
                                                @endif
                                            </div>
                                        @elseif (in_array($a->status, [\App\Enums\StatusKbm::Alpa, \App\Enums\StatusKbm::Bolos], true))
                                            <p class="mt-1.5 text-xs font-medium text-brand-danger-text">
                                                Tidak ada catatan izin di gerbang — perlu ditindaklanjuti.
                                            </p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- ---------- 4. FOTO BUKTI MENGAJAR ---------- --}}
                    <section>
                        <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-brand-muted">Foto Bukti Mengajar</h3>

                        @if ($sesi?->urlBukti())
                            <a href="{{ $sesi->urlBukti() }}" target="_blank" rel="noopener" class="block">
                                {{-- aspect-video + object-cover: foto dari HP guru bisa
                                     potret maupun lanskap, dan tanpa rasio tetap panel
                                     ini akan meloncat tingginya setiap kali dibuka. --}}
                                <img src="{{ $sesi->urlBukti() }}"
                                    alt="Foto bukti mengajar {{ $j->guru?->nama }} di {{ $j->kelas?->nama_kelas }}"
                                    loading="lazy"
                                    class="aspect-video w-full rounded-xl border border-brand-border object-cover dark:border-gray-800">
                            </a>
                            <p class="mt-1.5 text-xs text-brand-muted">Klik gambar untuk melihat ukuran penuh.</p>
                        @elseif ($sesi)
                            <p class="rounded-xl border border-dashed border-brand-border px-4 py-5 text-center text-sm text-brand-muted dark:border-gray-800">
                                Guru belum mengunggah foto bukti untuk sesi ini.
                            </p>
                        @else
                            <p class="rounded-xl border border-dashed border-brand-border px-4 py-5 text-center text-sm text-brand-muted dark:border-gray-800">
                                Belum ada sesi mengajar, jadi belum ada foto bukti.
                            </p>
                        @endif
                    </section>

                    {{-- ---------- CATATAN JUJUR ---------- --}}
                    <p class="flex items-start gap-2 rounded-xl border border-dashed border-brand-border px-4 py-3 text-xs leading-relaxed text-brand-muted dark:border-gray-800">
                        <x-icon name="inbox" class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            Halaman ini <strong>hanya menampilkan</strong> data. Koreksi absensi dikerjakan
                            guru mata pelajaran lewat Jurnal &amp; Absen Kelas, dan pencatatan izin oleh
                            petugas piket di gerbang.
                        </span>
                    </p>
                </div>
            </div>
        </div>
    @endif
</div>
