<?php

namespace App\Services\ImageLLM;

class ImageLLMProviderFactory
{
    /**
     * Create an Image LLM provider instance
     *
     * @param string $provider Provider name (dalle3, stable-diffusion, xai)
     * @return ImageLLMProvider
     * @throws \InvalidArgumentException
     */
    public static function make(string $provider): ImageLLMProvider
    {
        return match($provider) {
            'dalle3' => new DallE3Provider(
                config('services.openai.api_key'),
                config('services.openai.image_model', 'dall-e-3')
            ),
            'stable-diffusion' => new StableDiffusionProvider(
                config('services.stability.api_key'),
                config('services.stability.model', 'stable-diffusion-xl-1024-v1-0')
            ),
            'xai' => new XAIImageProvider(
                config('services.xai.api_key'),
                config('services.xai.image_model', 'grok-image-beta')
            ),
            default => throw new \InvalidArgumentException("Unsupported Image LLM provider: {$provider}"),
        };
    }

    /**
     * Get list of available providers
     */
    public static function getAvailableProviders(): array
    {
        $providers = ['dalle3', 'stable-diffusion', 'xai'];
        $available = [];

        foreach ($providers as $provider) {
            try {
                $instance = self::make($provider);
                if ($instance->isAvailable()) {
                    $available[] = $provider;
                }
            } catch (\Exception $e) {
                // Provider not available
            }
        }

        return $available;
    }
}
