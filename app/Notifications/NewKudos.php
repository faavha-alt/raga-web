<?php

namespace App\Notifications;

use App\Models\User;
use App\Models\Workout;
use App\Support\ActivityTypeIcon;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi "seseorang memberi kudos pada aktivitas Anda".
 *
 * Tidak mengimplementasikan ShouldQueue — server produksi tidak punya queue
 * worker; pengiriman harus sinkron.
 */
class NewKudos extends Notification
{
    public function __construct(
        private User $actor,
        private Workout $workout,
    ) {}

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
        $title = $this->workout->name ?: ActivityTypeIcon::label($this->workout->type);

        return [
            'message' => $this->actor->name.' memberi kudos pada '.$title,
            'url' => '/activities/'.$this->workout->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
