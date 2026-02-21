<?php

namespace App\Services\ImageLLM;

class DallE3Provider implements ImageLLMProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'dall-e-3')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    public function getName(): string
    {
        return 'dalle3';
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function isAvailable(): bool
    {
        return !empty($this->apiKey);
    }

    public function generate(string $prompt, array $options = []): array
    {
        // TODO: Implement DALL-E 3 API integration
        // Endpoint: https://api.openai.com/v1/images/generations
        // Pricing: DALL-E 3 Standard (1024x1024): $0.040/image, HD: $0.080/image

        throw new \Exception('DALL-E 3 provider not yet implemented. Use xai provider instead.');
    }
}
