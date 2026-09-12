{{--
    Tombol masuk/daftar lewat Google.

    Disembunyikan selama GOOGLE_CLIENT_ID belum diisi supaya tidak ada tombol yang
    menabrak halaman error Google di lingkungan yang belum dikonfigurasi. Pemisah
    "atau" ikut di dalam komponen agar tidak menggantung saat tombolnya tersembunyi.
--}}
@if (config('services.google.client_id'))
    <div class="space-y-5">
        <a href="{{ route('auth.google.redirect') }}"
           class="inline-flex w-full items-center justify-center gap-3 rounded-full border-2 border-gray-200 bg-white px-6 py-2.5 text-sm font-bold text-gray-700 transition duration-150 ease-in-out hover:bg-gray-50 hover:shadow-md active:scale-[0.98] focus:outline-none focus:ring-2 focus:ring-raga-primary focus:ring-offset-2">
            <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M23.52 12.27c0-.85-.08-1.67-.22-2.45H12v4.64h6.46a5.52 5.52 0 0 1-2.4 3.62v3.01h3.88c2.27-2.09 3.58-5.17 3.58-8.82Z"/>
                <path fill="#34A853" d="M12 24c3.24 0 5.96-1.08 7.94-2.91l-3.88-3.01c-1.08.72-2.45 1.15-4.06 1.15-3.12 0-5.77-2.11-6.71-4.95H1.28v3.11A12 12 0 0 0 12 24Z"/>
                <path fill="#FBBC05" d="M5.29 14.28a7.21 7.21 0 0 1 0-4.56V6.61H1.28a12 12 0 0 0 0 10.78l4.01-3.11Z"/>
                <path fill="#EA4335" d="M12 4.75c1.76 0 3.34.61 4.59 1.8l3.44-3.44C17.95 1.18 15.24 0 12 0A12 12 0 0 0 1.28 6.61l4.01 3.11C6.23 6.86 8.88 4.75 12 4.75Z"/>
            </svg>
            {{ $label ?? 'Lanjutkan dengan Google' }}
        </a>

        @error('google')
            <p class="text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror

        <div class="relative">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-200"></div>
            </div>
            <div class="relative flex justify-center">
                <span class="bg-white px-3 text-xs font-semibold uppercase tracking-wide text-gray-400">atau</span>
            </div>
        </div>
    </div>
@endif
