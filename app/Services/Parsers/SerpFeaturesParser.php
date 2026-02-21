<?php

namespace App\Services\Parsers;

class SerpFeaturesParser
{
    /**
     * Parse SERP features from CSV string to array
     *
     * @param string|null $csvString SERP features como string CSV (ej: "Feature1, Feature2, Feature3")
     * @return array|null
     */
    public static function parse(?string $csvString): ?array
    {
        if (empty($csvString)) {
            return null;
        }

        // Limpiar y separar por comas
        $features = array_map('trim', explode(',', $csvString));

        // Filtrar vacíos
        $features = array_filter($features, fn($f) => !empty($f));

        return empty($features) ? null : array_values($features);
    }

    /**
     * Parse SERP features from CSV string to JSON string
     *
     * @param string|null $csvString
     * @return string|null
     */
    public static function parseToJson(?string $csvString): ?string
    {
        $array = self::parse($csvString);
        return $array ? json_encode($array) : null;
    }

    /**
     * Check if SERP features contain a specific feature
     *
     * @param array|null $features
     * @param string $featureName
     * @return bool
     */
    public static function hasFeature(?array $features, string $featureName): bool
    {
        if (empty($features)) {
            return false;
        }

        return in_array($featureName, $features, true);
    }

    /**
     * Get common SERP features
     *
     * @return array
     */
    public static function getCommonFeatures(): array
    {
        return [
            'Featured Snippet',
            'People Also Ask',
            'Image Pack',
            'Video',
            'Local Pack',
            'Knowledge Panel',
            'Reviews',
            'Sitelinks',
            'Top Stories',
            'Twitter',
            'Ads',
        ];
    }
}
