<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiCoachService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiController extends Controller
{
    public function __construct(private AiCoachService $coach) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = $user->aiConversations()->latest('updated_at')->get();

        $activeId = (int) $request->query('conversation');
        $conversation = $activeId
            ? $conversations->firstWhere('id', $activeId)
            : $conversations->first();

        $messages = $conversation ? $conversation->messages()->orderBy('timestamp')->get() : collect();

        return view('ai.index', [
            'conversations' => $conversations,
            'conversation' => $conversation,
            'messages' => $messages,
            'configured' => filled(config('services.anthropic.api_key')),
        ]);
    }

    public function sendMessage(Request $request): array
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $conversation = $data['conversation_id']
            ? $user->aiConversations()->findOrFail($data['conversation_id'])
            : $user->aiConversations()->create([
                'title' => str($data['message'])->limit(50)->toString(),
            ]);

        $reply = $this->coach->reply($user, $conversation, $data['message']);

        $conversation->touch();

        return [
            'conversation_id' => $conversation->id,
            'reply' => $reply,
        ];
    }
}
