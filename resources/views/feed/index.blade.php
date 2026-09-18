<x-app-layout>
    <x-slot name="header">
        <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">
            {{ __('Feed') }}
        </h1>
        <p class="mt-1 text-sm font-medium text-telemetry-slate">Aktivitas terbaru dari atlet yang Anda ikuti.</p>
    </x-slot>

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl space-y-3 sm:space-y-5">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            @forelse ($activities as $workout)
                <x-activity-card :workout="$workout" :viewer="auth()->user()" />
            @empty
                <x-card class="py-8 text-center sm:py-12">
                    <p class="text-lg font-bold text-telemetry-ink">Feed masih kosong</p>
                    <p class="mt-2 text-sm text-telemetry-slate">
                        Ikuti atlet lain untuk melihat aktivitas mereka di sini.
                    </p>
                    <a href="{{ route('explore') }}"
                        class="mt-5 inline-flex items-center justify-center min-h-11 sm:min-h-0 rounded bg-telemetry-ember px-6 py-2.5 text-sm font-bold text-white transition-colors hover:bg-telemetry-ember-dark">
                        Jelajahi Atlet
                    </a>
                </x-card>
            @endforelse

            @if ($activities->hasPages())
                <div>{{ $activities->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
