<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('athletes.show', $athlete) }}" class="text-sm font-bold text-gray-400 hover:text-gray-600 transition">← {{ $athlete->name }}</a>
        <h2 class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">{{ $title }}</h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl space-y-4">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                @forelse ($people as $person)
                    <div class="flex items-center gap-3 px-5 py-4">
                        @if ($person->avatar_path)
                            <img src="{{ asset($person->avatar_path) }}" alt="{{ $person->name }}"
                                class="h-11 w-11 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-sm font-black text-white">
                                {{ $person->initials() }}
                            </span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a href="{{ $person->username ? route('athletes.show', $person) : '#' }}" class="block truncate font-bold text-gray-900 hover:text-raga-primary dark:text-gray-100">
                                {{ $person->name }}
                            </a>
                            @if ($person->username)
                                <p class="truncate text-xs text-gray-400">&#64;{{ $person->username }}</p>
                            @endif
                        </div>

                        @if ($person->id !== $viewer->id)
                            <div class="shrink-0">
                                @if (in_array($person->id, $followedIds, true))
                                    <form method="POST" action="{{ route('follows.destroy', $person) }}">
                                        @csrf
                                        @method('DELETE')
                                        <x-secondary-button class="!px-4 !py-1.5 !text-xs">Berhenti</x-secondary-button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('follows.store', $person) }}">
                                        @csrf
                                        <x-primary-button class="!px-4 !py-1.5 !text-xs">Ikuti</x-primary-button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm text-gray-400">
                        {{ $isFollowers ? 'Belum ada pengikut.' : 'Belum mengikuti siapa pun.' }}
                    </div>
                @endforelse
            </x-card>

            @if ($people->hasPages())
                <div>{{ $people->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
