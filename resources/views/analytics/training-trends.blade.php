<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 leading-tight">
            {{ __('Training Trends') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Tren volume dan beban latihan kamu dari waktu ke waktu.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <x-card>
                <h3 class="mb-3 text-xs font-bold uppercase tracking-wider text-gray-400">Tren</h3>
                <x-health-trend-chart :series="$series" :ranges="[7, 30, 90, 182, 365]" />
            </x-card>

        </div>
    </div>
</x-app-layout>
