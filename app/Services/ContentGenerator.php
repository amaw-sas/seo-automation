<?php

namespace App\Services;

use App\Models\GeneratedPost;
use App\Models\Keyword;
use App\Models\TopicResearch;
use App\Services\LLM\LLMProvider;
use App\Services\LLM\LLMProviderFactory;
use App\Services\ImageLLM\ImageLLMProvider;
use App\Services\ImageLLM\ImageLLMProviderFactory;
use Illuminate\Support\Str;

class ContentGenerator
{
    private LLMProvider $llm;
    private ?ImageLLMProvider $imageLLM = null;
    private int $totalPromptTokens = 0;
    private int $totalCompletionTokens = 0;
    private float $totalCost = 0.0;
    private float $totalImageCost = 0.0;

    public function __construct(string $llmProvider = 'anthropic', ?string $imageLLMProvider = null)
    {
        $this->llm = LLMProviderFactory::make($llmProvider);

        if ($imageLLMProvider) {
            try {
                $this->imageLLM = ImageLLMProviderFactory::make($imageLLMProvider);
            } catch (\Exception $e) {
                // Image provider not available, continue without images
                $this->imageLLM = null;
            }
        }
    }

    /**
     * Generate a blog post from a topic research or keyword
     *
     * @param TopicResearch|Keyword $source
     * @param array $options
     * @return GeneratedPost
     */
    public function generatePost($source, array $options = []): GeneratedPost
    {
        // Determine primary keyword
        if ($source instanceof TopicResearch) {
            $primaryKeyword = Keyword::where('keyword', $source->title)->first();
            if (!$primaryKeyword) {
                // Create a temporary keyword
                $primaryKeyword = Keyword::create([
                    'keyword' => $source->title,
                    'keyword_normalized' => Str::slug($source->title),
                    'city_id' => $source->city_id,
                    'search_volume_co' => $source->potential_traffic ?? 0,
                ]);
            }
        } else {
            $primaryKeyword = $source;
        }

        // 1. Generate outline
        $outline = $this->generateOutline($primaryKeyword, $options);

        // 2. Generate content
        $content = $this->generateContent($outline, $primaryKeyword);

        // 3. Generate meta description
        $metaDescription = $this->generateMetaDescription($primaryKeyword, $content);

        // 4. Generate excerpt
        $excerpt = $this->generateExcerpt($content);

        // 5. Generate images (if provider available)
        $images = $this->generateImages($outline, $primaryKeyword);

        // 6. Calculate quality score
        $qualityScore = $this->calculateQualityScore($content, $primaryKeyword);

        // 7. Create post record
        $post = GeneratedPost::create([
            'topic_research_id' => $source instanceof TopicResearch ? $source->id : null,
            'title' => $outline['title'],
            'slug' => Str::slug($outline['title']),
            'content' => $content,
            'excerpt' => $excerpt,
            'meta_description' => $metaDescription,
            'primary_keyword_id' => $primaryKeyword->id,
            'secondary_keywords' => $this->extractSecondaryKeywords($primaryKeyword),
            'llm_provider' => $this->llm->getName(),
            'llm_model' => $this->llm->getModel(),
            'llm_prompt_tokens' => $this->totalPromptTokens,
            'llm_completion_tokens' => $this->totalCompletionTokens,
            'llm_cost_usd' => $this->totalCost,
            'featured_image_url' => $images['featured'] ?? null,
            'inline_images' => $images['inline'] ?? null,
            'image_llm_provider' => $images['provider'] ?? null,
            'image_generation_cost_usd' => $this->totalImageCost,
            'word_count' => str_word_count(strip_tags($content)),
            'reading_time_minutes' => ceil(str_word_count(strip_tags($content)) / 200),
            'quality_score' => $qualityScore,
            'status' => 'draft',
        ]);

        return $post;
    }

    /**
     * Generate content outline
     */
    private function generateOutline(Keyword $keyword, array $options = []): array
    {
        $cityName = $keyword->city?->name ?? 'Colombia';
        $currentYear = now()->year;

        $prompt = <<<PROMPT
Genera un outline detallado para un post de blog sobre "{$keyword->keyword}".

**Información de la keyword**:
- Keyword: {$keyword->keyword}
- Volumen de búsqueda: {$keyword->search_volume_co} búsquedas/mes en Colombia
- Ciudad: {$cityName}
- Año actual: {$currentYear}

**Requisitos**:
- Título optimizado para SEO (máximo 60 caracteres)
- El contenido debe ser actual y relevante para {$currentYear}
- 6-8 secciones H2 que respondan intención de búsqueda
- Incluir sección de FAQ
- Longitud objetivo: 1500-2000 palabras
- Tono: profesional pero conversacional
- Enfoque: alquiler de carros en Colombia

Responde SOLO con JSON válido (sin markdown):
{
  "title": "Título del post",
  "sections": [
    {
      "heading": "H2 de la sección",
      "outline": "Qué cubrir en esta sección",
      "word_count": 200
    }
  ]
}
PROMPT;

        $response = $this->llm->complete($prompt, [
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ]);

        $this->trackUsage($response);

        // Try to extract JSON from response
        $content = $response['content'];

        // Remove markdown code blocks if present
        $content = preg_replace('/```json\s*(.*?)\s*```/s', '$1', $content);
        $content = preg_replace('/```\s*(.*?)\s*```/s', '$1', $content);

        $outline = json_decode(trim($content), true);

        if (!$outline || !isset($outline['title'])) {
            throw new \Exception('Failed to parse outline from LLM response');
        }

        return $outline;
    }

    /**
     * Generate content from outline
     */
    private function generateContent(array $outline, Keyword $keyword): string
    {
        $sections = [];

        foreach ($outline['sections'] as $section) {
            $sectionContent = $this->generateSection($section, $keyword);
            $sections[] = "<h2>{$section['heading']}</h2>\n\n{$sectionContent}";
        }

        return implode("\n\n", $sections);
    }

    /**
     * Generate a single section
     */
    private function generateSection(array $section, Keyword $keyword): string
    {
        $cityName = $keyword->city?->name ?? 'Colombia';
        $currentYear = now()->year;

        $prompt = <<<PROMPT
Escribe la sección "{$section['heading']}" para un post de blog sobre "{$keyword->keyword}".

**Outline**: {$section['outline']}
**Longitud objetivo**: {$section['word_count']} palabras
**Ciudad**: {$cityName}
**Año actual**: {$currentYear}

**Requisitos**:
- Tono conversacional pero profesional
- Toda la información debe ser actual y relevante para {$currentYear}
- No mencionar años anteriores como si fueran el presente
- Incluir información específica de Colombia
- Usar ejemplos prácticos
- Formato HTML (usar <p>, <ul>, <li>, <strong>, etc.)
- NO incluir el H2 (ya lo tengo)

Escribe SOLO el contenido de la sección en HTML:
PROMPT;

        $response = $this->llm->complete($prompt, [
            'temperature' => 0.8,
            'max_tokens' => max(1000, ($section['word_count'] ?? 200) * 5),
        ]);

        $this->trackUsage($response);

        return trim($response['content']);
    }

    /**
     * Generate meta description
     */
    private function generateMetaDescription(Keyword $keyword, string $content): string
    {
        $cityName = $keyword->city?->name ?? 'Colombia';

        $prompt = <<<PROMPT
Genera una meta description optimizada para SEO (máximo 155 caracteres) para un post sobre "{$keyword->keyword}".

El post habla sobre alquiler de carros en {$cityName}.

Responde SOLO con el texto de la meta description, sin comillas ni etiquetas:
PROMPT;

        $response = $this->llm->complete($prompt, [
            'temperature' => 0.7,
            'max_tokens' => 100,
        ]);

        $this->trackUsage($response);

        return Str::limit(trim($response['content']), 160);
    }

    /**
     * Generate excerpt
     */
    private function generateExcerpt(string $content): string
    {
        $plainText = strip_tags($content);
        return Str::limit($plainText, 300);
    }

    /**
     * Extract secondary keywords (placeholder)
     */
    private function extractSecondaryKeywords(Keyword $keyword): array
    {
        // TODO: Extract related keywords from database
        return [];
    }

    /**
     * Calculate quality score
     */
    private function calculateQualityScore(string $content, Keyword $keyword): int
    {
        $score = 0;

        // 1. Word count (max 30 points)
        $wordCount = str_word_count(strip_tags($content));
        if ($wordCount >= 1500 && $wordCount <= 3000) {
            $score += 30;
        } elseif ($wordCount >= 1000) {
            $score += 20;
        } elseif ($wordCount >= 500) {
            $score += 10;
        }

        // 2. Structure (max 30 points)
        $hasH2 = substr_count($content, '<h2>') >= 4;
        $hasLists = substr_count($content, '<ul>') >= 1;
        $hasParagraphs = substr_count($content, '<p>') >= 5;

        if ($hasH2 && $hasLists && $hasParagraphs) {
            $score += 30;
        } elseif ($hasH2 && $hasParagraphs) {
            $score += 20;
        } elseif ($hasH2) {
            $score += 10;
        }

        // 3. Keyword presence (max 20 points)
        $keywordDensity = substr_count(strtolower($content), strtolower($keyword->keyword));
        if ($keywordDensity >= 3 && $keywordDensity <= 10) {
            $score += 20;
        } elseif ($keywordDensity > 0) {
            $score += 10;
        }

        // 4. Content quality indicators (max 20 points)
        $hasStrong = substr_count($content, '<strong>') >= 2;
        $hasVariety = strlen($content) > 3000;

        if ($hasStrong && $hasVariety) {
            $score += 20;
        } elseif ($hasStrong || $hasVariety) {
            $score += 10;
        }

        return min($score, 100);
    }

    /**
     * Generate images for the post
     */
    private function generateImages(array $outline, Keyword $keyword): array
    {
        $images = [
            'featured' => null,
            'inline' => [],
            'provider' => null,
        ];

        // Skip if no image provider available
        if (!$this->imageLLM || !$this->imageLLM->isAvailable()) {
            return $images;
        }

        $cityName = $keyword->city?->name ?? 'Colombia';
        $countryName = $keyword->city?->country?->name ?? 'España';

        try {
            // Generate featured image
            $featuredPrompt = "No text, no words, no letters, no signs, no watermarks, no license plates with readable text. "
                . "Shot on Canon EOS R5 with 35mm f/1.8 lens, golden hour natural light, shallow depth of field. "
                . "Professional automotive stock photography: a clean modern rental car (sedan or SUV) parked on a real street in {$cityName}, {$countryName}. "
                . "The background shows authentic local architecture recognizable to that specific city — not generic European street. "
                . "Car occupies left third of frame, city's characteristic urban texture visible in background. "
                . "Warm afternoon light, photorealistic. No CGI look, no dystopian elements, no futuristic elements. "
                . "Editorial travel photography style for a premium car rental brand.";

            $featuredImage = $this->imageLLM->generate($featuredPrompt, [
                'size' => '1792x1024',   // landscape 16:9 — mejor para hero de blog
                'quality' => 'hd',       // HD: $0.080 vs $0.040 standard — duplica detalle
            ]);

            $images['featured'] = $featuredImage['url'];
            $images['provider'] = $this->imageLLM->getName();
            $this->totalImageCost += $featuredImage['cost'] ?? 0.0;

            // Generate 1-2 inline images (from first sections)
            $sectionsForImages = array_slice($outline['sections'], 0, 2);

            foreach ($sectionsForImages as $section) {
                $inlinePrompt = "No text, no words, no letters, no watermarks. "
                    . "Shot on Sony A7IV with 50mm f/2.0 lens, natural soft light. "
                    . "Documentary travel photography illustrating: \"{$section['heading']}\". "
                    . "Authentic scene in {$cityName}, {$countryName} — real environment, not staged studio. "
                    . "Photorealistic, editorial style, warm tones. No dystopian elements, no CGI look.";

                $inlineImage = $this->imageLLM->generate($inlinePrompt, [
                    'size' => '1024x1024',
                    'quality' => 'medium',
                ]);

                $images['inline'][] = $inlineImage['url'];
                $this->totalImageCost += $inlineImage['cost'] ?? 0.0;
            }
        } catch (\Exception $e) {
            // Image generation failed, continue without images
            // Log error for debugging
            \Log::warning('Image generation failed: ' . $e->getMessage());
        }

        return $images;
    }

    /**
     * Track token usage and cost
     */
    private function trackUsage(array $response): void
    {
        $this->totalPromptTokens += $response['usage']['prompt_tokens'] ?? 0;
        $this->totalCompletionTokens += $response['usage']['completion_tokens'] ?? 0;
        $this->totalCost += $response['cost'] ?? 0.0;
    }
}
