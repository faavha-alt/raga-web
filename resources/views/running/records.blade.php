<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Running Records') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Personal records dan lari terjauh kamu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">🏆 Personal Records</h3>
                @if ($personalRecords->isEmpty())
                    <x-card class="text-center py-8">
                        <p class="text-gray-400">Belum ada personal record tersinkron.</p>
                    </x-card>
                @else
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach ($personalRecords as $pr)
                            <x-card class="!p-4">
                                <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400">{{ $pr->label() }}</p>
                                <p class="mt-1 text-xl font-black text-gray-900">{{ $pr->formattedValue() }}</p>
                                <p class="mt-0.5 text-xs text-gray-400">{{ $pr->achieved_date->translatedFormat('d M Y') }}</p>
                            </x-card>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">🏅 Lari Terjauh</h3>
                <x-card class="!p-0 divide-y divide-gray-100 overflow-hidden">
                    @forelse ($longestRuns as $run)
                        <a href="{{ route('activities.show', $run) }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-50 transition">
                            <div>
                                <p class="text-sm font-bold text-gray-900">{{ number_format($run->distance_meters / 1000, 2) }} km</p>
                                <p class="text-xs text-gray-400">{{ $run->start_date->translatedFormat('d M Y') }}</p>
                            </div>
                            <p class="text-xs text-gray-400">{{ round($run->durationSeconds() / 60) }} min</p>
                        </a>
                    @empty
                        <div class="px-5 py-6 text-center text-gray-400">Belum ada aktivitas lari.</div>
                    @endforelse
                </x-card>
            </div>

        </div>
    </div>
</x-app-layout>
