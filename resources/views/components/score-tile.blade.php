@props(['title', 'score' => null, 'category' => null])

@php
    $colorClass = match ($category?->value ?? null) {
        'excellent', 'very_good' => 'text-raga-excellent',
        'good' => 'text-raga-good',
        'moderate' => 'text-raga-moderate',
        'low' => 'text-raga-low',
        default => 'text-gray-300 dark:text-gray-600',
    };

    $ringClass = match ($category?->value ?? null) {
        'excellent', 'very_good' => 'ring-raga-excellent/20',
        'good' => 'ring-raga-good/20',
        'moderate' => 'ring-raga-moderate/20',
        'low' => 'ring-raga-low/20',
        default => 'ring-gray-100 dark:ring-gray-700',
    };
@endphp

<x-card class="relative overflow-hidden">
    <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full {{ $ringClass }} ring-8"></div>
    <p class="relative text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">{{ $title }}</p>
    <p class="relative mt-2 text-5xl font-black {{ $colorClass }}">
        {{ $score ?? '--' }}
    </p>
</x-card>
