<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">
                    {{ __('Segments') }}
                </h1>
                <p class="mt-1 text-sm font-medium text-telemetry-slate">Potongan rute untuk diperlombakan — leaderboard per atlet.</p>
            </div>
            <a href="{{ route('segments.create') }}"
               class="inline-flex items-center gap-2 rounded bg-telemetry-ember px-5 py-2.5 text-sm font-bold text-white transition-colors hover:bg-telemetry-ember-dark">
                ➕ Buat Segment
            </a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            <x-card class="!p-4">
                <form method="GET" action="{{ route('segments.index') }}" class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[160px]">
                        <x-input-label for="search" value="Cari" />
                        <x-text-input id="search" name="search" type="text" placeholder="mis. Bukit Cinta" :value="$filters['search'] ?? ''" />
                    </div>

                    <div class="min-w-[150px]">
                        <x-input-label for="activity_type" value="Tipe Aktivitas" />
                        <select id="activity_type" name="activity_type"
                            class="w-full rounded border border-telemetry-line bg-telemetry-surface px-4 py-3 text-sm font-medium text-telemetry-ink focus:border-telemetry-ember focus:ring-telemetry-ember transition">
                            <option value="">Semua</option>
                            @foreach ($activityTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['activity_type'] ?? '') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[140px]">
                        <x-input-label for="sort" value="Urutkan" />
                        <select id="sort" name="sort"
                            class="w-full rounded border border-telemetry-line bg-telemetry-surface px-4 py-3 text-sm font-medium text-telemetry-ink focus:border-telemetry-ember focus:ring-telemetry-ember transition">
                            @foreach (['created' => 'Terbaru', 'name' => 'Nama', 'distance' => 'Jarak', 'elevation' => 'Elevasi', 'efforts' => 'Jumlah Effort'] as $key => $optionLabel)
                                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'created') === $key)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[120px]">
                        <x-input-label for="direction" value="Arah" />
                        <select id="direction" name="direction"
                            class="w-full rounded border border-telemetry-line bg-telemetry-surface px-4 py-3 text-sm font-medium text-telemetry-ink focus:border-telemetry-ember focus:ring-telemetry-ember transition">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Menurun</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Menaik</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">Terapkan</x-primary-button>
                        @if (array_filter($filters))
                            <a href="{{ route('segments.index') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-bold text-telemetry-slate hover:text-telemetry-ink transition-colors">Reset</a>
                        @endif
                    </div>
                </form>
            </x-card>

            <div class="space-y-4">
                @forelse ($segments as $segment)
                    <x-card>
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span>{{ \App\Support\ActivityTypeIcon::icon($segment->activity_type) }}</span>
                                    <a href="{{ route('segments.show', $segment) }}" class="font-bold text-telemetry-ink hover:text-telemetry-ember transition-colors">
                                        {{ $segment->name }}
                                    </a>
                                    @if (! $segment->is_public)
                                        <x-chip variant="neutral">🔒 Private</x-chip>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-telemetry-slate">
                                    {{ \App\Support\ActivityTypeIcon::label($segment->activity_type) }}
                                    @if ($segment->start_label) · {{ $segment->start_label }} @endif
                                </p>
                            </div>
                            <a href="{{ route('segments.show', $segment) }}" class="shrink-0 text-xs font-bold text-telemetry-ember hover:text-telemetry-ember-deep transition-colors">Leaderboard →</a>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded border border-telemetry-line bg-telemetry-well px-3 py-2">
                                <p class="telemetry-label">Jarak</p>
                                <p class="mt-1 telemetry-value text-lg">{{ $segment->distanceKm() }}<span class="text-xs font-semibold text-telemetry-slate"> km</span></p>
                            </div>
                            <div class="rounded border border-telemetry-line bg-telemetry-well px-3 py-2">
                                <p class="telemetry-label">Elevasi</p>
                                <p class="mt-1 telemetry-value text-lg">{{ round($segment->elevation_gain_meters) }}<span class="text-xs font-semibold text-telemetry-slate"> m</span></p>
                            </div>
                            <div class="rounded border border-telemetry-line bg-telemetry-well px-3 py-2">
                                <p class="telemetry-label">Effort</p>
                                <p class="mt-1 telemetry-value text-lg">{{ $segment->visible_effort_count }}</p>
                            </div>
                        </div>
                    </x-card>
                @empty
                    <x-card class="text-center py-12">
                        <p class="text-lg font-bold text-telemetry-ink">Belum Ada Segment</p>
                        <p class="mt-2 text-telemetry-slate">Buat segment pertamamu dari salah satu aktivitas GPS, lalu tantang temanmu di leaderboard.</p>
                        <a href="{{ route('segments.create') }}" class="mt-4 inline-block text-sm font-bold text-telemetry-ember hover:text-telemetry-ember-deep transition-colors">Buat Segment →</a>
                    </x-card>
                @endforelse
            </div>

            @if ($segments->hasPages())
                <div>{{ $segments->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
