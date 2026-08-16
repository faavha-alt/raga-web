<?php

namespace App\Http\Controllers;

use App\Services\Ai\AiCoachService;
use App\Services\Ai\AiNotConfiguredException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

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
            'configured' => filled($user->aiSetting?->api_key),
        ]);
    }

    public function sendMessage(Request $request): JsonResponse
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

        try {
            $reply = $this->coach->reply($user, $conversation, $data['message']);
        } catch (AiNotConfiguredException $e) {
            return response()->json(['error' => 'not_configured', 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 'provider_error', 'message' => 'AI Coach sedang bermasalah, coba lagi sebentar lagi.'], 502);
        }

        $conversation->touch();

        return response()->json([
            'conversation_id' => $conversation->id,
            'reply' => $reply,
        ]);
    }
}
