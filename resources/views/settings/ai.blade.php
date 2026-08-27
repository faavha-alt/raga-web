<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('AI Coach') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl space-y-4" x-data="{ provider: '{{ old('provider', $setting?->provider ?? 'anthropic') }}' }">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            <x-card>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    AI Coach memakai API key milik kamu sendiri — RAGA tidak menyediakan key bersama.
                    Key disimpan terenkripsi dan tidak pernah ditampilkan ulang setelah disimpan.
                </p>

                <form method="POST" action="{{ route('settings.ai.update') }}" class="space-y-4">
                    @csrf

                    <div>
                        <x-input-label for="provider" value="Provider" />
                        <select id="provider" name="provider" x-model="provider" required
                            class="mt-1 block w-full rounded-xl border-gray-300 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 focus:border-raga-primary focus:ring-raga-primary">
                            @foreach ($providers as $key => $meta)
                                <option value="{{ $key }}" @selected(old('provider', $setting?->provider) === $key)>
                                    {{ $meta['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('provider')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="mode" value="Mode Analisis" />
                        <div class="mt-2 grid grid-cols-2 gap-3" x-data="{ mode: '{{ old('mode', $setting?->mode ?? 'standard') }}' }">
                            @foreach ($modes as $key => $meta)
                                <label
                                    class="relative cursor-pointer rounded-2xl border p-4 transition-colors select-none
                                    {{ $key === 'pro' ? 'border-raga-primary/40 bg-raga-primary/5' : '' }}"
                                    :class="mode === '{{ $key }}' ? 'border-raga-primary ring-1 ring-raga-primary' : 'border-gray-200 dark:border-gray-700'">
                                    <input type="radio" name="mode" value="{{ $key }}" x-model="mode" class="sr-only" />
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">{{ $meta['label'] }}</span>
                                        @if ($key === 'pro')
                                            <span class="rounded-full bg-raga-primary/15 px-2 py-0.5 text-xs font-bold text-raga-primary">Lebih dalam</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        @if ($key === 'pro')
                                            Model lebih mumpuni, analisis lintas-metrik & tren yang lebih dalam, jawaban lebih panjang.
                                            Lebih lambat & lebih boros kuota.
                                        @else
                                            Cepat & hemat kuota, cocok untuk obrolan ringan sehari-hari.
                                        @endif
                                    </p>
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('mode')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="api_key" :value="$setting ? 'API Key (biarkan kosong jika tidak diganti)' : 'API Key'" />
                        <x-text-input id="api_key" type="password" name="api_key" autocomplete="off"
                            :placeholder="$setting ? '•••••••••••• (sudah diatur)' : 'sk-ant-... atau AIza...'"
                            :required="! $setting" />
                        <x-input-error :messages="$errors->get('api_key')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="model" value="Model (opsional)" />
                        <x-text-input id="model" type="text" name="model" :value="old('model', $setting?->model)"
                            placeholder="Kosongkan untuk pakai default provider" />
                        <p class="mt-1.5 text-xs text-gray-400">
                            Kosongkan untuk pakai default per mode. Default Standard: Claude → <code>claude-opus-5</code>,
                            Gemini → <code>gemini-2.5-flash</code>. Default Pro: Claude → <code>claude-opus-5</code>,
                            Gemini → <code>gemini-2.5-pro</code>.
                        </p>
                        <x-input-error :messages="$errors->get('model')" class="mt-2" />
                    </div>

                    <div class="flex gap-3 pt-2">
                        <x-primary-button>Simpan</x-primary-button>

                        @if ($setting)
                            <button type="button"
                                onclick="if (confirm('Hapus API key AI Coach? Chat dengan AI akan nonaktif sampai kamu isi lagi.')) document.getElementById('delete-ai-key-form').submit();"
                                class="text-sm font-semibold text-raga-low hover:underline">
                                Hapus API Key
                            </button>
                        @endif
                    </div>
                </form>

                @if ($setting)
                    <form id="delete-ai-key-form" method="POST" action="{{ route('settings.ai.destroy') }}" class="hidden">
                        @csrf
                        @method('DELETE')
                    </form>
                @endif
            </x-card>

            {{-- How to get an API key --}}
            <x-card x-show="provider === 'anthropic'" x-cloak>
                <p class="font-bold text-gray-900 dark:text-gray-100 mb-3">Cara dapat API key Claude (Anthropic)</p>
                <ol class="space-y-2 text-sm text-gray-600 dark:text-gray-300 list-decimal list-inside">
                    <li>Buka <a href="https://console.anthropic.com" target="_blank" rel="noopener" class="text-raga-primary font-semibold hover:underline">console.anthropic.com</a> dan login/daftar.</li>
                    <li>Di sidebar kiri, buka menu <strong>API Keys</strong>.</li>
                    <li>Klik <strong>Create Key</strong>, beri nama bebas (mis. "RAGA"), lalu <strong>Create</strong>.</li>
                    <li>Salin key yang muncul (diawali <code>sk-ant-...</code>) — hanya ditampilkan sekali, tidak bisa dilihat lagi setelah itu.</li>
                    <li>Tempel di kolom <strong>API Key</strong> di atas, lalu Simpan.</li>
                </ol>
                <p class="mt-3 text-xs text-gray-400">
                    Catatan: API key Anthropic ini berbayar per pemakaian (bukan langganan Claude.ai) — biasanya perlu isi metode pembayaran dulu di Console sebelum key bisa dipakai.
                </p>
            </x-card>

            <x-card x-show="provider === 'gemini'" x-cloak>
                <p class="font-bold text-gray-900 dark:text-gray-100 mb-3">Cara dapat API key Gemini (Google)</p>
                <ol class="space-y-2 text-sm text-gray-600 dark:text-gray-300 list-decimal list-inside">
                    <li>Buka <a href="https://aistudio.google.com/apikey" target="_blank" rel="noopener" class="text-raga-primary font-semibold hover:underline">aistudio.google.com/apikey</a> dan login pakai akun Google.</li>
                    <li>Klik <strong>Create API key</strong>.</li>
                    <li>Pilih project Google Cloud yang sudah ada, atau biarkan Google buatkan project baru otomatis.</li>
                    <li>Salin key yang muncul (diawali <code>AIza...</code>).</li>
                    <li>Tempel di kolom <strong>API Key</strong> di atas, lalu Simpan.</li>
                </ol>
                <p class="mt-3 text-xs text-gray-400">
                    Catatan: Google AI Studio menyediakan kuota gratis harian untuk sebagian model tanpa perlu kartu kredit — cocok untuk mulai coba-coba.
                </p>
            </x-card>
        </div>
    </div>
</x-app-layout>
