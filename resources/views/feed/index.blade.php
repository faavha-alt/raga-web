<x-app-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
            {{ __('Feed') }}
        </h2>
        <p class="mt-1 text-sm font-medium text-gray-500">Aktivitas terbaru dari atlet yang Anda ikuti.</p>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-2xl space-y-5">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            @forelse ($activities as $workout)
                <x-activity-card :workout="$workout" :viewer="auth()->user()" />
            @empty
                <x-card class="text-center py-12">
                    <p class="text-lg font-bold text-gray-900 dark:text-gray-100">Feed masih kosong</p>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                        Ikuti atlet lain untuk melihat aktivitas mereka di sini.
                    </p>
                    <a href="{{ route('explore') }}"
                        class="mt-5 inline-flex items-center justify-center rounded-full bg-gradient-to-r from-raga-accent to-raga-primary px-6 py-2.5 text-sm font-bold text-white shadow-glow transition hover:brightness-110">
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
