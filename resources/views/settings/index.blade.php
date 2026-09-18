<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ __('Settings') }}</h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">Koneksi data, kunci API, dan preferensi akunmu.</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8">
            <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                <a href="{{ route('profile.edit') }}" class="block px-6 py-4 text-sm font-medium text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    Account
                </a>
                <a href="{{ route('settings.garmin.show') }}" class="flex items-center justify-between px-6 py-4 text-sm font-medium text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    <span>⌚ Garmin Connect</span>
                    @if (auth()->user()->garminConnection?->connected_at)
                        <x-chip variant="recovery">Connected</x-chip>
                    @else
                        <x-chip variant="neutral">Not connected</x-chip>
                    @endif
                </a>
                <a href="{{ route('settings.suunto.show') }}" class="flex items-center justify-between px-6 py-4 text-sm font-medium text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    <span>⌚ Suunto</span>
                    @if (auth()->user()->suuntoConnection?->connected_at)
                        <x-chip variant="recovery">Connected</x-chip>
                    @else
                        <x-chip variant="neutral">Not connected</x-chip>
                    @endif
                </a>
                <a href="{{ route('settings.ai.show') }}" class="flex items-center justify-between px-6 py-4 text-sm font-medium text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    <span>🤖 AI Coach</span>
                    @if (auth()->user()->aiSetting?->api_key)
                        <x-chip variant="recovery">Configured</x-chip>
                    @else
                        <x-chip variant="neutral">Not configured</x-chip>
                    @endif
                </a>
                <a href="{{ route('settings.api-tokens.show') }}" class="flex items-center justify-between px-6 py-4 text-sm font-medium text-telemetry-ink transition-colors hover:bg-telemetry-well">
                    <span>🔑 API Tokens</span>
                    <span class="telemetry-label">{{ auth()->user()->tokens()->count() }} aktif</span>
                </a>
                @foreach ($rows as $row)
                    <div class="px-6 py-4 text-sm text-telemetry-slate">{{ $row }}</div>
                @endforeach
            </x-card>
        </div>
    </div>
</x-app-layout>
