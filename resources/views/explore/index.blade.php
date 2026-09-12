<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl leading-tight">
            {{ __('Jelajahi') }}
        </h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Temukan atlet lain dan aktivitas publik terbaru.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-4xl space-y-8">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            <section>
                <x-section-heading title="Atlet untuk Diikuti" />

                @if ($suggestions->isEmpty())
                    <x-card class="text-center py-8">
                        <p class="text-sm text-telemetry-slate">Belum ada atlet lain yang bisa disarankan.</p>
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
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-telemetry-line bg-telemetry-well text-sm font-bold text-telemetry-slate">
                                            {{ $person->initials() }}
                                        </span>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <a href="{{ route('athletes.show', $person) }}" class="block truncate font-bold text-telemetry-ink hover:text-telemetry-ember transition-colors">
                                            {{ $person->name }}
                                        </a>
                                        <p class="truncate text-xs text-telemetry-slate">
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
                <x-section-heading title="Aktivitas Publik Terbaru" />

                @forelse ($activities as $workout)
                    <x-activity-card :workout="$workout" :viewer="auth()->user()" class="mb-4" />
                @empty
                    <x-card class="text-center py-8">
                        <p class="text-sm text-telemetry-slate">Belum ada aktivitas publik untuk ditampilkan.</p>
                    </x-card>
                @endforelse
            </section>

        </div>
    </div>
</x-app-layout>
