<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('athletes.show', $athlete) }}" class="telemetry-label inline-flex items-center min-h-11 sm:min-h-0 transition-colors hover:text-telemetry-ink">← {{ $athlete->name }}</a>
        <h1 class="mt-2 telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">{{ $title }}</h1>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl space-y-4">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                @forelse ($people as $person)
                    <div class="flex items-center gap-3 px-5 py-4 transition-colors hover:bg-telemetry-well">
                        @if ($person->avatar_path)
                            <img src="{{ asset($person->avatar_path) }}" alt="{{ $person->name }}"
                                class="h-11 w-11 shrink-0 rounded-full object-cover">
                        @else
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-telemetry-line bg-telemetry-well text-sm font-bold text-telemetry-slate">
                                {{ $person->initials() }}
                            </span>
                        @endif

                        <div class="min-w-0 flex-1">
                            <a href="{{ $person->username ? route('athletes.show', $person) : '#' }}" class="block truncate font-bold text-telemetry-ink hover:text-telemetry-ember transition-colors">
                                {{ $person->name }}
                            </a>
                            @if ($person->username)
                                <p class="truncate text-xs text-telemetry-slate">&#64;{{ $person->username }}</p>
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
                    <div class="px-5 py-10 text-center text-sm text-telemetry-slate">
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
