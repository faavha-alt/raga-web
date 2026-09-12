<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('API Tokens') }}</h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Token statis untuk mengakses RAGA dari klien eksternal.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-xl space-y-4">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('plain_text_token'))
                <x-card class="border-2 border-telemetry-ember">
                    <p class="font-display text-sm font-semibold text-telemetry-ink">Token baru kamu</p>
                    <p class="mt-1 text-xs text-telemetry-slate">
                        Salin sekarang — token ini tidak akan ditampilkan lagi.
                    </p>
                    <code class="mt-3 block break-all rounded bg-telemetry-well px-3 py-2.5 font-display text-xs text-telemetry-ink select-all">{{ session('plain_text_token') }}</code>
                </x-card>
            @endif

            <x-card>
                <p class="text-sm text-telemetry-slate mb-5">
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
                    <div class="flex items-center justify-between px-6 py-4 {{ ! $loop->last ? 'border-b border-telemetry-line' : '' }}">
                        <div>
                            <p class="text-sm font-medium text-telemetry-ink">{{ $token->name }}</p>
                            <p class="text-xs text-telemetry-slate">
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
                            <button type="submit" class="font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-ember-deep hover:underline">Cabut</button>
                        </form>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-telemetry-slate">Belum ada token.</p>
                @endforelse
            </x-card>

        </div>
    </div>
</x-app-layout>
