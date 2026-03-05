<?php

namespace App\Services\LLM;

use Illuminate\Support\Facades\Http;

class OpenAIProvider implements LLMProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-4-turbo')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string
    {
        return 'openai';
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
            throw new \Exception('OpenAI API key not configured');
        }

        $maxTokens  = $options['max_tokens']  ?? 4096;
        $temperature = $options['temperature'] ?? 0.7;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->post('https://api.openai.com/v1/chat/completions', [
            'model'       => $this->model,
            'max_tokens'  => $maxTokens,
            'temperature' => $temperature,
            'messages'    => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        $data = $response->json();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage'   => [
                'prompt_tokens'     => $data['usage']['prompt_tokens']     ?? 0,
                'completion_tokens' => $data['usage']['completion_tokens'] ?? 0,
            ],
            'cost' => $this->calculateCost($data['usage'] ?? []),
        ];
    }

    private function calculateCost(array $usage): float
    {
        // GPT-4 Turbo pricing: $10/1M input, $30/1M output
        $inputCost  = (($usage['prompt_tokens']     ?? 0) / 1_000_000) * 10.00;
        $outputCost = (($usage['completion_tokens'] ?? 0) / 1_000_000) * 30.00;

        return round($inputCost + $outputCost, 4);
    }
}
