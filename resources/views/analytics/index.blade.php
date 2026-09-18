<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl">
            {{ __('Analytics') }}
        </h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">Hubungan antara data kesehatan dan latihan kamu.</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-3 sm:space-y-6">

            <div class="grid sm:grid-cols-2 gap-4">
                <x-card>
                    <div class="flex items-end justify-between gap-4 mb-2">
                        <p class="telemetry-label">📊 Health Trends</p>
                        <a href="{{ route('analytics.health_trends') }}" class="telemetry-label transition-colors hover:text-telemetry-ink">Lihat →</a>
                    </div>
                    <p class="text-sm text-telemetry-slate">Tren resting HR, stress, body battery, dan training load.</p>
                </x-card>

                <x-card>
                    <div class="flex items-end justify-between gap-4 mb-2">
                        <p class="telemetry-label">🏋️ Training Trends</p>
                        <a href="{{ route('analytics.training_trends') }}" class="telemetry-label transition-colors hover:text-telemetry-ink">Lihat →</a>
                    </div>
                    <p class="text-sm text-telemetry-slate">Tren jarak, durasi, elevasi, jumlah aktivitas, dan training load.</p>
                </x-card>
            </div>

            <div>
                <h3 class="mb-3 telemetry-label-lg text-telemetry-ink">🔗 Hubungan Antar Data (90 Hari Terakhir)</h3>
                <div class="space-y-3">
                    @foreach ($relationships as $rel)
                        <a href="{{ route('analytics.relationship', ['pair' => $rel['slug']]) }}" class="block">
                            <x-card>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-bold text-telemetry-ink">{{ $rel['title'] }}</p>
                                    @unless ($rel['sufficient_data'])
                                        <x-chip class="shrink-0">Belum Cukup Data</x-chip>
                                    @endunless
                                </div>
                                <p class="mt-1 text-sm text-telemetry-slate">{{ $rel['description'] }}</p>
                            </x-card>
                        </a>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
