<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="telemetry-value text-2xl sm:text-3xl lg:text-5xl leading-tight">
                    Notifikasi
                </h1>
                <p class="mt-1 text-sm font-medium text-telemetry-slate">
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

    <div class="py-4 sm:py-6 pb-16">
        <div class="px-4 sm:px-6 lg:px-8 max-w-3xl space-y-4">

            @if (session('status'))
                <div class="rounded border border-telemetry-emerald/20 bg-telemetry-emerald/10 px-4 py-3 text-sm font-semibold text-telemetry-emerald-deep">
                    {{ session('status') }}
                </div>
            @endif

            @if ($notifications->isEmpty())
                <x-card class="text-center py-12">
                    <p class="text-3xl">🔔</p>
                    <p class="mt-3 font-bold text-telemetry-ink">Belum ada notifikasi</p>
                    <p class="mt-1 text-sm text-telemetry-slate">
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
                                class="flex w-full items-start gap-3 rounded border p-4 text-left transition-colors hover:border-telemetry-line-strong {{ $notification['is_read']
                                    ? 'border-telemetry-line bg-telemetry-surface'
                                    : 'border-telemetry-ember/30 bg-telemetry-ember/5' }}"
                            >
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-telemetry-line bg-telemetry-well text-sm font-bold text-telemetry-slate">
                                    {{ $notification['actor_initials'] }}
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="flex items-start justify-between gap-2">
                                        <span class="block text-sm text-telemetry-slate {{ $notification['is_read'] ? 'font-medium' : 'font-bold text-telemetry-ink' }}">
                                            {{ $notification['message'] }}
                                        </span>
                                        @unless ($notification['is_read'])
                                            <x-chip variant="strain" class="shrink-0">Baru</x-chip>
                                        @endunless
                                    </span>

                                    <span class="mt-1 flex items-center gap-2 text-xs text-telemetry-slate">
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
