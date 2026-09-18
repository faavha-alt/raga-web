<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">
            {{ $athlete->name }}
        </h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">Profil privat</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl">
            <x-card class="text-center py-12">
                @if ($athlete->avatar_path)
                    <img src="{{ asset($athlete->avatar_path) }}" alt="{{ $athlete->name }}"
                        class="mx-auto h-20 w-20 rounded-full object-cover">
                @else
                    <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border border-telemetry-line bg-telemetry-well text-2xl font-bold text-telemetry-slate">
                        {{ $athlete->initials() }}
                    </span>
                @endif

                <h1 class="mt-4 telemetry-value text-xl">{{ $athlete->name }}</h1>
                @if ($athlete->username)
                    <p class="text-sm font-semibold text-telemetry-slate">&#64;{{ $athlete->username }}</p>
                @endif

                <p class="mx-auto mt-4 max-w-md text-sm text-telemetry-slate">
                    🔒 Profil ini privat. Ikuti atlet ini untuk melihat aktivitasnya.
                </p>

                @if ($viewer->id !== $athlete->id)
                    <form method="POST" action="{{ route('follows.store', $athlete) }}" class="mt-6">
                        @csrf
                        <x-primary-button>Ikuti</x-primary-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
