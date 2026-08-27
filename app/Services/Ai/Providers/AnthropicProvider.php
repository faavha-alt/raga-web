<?php

namespace App\Services\Ai\Providers;

use Anthropic\Client;
use RuntimeException;
use Throwable;

class AnthropicProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private int $maxTokens = 4096,
    ) {}

    public function sendMessage(string $systemPrompt, array $messages): string
    {
        try {
            $response = (new Client(apiKey: $this->apiKey))->messages->create(
                model: $this->model,
                maxTokens: $this->maxTokens,
                system: $systemPrompt,
                messages: $messages,
            );
        } catch (Throwable $e) {
            throw new RuntimeException('Claude request failed: '.$e->getMessage(), previous: $e);
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return $text;
    }
}
