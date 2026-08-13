<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Training') }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            @if ($plans->isEmpty())
                <x-card class="text-center py-10">
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Belum Ada Training Plan</p>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Buat training plan untuk lihat jadwal mingguan kamu di sini.</p>
                </x-card>
            @else
                <div class="space-y-4">
                    @foreach ($plans as $plan)
                        <x-card>
                            <p class="font-bold text-gray-900 dark:text-gray-100">{{ $plan->name }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ ucfirst($plan->status) }}</p>
                        </x-card>
                    @endforeach
                </div>
            @endif

            @if ($personalRecords->isNotEmpty())
                <div>
                    <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">🏆 Personal Records</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords as $pr)
                            <x-card class="!p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $pr->label() }}</p>
                                <p class="mt-1 text-xl font-black text-gray-900 dark:text-gray-100">{{ $pr->formattedValue() }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                </div>
            @endif

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">📋 Aktivitas Terakhir</h3>
                <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden">
                    @forelse ($recentWorkouts as $workout)
                        @php
                            $icon = match (true) {
                                str_contains($workout->type, 'run') || $workout->type === 'walking' => '🏃',
                                str_contains($workout->type, 'bik') || str_contains($workout->type, 'cycl') => '🚴',
                                str_contains($workout->type, 'swim') => '🏊',
                                str_contains($workout->type, 'strength') => '🏋️',
                                default => '💪',
                            };
                            $pace = $workout->average_pace_seconds_per_km;
                        @endphp
                        <div class="flex items-center justify-between px-5 py-3.5">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">{{ $icon }}</span>
                                <div>
                                    <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ ucwords(str_replace('_', ' ', $workout->type)) }}</p>
                                    <p class="text-xs text-gray-400">{{ $workout->start_date->translatedFormat('d M Y, H:i') }}</p>
                                </div>
                            </div>
                            <div class="text-right text-sm">
                                @if ($workout->distance_meters)
                                    <p class="font-bold text-gray-900 dark:text-gray-100">{{ number_format($workout->distance_meters / 1000, 2) }} km</p>
                                @endif
                                <p class="text-xs text-gray-400">
                                    @if ($pace) {{ sprintf('%d:%02d', intdiv((int) $pace, 60), $pace % 60) }}/km @endif
                                    @if ($workout->average_heart_rate) · {{ round($workout->average_heart_rate) }} bpm @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-gray-400">Belum ada aktivitas</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
