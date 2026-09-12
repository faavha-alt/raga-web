<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('activities') }}" class="text-sm font-bold text-gray-400 hover:text-gray-600 transition">← Activities</a>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            @php
                $icon = match (true) {
                    str_contains($workout->type, 'run') || $workout->type === 'walking' => '🏃',
                    str_contains($workout->type, 'bik') || str_contains($workout->type, 'cycl') => '🚴',
                    str_contains($workout->type, 'swim') => '🏊',
                    str_contains($workout->type, 'strength') => '🏋️',
                    default => '💪',
                };
                $durationMin = intdiv($workout->durationSeconds(), 60);
                $pace = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
                $isOwner = $viewer->id === $workout->user_id;
            @endphp

            <div class="flex items-center gap-4">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gray-50 text-2xl">{{ $icon }}</span>
                <div>
                    <h1 class="text-2xl font-black text-gray-900">{{ $workout->name ?: ucwords(str_replace('_', ' ', $workout->type)) }}</h1>
                    <p class="text-sm text-gray-500">
                        @if ($workout->name){{ ucwords(str_replace('_', ' ', $workout->type)) }} · @endif{{ $workout->start_date->translatedFormat('l, d F Y — H:i') }}
                    </p>
                </div>
            </div>

            {{-- Aksi sosial: kudos, jumlah komentar, dan visibilitas (pemilik saja). --}}
            <x-card class="!p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            x-data="activityKudos({
                                given: {{ $viewerHasKudos ? 'true' : 'false' }},
                                count: {{ $kudosCount }},
                                storeUrl: @js(route('kudos.store', $workout)),
                                destroyUrl: @js(route('kudos.destroy', $workout)),
                            })"
                            x-on:click="toggle()"
                            :disabled="pending"
                            :class="given ? 'bg-raga-accent/10 text-raga-accent' : 'text-gray-500 hover:text-raga-accent'"
                            class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-bold transition disabled:opacity-60"
                            aria-label="Beri kudos"
                        >
                            <span x-text="given ? '👍' : '👏'"></span>
                            <span x-text="count">{{ $kudosCount }}</span>
                            <span class="font-semibold">Kudos</span>
                        </button>

                        <a href="#comments" class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-bold text-gray-500 transition hover:text-raga-primary">
                            💬 {{ $commentsCount }} Komentar
                        </a>
                    </div>

                    @if ($viewer->id === $workout->user_id)
                        <form method="POST" action="{{ route('activities.visibility.update', $workout) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <label for="visibility" class="text-[11px] font-bold uppercase tracking-wider text-gray-400">Visibilitas</label>
                            <select id="visibility" name="visibility"
                                class="rounded-2xl border-2 border-gray-200 bg-gray-50 px-3 py-2 text-sm font-semibold text-gray-900 focus:border-raga-primary focus:bg-white focus:ring-raga-primary dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100">
                                @foreach (\App\Support\ActivityVisibility::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($workout->visibility === $case)>{{ $case->icon() }} {{ $case->label() }}</option>
                                @endforeach
                            </select>
                            <x-secondary-button class="!px-4 !py-2 !text-xs">Simpan</x-secondary-button>
                        </form>
                    @endif
                </div>
            </x-card>

            <x-card>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-5">
                    <div>
                        <p class="text-[10px] font-bold uppercase text-gray-400">Duration</p>
                        <p class="mt-0.5 text-lg font-black text-gray-900">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
                    </div>
                    @if ($workout->distance_meters)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Distance</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                        </div>
                    @endif
                    @if ($pace)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Avg Pace</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }} /km</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->average_heart_rate)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Avg HR</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->average_heart_rate) }} bpm</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->max_heart_rate)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Max HR</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->max_heart_rate) }} bpm</p>
                        </div>
                    @endif
                    @if ($workout->active_calories)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Calories</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->active_calories) }}</p>
                        </div>
                    @endif
                    @if ($workout->elevation_gain_meters)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Elevation Gain</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->elevation_gain_meters) }} m</p>
                        </div>
                    @endif
                    @if ($workout->elevation_loss_meters)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Elevation Loss</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->elevation_loss_meters) }} m</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->training_load)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Training Load (Garmin)</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ round($workout->training_load) }}</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->relative_effort)
                        <div>
                            <p class="text-[10px] font-bold uppercase text-gray-400">Relative Effort</p>
                            <p class="mt-0.5 text-lg font-black text-gray-900">{{ $workout->relative_effort }}</p>
                            <p class="mt-0.5 text-[10px] font-medium text-gray-400">estimasi dari HR per-detik</p>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Catatan privasi untuk non-pemilik (data detak jantung = data kesehatan). --}}
            @unless ($isOwner)
                <p class="text-xs font-medium text-gray-400">Data detak jantung hanya terlihat oleh pemilik aktivitas.</p>
            @endunless

            @if ($isOwner && ($workout->training_effect_aerobic || $workout->training_effect_anaerobic))
                <x-card>
                    <p class="text-[10px] font-bold uppercase text-gray-400 mb-3">Training Effect</p>
                    <div class="grid grid-cols-2 gap-5">
                        @if ($workout->training_effect_aerobic)
                            <div>
                                <p class="text-xs font-semibold text-gray-500">Aerobic</p>
                                <p class="mt-0.5 text-2xl font-black text-gray-900">{{ number_format($workout->training_effect_aerobic, 1) }}<span class="text-sm font-semibold text-gray-400">/5</span></p>
                            </div>
                        @endif
                        @if ($workout->training_effect_anaerobic)
                            <div>
                                <p class="text-xs font-semibold text-gray-500">Anaerobic</p>
                                <p class="mt-0.5 text-2xl font-black text-gray-900">{{ number_format($workout->training_effect_anaerobic, 1) }}<span class="text-sm font-semibold text-gray-400">/5</span></p>
                            </div>
                        @endif
                    </div>
                    @if ($workout->training_effect_label)
                        <p class="mt-3 text-xs font-semibold text-raga-primary">{{ ucwords(str_replace('_', ' ', strtolower($workout->training_effect_label))) }}</p>
                    @endif
                </x-card>
            @endif

            @if (count($routePoints) > 1)
                <x-card class="!p-2">
                    <x-route-map :points="$routePoints" />
                </x-card>
            @endif

            @if (collect($charts)->every(fn ($c) => empty($c['points'])))
                <x-card class="text-center py-8">
                    <p class="text-gray-400">{{ $isOwner ? 'Tidak ada data time-series (heart rate/pace/elevation) untuk aktivitas ini.' : 'Tidak ada data time-series untuk aktivitas ini.' }}</p>
                </x-card>
            @else
                @foreach ($charts as $chart)
                    @if (! empty($chart['points']))
                        <x-card>
                            <x-sample-chart
                                :label="$chart['label']"
                                :unit="$chart['unit']"
                                :color="$chart['color']"
                                :points="$chart['points']"
                                :decimals="$chart['decimals']"
                            />
                        </x-card>
                    @endif
                @endforeach
            @endif

            @if ($laps->isNotEmpty())
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Laps</h3>
                    <x-card class="!p-0 divide-y divide-gray-100 overflow-hidden">
                        @foreach ($laps as $lap)
                            @php
                                $lapPace = $lap->average_pace_seconds_per_km ? (int) round($lap->average_pace_seconds_per_km) : null;
                                $lapMin = $lap->duration_seconds ? intdiv((int) round($lap->duration_seconds), 60) : 0;
                                $lapSec = $lap->duration_seconds ? ((int) round($lap->duration_seconds)) % 60 : 0;
                            @endphp
                            <div class="flex items-center justify-between px-5 py-3 gap-4">
                                <span class="text-sm font-bold text-gray-900 shrink-0">Lap {{ $lap->lap_index }}</span>
                                <div class="flex flex-wrap justify-end gap-x-4 gap-y-1 text-sm">
                                    @if ($lap->distance_meters)
                                        <span class="font-semibold text-gray-700">{{ number_format($lap->distance_meters / 1000, 2) }} km</span>
                                    @endif
                                    <span class="text-gray-500">{{ $lapMin }}:{{ sprintf('%02d', $lapSec) }}</span>
                                    @if ($lapPace)
                                        <span class="text-gray-500">{{ sprintf('%d:%02d', intdiv($lapPace, 60), $lapPace % 60) }}/km</span>
                                    @endif
                                    @if ($isOwner && $lap->average_heart_rate)
                                        <span class="text-gray-500">{{ round($lap->average_heart_rate) }} bpm</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-card>
                </div>
            @endif

            {{-- Komentar --}}
            <div id="comments" class="space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-gray-400">Komentar</h3>

                <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-800 overflow-hidden">
                    @forelse ($comments as $comment)
                        <div class="flex gap-3 px-5 py-4">
                            @if ($comment->user->avatar_path)
                                <img src="{{ asset($comment->user->avatar_path) }}"
                                    alt="{{ $comment->user->name }}" class="h-9 w-9 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-xs font-black text-white">
                                    {{ $comment->user->initials() }}
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ $comment->user->name }}</span>
                                    <span class="ml-1 text-xs text-gray-400">{{ str_replace(' yang lalu', ' lalu', $comment->created_at->locale('id')->diffForHumans()) }}</span>
                                </p>
                                <p class="mt-0.5 break-words text-sm text-gray-700 dark:text-gray-300">{{ $comment->body }}</p>
                            </div>

                            @if ($viewer->id === $comment->user_id || $viewer->id === $workout->user_id)
                                <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-raga-low hover:underline">Hapus</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-gray-400">Belum ada komentar.</div>
                    @endforelse
                </x-card>

                <x-card>
                    <form method="POST" action="{{ route('comments.store', $workout) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="body" value="Tambah Komentar" />
                        <textarea id="body" name="body" rows="3" required maxlength="1000" placeholder="Tulis komentar..."
                            class="w-full rounded-2xl border-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100 placeholder:text-gray-400 focus:border-raga-primary focus:bg-white dark:focus:bg-gray-800 focus:ring-raga-primary transition">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        <x-primary-button>Kirim</x-primary-button>
                    </form>
                </x-card>
            </div>

            @include('feed.partials.kudos-script')

        </div>
    </div>
</x-app-layout>
