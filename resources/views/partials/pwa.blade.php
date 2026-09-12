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
    });
</script>

@push('scripts')
    <div
        x-data="ragaInstallPrompt"
        x-show="installable"
        x-cloak
        x-transition.opacity
        class="fixed bottom-4 left-1/2 z-50 w-[calc(100%-2rem)] max-w-sm -translate-x-1/2"
    >
        <div class="flex items-center gap-3 rounded-2xl border border-gray-100 bg-white px-4 py-3 shadow-glow dark:border-gray-800 dark:bg-gray-900">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-raga-accent to-raga-primary text-base">📲</span>
            <p class="min-w-0 flex-1 text-xs font-semibold text-gray-700 dark:text-gray-200">
                Pasang RAGA di layar utama untuk akses lebih cepat.
            </p>
            <button type="button" @click="install()" class="shrink-0 rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-3 py-1.5 text-xs font-bold text-white">
                Pasang aplikasi
            </button>
            <button type="button" @click="installable = false" class="shrink-0 text-xs font-semibold text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                Nanti
            </button>
        </div>
    </div>
@endpush
