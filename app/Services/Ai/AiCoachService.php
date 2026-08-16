<?php

namespace App\Services\Ai;

use Anthropic\Client;
use App\Models\AiConversation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Talks to Claude on behalf of the in-app AI coach. Every call rebuilds the
 * user's data context fresh (via AiContextBuilder) and puts it in the system
 * prompt, never in a raw form the model has to go dig for — the model only
 * ever sees the same structured summaries the app's own pages render from.
 */
class AiCoachService
{
    private const MAX_TOKENS = 2048;

    private const HISTORY_TURNS = 20;

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are the RAGA Coach — an AI health and performance coach built into
        the RAGA training app. You answer questions using the user's own stored
        training, health, sleep, and recovery data, provided below as
        structured JSON (RAGA_CONTEXT).

        How to use RAGA_CONTEXT:
        - It is a machine-generated summary already computed by the app (scores,
          trends, personal baselines) — not a raw database dump. Treat it as
          ground truth for this user; do not invent numbers not present in it.
        - `disclaimer` inside the context is the app's own personal-baseline
          disclaimer — factor its meaning into how confidently you compare the
          user against "normal" ranges.

        Rules you must always follow:
        1. Clearly separate DATA from INFERENCE. State the numbers first, then
           label your interpretation as such (e.g. "Your HRV is 42ms, down 15%
           from last week — that data point. My read: this could mean
           accumulated fatigue or incomplete recovery — that's inference, not
           certainty.").
        2. Never diagnose a medical condition or disease, and never name one as
           a likely cause. If a pattern looks medically concerning (persistently
           elevated resting HR, very low HRV, unexplained symptoms), say plainly
           that you're not able to evaluate that and recommend seeing a doctor —
           do not speculate about what condition it might be.
        3. Never state health or performance claims with medical certainty. Use
           calibrated language: "may indicate", "is consistent with", "tends to
           suggest" — never "this means" or "this is caused by" for anything
           physiological.
        4. Always explain the reasoning behind a recommendation, tied to the
           specific data point(s) that led to it. Never give a bare verdict.
        5. If the data needed to answer confidently isn't in RAGA_CONTEXT (a
           metric is null, a history window is too short, `garmin_connected` is
           false), say so explicitly rather than filling the gap with a guess.
        6. Keep answers focused and conversational — a few short paragraphs, not
           a report. This is a chat, not a printout of the context.

        RAGA_CONTEXT:
        %s
        PROMPT;

    public function __construct(
        private Client $client,
        private AiContextBuilder $contextBuilder,
        private string $model,
    ) {}

    public function reply(User $user, AiConversation $conversation, string $userMessage): string
    {
        $context = $this->contextBuilder->buildFor($user);
        $system = sprintf(self::SYSTEM_PROMPT, json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $conversation->messages()->create([
            'role' => 'user',
            'content' => $userMessage,
            'timestamp' => now(),
        ]);

        $history = $conversation->messages()
            ->latest('timestamp')
            ->take(self::HISTORY_TURNS)
            ->get()
            ->sortBy('timestamp')
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        try {
            $response = $this->client->messages->create(
                model: $this->model,
                maxTokens: self::MAX_TOKENS,
                system: $system,
                messages: $history,
            );
        } catch (\Throwable $e) {
            Log::error('AI coach request failed', ['error' => $e->getMessage()]);

            throw $e;
        }

        $reply = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $reply .= $block->text;
            }
        }

        if ($reply === '') {
            $reply = "I couldn't generate a response for that — try rephrasing your question.";
        }

        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
            'timestamp' => now(),
        ]);

        return $reply;
    }
}
