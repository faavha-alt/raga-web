@props(['title', 'score' => null, 'category' => null])

@php
    $colorClass = match ($category?->value ?? null) {
        'excellent', 'very_good' => 'text-raga-excellent',
        'good' => 'text-raga-good',
        'moderate' => 'text-raga-moderate',
        'low' => 'text-raga-low',
        default => 'text-gray-400 dark:text-gray-500',
    };
@endphp

<x-card>
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $title }}</p>
    <p class="mt-1 text-4xl font-bold {{ $colorClass }}">
        {{ $score ?? '--' }}
    </p>
</x-card>
