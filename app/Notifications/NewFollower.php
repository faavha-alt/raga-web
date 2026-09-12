<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi "pengguna baru mengikuti Anda".
 *
 * Sengaja TIDAK mengimplementasikan ShouldQueue: server produksi tidak punya
 * queue worker, jadi notifikasi harus terkirim sinkron lewat channel database.
 */
class NewFollower extends Notification
{
    public function __construct(private User $actor) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{message: string, url: string, actor_id: int, actor_name: string}
     */
    public function toArray(object $notifiable): array
    {
        $url = $this->actor->username
            ? '/@'.$this->actor->username
            : '/feed';

        return [
            'message' => $this->actor->name.' mulai mengikuti Anda.',
            'url' => $url,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
