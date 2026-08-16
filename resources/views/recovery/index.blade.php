<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Recovery & Readiness') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Seberapa pulih tubuh kamu, dan seberapa siap untuk hari ini.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded-2xl bg-gray-50 border border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500">
                ℹ️ {{ $disclaimer }} Skor ini bukan angka medis — cuma cara transparan buat lihat pola tubuh kamu sendiri.
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                @foreach ([
                    ['title' => 'Recovery Score', 'icon' => '🔄', 'data' => $recovery],
                    ['title' => 'Readiness Score', 'icon' => '⚡', 'data' => $readiness],
                ] as $card)
                    @php
                        $model = $card['data']['model'];
                        $category = $model->category();
                        $categoryClasses = match ($category->value) {
                            'excellent', 'very_good' => 'bg-emerald-50 text-emerald-600',
                            'good' => 'bg-sky-50 text-sky-600',
                            'moderate' => 'bg-amber-50 text-amber-600',
                            default => 'bg-rose-50 text-rose-600',
                        };
                    @endphp
                    <x-card>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-raga-accent to-raga-primary text-white text-base shadow-glow">{{ $card['icon'] }}</span>
                                <p class="text-sm font-bold text-gray-500">{{ $card['title'] }}</p>
                            </div>
                            <span class="rounded-full px-3 py-1 text-xs font-bold {{ $categoryClasses }}">{{ $category->label() }}</span>
                        </div>
                        <p class="mt-4 text-6xl font-black text-gray-900 tracking-tight">{{ $model->score }}</p>

                        <div class="mt-5 pt-4 border-t border-gray-100 space-y-2">
                            @foreach ($card['data']['breakdown'] as $factor)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-gray-500">{{ $factor['label'] }}</span>
                                    @if ($factor['insufficient_data'])
                                        <span class="text-xs font-medium text-gray-300">Belum cukup data</span>
                                    @else
                                        <span class="font-bold tabular-nums {{ $factor['contribution'] > 0 ? 'text-raga-excellent' : ($factor['contribution'] < 0 ? 'text-raga-low' : 'text-gray-400') }}">
                                            {{ $factor['contribution'] > 0 ? '+' : '' }}{{ $factor['contribution'] }}
                                        </span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </x-card>
                @endforeach
            </div>

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Riwayat</h3>
                <x-health-trend-chart :series="$trendSeries" :ranges="[7, 30, 90]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
