<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-2xl font-extrabold text-gray-900 dark:text-white leading-tight">
                    Notifikasi
                </h2>
                <p class="mt-1 text-sm font-medium text-gray-500">
                    Kabar terbaru dari atlet yang berinteraksi denganmu.
                </p>
            </div>

            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <x-secondary-button type="submit">Tandai semua sudah dibaca</x-secondary-button>
                </form>
            @endif
        </div>
    </x-slot>

    <div class="py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-3xl space-y-4">

            @if (session('status'))
                <div class="rounded-2xl bg-raga-excellent/10 border border-raga-excellent/20 px-4 py-3 text-sm font-semibold text-raga-excellent">
                    {{ session('status') }}
                </div>
            @endif

            @if ($notifications->isEmpty())
                <x-card class="text-center py-12">
                    <p class="text-3xl">🔔</p>
                    <p class="mt-3 font-bold text-gray-900 dark:text-white">Belum ada notifikasi</p>
                    <p class="mt-1 text-sm text-gray-400">
                        Saat ada atlet yang mengikuti, memberi kudos, atau mengomentari aktivitasmu,
                        kabarnya akan muncul di sini.
                    </p>
                </x-card>
            @else
                <div class="space-y-3">
                    @foreach ($notifications as $notification)
                        <form method="POST" action="{{ route('notifications.read', $notification['id']) }}">
                            @csrf
                            <button
                                type="submit"
                                data-unread="{{ $notification['is_read'] ? 'false' : 'true' }}"
                                class="flex w-full items-start gap-3 rounded-3xl border p-4 text-left shadow-sm transition hover:shadow-md {{ $notification['is_read']
                                    ? 'border-gray-100 bg-white dark:border-gray-700/60 dark:bg-gray-800'
                                    : 'border-raga-accent/30 bg-raga-accent/5 dark:border-raga-accent/40 dark:bg-raga-accent/10' }}"
                            >
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-raga-accent to-raga-primary text-sm font-bold text-white">
                                    {{ $notification['actor_initials'] }}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="block text-sm text-gray-700 dark:text-gray-200 {{ $notification['is_read'] ? 'font-medium' : 'font-bold text-gray-900 dark:text-white' }}">
                                            {{ $notification['message'] }}
                                        </span>
                                        @unless ($notification['is_read'])
                                            <span class="shrink-0 rounded-full bg-raga-accent/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-raga-accent">
                                                Baru
                                            </span>
                                        @endunless
                                    </span>

                                    <span class="mt-1 flex items-center gap-2 text-xs text-gray-400">
                                        <span>{{ $notification['actor_name'] }}</span>
                                        <span aria-hidden="true">•</span>
                                        <span>{{ $notification['created_at']?->locale('id')->diffForHumans() ?? 'Baru saja' }}</span>
                                    </span>
                                </span>
                            </button>
                        </form>
                    @endforeach
                </div>

                <div>{{ $notifications->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
