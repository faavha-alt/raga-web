<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('health') }}" class="telemetry-label transition-colors hover:text-telemetry-ink">← Health</a>
        <h1 class="mt-2 telemetry-value text-4xl sm:text-5xl">❤️ Heart Rate</h1>
    </x-slot>

    <div class="py-6 pb-16">
        <x-health-detail :series="$series" :baselines="$baselines" :metrics="$metrics" :daily-rows="$dailyRows" :disclaimer="$disclaimer" />
    </div>
</x-app-layout>
