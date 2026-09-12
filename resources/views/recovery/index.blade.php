<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl">
            {{ __('Recovery & Readiness') }}
        </h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Seberapa pulih tubuh kamu, dan seberapa siap untuk hari ini.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div class="rounded border border-telemetry-line bg-telemetry-well px-4 py-3 text-xs font-semibold text-telemetry-slate">
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
                            'excellent', 'very_good' => 'bg-[rgba(0,184,101,0.08)] text-telemetry-emerald-deep border border-telemetry-emerald/20',
                            'good' => 'bg-[rgba(0,112,243,0.08)] text-telemetry-chrono-deep border border-telemetry-chrono/20',
                            'moderate' => 'bg-[rgba(245,158,11,0.08)] text-telemetry-amber border border-telemetry-amber/20',
                            default => 'bg-[rgba(255,62,29,0.08)] text-telemetry-ember-deep border border-telemetry-ember/25',
                        };
                    @endphp
                    <x-card>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 items-center justify-center rounded border border-telemetry-line bg-telemetry-well text-base">{{ $card['icon'] }}</span>
                                <p class="text-sm font-bold text-telemetry-slate">{{ $card['title'] }}</p>
                            </div>
                            <span class="rounded px-2 py-1 text-[10px] font-bold uppercase tracking-[0.08em] {{ $categoryClasses }}">{{ $category->label() }}</span>
                        </div>
                        <p class="mt-4 telemetry-value text-6xl">{{ $model->score }}</p>

                        <div class="mt-5 pt-4 border-t border-telemetry-line space-y-2">
                            @foreach ($card['data']['breakdown'] as $factor)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-telemetry-slate">{{ $factor['label'] }}</span>
                                    @if ($factor['insufficient_data'])
                                        <span class="text-xs font-medium text-telemetry-slate/70">Belum cukup data</span>
                                    @else
                                        <span class="font-bold tabular-nums {{ $factor['contribution'] > 0 ? 'text-telemetry-emerald-deep' : ($factor['contribution'] < 0 ? 'text-telemetry-ember-deep' : 'text-telemetry-slate') }}">
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
                <h3 class="mb-3 telemetry-label-lg text-telemetry-ink">Riwayat</h3>
                <x-health-trend-chart :series="$trendSeries" :ranges="[7, 30, 90]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
