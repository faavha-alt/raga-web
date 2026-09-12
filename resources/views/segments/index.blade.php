<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    {{ __('Segments') }}
                </h2>
                <p class="mt-1 text-sm font-medium text-gray-500">Potongan rute untuk diperlombakan — leaderboard per atlet.</p>
            </div>
            <a href="{{ route('segments.create') }}"
               class="inline-flex items-center gap-2 rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-5 py-2.5 text-sm font-bold text-white shadow-glow transition hover:brightness-110">
                ➕ Buat Segment
            </a>
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
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
                            class="w-full rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 focus:border-raga-primary focus:ring-raga-primary transition">
                            <option value="">Semua</option>
                            @foreach ($activityTypes as $type)
                                <option value="{{ $type }}" @selected(($filters['activity_type'] ?? '') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[140px]">
                        <x-input-label for="sort" value="Urutkan" />
                        <select id="sort" name="sort"
                            class="w-full rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 focus:border-raga-primary focus:ring-raga-primary transition">
                            @foreach (['created' => 'Terbaru', 'name' => 'Nama', 'distance' => 'Jarak', 'elevation' => 'Elevasi', 'efforts' => 'Jumlah Effort'] as $key => $optionLabel)
                                <option value="{{ $key }}" @selected(($filters['sort'] ?? 'created') === $key)>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="min-w-[120px]">
                        <x-input-label for="direction" value="Arah" />
                        <select id="direction" name="direction"
                            class="w-full rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 focus:border-raga-primary focus:ring-raga-primary transition">
                            <option value="desc" @selected(($filters['direction'] ?? 'desc') === 'desc')>Menurun</option>
                            <option value="asc" @selected(($filters['direction'] ?? 'desc') === 'asc')>Menaik</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <x-primary-button type="submit">Terapkan</x-primary-button>
                        @if (array_filter($filters))
                            <a href="{{ route('segments.index') }}" class="inline-flex items-center px-4 py-2.5 text-sm font-bold text-gray-400 hover:text-gray-600 transition">Reset</a>
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
                                    <a href="{{ route('segments.show', $segment) }}" class="font-bold text-gray-900 dark:text-gray-100 hover:text-raga-primary transition">
                                        {{ $segment->name }}
                                    </a>
                                    @if (! $segment->is_public)
                                        <span class="rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-bold text-gray-500">🔒 Private</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ \App\Support\ActivityTypeIcon::label($segment->activity_type) }}
                                    @if ($segment->start_label) · {{ $segment->start_label }} @endif
                                </p>
                            </div>
                            <a href="{{ route('segments.show', $segment) }}" class="shrink-0 text-xs font-bold text-raga-primary hover:text-raga-accent transition">Leaderboard →</a>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-2xl bg-gray-50 dark:bg-gray-800/60 px-3 py-2">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Jarak</p>
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ $segment->distanceKm() }}<span class="text-xs font-semibold text-gray-400"> km</span></p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 dark:bg-gray-800/60 px-3 py-2">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Elevasi</p>
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ round($segment->elevation_gain_meters) }}<span class="text-xs font-semibold text-gray-400"> m</span></p>
                            </div>
                            <div class="rounded-2xl bg-gray-50 dark:bg-gray-800/60 px-3 py-2">
                                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Effort</p>
                                <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ $segment->visible_effort_count }}</p>
                            </div>
                        </div>
                    </x-card>
                @empty
                    <x-card class="text-center py-12">
                        <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Belum Ada Segment</p>
                        <p class="mt-2 text-gray-500 dark:text-gray-400">Buat segment pertamamu dari salah satu aktivitas GPS, lalu tantang temanmu di leaderboard.</p>
                        <a href="{{ route('segments.create') }}" class="mt-4 inline-block text-sm font-bold text-raga-primary hover:text-raga-accent transition">Buat Segment →</a>
                    </x-card>
                @endforelse
            </div>

            @if ($segments->hasPages())
                <div>{{ $segments->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
