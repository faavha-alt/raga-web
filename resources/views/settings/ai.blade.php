<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('AI Coach') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl space-y-4">

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
                        <select id="provider" name="provider" required
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
                            Default: Claude → <code>claude-opus-5</code>, Gemini → <code>gemini-3.7-flash</code>.
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
        </div>
    </div>
</x-app-layout>
