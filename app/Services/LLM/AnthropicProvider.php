<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;

class AnthropicProvider implements LLMProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'claude-3-5-sonnet-20241022')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string
    {
        return 'anthropic';
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
            throw new \Exception('Anthropic API key not configured');
        }

        $maxTokens = $options['max_tokens'] ?? 4096;
        $temperature = $options['temperature'] ?? 0.7;

        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
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
            throw new \Exception('Anthropic API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['content'][0]['text'] ?? '',
            'usage' => [
                'prompt_tokens' => $data['usage']['input_tokens'] ?? 0,
                'completion_tokens' => $data['usage']['output_tokens'] ?? 0,
            ],
            'cost' => $this->calculateCost($data['usage'] ?? []),
        ];
    }

    private function calculateCost(array $usage): float
    {
        // Claude 3.5 Sonnet pricing (as of Jan 2025)
        // Input: $3 per million tokens
        // Output: $15 per million tokens
        $inputTokens = $usage['input_tokens'] ?? 0;
        $outputTokens = $usage['output_tokens'] ?? 0;

        $inputCost = ($inputTokens / 1_000_000) * 3.00;
        $outputCost = ($outputTokens / 1_000_000) * 15.00;

        return round($inputCost + $outputCost, 4);
    }
}
