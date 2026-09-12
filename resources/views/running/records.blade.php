<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="telemetry-value text-4xl sm:text-5xl">{{ __('Running Records') }}</h1>
            <p class="mt-2 text-sm font-medium text-telemetry-slate">Personal records dan lari terjauh kamu.</p>
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <p class="mb-3 telemetry-label-lg text-telemetry-ink">Personal Records</p>
                @if ($personalRecords->isEmpty())
                    <x-card class="text-center py-8">
                        <p class="text-sm text-telemetry-slate">Belum ada personal record tersinkron.</p>
                    </x-card>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords as $pr)
                            <x-card class="!p-4">
                                <p class="telemetry-label">{{ $pr->label() }}</p>
                                <p class="mt-1.5 telemetry-value text-xl">{{ $pr->formattedValue() }}</p>
                                <p class="mt-1 text-[11px] text-telemetry-slate">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <p class="mb-3 telemetry-label-lg text-telemetry-ink">Lari Terjauh</p>
                <x-card class="!p-0 divide-y divide-telemetry-line overflow-hidden">
                    @forelse ($longestRuns as $run)
                        <a href="{{ route('activities.show', $run) }}" class="flex items-center justify-between px-5 py-3.5 transition-colors hover:bg-telemetry-well">
                            <div>
                                <p class="telemetry-value text-sm">{{ number_format($run->distance_meters / 1000, 2) }} km</p>
                                <p class="mt-0.5 text-[11px] text-telemetry-slate">{{ $run->start_date->translatedFormat('d M Y') }}</p>
                            </div>
                            <p class="telemetry-value text-sm">{{ round($run->durationSeconds() / 60) }} min</p>
                        </a>
                    @empty
                        <div class="px-5 py-6 text-center text-sm text-telemetry-slate">Belum ada aktivitas lari.</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
