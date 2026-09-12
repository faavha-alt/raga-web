<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-4xl sm:text-5xl">
            {{ __('Training Trends') }}
        </h1>
        <p class="mt-2 text-sm font-medium text-telemetry-slate">Tren volume dan beban latihan kamu dari waktu ke waktu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 space-y-6">

            <x-card>
                <h3 class="mb-3 telemetry-label-lg text-telemetry-ink">Tren</h3>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 182, 365]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
