<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ $plan->name }}
        </h2>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-4xl space-y-5">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <a href="{{ route('training') }}" class="text-sm font-bold text-raga-primary hover:text-raga-accent transition">← Kembali ke Training</a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('training.calendar') }}" class="text-sm font-bold text-raga-primary hover:text-raga-accent transition">Lihat Kalender →</a>
                    <button type="button"
                        onclick="if (confirm('Hapus training plan "{{ $plan->name }}"? Semua minggu dan workout terencana di dalamnya akan ikut terhapus.')) document.getElementById('delete-plan-form').submit();"
                        class="text-sm font-semibold text-raga-low hover:underline">
                        Hapus Plan
                    </button>
                </div>
            </div>

            <form id="delete-plan-form" method="POST" action="{{ route('training.plan.destroy', $plan) }}" class="hidden">
                @csrf
                @method('DELETE')
            </form>

            <x-card>
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="text-lg font-black text-gray-900 dark:text-gray-100">{{ $plan->name }}</p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ ucfirst($plan->status) }} ·
                            {{ $plan->start_date->translatedFormat('d M Y') }} – {{ $plan->target_date->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="font-bold text-gray-900 dark:text-gray-100">{{ $plan->weeks->count() }} minggu</p>
                        <p class="text-xs text-gray-400">
                            {{ $plan->weeks->sum(fn ($w) => $w->days->count()) }} hari ·
                            {{ $plan->weeks->flatMap->days->sum(fn ($d) => $d->plannedWorkouts->count()) }} workout terencana
                        </p>
                    </div>
                </div>
            </x-card>

            @forelse ($plan->weeks as $week)
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">Minggu {{ $week->week_number }}</p>
                        <p class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                            {{ $week->start_date->translatedFormat('d M') }} – {{ $week->end_date->translatedFormat('d M Y') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        @forelse ($week->days as $day)
                            <div class="rounded-xl border border-gray-100 dark:border-gray-700 p-3">
                                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                    {{ $day->date->translatedFormat('D, d M') }}
                                </p>
                                @forelse ($day->plannedWorkouts as $wo)
                                    @php
                                        $icon = \App\Support\ActivityTypeIcon::icon($wo->type);
                                        $label = \App\Support\ActivityTypeIcon::label($wo->type);
                                        $pace = $wo->target_pace_seconds_per_km ? (int) round($wo->target_pace_seconds_per_km) : null;
                                    @endphp
                                    <div class="mt-2 flex flex-wrap items-start justify-between gap-2 rounded-lg bg-gray-50 dark:bg-gray-800/60 p-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">{{ $icon }}</span>
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                                    {{ $label }}
                                                    @if ($wo->intensity)
                                                        <span class="ml-1 text-xs font-medium text-raga-primary">({{ $wo->intensity }})</span>
                                                    @endif
                                                </p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                                    @if ($wo->distance_meters)
                                                        {{ number_format($wo->distance_meters / 1000, 2) }} km
                                                    @endif
                                                    @if ($wo->duration_minutes)
                                                        {{ $wo->duration_minutes }} menit
                                                    @endif
                                                    @if ($pace)
                                                        · {{ sprintf('%d:%02d', intdiv($pace, 60), $pace % 60) }}/km
                                                    @endif
                                                    @if ($wo->target_heart_rate_zone)
                                                        · Zona {{ $wo->target_heart_rate_zone }}
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                        @if ($wo->warm_up || $wo->main_set || $wo->cool_down || $wo->notes)
                                            <p class="max-w-md text-xs text-gray-500 dark:text-gray-400 text-right">
                                                @if ($wo->warm_up)<span>Warm-up: {{ $wo->warm_up }}</span><br>@endif
                                                @if ($wo->main_set)<span>Main: {{ $wo->main_set }}</span><br>@endif
                                                @if ($wo->cool_down)<span>Cool-down: {{ $wo->cool_down }}</span><br>@endif
                                                @if ($wo->notes)<span>{{ $wo->notes }}</span>@endif
                                            </p>
                                        @endif
                                    </div>
                                @empty
                                    <p class="mt-1 text-xs text-gray-400">Rest day</p>
                                @endforelse
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Minggu ini tidak punya hari.</p>
                        @endforelse
                    </div>
                </x-card>
            @empty
                <x-card class="text-center py-10">
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Plan Ini Kosong</p>
                    <p class="mt-2 text-gray-500 dark:text-gray-400">Belum ada minggu yang tercatat untuk plan ini.</p>
                </x-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
