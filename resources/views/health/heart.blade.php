<x-app-layout>
    <x-slot name="header">
        <a href="{{ route('health') }}" class="text-sm font-bold text-gray-400 hover:text-gray-600 transition">← Health</a>
        <h2 class="mt-1 text-2xl font-extrabold text-gray-900 leading-tight">❤️ Heart &amp; HRV</h2>
    </x-slot>

    <div class="py-6 pb-16">
        <x-health-detail :series="$series" :baselines="$baselines" :metrics="$metrics" :daily-rows="$dailyRows" :disclaimer="$disclaimer" />
    </div>
</x-app-layout>
