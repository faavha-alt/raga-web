@props(['title', 'score' => null, 'category' => null])

@php
    $colorClass = match ($category?->value ?? null) {
        'excellent', 'very_good' => 'text-telemetry-emerald-deep',
        'good' => 'text-telemetry-chrono-deep',
        'moderate' => 'text-telemetry-amber',
        'low' => 'text-telemetry-ember-deep',
        default => 'text-telemetry-slate',
    };

    $ringClass = match ($category?->value ?? null) {
        'excellent', 'very_good' => 'bg-telemetry-emerald',
        'good' => 'bg-telemetry-chrono',
        'moderate' => 'bg-telemetry-amber',
        'low' => 'bg-telemetry-ember',
        default => 'bg-telemetry-line',
    };
@endphp

<x-card class="relative overflow-hidden">
    <span class="absolute right-0 top-0 h-full w-1 {{ $ringClass }}" aria-hidden="true"></span>
    <p class="relative telemetry-label">{{ $title }}</p>
    <p class="relative mt-2 text-4xl telemetry-value {{ $colorClass }}">
        {{ $score ?? '--' }}
    </p>
</x-card>
