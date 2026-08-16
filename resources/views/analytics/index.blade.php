<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Analytics') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Hubungan antara data kesehatan dan latihan kamu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="grid sm:grid-cols-2 gap-4">
                <x-card>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">📊 Health Trends</p>
                        <a href="{{ route('analytics.health_trends') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat →</a>
                    </div>
                    <p class="text-sm text-gray-500">Tren resting HR, HRV, stress, tidur, body battery, dan SpO2.</p>
                </x-card>

                <x-card>
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-xs font-bold uppercase tracking-wider text-gray-400">🏋️ Training Trends</p>
                        <a href="{{ route('analytics.training_trends') }}" class="text-xs font-bold text-raga-primary hover:text-raga-accent transition">Lihat →</a>
                    </div>
                    <p class="text-sm text-gray-500">Tren jarak, durasi, elevasi, jumlah aktivitas, dan training load.</p>
                </x-card>
            </div>

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">🔗 Hubungan Antar Data (90 Hari Terakhir)</h3>
                <div class="space-y-3">
                    @foreach ($relationships as $rel)
                        <a href="{{ route('analytics.relationship', ['pair' => $rel['slug']]) }}" class="block">
                            <x-card class="hover:shadow-md transition">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-bold text-gray-900">{{ $rel['title'] }}</p>
                                    @unless ($rel['sufficient_data'])
                                        <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold bg-gray-100 text-gray-400">Belum Cukup Data</span>
                                    @endunless
                                </div>
                                <p class="mt-1 text-sm text-gray-500">{{ $rel['description'] }}</p>
                            </x-card>
                        </a>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
