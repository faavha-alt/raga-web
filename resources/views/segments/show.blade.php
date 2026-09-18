<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">
                    {{ $segment->name }}
                </h1>
                <p class="mt-1 text-sm font-medium text-telemetry-slate">
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

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            @if ($segment->description)
                <x-card class="!py-4">
                    <p class="text-sm text-telemetry-slate">{{ $segment->description }}</p>
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
                    <x-section-heading title="Rute Segment" />
                    <x-route-map :points="$routePoints" />
                </x-card>
            @endif

            <x-card class="!p-0 overflow-hidden">
                <div class="px-6 py-4 border-b border-telemetry-line">
                    <div class="flex items-center justify-between">
                        <h3 class="telemetry-label-lg text-telemetry-ink">Leaderboard</h3>
                        <p class="telemetry-label">1 baris = 1 atlet (usaha terbaiknya)</p>
                    </div>
                    <p class="mt-1 text-xs text-telemetry-slate">Detak jantung hanya terlihat oleh pemilik aktivitas.</p>
                </div>

                @php
                    $showHeartRateColumn = $myEfforts->isNotEmpty();
                @endphp

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-telemetry-line text-left">
                                <th class="px-4 py-3 telemetry-label">#</th>
                                <th class="px-4 py-3 telemetry-label">Atlet</th>
                                <th class="px-4 py-3 telemetry-label text-right">Waktu</th>
                                <th class="px-4 py-3 telemetry-label text-right">Pace</th>
                                <th class="px-4 py-3 telemetry-label">Tanggal</th>
                                @if ($showHeartRateColumn)
                                    <th class="px-4 py-3 telemetry-label text-right">Avg HR</th>
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
                                <tr class="border-b border-telemetry-line/70 hover:bg-telemetry-well {{ $isViewer ? 'bg-telemetry-ember/5' : '' }}">
                                    <td class="px-4 py-3 telemetry-value">{{ $ranking + $loop->iteration }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-telemetry-line bg-telemetry-well text-[11px] font-bold text-telemetry-slate">
                                                {{ $effort->user->initials() }}
                                            </span>
                                            @if ($athleteUrl)
                                                <a href="{{ $athleteUrl }}" class="font-bold text-telemetry-ink hover:text-telemetry-ember transition-colors">
                                                    {{ $effort->user->name }}@if ($isViewer) <span class="text-xs font-semibold text-telemetry-ember">(kamu)</span>@endif
                                                </a>
                                            @else
                                                <span class="font-bold text-telemetry-ink">
                                                    {{ $effort->user->name }}@if ($isViewer) <span class="text-xs font-semibold text-telemetry-ember">(kamu)</span>@endif
                                                </span>
                                            @endif
                                            @if ($effort->is_personal_best)
                                                <x-chip variant="recovery">PB</x-chip>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right telemetry-value">{{ $formatDuration($effort->elapsed_seconds) }}</td>
                                    <td class="px-4 py-3 text-right telemetry-value text-telemetry-chrono-deep">{{ $formatPace($effort->average_pace_seconds_per_km) }}</td>
                                    <td class="px-4 py-3 text-telemetry-slate">{{ $effort->started_at->translatedFormat('d M Y') }}</td>
                                    @if ($showHeartRateColumn)
                                        {{-- Data kesehatan: hanya dirender untuk effort milik viewer sendiri. --}}
                                        <td class="px-4 py-3 text-right telemetry-value">
                                            @if ($isViewer && $effort->average_heart_rate !== null)
                                                {{ round($effort->average_heart_rate).' bpm' }}
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr class="border-b border-telemetry-line/70">
                                    <td colspan="{{ $showHeartRateColumn ? 6 : 5 }}" class="px-4 py-10 text-center text-sm text-telemetry-slate">Belum ada atlet yang melewati segment ini.</td>
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
                <x-section-heading title="Effort Saya" />
                @forelse ($myEfforts as $effort)
                    <div class="flex items-center justify-between gap-3 border-t border-telemetry-line/70 py-2.5 first:border-t-0">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-telemetry-ink">
                                {{ $effort->started_at->translatedFormat('d M Y, H:i') }}
                                @if ($effort->is_personal_best)
                                    <x-chip variant="recovery" class="ml-1">PB</x-chip>
                                @endif
                            </p>
                            <p class="text-xs text-telemetry-slate">
                                {{ $effort->workout->name ?? 'Aktivitas' }} · {{ $formatPace($effort->average_pace_seconds_per_km) }}
                                @if ($effort->average_heart_rate !== null)
                                    · {{ round($effort->average_heart_rate) }} bpm
                                @endif
                            </p>
                        </div>
                        <p class="shrink-0 telemetry-value">{{ $formatDuration($effort->elapsed_seconds) }}</p>
                    </div>
                @empty
                    <p class="text-sm text-telemetry-slate">Kamu belum punya effort di segment ini.</p>
                @endforelse
            </x-card>

            <a href="{{ route('segments.index') }}" class="inline-block text-xs font-bold text-telemetry-ember hover:text-telemetry-ember-deep transition-colors">← Kembali ke daftar segment</a>
        </div>
    </div>
</x-app-layout>
