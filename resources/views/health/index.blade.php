<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Health') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500 dark:text-gray-400">Data terbaru dari Garmin Connect.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">❤️ Jantung &amp; Recovery</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Resting HR</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['resting_heart_rate'] ? round($latestVitals['resting_heart_rate']->value) : '--' }}<span class="text-sm font-semibold text-gray-400"> bpm</span></p>
                    </x-card>
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">HRV Semalam</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['hrv_overnight_avg'] ? round($latestVitals['hrv_overnight_avg']->value) : '--' }}<span class="text-sm font-semibold text-gray-400"> ms</span></p>
                    </x-card>
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Training Readiness</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['training_readiness'] ? round($latestVitals['training_readiness']->value) : '--' }}</p>
                    </x-card>
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">VO2 Max</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['vo2max'] ? round($latestVitals['vo2max']->value, 1) : '--' }}</p>
                    </x-card>
                </div>
            </div>

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">🫁 Napas &amp; Stress</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Stress</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['stress_avg'] ? round($latestVitals['stress_avg']->value) : '--' }}</p>
                    </x-card>
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Respirasi</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['respiration_rate'] ? round($latestVitals['respiration_rate']->value) : '--' }}<span class="text-sm font-semibold text-gray-400"> /min</span></p>
                    </x-card>
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">SpO2</p>
                        <p class="mt-1 text-2xl font-black text-gray-900 dark:text-gray-100">{{ $latestVitals['spo2_avg'] ? round($latestVitals['spo2_avg']->value) : '--' }}<span class="text-sm font-semibold text-gray-400">%</span></p>
                    </x-card>
                    <x-card class="!p-4">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Body Battery</p>
                        <p class="mt-1 text-lg font-black text-raga-excellent">+{{ $latestVitals['body_battery_charged'] ? round($latestVitals['body_battery_charged']->value) : 0 }}</p>
                        <p class="text-lg font-black text-raga-low">-{{ $latestVitals['body_battery_drained'] ? round($latestVitals['body_battery_drained']->value) : 0 }}</p>
                    </x-card>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-card>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">😴 Sleep Terakhir</p>
                    @if ($sleep)
                        @php $minutes = $sleep->totalDurationMinutes(); @endphp
                        <p class="mt-2 text-3xl font-black text-gray-900 dark:text-gray-100">
                            {{ intdiv((int) $minutes, 60) }}h {{ (int) $minutes % 60 }}m
                        </p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $sleep->bedtime->translatedFormat('d M, H:i') }} → {{ $sleep->wake_time->translatedFormat('H:i') }}</p>
                        @if ($sleep->sleep_score)
                            <p class="mt-1 text-sm font-semibold text-raga-primary">Sleep score: {{ $sleep->sleep_score }}</p>
                        @endif
                    @else
                        <p class="mt-2 text-gray-400 dark:text-gray-500">Belum ada data tidur dari Garmin — kemungkinan watch tidak dipakai tidur.</p>
                    @endif
                </x-card>

                <x-card>
                    <p class="text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">⚖️ Body</p>
                    @if ($bodyMeasurement)
                        <p class="mt-2 text-3xl font-black text-gray-900 dark:text-gray-100">{{ $bodyMeasurement->weight_kg ? round($bodyMeasurement->weight_kg, 1) : '--' }}<span class="text-base font-semibold text-gray-400"> kg</span></p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            @if ($bodyMeasurement->bmi) BMI {{ round($bodyMeasurement->bmi, 1) }} @endif
                            @if ($bodyMeasurement->body_fat_percent) · Body fat {{ round($bodyMeasurement->body_fat_percent, 1) }}% @endif
                        </p>
                    @else
                        <p class="mt-2 text-gray-400 dark:text-gray-500">Belum ada data body composition</p>
                    @endif
                </x-card>
            </div>

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500">🏃 Aktivitas 7 Hari Terakhir</h3>
                <x-card class="!p-0 divide-y divide-gray-100 dark:divide-gray-700 overflow-hidden">
                    @forelse ($activityWeek as $day)
                        <div class="flex items-center justify-between px-5 py-3">
                            <span class="text-sm font-semibold text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Carbon::parse($day->date)->translatedFormat('D, d M') }}</span>
                            <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ number_format($day->steps) }} langkah</span>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-center text-gray-400">Belum ada data aktivitas</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
