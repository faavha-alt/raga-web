<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\User;
use App\Support\ActivityTypeIcon;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi "seseorang mengomentari aktivitas Anda".
 *
 * Tidak mengimplementasikan ShouldQueue — server produksi tidak punya queue
 * worker; pengiriman harus sinkron.
 */
class NewComment extends Notification
{
    public function __construct(
        private User $actor,
        private Comment $comment,
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
        $workout = $this->comment->workout;
        $title = $workout->name ?: ActivityTypeIcon::label($workout->type);

        return [
            'message' => $this->actor->name.' mengomentari '.$title.': '.mb_substr($this->comment->body, 0, 80),
            'url' => '/activities/'.$workout->id,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
        ];
    }
}
