<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ $segment->name }}
                </h2>
                <p class="mt-1 text-sm font-medium text-gray-500">
                    {{ \App\Support\ActivityTypeIcon::icon($segment->activity_type) }}
                    {{ \App\Support\ActivityTypeIcon::label($segment->activity_type) }}
                    @if ($segment->start_label) · {{ $segment->start_label }} @endif
                    @if (! $segment->is_public) · 🔒 Private @endif
                </p>
            </div>

            @if ($segment->user_id === auth()->id())
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('segments.rescan', $segment) }}">
                        @csrf
                        <x-secondary-button type="submit">🔄 Jalankan ulang pemindaian</x-secondary-button>
                    </form>
                    <form method="POST" action="{{ route('segments.destroy', $segment) }}"
                        onsubmit="return confirm('Hapus segment ini beserta seluruh effort-nya?');">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="submit">Hapus</x-danger-button>
                    </form>
                </div>
            @endif
        </div>
    </x-slot>

    @php
        $formatDuration = function (float $seconds): string {
            $total = (int) round($seconds);
            $hours = intdiv($total, 3600);
            $minutes = intdiv($total % 3600, 60);
            $secs = $total % 60;

            return $hours > 0
                ? sprintf('%d:%02d:%02d', $hours, $minutes, $secs)
                : sprintf('%d:%02d', $minutes, $secs);
        };
        $formatPace = function (?float $secondsPerKm): string {
            if ($secondsPerKm === null || $secondsPerKm <= 0) {
                return '--';
            }
            $minutes = intdiv((int) round($secondsPerKm), 60);
            $secs = (int) round($secondsPerKm) % 60;

            return sprintf('%d:%02d /km', $minutes, $secs);
        };
        $ranking = (int) ($leaderboard->currentPage() - 1) * $leaderboard->perPage();
    @endphp

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            @if ($segment->description)
                <x-card class="!py-4">
                    <p class="text-sm text-gray-600 dark:text-gray-300">{{ $segment->description }}</p>
                </x-card>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <x-metric-tile icon="📏" label="Jarak" :value="$segment->distanceKm()" unit="km" />
                <x-metric-tile icon="⛰️" label="Elevasi" :value="round($segment->elevation_gain_meters)" unit="m" />
                <x-metric-tile icon="📈" label="Grade Rata-rata" :value="$segment->average_grade_percent" unit="%" />
                <x-metric-tile icon="🏁" label="Total Effort" :value="$visibleEffortCount" />
            </div>

            @if (count($routePoints) > 1)
                <x-card>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Rute Segment</h3>
                    <x-route-map :points="$routePoints" />
                </x-card>
            @endif

            <x-card class="!p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Leaderboard</h3>
                        <p class="text-xs text-gray-400">1 baris = 1 atlet (usaha terbaiknya)</p>
                    </div>
                    <p class="mt-1 text-xs text-gray-400">Detak jantung hanya terlihat oleh pemilik aktivitas.</p>
                </div>

                @php
                    $showHeartRateColumn = $myEfforts->isNotEmpty();
                @endphp

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                <th class="px-4 py-3">#</th>
                                <th class="px-4 py-3">Atlet</th>
                                <th class="px-4 py-3">Waktu</th>
                                <th class="px-4 py-3">Pace</th>
                                <th class="px-4 py-3">Tanggal</th>
                                @if ($showHeartRateColumn)
                                    <th class="px-4 py-3 text-right">Avg HR</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($leaderboard as $effort)
                                @php
                                    $isViewer = $effort->user_id === auth()->id();
                                    $athleteUrl = \Illuminate\Support\Facades\Route::has('athletes.show')
                                        ? route('athletes.show', $effort->user)
                                        : null;
                                @endphp
                                <tr class="border-t border-gray-100 dark:border-gray-800 {{ $isViewer ? 'bg-raga-primary/5' : '' }}">
                                    <td class="px-4 py-3 font-black text-gray-900 dark:text-gray-100">{{ $ranking + $loop->iteration }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-[11px] font-black text-white">
                                                {{ $effort->user->initials() }}
                                            </span>
                                            @if ($athleteUrl)
                                                <a href="{{ $athleteUrl }}" class="font-bold text-gray-900 dark:text-gray-100 hover:text-raga-primary transition">
                                                    {{ $effort->user->name }}@if ($isViewer) <span class="text-xs font-semibold text-raga-primary">(kamu)</span>@endif
                                                </a>
                                            @else
                                                <span class="font-bold text-gray-900 dark:text-gray-100">
                                                    {{ $effort->user->name }}@if ($isViewer) <span class="text-xs font-semibold text-raga-primary">(kamu)</span>@endif
                                                </span>
                                            @endif
                                            @if ($effort->is_personal_best)
                                                <span class="rounded-full bg-raga-excellent/15 px-2 py-0.5 text-[11px] font-bold text-raga-excellent">PB</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-bold text-gray-900 dark:text-gray-100">{{ $formatDuration($effort->elapsed_seconds) }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $formatPace($effort->average_pace_seconds_per_km) }}</td>
                                    <td class="px-4 py-3 text-gray-500 dark:text-gray-400">{{ $effort->started_at->translatedFormat('d M Y') }}</td>
                                    @if ($showHeartRateColumn)
                                        {{-- Data kesehatan: hanya dirender untuk effort milik viewer sendiri. --}}
                                        <td class="px-4 py-3 text-right text-gray-500 dark:text-gray-400">
                                            @if ($isViewer && $effort->average_heart_rate !== null)
                                                {{ round($effort->average_heart_rate).' bpm' }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td colspan="{{ $showHeartRateColumn ? 6 : 5 }}" class="px-4 py-10 text-center text-gray-400">Belum ada atlet yang melewati segment ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-card>

            @if ($leaderboard->hasPages())
                <div>{{ $leaderboard->links() }}</div>
            @endif

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Effort Saya</h3>
                @forelse ($myEfforts as $effort)
                    <div class="flex items-center justify-between gap-3 border-t border-gray-100 dark:border-gray-800 py-2.5 first:border-t-0">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                {{ $effort->started_at->translatedFormat('d M Y, H:i') }}
                                @if ($effort->is_personal_best)
                                    <span class="ml-1 rounded-full bg-raga-excellent/15 px-2 py-0.5 text-[11px] font-bold text-raga-excellent">PB</span>
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $effort->workout->name ?? 'Aktivitas' }} · {{ $formatPace($effort->average_pace_seconds_per_km) }}
                                @if ($effort->average_heart_rate !== null)
                                    · {{ round($effort->average_heart_rate) }} bpm
                                @endif
                            </p>
                        </div>
                        <p class="shrink-0 font-black text-gray-900 dark:text-gray-100">{{ $formatDuration($effort->elapsed_seconds) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kamu belum punya effort di segment ini.</p>
                @endforelse
            </x-card>

            <a href="{{ route('segments.index') }}" class="inline-block text-xs font-bold text-raga-primary hover:text-raga-accent transition">← Kembali ke daftar segment</a>
        </div>
    </div>
</x-app-layout>
