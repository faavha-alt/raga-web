<?php

namespace App\Services\Ai\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Google has no official PHP SDK for the Gemini API (confirmed against
 * ai.google.dev/gemini-api/docs/libraries — Python/JS/Go/Java/C# only), so
 * this calls the documented REST endpoint directly.
 */
class GeminiProvider implements AiProvider
{
    private const ENDPOINT = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

    public function __construct(private string $apiKey, private string $model) {}

    public function sendMessage(string $systemPrompt, array $messages): string
    {
        $contents = array_map(fn (array $m) => [
            'role' => $m['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $m['content']]],
        ], $messages);

        $response = Http::timeout(60)->post(
            sprintf(self::ENDPOINT, $this->model).'?key='.urlencode($this->apiKey),
            [
                'system_instruction' => ['parts' => ['text' => $systemPrompt]],
                'contents' => $contents,
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException('Gemini request failed: '.($response->json('error.message') ?? $response->status()));
        }

        $text = $response->json('candidates.0.content.parts.0.text');

        if (! is_string($text)) {
            throw new RuntimeException('Gemini returned no text content.');
        }

        return $text;
    }
}
