{{--
    Lonceng notifikasi.

    Aman dipasang di semua halaman: jumlah belum dibaca dihitung dengan satu
    query count() lewat relasi, bukan dengan memuat seluruh notifikasi.

    Pemakaian (di resources/views/layouts/navigation.blade.php):
        <x-notification-bell />
--}}

@php
    $unreadCount = auth()->check() ? auth()->user()->unreadNotifications()->count() : 0;
@endphp

<a
    href="{{ route('notifications.index') }}"
    title="Notifikasi"
    aria-label="Notifikasi"
    class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-200"
>
    <svg class="h-5 w-5" stroke="currentColor" fill="none" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0a3 3 0 11-6 0" />
    </svg>

    @if ($unreadCount > 0)
        <span class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-raga-energy px-1 text-[10px] font-bold leading-none text-white">{{ $unreadCount > 99 ? '99+' : $unreadCount }}</span>
    @endif
</a>
