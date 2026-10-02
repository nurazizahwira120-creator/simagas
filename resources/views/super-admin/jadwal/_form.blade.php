{{-- Dipakai bersama oleh create.blade.php & edit.blade.php --}}
<div class="max-w-lg space-y-4">
    <div>
        <label for="kelas_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="academic-cap" class="h-3.5 w-3.5" />
            Kelas
        </label>
        <div class="relative">
            <select id="kelas_id" name="kelas_id" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Pilih kelas —</option>
                @foreach ($daftarKelas as $k)
                    <option value="{{ $k->id }}" @selected((string) old('kelas_id', $jadwal->kelas_id ?? '') === (string) $k->id)>
                        {{ $k->nama_kelas }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
    </div>

    <div>
        <label for="guru_id" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="briefcase" class="h-3.5 w-3.5" />
            Guru Pengajar
        </label>
        <div class="relative">
            <select id="guru_id" name="guru_id" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Pilih guru —</option>

                @if ($guruPengajar->isNotEmpty())
                    <optgroup label="Guru & Wali Kelas">
                        @foreach ($guruPengajar as $g)
                            <option value="{{ $g->id }}" @selected((string) old('guru_id', $jadwal->guru_id ?? '') === (string) $g->id)>
                                {{ $g->nama }} — {{ $g->jabatan }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif

                @if ($pegawaiLain->isNotEmpty())
                    <optgroup label="Pegawai Lain">
                        @foreach ($pegawaiLain as $g)
                            <option value="{{ $g->id }}" @selected((string) old('guru_id', $jadwal->guru_id ?? '') === (string) $g->id)>
                                {{ $g->nama }} — {{ $g->jabatan }}
                            </option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
        @if ($guruPengajar->isEmpty() && $pegawaiLain->isEmpty())
            <p class="mt-1 text-xs text-brand-muted">
                Belum ada data pegawai.
                <a href="{{ route($panelPrefix . '.pegawai.create') }}" class="text-brand-accent-text hover:underline">Tambahkan dulu</a>.
            </p>
        @endif
    </div>

    <div>
        <label for="mata_pelajaran" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="document-report" class="h-3.5 w-3.5" />
            Mata Pelajaran
        </label>
        <input id="mata_pelajaran" name="mata_pelajaran" type="text"
            value="{{ old('mata_pelajaran', $jadwal->mata_pelajaran ?? '') }}" required
            placeholder="mis. Pemrograman Web"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="ruangan" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="building" class="h-3.5 w-3.5" />
            Ruangan <span class="font-normal text-brand-muted/70">(opsional)</span>
        </label>
        <input id="ruangan" name="ruangan" type="text"
            value="{{ old('ruangan', $jadwal->ruangan ?? '') }}"
            placeholder="mis. Lab RPL 1"
            class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
    </div>

    <div>
        <label for="hari" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
            <x-icon name="calendar" class="h-3.5 w-3.5" />
            Hari
        </label>
        <div class="relative">
            <select id="hari" name="hari" required
                class="w-full appearance-none rounded-lg border border-brand-border px-3 py-2 pr-9 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
                <option value="">— Pilih hari —</option>
                @foreach ($daftarHari as $h)
                    <option value="{{ $h->value }}" @selected(old('hari', $jadwal->hari->value ?? '') === $h->value)>
                        {{ $h->label() }}
                    </option>
                @endforeach
            </select>
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-brand-muted">
                <x-icon name="chevron-down" class="h-4 w-4" />
            </span>
        </div>
    </div>

    {{-- ============ PEMBANTU JAM PELAJARAN (JP) ============
         Admin cukup mengisi jam mulai + jumlah JP, jam selesainya dihitung
         otomatis dari durasi 1 JP di Pengaturan Sistem. Jam selesai tetap
         bisa diketik manual — kolom "Jumlah JP" hanya pembantu dan TIDAK
         ikut tersimpan (jumlah JP selalu dihitung ulang dari panjang jadwal,
         lihat App\Services\JamPelajaran).

         Ditulis dengan Alpine (ikut terbawa Livewire). Kalau Alpine gagal
         dimuat, form tetap bekerja seperti semula: kedua kolom jam tetap
         wajib diisi dan divalidasi server. --}}
    @php $durasiJp = app(\App\Services\JamPelajaran::class)->durasiMenit(); @endphp

    <div x-data="{
            durasi: {{ $durasiJp }},
            jp: '',
            info: '',
            menit(jam) {
                if (! jam || jam.indexOf(':') < 0) return null;
                const [j, m] = jam.split(':').map(Number);
                return j * 60 + m;
            },
            segarkan() {
                const a = this.menit(this.$refs.mulai.value);
                const b = this.menit(this.$refs.selesai.value);
                if (a === null || b === null || b <= a) { this.info = ''; return; }
                const lama = b - a;
                const jumlah = Math.max(1, Math.round(lama / this.durasi));
                this.info = lama + ' menit = ' + jumlah + ' JP'
                    + (lama % this.durasi ? ' (bukan kelipatan ' + this.durasi + ' menit)' : '');
            },
            terapkan() {
                const a = this.menit(this.$refs.mulai.value);
                const n = parseInt(this.jp, 10);
                if (a === null || ! n || n < 1) return;
                const akhir = a + n * this.durasi;
                if (akhir >= 24 * 60) return;
                this.$refs.selesai.value = String(Math.floor(akhir / 60)).padStart(2, '0') + ':' + String(akhir % 60).padStart(2, '0');
                this.segarkan();
            },
        }" x-init="segarkan()" class="space-y-3">

    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="jam_mulai" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="clock" class="h-3.5 w-3.5" />
                Jam Mulai
            </label>
            <input id="jam_mulai" name="jam_mulai" type="time" required x-ref="mulai" x-on:input="terapkan(); segarkan()" x-on:change="terapkan(); segarkan()"
                value="{{ old('jam_mulai', isset($jadwal) ? $jadwal->jam_mulai->format('H:i') : '') }}"
                class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        </div>
        <div>
            <label for="jam_selesai" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="clock" class="h-3.5 w-3.5" />
                Jam Selesai
            </label>
            <input id="jam_selesai" name="jam_selesai" type="time" required x-ref="selesai" x-on:input="jp = ''; segarkan()" x-on:change="jp = ''; segarkan()"
                value="{{ old('jam_selesai', isset($jadwal) ? $jadwal->jam_selesai->format('H:i') : '') }}"
                class="w-full rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        </div>
    </div>

    <div class="flex flex-wrap items-end gap-3">
        <div>
            <label for="jumlah_jp" class="mb-1.5 flex items-center gap-1.5 text-xs font-medium text-brand-muted">
                <x-icon name="hashtag" class="h-3.5 w-3.5" />
                Jumlah JP <span class="font-normal text-brand-muted/70">(opsional, pengisi jam selesai)</span>
            </label>
            {{-- Tanpa atribut name: nilainya sengaja tidak ikut terkirim. --}}
            <input id="jumlah_jp" type="number" min="1" max="12" step="1" inputmode="numeric"
                x-model="jp" x-on:input="terapkan()" placeholder="mis. 2"
                class="w-28 rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none focus:ring-2 focus:ring-brand-accent/15">
        </div>
        <p class="pb-2 text-xs text-brand-muted">
            1 JP = <strong>{{ $durasiJp }} menit</strong>
            <span x-show="info" x-cloak> &middot; <span class="font-semibold text-brand-accent-text" x-text="info"></span></span>
        </p>
    </div>
    </div>

    <p class="flex items-start gap-1.5 rounded-lg bg-brand-surface-muted px-3 py-2 text-xs text-brand-muted">
        <x-icon name="shield-check" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
        Sistem otomatis menolak jadwal yang bentrok — baik guru yang sama mengajar
        dua kelas sekaligus, maupun satu kelas kebagian dua pelajaran di jam yang sama.
    </p>
</div>
