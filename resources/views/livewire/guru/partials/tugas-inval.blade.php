{{--
    Kotak TUGAS untuk guru inval (dan penunjuknya).

    Variabel:
      $tugas      hasil App\Services\TugasInval::ringkas()
      $namaGuru   nama guru yang digantikan
      $prefix     awalan rute panel (untuk tautan lampiran)
      $ringkas    true = tampilan rapat (daftar tugas mendatang / header penunjuk)

    Lampiran dibuka lewat rute guru-inval.lampiran — hak aksesnya diperiksa
    ulang di server, jadi tautan ini aman walau terlihat di HTML.
--}}
@php
    $ringkas = $ringkas ?? false;
    $idt = $tugas['jenis'] === \App\Enums\JenisIzinGuru::Idt;
    $itt = $tugas['jenis'] === \App\Enums\JenisIzinGuru::Itt;
@endphp

<div class="{{ $ringkas ? 'mt-2' : 'mt-3' }} space-y-2">
    @if ($tugas['detail'] || $tugas['lampiran_id'])
        <div class="rounded-lg border border-sky-200 bg-sky-50 px-3.5 py-3 dark:border-sky-500/30 dark:bg-sky-500/10">
            {{-- Tanpa uppercase: gelar seperti "S.Pd." akan terbaca "S.PD." --}}
            <p class="flex items-center gap-1.5 text-xs font-semibold text-sky-700 dark:text-sky-300">
                <x-icon name="clipboard-check" class="h-4 w-4 shrink-0" />
                Tugas dari {{ $namaGuru ?: 'guru' }}{{ $idt ? ' (IDT)' : '' }}
            </p>

            @if ($tugas['detail'])
                {{-- whitespace-pre-line: baris baru yang ditulis guru tetap
                     terbaca sebagai daftar langkah. --}}
                <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-brand-ink [overflow-wrap:anywhere] dark:text-gray-100 {{ $ringkas ? 'line-clamp-3' : '' }}">{{ $tugas['detail'] }}</p>
            @endif

            @if ($tugas['lampiran_id'])
                <a href="{{ route($prefix . '.guru-inval.lampiran', $tugas['lampiran_id']) }}" target="_blank" rel="noopener"
                    class="mt-2.5 inline-flex min-h-10 items-center gap-1.5 rounded-lg border border-sky-300 bg-white px-3.5 py-2 text-xs font-semibold text-sky-700 transition hover:bg-sky-100 dark:border-sky-500/40 dark:bg-transparent dark:text-sky-300 dark:hover:bg-sky-500/10 sm:min-h-0 sm:py-1.5">
                    <x-icon name="paperclip" class="h-4 w-4" />
                    Buka lampiran tugas
                </a>
            @endif
        </div>
    @elseif ($itt && ! $ringkas)
        <p class="rounded-lg border border-dashed border-gray-300 px-3.5 py-2.5 text-xs leading-relaxed text-brand-muted dark:border-gray-700 dark:text-brand-faint">
            <span class="font-semibold">Tanpa tugas (ITT).</span> Isi absensi, lalu beri kegiatan mandiri — misalnya membaca materi bab berikutnya atau mengerjakan latihan di buku paket.
        </p>
    @elseif (! $ringkas && ! $tugas['catatan'])
        <p class="rounded-lg border border-dashed border-gray-300 px-3.5 py-2.5 text-xs leading-relaxed text-brand-muted dark:border-gray-700 dark:text-brand-faint">
            Guru tidak meninggalkan tugas tertulis. Isi absensi dan beri kegiatan mandiri.
        </p>
    @endif

    @if ($tugas['catatan'])
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3.5 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Catatan dari Kepala Sekolah / Admin</p>
            <p class="mt-1.5 whitespace-pre-line text-sm leading-relaxed text-brand-ink [overflow-wrap:anywhere] dark:text-gray-100 {{ $ringkas ? 'line-clamp-3' : '' }}">{{ $tugas['catatan'] }}</p>
        </div>
    @endif
</div>
