<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ $athlete->name }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">&#64;{{ $athlete->username }}</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-3xl space-y-6">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            <x-card>
                <div class="flex flex-wrap items-start gap-4">
                    @if ($athlete->avatar_path)
                        <img src="{{ asset($athlete->avatar_path) }}" alt="{{ $athlete->name }}"
                            class="h-20 w-20 rounded-3xl object-cover shadow-sm">
                    @else
                        <span class="flex h-20 w-20 items-center justify-center rounded-3xl bg-gradient-to-br from-raga-accent to-raga-primary text-2xl font-black text-white shadow-glow-accent">
                            {{ $athlete->initials() }}
                        </span>
                    @endif

                    <div class="min-w-0 flex-1">
                        <h1 class="text-xl font-black text-gray-900 dark:text-gray-100">{{ $athlete->name }}</h1>
                        <p class="text-sm font-semibold text-gray-400">&#64;{{ $athlete->username }}</p>

                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-400">
                            @if ($athlete->location)
                                <span>📍 {{ $athlete->location }}</span>
                            @endif
                            <span>🗓️ Bergabung {{ $athlete->created_at->translatedFormat('F Y') }}</span>
                        </div>

                        @if ($athlete->bio)
                            <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $athlete->bio }}</p>
                        @endif

                        <div class="mt-4 flex items-center gap-5 text-sm">
                            <a href="{{ route('athletes.followers', $athlete) }}" class="hover:text-raga-primary">
                                <span class="font-black text-gray-900 dark:text-gray-100">{{ $followerCount }}</span>
                                <span class="text-gray-500">Pengikut</span>
                            </a>
                            <a href="{{ route('athletes.following', $athlete) }}" class="hover:text-raga-primary">
                                <span class="font-black text-gray-900 dark:text-gray-100">{{ $followingCount }}</span>
                                <span class="text-gray-500">Mengikuti</span>
                            </a>
                        </div>
                    </div>

                    <div class="shrink-0">
                        @if ($viewer->id === $athlete->id)
                            <a href="{{ route('profile.edit') }}"
                                class="inline-flex items-center justify-center rounded-full border-2 border-gray-200 px-6 py-2.5 text-sm font-bold text-gray-700 transition hover:border-raga-primary hover:text-raga-primary dark:border-gray-700 dark:text-gray-200">
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
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Jarak 4 Minggu Terakhir</p>
                <p class="mt-1 text-2xl font-black text-gradient">{{ number_format($stats['last_4_weeks_distance_meters'] / 1000, 1) }} km</p>
            </x-card>

            <section>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Aktivitas Terbaru</h3>

                <div class="space-y-4">
                    @forelse ($activities as $workout)
                        <x-activity-card :workout="$workout" :viewer="$viewer" />
                    @empty
                        <x-card class="text-center py-10">
                            <p class="text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas yang bisa ditampilkan.</p>
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
