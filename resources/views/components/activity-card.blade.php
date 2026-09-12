@props([
    'workout',
    'viewer' => null,
    'showAuthor' => true,
])

@php
    // Selalu eager load `user`; tetap aman kalau suatu saat tidak ter-load.
    $author = $workout->relationLoaded('user') ? $workout->user : null;

    $isOwner = $viewer !== null && $workout->user_id === $viewer->id;
    $kudosCount = (int) ($workout->kudos_count ?? 0);
    $commentsCount = (int) ($workout->comments_count ?? 0);
    $hasKudos = (bool) ($workout->viewer_has_kudos ?? false);

    $label = \App\Support\ActivityTypeIcon::label($workout->type);
    $icon = \App\Support\ActivityTypeIcon::icon($workout->type);
    $title = $workout->name ?: $label;

    $durationSeconds = $workout->durationSeconds();
    $durationMin = intdiv($durationSeconds, 60);
    $pace = $workout->average_pace_seconds_per_km ? (int) round($workout->average_pace_seconds_per_km) : null;
    $isRide = str_contains($workout->type, 'bik') || str_contains($workout->type, 'cycl');
    $speed = $isRide && $durationSeconds > 0 && $workout->distance_meters
        ? ($workout->distance_meters / 1000) / ($durationSeconds / 3600)
        : null;

    $relative = str_replace(' yang lalu', ' lalu', $workout->start_date->locale('id')->diffForHumans());
    $visibility = $workout->visibility;
@endphp

<x-card class="!p-0 overflow-hidden">
    @if ($showAuthor && $author)
        <div class="flex items-center gap-3 px-5 pt-5">
            <a href="{{ $author->username ? route('athletes.show', $author) : '#' }}" class="shrink-0">
                @if ($author->avatar_path)
                    <img src="{{ asset($author->avatar_path) }}" alt="{{ $author->name }}"
                        class="h-10 w-10 rounded-full object-cover ring-2 ring-telemetry-surface">
                @else
                    <span class="flex h-10 w-10 items-center justify-center rounded-full bg-telemetry-steel text-sm font-bold text-white">
                        {{ $author->initials() }}
                    </span>
                @endif
            </a>
            <div class="min-w-0">
                <p class="truncate text-sm font-bold text-telemetry-ink">
                    {{ $author->name }}
                    @if ($author->username)
                        <a href="{{ route('athletes.show', $author) }}" class="font-medium text-telemetry-slate hover:text-telemetry-ember-deep">&#64;{{ $author->username }}</a>
                    @endif
                </p>
                <p class="text-xs text-telemetry-slate">{{ $relative }}</p>
            </div>
            @if ($isOwner)
                <span class="ml-auto shrink-0 rounded border border-telemetry-line bg-telemetry-well px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-slate">
                    {{ $visibility->icon() }} {{ $visibility->label() }}
                </span>
            @endif
        </div>
    @endif

    <div class="px-5 py-4">
        <div class="flex items-center gap-2">
            <span class="flex h-9 w-9 items-center justify-center rounded border border-telemetry-line bg-telemetry-well text-lg">{{ $icon }}</span>
            <div class="min-w-0">
                <a href="{{ route('activities.show', $workout) }}" class="block truncate font-bold text-telemetry-ink hover:text-telemetry-ember-deep">
                    {{ $title }}
                </a>
                <p class="telemetry-label">{{ $label }}</p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            @if ($workout->distance_meters)
                <div>
                    <p class="telemetry-label">Jarak</p>
                    <p class="mt-1 telemetry-value text-base">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                </div>
            @endif
            <div>
                <p class="telemetry-label">Durasi</p>
                <p class="mt-1 telemetry-value text-base">{{ intdiv($durationMin, 60) }}h {{ $durationMin % 60 }}m</p>
            </div>
            @if ($isRide && $speed)
                <div>
                    <p class="telemetry-label">Kecepatan</p>
                    <p class="mt-1 telemetry-value text-base">{{ number_format($speed, 1) }} km/j</p>
                </div>
            @elseif ($pace)
                <div>
                    <p class="telemetry-label">Pace</p>
                    <p class="mt-1 telemetry-value text-base">{{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }} /km</p>
                </div>
            @endif
            @if ($workout->elevation_gain_meters)
                <div>
                    <p class="telemetry-label">Elevasi</p>
                    <p class="mt-1 telemetry-value text-base">{{ round($workout->elevation_gain_meters) }} m</p>
                </div>
            @endif
        </div>
    </div>

    <div class="flex items-center gap-4 border-t border-telemetry-line px-5 py-3">
        <button
            type="button"
            x-data="activityKudos({
                given: {{ $hasKudos ? 'true' : 'false' }},
                count: {{ $kudosCount }},
                storeUrl: @js(route('kudos.store', $workout)),
                destroyUrl: @js(route('kudos.destroy', $workout)),
            })"
            x-on:click="toggle()"
            :disabled="pending"
            :class="given
                ? 'border-telemetry-ember/25 bg-[rgba(255,62,29,0.08)] text-telemetry-ember-deep'
                : 'border-telemetry-line text-telemetry-slate hover:border-telemetry-line-strong hover:text-telemetry-ember-deep'"
            class="inline-flex h-8 items-center gap-1.5 rounded border px-3 text-xs font-bold uppercase tracking-[0.08em] transition disabled:opacity-60"
            :aria-pressed="given ? 'true' : 'false'"
            aria-label="Beri kudos"
        >
            <span x-text="given ? '👍' : '👏'"></span>
            <span x-text="count">{{ $kudosCount }}</span>
        </button>

        <a href="{{ route('activities.show', $workout) }}#comments" class="inline-flex h-8 items-center gap-1.5 rounded border border-transparent px-3 text-xs font-bold uppercase tracking-[0.08em] text-telemetry-slate transition hover:border-telemetry-line hover:text-telemetry-ember-deep">
            💬 <span>{{ $commentsCount }}</span>
        </a>
    </div>
</x-card>

@include('feed.partials.kudos-script')
