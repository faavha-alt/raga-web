<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ $athlete->name }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Profil privat</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl">
            <x-card class="text-center py-12">
                @if ($athlete->avatar_path)
                    <img src="{{ asset($athlete->avatar_path) }}" alt="{{ $athlete->name }}"
                        class="mx-auto h-20 w-20 rounded-3xl object-cover">
                @else
                    <span class="mx-auto flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-raga-accent to-raga-primary text-2xl font-black text-white">
                        {{ $athlete->initials() }}
                    </span>
                @endif

                <h1 class="mt-4 text-xl font-black text-gray-900 dark:text-gray-100">{{ $athlete->name }}</h1>
                @if ($athlete->username)
                    <p class="text-sm font-semibold text-gray-400">&#64;{{ $athlete->username }}</p>
                @endif

                <p class="mx-auto mt-4 max-w-md text-sm text-gray-500 dark:text-gray-400">
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
