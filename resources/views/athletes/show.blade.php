<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">
            {{ $athlete->name }}
        </h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">&#64;{{ $athlete->username }}</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-3xl space-y-3 sm:space-y-6">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            <x-card>
                <div class="flex flex-wrap items-start gap-4">
                    @if ($athlete->avatar_path)
                        <img src="{{ asset($athlete->avatar_path) }}" alt="{{ $athlete->name }}"
                            class="h-20 w-20 rounded-full object-cover">
                    @else
                        <span class="flex h-20 w-20 items-center justify-center rounded-full border border-telemetry-line bg-telemetry-well text-2xl font-bold text-telemetry-slate">
                            {{ $athlete->initials() }}
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <h1 class="telemetry-value text-xl">{{ $athlete->name }}</h1>
                        <p class="text-sm font-semibold text-telemetry-slate">&#64;{{ $athlete->username }}</p>

                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-telemetry-slate">
                            @if ($athlete->location)
                                <span>📍 {{ $athlete->location }}</span>
                            @endif
                            <span>🗓️ Bergabung {{ $athlete->created_at->translatedFormat('F Y') }}</span>
                        </div>

                        @if ($athlete->bio)
                            <p class="mt-3 text-sm text-telemetry-slate">{{ $athlete->bio }}</p>
                        @endif

                        <div class="mt-4 flex items-center gap-5 text-sm">
                            <a href="{{ route('athletes.followers', $athlete) }}" class="hover:text-telemetry-ember transition-colors">
                                <span class="telemetry-value">{{ $followerCount }}</span>
                                <span class="text-telemetry-slate">Pengikut</span>
                            </a>
                            <a href="{{ route('athletes.following', $athlete) }}" class="hover:text-telemetry-ember transition-colors">
                                <span class="telemetry-value">{{ $followingCount }}</span>
                                <span class="text-telemetry-slate">Mengikuti</span>
                            </a>
                        </div>
                    </div>

                    <div class="shrink-0">
                        @if ($viewer->id === $athlete->id)
                            <a href="{{ route('profile.edit') }}"
                                class="inline-flex items-center justify-center min-h-11 sm:min-h-0 rounded border border-telemetry-line px-6 py-2.5 text-sm font-bold text-telemetry-ink transition-colors hover:border-telemetry-ember hover:text-telemetry-ember">
                                Edit Profil
                            </a>
                        @elseif ($isFollowing)
                            <form method="POST" action="{{ route('follows.destroy', $athlete) }}">
                                @csrf
                                @method('DELETE')
                                <x-secondary-button>Berhenti Mengikuti</x-secondary-button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('follows.store', $athlete) }}">
                                @csrf
                                <x-primary-button>Ikuti</x-primary-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📋" label="Aktivitas" :value="$stats['total_activities']" />
                <x-metric-tile icon="📏" label="Total Jarak" :value="number_format($stats['total_distance_meters'] / 1000, 1)" unit="km" />
                @php $totalMin = intdiv($stats['total_duration_seconds'], 60); @endphp
                <x-metric-tile icon="⏱️" label="Total Durasi" :value="intdiv($totalMin, 60).'h '.($totalMin % 60).'m'" />
                <x-metric-tile icon="⛰️" label="Total Elevasi" :value="number_format($stats['total_elevation_meters'])" unit="m" />
            </div>

            <x-card class="!p-4 text-center">
                <p class="telemetry-label">Jarak 4 Minggu Terakhir</p>
                <p class="mt-1 telemetry-value text-2xl">{{ number_format($stats['last_4_weeks_distance_meters'] / 1000, 1) }} km</p>
            </x-card>

            <section>
                <x-section-heading title="Aktivitas Terbaru" />

                <div class="space-y-4">
                    @forelse ($activities as $workout)
                        <x-activity-card :workout="$workout" :viewer="$viewer" />
                    @empty
                        <x-card class="text-center py-10">
                            <p class="text-sm text-telemetry-slate">Belum ada aktivitas yang bisa ditampilkan.</p>
                        </x-card>
                    @endforelse
                </div>

                @if ($activities->hasPages())
                    <div class="mt-4">{{ $activities->links() }}</div>
                @endif
            </section>

        </div>
    </div>
</x-app-layout>
