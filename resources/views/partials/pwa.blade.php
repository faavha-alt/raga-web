{{--
    Partial PWA: registrasi service worker dan tombol "Pasang aplikasi".

    CARA PAKAI — sertakan SEKALI di dalam <body>, setelah konten utama:

        @include('partials.pwa')

    Tag <head> PWA (manifest, theme-color, ikon) ditulis langsung di
    layouts/app.blade.php supaya hanya ada satu sumber kebenaran; partial ini
    sengaja tidak mengirim @push('head') agar tidak terjadi duplikasi.
    Tombol install dikirim lewat @push('scripts') sehingga dirender di akhir
    <body> oleh layout.
--}}

<script>
    (function () {
        window.__ragaInstallPrompt = null;

        window.addEventListener('beforeinstallprompt', function (event) {
            event.preventDefault();
            window.__ragaInstallPrompt = event;
            window.dispatchEvent(new CustomEvent('raga:installable'));
        });

        window.addEventListener('appinstalled', function () {
            window.__ragaInstallPrompt = null;
            window.dispatchEvent(new CustomEvent('raga:installed'));
        });

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').catch(function () {});
            });
        }
    })();
</script>

<script>
    document.addEventListener('alpine:init', function () {
        Alpine.data('ragaInstallPrompt', function () {
            return {
                installable: false,
                init: function () {
                    this.installable = !!window.__ragaInstallPrompt;
                    window.addEventListener('raga:installable', () => {
                        this.installable = true;
                    });
                    window.addEventListener('raga:installed', () => {
                        this.installable = false;
                    });
                },
                install: async function () {
                    const prompt = window.__ragaInstallPrompt;
                    if (!prompt) {
                        this.installable = false;
                        return;
                    }
                    prompt.prompt();
                    await prompt.userChoice;
                    window.__ragaInstallPrompt = null;
                    this.installable = false;
                },
            };
        });

        // Safari iOS tidak pernah mengirim `beforeinstallprompt`, jadi pengguna
        // iPhone/iPad tidak pernah melihat ajakan "Pasang aplikasi". Komponen
        // ini menampilkan panduan manual, dan hanya di perangkat iOS yang belum
        // terpasang. iPadOS modern melaporkan dirinya sebagai Macintosh, jadi
        // deteksinya harus lewat maxTouchPoints.
        Alpine.data('ragaIosInstallHint', function () {
            const ua = window.navigator.userAgent || '';
            const isIpadOs = /Macintosh/.test(ua) && (window.navigator.maxTouchPoints || 0) > 1;
            const isIos = /iPhone|iPad|iPod/.test(ua) || isIpadOs;
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                || window.navigator.standalone === true;
            const storageKey = 'raga:ios-install-hint-dismissed';

            return {
                visible: false,
                init: function () {
                    let dismissed = false;
                    try {
                        dismissed = window.localStorage.getItem(storageKey) === '1';
                    } catch (error) {
                        dismissed = false;
                    }
                    this.visible = isIos && !isStandalone && !dismissed;
                    window.addEventListener('raga:installed', () => {
                        this.visible = false;
                    });
                },
                dismiss: function () {
                    this.visible = false;
                    try {
                        window.localStorage.setItem(storageKey, '1');
                    } catch (error) {
                        // Mode privat Safari bisa menolak localStorage; abaikan.
                    }
                },
            };
        });
    });
</script>

@push('scripts')
    <div
        x-data="ragaIosInstallHint"
        x-show="visible"
        x-cloak
        x-transition.opacity
        class="fixed bottom-4 left-1/2 z-50 w-[calc(100%-2rem)] max-w-sm -translate-x-1/2"
    >
        <div class="rounded-lg border border-telemetry-line bg-telemetry-surface px-4 py-3 shadow-overlay">
            <div class="flex items-start gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-telemetry-well text-base">📲</span>
                <div class="min-w-0 flex-1">
                    <p class="font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-ink">
                        Pasang RAGA di iPhone atau iPad
                    </p>
                    <p class="mt-1 text-xs font-medium text-telemetry-slate">
                        Ketuk tombol Bagikan di Safari, lalu pilih Tambahkan ke Layar Utama.
                    </p>
                    <ol class="mt-2 list-decimal space-y-1 pl-4 text-xs text-telemetry-slate">
                        <li>Buka halaman ini di <span class="font-semibold text-telemetry-ink">Safari</span> — bukan browser di dalam WhatsApp atau Instagram.</li>
                        <li>Ketuk tombol <span class="font-semibold text-telemetry-ink">Bagikan</span> (kotak dengan panah ke atas) di bagian bawah layar.</li>
                        <li>Pilih <span class="font-semibold text-telemetry-ink">Tambahkan ke Layar Utama</span>, lalu ketuk <span class="font-semibold text-telemetry-ink">Tambah</span>.</li>
                    </ol>
                </div>
                <button
                    type="button"
                    @click="dismiss()"
                    aria-label="Tutup panduan pasang"
                    class="shrink-0 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate transition-colors hover:text-telemetry-ink"
                >
                    Tutup
                </button>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <div
        x-data="ragaInstallPrompt"
        x-show="installable"
        x-cloak
        x-transition.opacity
        class="fixed bottom-4 left-1/2 z-50 w-[calc(100%-2rem)] max-w-sm -translate-x-1/2"
    >
        <div class="flex items-center gap-3 rounded-lg border border-telemetry-line bg-white px-4 py-3 shadow-overlay">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-telemetry-ink text-base">📲</span>
            <p class="min-w-0 flex-1 text-xs font-medium text-telemetry-slate">
                Pasang RAGA di layar utama untuk akses lebih cepat.
            </p>
            <button type="button" @click="install()" class="shrink-0 rounded bg-telemetry-ember px-3 py-1.5 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-white transition-colors hover:bg-telemetry-ember-dark">
                Pasang aplikasi
            </button>
            <button type="button" @click="installable = false" class="shrink-0 font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate transition-colors hover:text-telemetry-ink">
                Nanti
            </button>
        </div>
    </div>
@endpush
