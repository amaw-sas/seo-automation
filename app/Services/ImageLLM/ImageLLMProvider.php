<?php

namespace App\Services\ImageLLM;

interface ImageLLMProvider
{
    /**
     * Get provider name
     */
    public function getName(): string;

    /**
     * Get model being used
     */
    public function getModel(): string;

    /**
     * Generate an image from a prompt
     *
     * @param string $prompt The prompt describing the image
     * @param array $options Options like size, quality, style, etc.
     * @return array ['url' => string, 'cost' => float, 'metadata' => array]
     */
    public function generate(string $prompt, array $options = []): array;

    /**
     * Check if provider is available (API key configured)
     */
    public function isAvailable(): bool;
}
