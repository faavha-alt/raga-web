<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('API Tokens') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl space-y-4">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('plain_text_token'))
                <x-card class="border-2 border-raga-primary">
                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">Token baru kamu</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Salin sekarang — token ini tidak akan ditampilkan lagi.
                    </p>
                    <code class="mt-3 block break-all rounded-xl bg-gray-100 dark:bg-gray-800 px-3 py-2.5 text-xs text-gray-800 dark:text-gray-100 select-all">{{ session('plain_text_token') }}</code>
                </x-card>
            @endif

            <x-card>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-5">
                    Token statis untuk mengakses RAGA tanpa alur login OAuth. Pakai sebagai
                    <code>Authorization: Bearer &lt;token&gt;</code> untuk REST API (<code>/api/*</code>)
                    maupun endpoint MCP jarak jauh (<code>POST {{ url('/mcp') }}</code>).
                    Perlakukan seperti password.
                </p>

                <form method="POST" action="{{ route('settings.api-tokens.store') }}" class="flex items-end gap-3">
                    @csrf
                    <div class="flex-1">
                        <x-input-label for="name" value="Nama token" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                            placeholder="mis. claude-desktop, laptop-cli" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <x-primary-button>Buat</x-primary-button>
                </form>
            </x-card>

            <x-card class="!p-0 overflow-hidden">
                @forelse ($tokens as $token)
                    <div class="flex items-center justify-between px-6 py-4 {{ ! $loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                        <div>
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $token->name }}</p>
                            <p class="text-xs text-gray-400">
                                Dibuat {{ $token->created_at->diffForHumans() }} ·
                                @if ($token->last_used_at)
                                    terakhir dipakai {{ $token->last_used_at->diffForHumans() }}
                                @else
                                    belum pernah dipakai
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('settings.api-tokens.destroy', $token->id) }}"
                            onsubmit="return confirm('Cabut token &quot;{{ $token->name }}&quot;? Klien yang memakainya akan langsung kehilangan akses.')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-semibold text-raga-low hover:underline">Cabut</button>
                        </form>
                    </div>
                @empty
                    <p class="px-6 py-4 text-sm text-gray-400">Belum ada token.</p>
                @endforelse
            </x-card>

        </div>
    </div>
</x-app-layout>
