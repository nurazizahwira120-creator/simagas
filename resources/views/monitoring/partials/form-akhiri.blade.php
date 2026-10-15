{{--
    Tombol + form "Bantu Akhiri Sesi" (Kepsek & Super Admin).

    Variabel: $jenis ('kbm'|'ekskul'), $idSesi, $nama (untuk kalimat konfirmasi).
    relative z-20: kartu KBM ditutup tombol tembus pandang z-10 (pembuka
    panel detail) — form ini harus berada di atasnya supaya bisa diklik.
    Konfirmasi memakai SweetAlert global (data-konfirmasi pada <form>).
--}}
<div x-data="{ buka: false }" class="relative z-20 mt-4">
    <button type="button" x-show="! buka" x-on:click="buka = true; $nextTick(() => $refs.alasan.focus())"
        class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-orange-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-orange-400">
        <x-icon name="check-circle" class="h-4 w-4" />
        Bantu Akhiri Sesi
    </button>

    <form x-show="buka" style="display: none" method="POST"
        action="{{ route($panelPrefix . '.live-monitoring.akhiri', ['jenis' => $jenis, 'id' => $idSesi]) }}"
        data-konfirmasi="Apakah Anda yakin ingin mengakhiri sesi {{ $nama }} secara manual? Waktu selesai dicatat sesuai jam selesai jadwal, honor JP dihitung, dan guru/pembina akan diberi tahu."
        data-konfirmasi-judul="Akhiri sesi ini?"
        data-konfirmasi-ya="Ya, Akhiri Sesi"
        class="rounded-lg border border-orange-300 bg-orange-500/5 p-3">
        @csrf
        <label class="block text-xs font-semibold text-orange-800 dark:text-orange-300">
            Alasan (wajib)
            <input x-ref="alasan" type="text" name="alasan" required minlength="5" maxlength="255"
                placeholder="Contoh: guru lupa menekan Akhiri Sesi, sudah dikonfirmasi"
                class="mt-1 w-full rounded-md border border-orange-300 bg-white px-3 py-2 text-sm font-normal text-brand-ink focus:border-orange-500 focus:outline-none dark:bg-gray-900 dark:text-white">
        </label>
        <div class="mt-2 flex gap-2">
            <button type="submit" class="flex-1 rounded-md bg-orange-500 px-3 py-2 text-sm font-semibold text-white hover:bg-orange-600">Akhiri Sekarang</button>
            <button type="button" x-on:click="buka = false" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-brand-muted hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/[0.05]">Batal</button>
        </div>
    </form>
</div>
