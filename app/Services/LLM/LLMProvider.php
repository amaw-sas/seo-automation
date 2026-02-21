<?php

namespace App\Services\LLM;

interface LLMProvider
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
     * Complete a prompt
     *
     * @param string $prompt The prompt to complete
     * @param array $options Options like temperature, max_tokens, etc.
     * @return array ['content' => string, 'usage' => array, 'cost' => float]
     */
    public function complete(string $prompt, array $options = []): array;

    /**
     * Check if provider is available (API key configured)
     */
    public function isAvailable(): bool;
}
