<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Jelajahi') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Temukan atlet lain dan aktivitas publik terbaru.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-4xl space-y-8">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Atlet untuk Diikuti</h3>

                @if ($suggestions->isEmpty())
                    <x-card class="text-center py-8">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada atlet lain yang bisa disarankan.</p>
                    </x-card>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($suggestions as $person)
                            <x-card class="!p-4">
                                <div class="flex items-center gap-3">
                                    @if ($person->avatar_path)
                                        <img src="{{ asset($person->avatar_path) }}" alt="{{ $person->name }}"
                                            class="h-11 w-11 shrink-0 rounded-full object-cover">
                                    @else
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-sm font-black text-white">
                                            {{ $person->initials() }}
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('athletes.show', $person) }}" class="block truncate font-bold text-gray-900 hover:text-raga-primary dark:text-gray-100">
                                            {{ $person->name }}
                                        </a>
                                        <p class="truncate text-xs text-gray-400">
                                            &#64;{{ $person->username }} · {{ $person->followers_count }} pengikut
                                        </p>
                                    </div>
                                    <form method="POST" action="{{ route('follows.store', $person) }}">
                                        @csrf
                                        <x-primary-button class="!px-4 !py-1.5 !text-xs">Ikuti</x-primary-button>
                                    </form>
                                </div>
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Aktivitas Publik Terbaru</h3>

                @forelse ($activities as $workout)
                    <x-activity-card :workout="$workout" :viewer="auth()->user()" class="mb-4" />
                @empty
                    <x-card class="text-center py-8">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas publik untuk ditampilkan.</p>
                    </x-card>
                @endforelse
            </section>

        </div>
    </div>
</x-app-layout>
