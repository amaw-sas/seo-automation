<?php

namespace App\Services\LLM;

class GoogleAIProvider implements LLMProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gemini-2.0-flash-exp')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string
    {
        return 'google';
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
        // TODO: Implement Google AI (Gemini) API integration
        // Endpoint: https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
        // Pricing: Gemini 2.0 Flash: Free tier available, then $0.075/1M input, $0.30/1M output

        throw new \Exception('Google AI provider not yet implemented. Use anthropic provider instead.');
    }
}
