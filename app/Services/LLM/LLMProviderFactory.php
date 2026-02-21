<?php

namespace App\Services\LLM;

class LLMProviderFactory
{
    /**
     * Create an LLM provider instance
     *
     * @param string $provider Provider name (anthropic, openai, google, xai)
     * @return LLMProvider
     * @throws \InvalidArgumentException
     */
    public static function make(string $provider): LLMProvider
    {
        return match($provider) {
            'anthropic' => new AnthropicProvider(
                config('services.anthropic.api_key'),
                config('services.anthropic.model', 'claude-3-5-sonnet-20241022')
            ),
            'openai' => new OpenAIProvider(
                config('services.openai.api_key'),
                config('services.openai.model', 'gpt-4-turbo')
            ),
            'google' => new GoogleAIProvider(
                config('services.google.api_key'),
                config('services.google.model', 'gemini-2.0-flash-exp')
            ),
            'xai' => new XAIProvider(
                config('services.xai.api_key'),
                config('services.xai.model', 'grok-2-latest')
            ),
            default => throw new \InvalidArgumentException("Unsupported LLM provider: {$provider}"),
        };
    }

    /**
     * Get list of available providers
     */
    public static function getAvailableProviders(): array
    {
        $providers = ['anthropic', 'openai', 'google', 'xai'];
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
