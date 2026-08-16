<?php

namespace App\Services\Ai\Providers;

interface AiProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages  Chronological, alternating user/assistant turns.
     *
     * @throws \RuntimeException on any provider-side failure (auth, quota, network).
     */
    public function sendMessage(string $systemPrompt, array $messages): string;
}
