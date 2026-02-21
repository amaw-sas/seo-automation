<?php

namespace App\Services\ImageLLM;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class XAIImageProvider implements ImageLLMProvider
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'grok-image-beta')
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

    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->isAvailable()) {
            throw new \Exception('xAI API key not configured');
        }

        $quality = $options['quality'] ?? 'medium'; // low, medium, high

        // xAI Image API
        // Note: xAI doesn't support size parameter, images are generated in a fixed size
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ])->timeout(180)->post('https://api.x.ai/v1/images/generations', [
            'model' => $this->model,
            'prompt' => $prompt,
            'n' => 1,
            'quality' => $quality,
        ]);

        if (!$response->successful()) {
            throw new \Exception('xAI Image API error: ' . $response->body());
        }

        $data = $response->json();

        // Get image URL
        $imageUrl = $data['data'][0]['url'] ?? null;

        if (!$imageUrl) {
            throw new \Exception('No image URL returned from xAI API');
        }

        // Download and save image locally
        $savedUrl = $this->downloadAndSaveImage($imageUrl);

        return [
            'url' => $savedUrl,
            'cost' => $this->calculateCost($quality),
            'metadata' => [
                'provider' => 'xai',
                'model' => $this->model,
                'quality' => $quality,
                'original_url' => $imageUrl,
            ],
        ];
    }

    /**
     * Download image from URL and save to storage
     */
    private function downloadAndSaveImage(string $url): string
    {
        try {
            $imageContent = Http::timeout(60)->get($url)->body();

            $filename = 'generated/' . uniqid('img_') . '.png';
            Storage::disk('public')->put($filename, $imageContent);

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            // If download fails, return original URL
            return $url;
        }
    }

    /**
     * Calculate cost based on quality
     */
    private function calculateCost(string $quality): float
    {
        // Estimated xAI Grok Image pricing (as of Jan 2025)
        // Low quality: ~$0.01 per image
        // Medium quality: ~$0.02 per image
        // High quality: ~$0.04 per image

        return match($quality) {
            'low' => 0.01,
            'medium' => 0.02,
            'high' => 0.04,
            default => 0.02,
        };
    }
}
