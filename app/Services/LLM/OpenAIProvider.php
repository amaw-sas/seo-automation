<?php

namespace App\Services\LLM;

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
        // TODO: Implement OpenAI API integration
        // Use HTTP client similar to AnthropicProvider
        // Endpoint: https://api.openai.com/v1/chat/completions
        // Pricing: GPT-4 Turbo: $10/1M input, $30/1M output

        throw new \Exception('OpenAI provider not yet implemented. Use anthropic provider instead.');
    }
}
