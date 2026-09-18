<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">{{ $plan->name }}</h1>
            <p class="mt-1 text-sm font-medium text-telemetry-slate">Detail rencana latihan, minggu, dan workout terencana.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-4xl space-y-3 sm:space-y-5">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-[rgba(0,184,101,0.08)] px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <a href="{{ route('training') }}" class="telemetry-label inline-flex items-center min-h-11 sm:min-h-0 transition-colors hover:text-telemetry-ink">← Kembali ke Training</a>
                <div class="flex items-center gap-3">
                    <a href="{{ route('training.calendar') }}" class="telemetry-label inline-flex items-center min-h-11 sm:min-h-0 transition-colors hover:text-telemetry-ink">Lihat Kalender →</a>
                    <button type="button"
                        onclick="if (confirm('Hapus training plan "{{ $plan->name }}"? Semua minggu dan workout terencana di dalamnya akan ikut terhapus.')) document.getElementById('delete-plan-form').submit();"
                        class="font-display text-[10px] font-bold uppercase tracking-[0.12em] text-telemetry-ember-deep hover:underline">
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
                        <p class="telemetry-value text-lg">{{ $plan->name }}</p>
                        <p class="mt-1 text-sm text-telemetry-slate">
                            {{ ucfirst($plan->status) }} ·
                            {{ $plan->start_date->translatedFormat('d M Y') }} – {{ $plan->target_date->translatedFormat('d M Y') }}
                        </p>
                    </div>
                    <div class="text-right text-sm">
                        <p class="telemetry-value">{{ $plan->weeks->count() }} minggu</p>
                        <p class="mt-0.5 text-[11px] text-telemetry-slate">
                            {{ $plan->weeks->sum(fn ($w) => $w->days->count()) }} hari ·
                            {{ $plan->weeks->flatMap->days->sum(fn ($d) => $d->plannedWorkouts->count()) }} workout terencana
                        </p>
                    </div>
                </div>

                @php
                    $totalWo = $plan->totalPlannedWorkouts();
                    $doneWo = $plan->completedPlannedWorkouts();
                    $woPercent = $totalWo > 0 ? (int) round(($doneWo / $totalWo) * 100) : 0;
                @endphp
                <div class="mt-4 border-t border-telemetry-line pt-3">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span class="telemetry-label">Progress Plan</span>
                        <span class="telemetry-value">{{ $doneWo }} / {{ $totalWo }} selesai ({{ $woPercent }}%)</span>
                    </div>
                    <div class="mt-2 h-1 w-full bg-telemetry-well">
                        <div class="h-full bg-telemetry-ink transition-all" style="width: {{ $woPercent }}%"></div>
                    </div>
                </div>
            </x-card>

            @forelse ($plan->weeks as $week)
                <x-card>
                    <div class="mb-4 flex items-center justify-between">
                        <p class="telemetry-label">Minggu {{ $week->week_number }}</p>
                        <p class="telemetry-value text-sm">
                            {{ $week->start_date->translatedFormat('d M') }} – {{ $week->end_date->translatedFormat('d M Y') }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        @forelse ($week->days as $day)
                            <div class="rounded border border-telemetry-line p-3">
                                <p class="telemetry-value text-sm">
                                    {{ $day->date->translatedFormat('D, d M') }}
                                </p>
                                @forelse ($day->plannedWorkouts as $wo)
                                    @php
                                        $icon = \App\Support\ActivityTypeIcon::icon($wo->type);
                                        $label = \App\Support\ActivityTypeIcon::label($wo->type);
                                        $pace = $wo->target_pace_seconds_per_km ? (int) round($wo->target_pace_seconds_per_km) : null;
                                    @endphp
                                    <div class="mt-2 flex flex-wrap items-start justify-between gap-2 rounded bg-telemetry-well p-2.5 {{ $wo->completedWorkout ? 'opacity-70' : '' }}">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">{{ $icon }}</span>
                                            <div>
                                                <p class="text-sm font-semibold text-telemetry-ink">
                                                    {{ $label }}
                                                    @if ($wo->completedWorkout)
                                                        <span class="ml-1 inline-flex h-5 items-center rounded border border-telemetry-emerald/20 bg-[rgba(0,184,101,0.08)] px-2 text-[10px] font-bold uppercase tracking-[0.08em] text-telemetry-emerald-deep">✓ Selesai</span>
                                                    @endif
                                                    @if ($wo->intensity)
                                                        <span class="ml-1 text-xs font-medium text-telemetry-chrono-deep">({{ $wo->intensity }})</span>
                                                    @endif
                                                </p>
                                                <p class="mt-0.5 text-[11px] text-telemetry-slate">
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
                                        <div class="flex flex-col items-end gap-1.5">
                                            @if ($wo->warm_up || $wo->main_set || $wo->cool_down || $wo->notes)
                                                <p class="max-w-md text-right text-[11px] text-telemetry-slate">
                                                    @if ($wo->warm_up)<span>Warm-up: {{ $wo->warm_up }}</span><br>@endif
                                                    @if ($wo->main_set)<span>Main: {{ $wo->main_set }}</span><br>@endif
                                                    @if ($wo->cool_down)<span>Cool-down: {{ $wo->cool_down }}</span><br>@endif
                                                    @if ($wo->notes)<span>{{ $wo->notes }}</span>@endif
                                                </p>
                                            @endif
                                            <form method="POST" action="{{ route('training.planned-workout.toggle', $wo) }}">
                                                @csrf
                                                <label class="inline-flex cursor-pointer select-none items-center gap-1.5 text-[11px] font-semibold text-telemetry-slate">
                                                    <input type="checkbox" {{ $wo->completedWorkout ? 'checked' : '' }} onchange="this.form.submit()" class="rounded border-telemetry-line-strong text-telemetry-ember focus:ring-telemetry-ember" />
                                                    Selesai
                                                </label>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <p class="mt-1 text-[11px] text-telemetry-slate">Rest day</p>
                                @endforelse
                            </div>
                        @empty
                            <p class="text-sm text-telemetry-slate">Minggu ini tidak punya hari.</p>
                        @endforelse
                    </div>
                </x-card>
            @empty
                <x-card class="text-center py-10">
                    <p class="telemetry-value text-lg">Plan Ini Kosong</p>
                    <p class="mt-2 text-sm text-telemetry-slate">Belum ada minggu yang tercatat untuk plan ini.</p>
                </x-card>
            @endforelse
        </div>
    </div>
</x-app-layout>
