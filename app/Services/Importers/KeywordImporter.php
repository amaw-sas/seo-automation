<?php

namespace App\Services\Importers;

use App\Models\Keyword;
use App\Models\City;
use App\Models\Category;
use App\Models\SearchIntent;
use App\Services\Parsers\SerpFeaturesParser;
use Illuminate\Support\Facades\DB;

class KeywordImporter
{
    /**
     * Import or update keyword
     *
     * @param array $data
     * @return Keyword
     */
    public function importKeyword(array $data): Keyword
    {
        $keywordNormalized = Keyword::normalize($data['keyword']);

        // Parse SERP features si viene como string
        if (isset($data['serp_features']) && is_string($data['serp_features'])) {
            $data['serp_features'] = SerpFeaturesParser::parse($data['serp_features']);
        }

        // Buscar o crear keyword
        $keyword = Keyword::updateOrCreate(
            [
                'keyword_normalized' => $keywordNormalized,
                'city_id' => $data['city_id'] ?? null,
            ],
            [
                'keyword' => $data['keyword'],
                'category_id' => $data['category_id'] ?? null,
                'intent_id' => $data['intent_id'] ?? null,
                'search_volume_co' => $data['search_volume_co'] ?? 0,
                'search_volume_global' => $data['search_volume_global'] ?? null,
                'keyword_difficulty' => $data['keyword_difficulty'] ?? null,
                'cpc_usd' => $data['cpc_usd'] ?? null,
                'serp_features' => $data['serp_features'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]
        );

        return $keyword;
    }

    /**
     * Detect city from keyword text
     *
     * @param string $keyword
     * @return int|null
     */
    public function detectCityFromKeyword(string $keyword): ?int
    {
        $keywordLower = strtolower($keyword);

        // Obtener todas las ciudades
        $cities = City::all();

        foreach ($cities as $city) {
            $cityName = strtolower($city->name);

            // Buscar nombre de ciudad en keyword
            if (str_contains($keywordLower, $cityName)) {
                return $city->id;
            }

            // Buscar variaciones sin acentos
            $cityNameNoAccents = $this->removeAccents($cityName);
            if (str_contains($keywordLower, $cityNameNoAccents)) {
                return $city->id;
            }
        }

        return null;
    }

    /**
     * Detect category from keyword or filename
     *
     * @param string $keyword
     * @param string|null $sourceFile
     * @return int|null
     */
    public function detectCategoryFromKeyword(string $keyword, ?string $sourceFile = null): ?int
    {
        $keywordLower = strtolower($keyword);

        // Detectar por tipo de vehículo
        if (str_contains($keywordLower, 'suv') || str_contains($keywordLower, 'camioneta') || str_contains($keywordLower, '4x4')) {
            return Category::where('slug', 'tipo-vehiculo')->value('id');
        }

        // Detectar por marca
        $brands = ['toyota', 'chevrolet', 'nissan', 'mazda', 'ford', 'renault', 'volkswagen'];
        foreach ($brands as $brand) {
            if (str_contains($keywordLower, $brand)) {
                return Category::where('slug', 'marca')->value('id');
            }
        }

        // Detectar por temporal
        if (str_contains($keywordLower, 'por dia') || str_contains($keywordLower, 'por mes') || str_contains($keywordLower, 'semanal')) {
            return Category::where('slug', 'temporal')->value('id');
        }

        // Detectar por comparación
        if (str_contains($keywordLower, 'comparar') || str_contains($keywordLower, 'vs') || str_contains($keywordLower, 'mejor')) {
            return Category::where('slug', 'comparacion')->value('id');
        }

        // Detectar por ciudad desde filename
        if ($sourceFile && str_contains(strtolower($sourceFile), 'ciudad')) {
            return Category::where('slug', 'alquiler-ciudad')->value('id');
        }

        // Default: alquiler genérico
        return Category::where('slug', 'alquiler-carro')->value('id');
    }

    /**
     * Detect search intent from keyword
     *
     * @param string $keyword
     * @return int|null
     */
    public function detectIntentFromKeyword(string $keyword): ?int
    {
        $keywordLower = strtolower($keyword);

        // Transactional
        if (str_contains($keywordLower, 'alquiler') || str_contains($keywordLower, 'rentar') || str_contains($keywordLower, 'reservar')) {
            return SearchIntent::where('slug', 'transactional')->value('id');
        }

        // Commercial
        if (str_contains($keywordLower, 'mejor') || str_contains($keywordLower, 'precio') || str_contains($keywordLower, 'comparar')) {
            return SearchIntent::where('slug', 'commercial')->value('id');
        }

        // Informational
        if (str_contains($keywordLower, 'como') || str_contains($keywordLower, 'que es') || str_contains($keywordLower, 'requisitos')) {
            return SearchIntent::where('slug', 'informational')->value('id');
        }

        // Default: Commercial
        return SearchIntent::where('slug', 'commercial')->value('id');
    }

    /**
     * Remove accents from string
     *
     * @param string $str
     * @return string
     */
    private function removeAccents(string $str): string
    {
        $accents = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
        ];

        return strtr($str, $accents);
    }

    /**
     * Batch import keywords
     *
     * @param array $keywords
     * @return int Count of imported keywords
     */
    public function batchImport(array $keywords): int
    {
        $count = 0;

        DB::transaction(function () use ($keywords, &$count) {
            foreach ($keywords as $keywordData) {
                $this->importKeyword($keywordData);
                $count++;
            }
        });

        return $count;
    }
}
