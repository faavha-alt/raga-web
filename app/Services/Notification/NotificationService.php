<?php

namespace App\Services\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * Pusat notifikasi in-app.
 *
 * Service ini sengaja tidak bergantung pada kelas notifikasi konkret
 * (App\Notifications\*). Setiap notifikasi hanya dibaca lewat array `data`
 * yang sudah disepakati: message, url, actor_id, actor_name. Kunci yang
 * hilang selalu diganti fallback agar baris tetap bisa dirender.
 */
class NotificationService
{
    public const PER_PAGE = 25;

    /**
     * Notifikasi milik $user, terbaru lebih dulu.
     *
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function paginateFor(User $user): LengthAwarePaginator
    {
        return $user->notifications()
            ->latest()
            ->paginate(self::PER_PAGE);
    }

    /**
     * Jumlah notifikasi belum dibaca — satu query count, tanpa memuat model.
     */
    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    public function markRead(DatabaseNotification $notification): void
    {
        $notification->markAsRead();
    }

    /**
     * Tandai semua notifikasi belum dibaca milik $user sebagai sudah dibaca.
     *
     * @return int jumlah baris yang diperbarui
     */
    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    /**
     * Ubah satu notifikasi menjadi baris siap-render.
     *
     * @return array{id: string, message: string, url: string, actor_name: string, actor_initials: string, is_read: bool, created_at: Carbon|null}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'message' => $this->stringOr($data['message'] ?? null, 'Ada aktivitas baru di RAGA.'),
            'url' => $this->safeUrl($data['url'] ?? null),
            'actor_name' => $this->stringOr($data['actor_name'] ?? null, 'Pengguna RAGA'),
            'actor_initials' => $this->initialsFrom($this->stringOr($data['actor_name'] ?? null, 'Pengguna RAGA')),
            'is_read' => $notification->read_at !== null,
            'created_at' => $notification->created_at,
        ];
    }

    private function stringOr(mixed $value, string $fallback): string
    {
        return is_string($value) && trim($value) !== '' ? $value : $fallback;
    }

    /**
     * Hanya izinkan path relatif di dalam aplikasi sebagai tujuan redirect.
     * Nilai aneh/absolut/protocol-relative dikembalikan ke halaman notifikasi.
     */
    private function safeUrl(mixed $url): string
    {
        if (! is_string($url) || $url === '' || ! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return route('notifications.index');
        }

        return $url;
    }

    private function initialsFrom(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $initials !== '' ? $initials : '?';
    }
}
