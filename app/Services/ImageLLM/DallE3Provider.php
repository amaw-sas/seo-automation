<?php

namespace App\Services\ImageLLM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

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
        if (!$this->isAvailable()) {
            throw new \Exception('OpenAI API key not configured');
        }

        $size    = $options['size']    ?? '1024x1024'; // 1024x1024, 1024x1792, 1792x1024
        $quality = $options['quality'] ?? 'standard';  // standard, hd

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(120)->post('https://api.openai.com/v1/images/generations', [
            'model'           => $this->model,
            'prompt'          => $prompt,
            'n'               => 1,
            'size'            => $size,
            'quality'         => $quality,
            'response_format' => 'url',
        ]);

        if (!$response->successful()) {
            throw new \Exception('DALL-E 3 API error: ' . $response->body());
        }

        $data = $response->json();

        $imageUrl = $data['data'][0]['url'] ?? null;

        if (!$imageUrl) {
            throw new \Exception('No image URL returned from DALL-E 3 API');
        }

        $savedUrl = $this->downloadAndSaveImage($imageUrl);

        return [
            'url'      => $savedUrl,
            'cost'     => $this->calculateCost($quality),
            'metadata' => [
                'provider'     => 'dalle3',
                'model'        => $this->model,
                'size'         => $size,
                'quality'      => $quality,
                'original_url' => $imageUrl,
            ],
        ];
    }

    private function downloadAndSaveImage(string $url): string
    {
        try {
            $imageContent = Http::timeout(60)->get($url)->body();

            $filename = 'generated/' . uniqid('img_') . '.png';
            Storage::disk('public')->put($filename, $imageContent);

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            return $url;
        }
    }

    private function calculateCost(string $quality): float
    {
        // DALL-E 3 pricing (1024x1024): Standard $0.040, HD $0.080
        return match($quality) {
            'hd'    => 0.080,
            default => 0.040,
        };
    }
}
