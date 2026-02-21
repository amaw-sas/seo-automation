<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;

class XAIProvider implements LLMProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'grok-2-latest')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string
    {
        return 'xai';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function complete(string $prompt, array $options = []): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('xAI API key not configured');
        }

        $maxTokens = $options['max_tokens'] ?? 4096;
        $temperature = $options['temperature'] ?? 0.7;

        // xAI API uses OpenAI-compatible format
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.x.ai/v1/chat/completions', [
            'model' => $this->model,
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('xAI API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
            ],
            'cost' => $this->calculateCost($data['usage'] ?? []),
        ];
    }

    private function calculateCost(array $usage): float
    {
        // Grok pricing (as of Jan 2025)
        // Input: $5 per million tokens
        // Output: $15 per million tokens
        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;

        $inputCost = ($inputTokens / 1_000_000) * 5.00;
        $outputCost = ($outputTokens / 1_000_000) * 15.00;

        return round($inputCost + $outputCost, 4);
    }
}
