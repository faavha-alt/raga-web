<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('activities') }}" class="telemetry-label transition-colors hover:text-telemetry-ink">← Activities</a>
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
                <span class="flex h-14 w-14 items-center justify-center border border-telemetry-line bg-telemetry-well text-2xl">{{ $icon }}</span>
                <div>
                    <h1 class="telemetry-value text-2xl">{{ $workout->name ?: ucwords(str_replace('_', ' ', $workout->type)) }}</h1>
                    <p class="mt-0.5 telemetry-label">
                        @if ($workout->name){{ strtoupper(str_replace('_', ' ', $workout->type)) }} · @endif{{ $workout->start_date->translatedFormat('l, d F Y — H:i') }}
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
                            :class="given ? 'bg-[rgba(0,184,101,0.08)] text-telemetry-emerald-deep' : 'text-telemetry-slate hover:text-telemetry-emerald-deep'"
                            class="inline-flex items-center gap-1.5 rounded border border-telemetry-line px-3 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] transition-colors disabled:opacity-60"
                            aria-label="Beri kudos"
                        >
                            <span x-text="given ? '👍' : '👏'"></span>
                            <span x-text="count">{{ $kudosCount }}</span>
                            <span class="font-semibold">Kudos</span>
                        </button>

                        <a href="#comments" class="inline-flex items-center gap-1.5 rounded border border-transparent px-3 py-2 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-slate transition-colors hover:border-telemetry-line hover:text-telemetry-chrono-deep">
                            💬 {{ $commentsCount }} Komentar
                        </a>
                    </div>

                    @if ($viewer->id === $workout->user_id)
                        <form method="POST" action="{{ route('activities.visibility.update', $workout) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <label for="visibility" class="telemetry-label">Visibilitas</label>
                            <select id="visibility" name="visibility"
                                class="rounded border border-telemetry-line bg-telemetry-well px-3 py-2 text-sm font-semibold text-telemetry-ink transition-colors focus:border-telemetry-ink focus:outline-none focus:ring-0">
                                @foreach (\App\Support\ActivityVisibility::cases() as $case)
                                    <option value="{{ $case->value }}" @selected($workout->visibility === $case)>{{ $case->icon() }} {{ $case->label() }}</option>
                                @endforeach
                            </select>
                            <x-secondary-button class="!px-4 !py-2 !text-xs">Simpan</x-secondary-button>
                        </form>
                    @endif
                </div>
            </x-card>

            <x-section-heading title="Ringkasan Aktivitas" :hint="$workout->source === 'browser' ? 'Rekaman Browser' : 'Garmin Connect'" />

            <x-card>
                <div class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4">
                    <div>
                        <p class="telemetry-label">Duration</p>
                        <p class="mt-0.5 telemetry-value text-lg">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
                    </div>
                    @if ($workout->distance_meters)
                        <div>
                            <p class="telemetry-label">Distance</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                        </div>
                    @endif
                    @if ($pace)
                        <div>
                            <p class="telemetry-label">Avg Pace</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }} /km</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->average_heart_rate)
                        <div>
                            <p class="telemetry-label">Avg HR</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ round($workout->average_heart_rate) }} bpm</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->max_heart_rate)
                        <div>
                            <p class="telemetry-label">Max HR</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ round($workout->max_heart_rate) }} bpm</p>
                        </div>
                    @endif
                    @if ($workout->active_calories)
                        <div>
                            <p class="telemetry-label">Calories</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ round($workout->active_calories) }}</p>
                        </div>
                    @endif
                    @if ($workout->elevation_gain_meters)
                        <div>
                            <p class="telemetry-label">Elevation Gain</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ round($workout->elevation_gain_meters) }} m</p>
                        </div>
                    @endif
                    @if ($workout->elevation_loss_meters)
                        <div>
                            <p class="telemetry-label">Elevation Loss</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ round($workout->elevation_loss_meters) }} m</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->training_load)
                        <div>
                            <p class="telemetry-label">Training Load (Garmin)</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ round($workout->training_load) }}</p>
                        </div>
                    @endif
                    @if ($isOwner && $workout->relative_effort)
                        <div>
                            <p class="telemetry-label">Relative Effort</p>
                            <p class="mt-0.5 telemetry-value text-lg">{{ $workout->relative_effort }}</p>
                            <p class="mt-0.5 text-[10px] font-medium text-telemetry-slate">estimasi dari HR per-detik</p>
                        </div>
                    @endif
                </div>
            </x-card>

            {{-- Catatan privasi untuk non-pemilik (data detak jantung = data kesehatan). --}}
            @unless ($isOwner)
                <p class="text-xs font-medium text-telemetry-slate">Data detak jantung hanya terlihat oleh pemilik aktivitas.</p>
            @endunless

            @if ($isOwner && ($workout->training_effect_aerobic || $workout->training_effect_anaerobic))
                <x-card>
                    <p class="telemetry-label mb-3">Training Effect</p>
                    <div class="grid grid-cols-2 gap-5">
                        @if ($workout->training_effect_aerobic)
                            <div>
                                <p class="text-xs font-semibold text-telemetry-slate">Aerobic</p>
                                <p class="mt-0.5 text-2xl font-black text-telemetry-ink">{{ number_format($workout->training_effect_aerobic, 1) }}<span class="text-sm font-semibold text-telemetry-slate">/5</span></p>
                            </div>
                        @endif
                        @if ($workout->training_effect_anaerobic)
                            <div>
                                <p class="text-xs font-semibold text-telemetry-slate">Anaerobic</p>
                                <p class="mt-0.5 text-2xl font-black text-telemetry-ink">{{ number_format($workout->training_effect_anaerobic, 1) }}<span class="text-sm font-semibold text-telemetry-slate">/5</span></p>
                            </div>
                        @endif
                    </div>
                    @if ($workout->training_effect_label)
                        <p class="mt-3 font-display text-[11px] font-bold uppercase tracking-[0.08em] text-telemetry-chrono-deep">{{ ucwords(str_replace('_', ' ', strtolower($workout->training_effect_label))) }}</p>
                    @endif
                </x-card>
            @endif

            @if (count($routePoints) > 1)
                <x-card class="!p-2">
                    <x-route-map :points="$routePoints" />
                </x-card>
            @endif

            @php $streamCharts = collect($charts)->except('elevation'); @endphp

            @if ($streamCharts->every(fn ($c) => empty($c['points'])) && ! $elevationProfile['available'])
                <x-card class="py-8 text-center">
                    <p class="text-telemetry-slate">{{ $isOwner ? 'Tidak ada data time-series (heart rate/pace/elevation) untuk aktivitas ini.' : 'Tidak ada data time-series untuk aktivitas ini.' }}</p>
                </x-card>
            @else
                @foreach ($streamCharts as $chart)
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

                @if ($elevationProfile['available'])
                    <x-card>
                        <x-section-heading title="Profil Elevasi // Kemiringan" :hint="$elevationProfile['total_distance_km'].' km'" />
                        <x-elevation-band-chart :profile="$elevationProfile" />
                    </x-card>
                @endif
            @endif

            {{-- Split per kilometer --}}
            <section>
                <x-section-heading title="Split Per Kilometer" :hint="count($splits) > 0 ? count($splits).' split' : null" />
                <x-card>
                    <x-split-table :splits="$splits" :is-owner="$isOwner" />
                </x-card>
            </section>

            @if ($laps->isNotEmpty())
                <div>
                    <x-section-heading title="Laps" :hint="$laps->count().' lap'" />
                    <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                        @foreach ($laps as $lap)
                            @php
                                $lapPace = $lap->average_pace_seconds_per_km ? (int) round($lap->average_pace_seconds_per_km) : null;
                                $lapMin = $lap->duration_seconds ? intdiv((int) round($lap->duration_seconds), 60) : 0;
                                $lapSec = $lap->duration_seconds ? ((int) round($lap->duration_seconds)) % 60 : 0;
                            @endphp
                            <div class="flex items-center justify-between px-5 py-3 gap-4">
                                <span class="text-sm font-bold text-telemetry-ink shrink-0">Lap {{ $lap->lap_index }}</span>
                                <div class="flex flex-wrap justify-end gap-x-4 gap-y-1 text-sm">
                                    @if ($lap->distance_meters)
                                        <span class="font-semibold text-telemetry-ink">{{ number_format($lap->distance_meters / 1000, 2) }} km</span>
                                    @endif
                                    <span class="text-telemetry-slate">{{ $lapMin }}:{{ sprintf('%02d', $lapSec) }}</span>
                                    @if ($lapPace)
                                        <span class="text-telemetry-slate">{{ sprintf('%d:%02d', intdiv($lapPace, 60), $lapPace % 60) }}/km</span>
                                    @endif
                                    @if ($isOwner && $lap->average_heart_rate)
                                        <span class="text-telemetry-slate">{{ round($lap->average_heart_rate) }} bpm</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </x-card>
                </div>
            @endif

            {{-- Komentar --}}
            <div id="comments" class="space-y-4">
                <h3 class="telemetry-label">Komentar</h3>

                <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                    @forelse ($comments as $comment)
                        <div class="flex gap-3 px-5 py-4">
                            @if ($comment->user->avatar_path)
                                <img src="{{ asset($comment->user->avatar_path) }}"
                                    alt="{{ $comment->user->name }}" class="h-9 w-9 shrink-0 rounded-full object-cover">
                            @else
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-telemetry-ink font-display text-xs font-bold text-white">
                                    {{ $comment->user->initials() }}
                                </span>
                            @endif

                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-semibold text-telemetry-ink">{{ $comment->user->name }}</span>
                                    <span class="ml-1 text-xs text-telemetry-slate">{{ str_replace(' yang lalu', ' lalu', $comment->created_at->locale('id')->diffForHumans()) }}</span>
                                </p>
                                <p class="mt-0.5 break-words text-sm text-telemetry-ink">{{ $comment->body }}</p>
                            </div>

                            @if ($viewer->id === $comment->user_id || $viewer->id === $workout->user_id)
                                <form method="POST" action="{{ route('comments.destroy', $comment) }}" class="shrink-0">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="font-display text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-ember-deep hover:underline">Hapus</button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-telemetry-slate">Belum ada komentar.</div>
                    @endforelse
                </x-card>

                <x-card>
                    <form method="POST" action="{{ route('comments.store', $workout) }}" class="space-y-3">
                        @csrf
                        <x-input-label for="body" value="Tambah Komentar" />
                        <textarea id="body" name="body" rows="3" required maxlength="1000" placeholder="Tulis komentar..."
                            class="w-full rounded border border-telemetry-line bg-telemetry-well px-3 py-2 text-sm font-medium text-telemetry-ink placeholder:text-telemetry-slate transition-colors focus:border-telemetry-ink focus:outline-none focus:ring-0">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        <x-primary-button>Kirim</x-primary-button>
                    </form>
                </x-card>
            </div>

            @include('feed.partials.kudos-script')

        </div>
    </div>
</x-app-layout>
