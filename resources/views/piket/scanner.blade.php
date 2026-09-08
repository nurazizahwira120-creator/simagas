<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1">
    <meta name="theme-color" content="#1f4d38">
    <title>Scanner Absensi — SIMAGAS</title>

    @include('partials.head-assets')

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
</head>
<body class="min-h-screen bg-brand-bg font-sans text-brand-ink antialiased">

    <header class="sticky top-0 z-10 flex items-center justify-between border-b border-brand-border bg-brand-surface/90 px-4 py-3 backdrop-blur">
        <div class="flex min-w-0 items-center gap-2.5">
            <img src="{{ asset('logo-mark.png') }}" alt="" class="h-9 w-9 shrink-0 object-contain">
            <div class="min-w-0">
                <p class="truncate text-sm font-bold leading-tight">SIMAGAS — Piket</p>
                <p class="truncate text-xs text-brand-muted">Sistem Absensi Digital</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="hidden items-center gap-1 rounded-full bg-brand-accent-soft px-2.5 py-1 text-[11px] font-semibold text-brand-accent-dark sm:inline-flex">
                <x-icon name="shield-check" class="h-3 w-3" />
                {{ $user->role->label() }}
            </span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex h-8 w-8 items-center justify-center rounded-lg border border-brand-border text-brand-muted active:border-brand-danger active:text-brand-danger">
                    <x-icon name="logout" class="h-4 w-4" />
                </button>
            </form>
        </div>
    </header>

    <main class="mx-auto flex w-full max-w-md flex-col gap-4 p-4 pb-10">

        <div class="flex items-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-xs text-brand-muted shadow-soft">
            <x-icon name="swap" class="h-4 w-4 shrink-0 text-brand-accent" />
            Scanner ini otomatis mengenali NIS siswa maupun NIP pegawai — tidak perlu pilih mode.
        </div>

        <div class="overflow-hidden rounded-2xl border border-brand-border bg-black shadow-soft">
            <div id="reader" class="w-full"></div>
        </div>

        <div class="flex gap-2">
            <button id="start-btn" type="button"
                class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-brand-accent px-4 py-3 text-sm font-semibold text-white shadow-soft active:bg-brand-accent-dark">
                <x-icon name="camera" class="h-5 w-5" />
                Nyalakan Kamera
            </button>
            <button id="stop-btn" type="button"
                class="hidden flex-1 items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-sm font-semibold text-brand-ink active:bg-brand-surface-muted">
                <x-icon name="x-circle" class="h-5 w-5" />
                Matikan Kamera
            </button>
        </div>

        <div id="scan-feedback"
            class="flex min-h-[3.25rem] items-center gap-2.5 rounded-xl border border-brand-border bg-brand-surface px-4 py-3 text-sm font-medium text-brand-muted transition-colors">
            <span id="scan-feedback-icon" class="shrink-0"><x-icon name="qr-code" class="h-5 w-5" /></span>
            <span id="scan-feedback-text">Tekan &ldquo;Nyalakan Kamera&rdquo;, lalu arahkan ke QR/barcode NIS atau NIP.</span>
        </div>

        <details class="rounded-xl border border-brand-border bg-brand-surface open:pb-3">
            <summary class="flex cursor-pointer select-none items-center gap-2 px-4 py-3 text-sm font-medium text-brand-muted">
                <x-icon name="keyboard" class="h-4 w-4" />
                Input manual (kalau kamera/QR tidak bisa dipakai)
            </summary>
            <form id="manual-form" class="flex gap-2 px-4">
                <input id="manual-kode" type="text" inputmode="numeric" placeholder="Ketik NIS atau NIP…"
                    class="flex-1 rounded-lg border border-brand-border px-3 py-2 text-sm focus:border-brand-accent focus:outline-none">
                <button type="submit"
                    class="rounded-lg bg-brand-ink px-4 py-2 text-sm font-medium text-white active:opacity-80">
                    Kirim
                </button>
            </form>
        </details>

        <section>
            <h2 class="mb-2 flex items-center gap-1.5 px-1 text-xs font-semibold uppercase tracking-wide text-brand-muted">
                <x-icon name="clock" class="h-3.5 w-3.5" />
                Riwayat scan sesi ini
            </h2>
            <ul id="scan-log" class="divide-y divide-brand-border/60 rounded-xl border border-brand-border bg-brand-surface px-4 empty:hidden">
            </ul>
            <p id="scan-log-empty" class="rounded-xl border border-dashed border-brand-border px-4 py-6 text-center text-sm text-brand-muted">
                Belum ada yang di-scan.
            </p>
        </section>

    </main>

    <script>
        (function () {
            // Satu endpoint AJAX untuk SEMUA scan — backend (ScannerController::store())
            // yang otomatis mengenali apakah kode-nya NIS siswa atau NIP
            // pegawai, jadi frontend tidak perlu tahu/memilih jenisnya.
            const scanUrl = @json(route('piket.scan'));

            const LABEL_ENTITAS = {
                siswa: 'Siswa',
                pegawai: 'Pegawai',
            };

            const csrfToken = @json(csrf_token());

            const feedbackEl = document.getElementById('scan-feedback');
            const feedbackIconEl = document.getElementById('scan-feedback-icon');
            const feedbackTextEl = document.getElementById('scan-feedback-text');
            const logEl = document.getElementById('scan-log');
            const logEmptyEl = document.getElementById('scan-log-empty');
            const startBtn = document.getElementById('start-btn');
            const stopBtn = document.getElementById('stop-btn');
            const manualForm = document.getElementById('manual-form');
            const manualInput = document.getElementById('manual-kode');

            let html5QrCode = null;
            let isProcessing = false;
            let lastCode = null;
            let lastCodeAt = 0;

            const ICONS = {
                idle: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><rect x="3.5" y="3.5" width="6.5" height="6.5" rx="1" /><rect x="14" y="3.5" width="6.5" height="6.5" rx="1" /><rect x="3.5" y="14" width="6.5" height="6.5" rx="1" /><path d="M14 14h3v3h-3z" /><path d="M20.5 14v3.2" /><path d="M14 20.5h3.2" /><path d="M20.5 20.5h.01" /></svg>',
                busy: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5 animate-spin"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3.5 2" /></svg>',
                ok: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="9" /><path d="M8.3 12.3l2.5 2.5 4.9-5.4" /></svg>',
                warn: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><path d="M12 3.5 21.5 20h-19L12 3.5Z" /><path d="M12 10v4.2" /><path d="M12 17.2h.01" /></svg>',
                error: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" class="h-5 w-5"><circle cx="12" cy="12" r="9" /><path d="M9.5 9.5l5 5" /><path d="M14.5 9.5l-5 5" /></svg>',
            };

            const FEEDBACK_STYLES = {
                idle: 'border-brand-border bg-brand-surface text-brand-muted',
                busy: 'border-brand-border bg-brand-surface-muted text-brand-ink',
                ok: 'border-emerald-300 bg-emerald-50 text-emerald-800',
                warn: 'border-amber-300 bg-amber-50 text-amber-800',
                error: 'border-red-300 bg-red-50 text-red-800',
            };

            const LOG_BADGE = {
                ok: 'bg-emerald-100 text-emerald-700',
                warn: 'bg-amber-100 text-amber-700',
                error: 'bg-red-100 text-red-700',
            };

            function setFeedback(kind, message) {
                feedbackEl.className = 'flex min-h-[3.25rem] items-center gap-2.5 rounded-xl border px-4 py-3 text-sm font-medium transition-colors ' + FEEDBACK_STYLES[kind];
                feedbackIconEl.innerHTML = ICONS[kind] || ICONS.idle;
                feedbackTextEl.textContent = message;
            }

            function addLogEntry(kind, title, subtitle) {
                logEmptyEl.classList.add('hidden');

                const item = document.createElement('li');
                item.className = 'flex items-center gap-3 py-2.5';
                item.innerHTML = `
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg ${LOG_BADGE[kind] || 'bg-slate-100 text-slate-500'}">${ICONS[kind] ? ICONS[kind].replace('h-5 w-5', 'h-4 w-4') : ''}</span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-brand-ink"></p>
                        <p class="truncate text-xs text-brand-muted"></p>
                    </div>
                `;
                item.querySelector('p.text-sm').textContent = title;
                item.querySelector('p.text-xs').textContent = subtitle;
                logEl.prepend(item);

                while (logEl.children.length > 8) {
                    logEl.removeChild(logEl.lastChild);
                }
            }

            function beep() {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    const ctx = new AudioCtx();
                    const oscillator = ctx.createOscillator();
                    const gain = ctx.createGain();
                    oscillator.connect(gain);
                    gain.connect(ctx.destination);
                    oscillator.type = 'sine';
                    oscillator.frequency.value = 880;
                    gain.gain.setValueAtTime(0.15, ctx.currentTime);
                    oscillator.start();
                    oscillator.stop(ctx.currentTime + 0.15);
                    oscillator.onended = () => ctx.close();
                } catch (error) {
                    // Web Audio tidak tersedia di device ini — abaikan, tidak fatal.
                }
            }

            async function submitKode(kode) {
                if (isProcessing) {
                    return;
                }

                const now = Date.now();
                if (kode === lastCode && (now - lastCodeAt) < 4000) {
                    // QR/kartu yang sama masih ada di depan kamera — jangan kirim dobel.
                    return;
                }

                isProcessing = true;
                lastCode = kode;
                lastCodeAt = now;

                beep();
                setFeedback('busy', `Memproses kode ${kode}…`);

                try {
                    const response = await fetch(scanUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ kode: kode }),
                    });

                    const result = await response.json();
                    const labelEntitas = LABEL_ENTITAS[result.entitas] ?? null;
                    const subJamMasuk = result.data?.jam_masuk ? ` • ${result.data.jam_masuk}` : '';
                    const subEntitas = labelEntitas ? `${labelEntitas}${result.data?.keterangan ? ' • ' + result.data.keterangan : ''}` : null;

                    if (response.ok && result.success) {
                        setFeedback('ok', result.message);
                        addLogEntry('ok', result.data?.nama ?? kode, `${subEntitas ?? 'Hadir'}${subJamMasuk}`);
                    } else if (response.status === 409) {
                        setFeedback('warn', result.message);
                        addLogEntry('warn', result.data?.nama ?? kode, result.message);
                    } else {
                        setFeedback('error', result.message ?? 'Gagal memproses scan.');
                        addLogEntry('error', kode, result.message ?? 'Gagal diproses');
                    }
                } catch (error) {
                    setFeedback('error', 'Tidak bisa menghubungi server. Periksa koneksi lalu coba lagi.');
                    addLogEntry('error', kode, 'Koneksi gagal');
                } finally {
                    setTimeout(() => { isProcessing = false; }, 1200);
                }
            }

            function onScanSuccess(decodedText) {
                submitKode(String(decodedText).trim());
            }

            async function startScanner() {
                if (html5QrCode) {
                    return;
                }

                if (typeof Html5Qrcode === 'undefined') {
                    setFeedback('error', 'Library scanner gagal dimuat. Periksa koneksi internet lalu muat ulang halaman.');
                    return;
                }

                html5QrCode = new Html5Qrcode('reader');

                try {
                    await html5QrCode.start(
                        { facingMode: 'environment' },
                        { fps: 10, qrbox: { width: 240, height: 240 } },
                        onScanSuccess,
                        () => { /* tidak ada QR di frame saat ini — normal, diabaikan */ }
                    );
                    startBtn.classList.add('hidden');
                    stopBtn.classList.remove('hidden');
                    stopBtn.classList.add('flex');
                    setFeedback('idle', 'Kamera aktif — arahkan ke QR/barcode NIS atau NIP.');
                } catch (error) {
                    html5QrCode = null;
                    setFeedback('error', 'Tidak bisa mengakses kamera. Pastikan izin kamera diberikan untuk halaman ini, lalu coba lagi.');
                }
            }

            async function stopScanner() {
                if (!html5QrCode) {
                    return;
                }

                try {
                    await html5QrCode.stop();
                    html5QrCode.clear();
                } catch (error) {
                    // Kamera sudah berhenti / belum sempat menyala sepenuhnya — abaikan.
                }

                html5QrCode = null;
                startBtn.classList.remove('hidden');
                stopBtn.classList.add('hidden');
                stopBtn.classList.remove('flex');
                setFeedback('idle', 'Kamera dimatikan.');
            }

            startBtn.addEventListener('click', startScanner);
            stopBtn.addEventListener('click', stopScanner);

            manualForm.addEventListener('submit', function (event) {
                event.preventDefault();
                const kode = manualInput.value.trim();
                if (!kode) {
                    return;
                }
                manualInput.value = '';
                submitKode(kode);
            });

            // Nyalakan kamera otomatis begitu halaman dibuka — kalau browser
            // menolak (izin belum diberikan / autoplay diblokir), tombol
            // "Nyalakan Kamera" tetap tampil untuk dicoba manual.
            document.addEventListener('DOMContentLoaded', startScanner);
        })();
    </script>
</body>
</html>
