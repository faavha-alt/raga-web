<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ai_conversation_id', 'role', 'content', 'timestamp'])]
class AiMessage extends Model
{
    protected $table = 'ai_messages';

    protected function casts(): array
    {
        return ['timestamp' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
