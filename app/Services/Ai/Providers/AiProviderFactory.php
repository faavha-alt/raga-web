<?php

namespace App\Services\Ai\Providers;

use InvalidArgumentException;

class AiProviderFactory
{
    public static function make(string $provider, string $apiKey, ?string $model, int $maxTokens = 4096): AiProvider
    {
        $resolvedModel = $model ?: config("ai.providers.{$provider}.default_model");

        if ($resolvedModel === null) {
            throw new InvalidArgumentException("Unsupported AI provider: {$provider}");
        }

        return match ($provider) {
            'anthropic' => new AnthropicProvider($apiKey, $resolvedModel, $maxTokens),
            'gemini' => new GeminiProvider($apiKey, $resolvedModel, $maxTokens),
            default => throw new InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }
}
