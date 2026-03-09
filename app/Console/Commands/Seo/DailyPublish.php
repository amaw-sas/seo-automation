<?php

namespace App\Console\Commands\Seo;

use App\Models\GeneratedPost;
use App\Models\Keyword;
use App\Models\NuxtSite;
use App\Models\TopicResearch;
use App\Models\WordPressSite;
use App\Services\ContentGenerator;
use App\Services\NuxtBlogPublisher;
use App\Services\PostValidator;
use App\Services\WordPressPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DailyPublish extends Command
{
    protected $signature = 'seo:daily:publish
                            {--site= : ID del WordPressSite destino (requerido)}
                            {--source=keywords : Fuente de contenido: keywords|topics}
                            {--llm=anthropic : LLM provider (anthropic, openai, xai)}
                            {--image-llm=xai : Image LLM provider (xai, dalle3)}
                            {--min-quality=70 : Quality score mínimo para publicar}
                            {--sync-nuxt : Sincroniza el post publicado al sitio Nuxt vinculado}
                            {--dry-run : Muestra qué se seleccionaría sin generar ni publicar}';

    protected $description = 'Selecciona el mejor keyword/topic disponible, genera el post y lo publica (flujo end-to-end diario)';

    public function handle(
        WordPressPublisher $wpPublisher,
        NuxtBlogPublisher $nuxtPublisher
    ): int {
        $siteId = (int) $this->option('site');

        if (!$siteId) {
            $this->error('--site es requerido. Ej: seo:daily:publish --site=1');
            return self::FAILURE;
        }

        $site = WordPressSite::find($siteId);

        if (!$site || !$site->is_active) {
            $this->error("WordPressSite #{$siteId} no encontrado o inactivo.");
            return self::FAILURE;
        }

        $this->info("Site: {$site->site_name} ({$site->site_url})");
        $this->newLine();

        // 1. Select best source (keyword or topic)
        $source = $this->selectBestSource($siteId);

        if (!$source) {
            $this->warn('No hay keywords/topics disponibles sin publicar para este sitio. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->displaySourceInfo($source);

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('[dry-run] No se generó ni publicó nada.');
            return self::SUCCESS;
        }

        // 2. Generate post
        $post = $this->generatePost($source);
        if (!$post) {
            return self::FAILURE;
        }

        // 3. Validate
        if (!$this->validatePost($post)) {
            return self::FAILURE;
        }

        // 4. Set target site before publishing
        $post->target_wordpress_site_id = $siteId;
        $post->save();

        // 5. Publish to WordPress
        $result = $this->publishToWordPress($post, $site, $wpPublisher);
        if (!$result) {
            return self::FAILURE;
        }

        // 6. Sync to Nuxt (optional)
        if ($this->option('sync-nuxt')) {
            $this->syncToNuxt($post, $site, $nuxtPublisher);
        }

        // 7. Summary
        $this->newLine();
        $this->table(['Métrica', 'Valor'], [
            ['Post ID', $post->id],
            ['Título', $post->title],
            ['Quality Score', $post->quality_score . '/100'],
            ['Costo LLM', '$' . number_format($post->llm_cost_usd, 4)],
            ['Costo imágenes', '$' . number_format($post->image_generation_cost_usd ?? 0, 4)],
            ['Costo total', '$' . number_format($post->total_cost, 4)],
            ['URL publicada', $post->published_url ?? '—'],
        ]);

        Log::info("DailyPublish: post #{$post->id} published for site #{$siteId}", [
            'title' => $post->title,
            'quality_score' => $post->quality_score,
            'published_url' => $post->published_url,
            'total_cost' => $post->total_cost,
        ]);

        return self::SUCCESS;
    }

    /**
     * Select best available keyword or topic for the given site.
     */
    private function selectBestSource(int $siteId): Keyword|TopicResearch|null
    {
        $sourceType = $this->option('source');

        if ($sourceType === 'topics') {
            $source = TopicResearch::availableForSite($siteId)->first();
            if ($source) {
                return $source;
            }
            $this->warn('No hay topics disponibles, intentando con keywords...');
        }

        // Default: keywords (also fallback from topics)
        return Keyword::availableForSite($siteId)->first();
    }

    /**
     * Display selected source info.
     */
    private function displaySourceInfo(Keyword|TopicResearch $source): void
    {
        if ($source instanceof TopicResearch) {
            $score = $source->competition_level > 0
                ? round($source->potential_traffic / ($source->competition_level + 1), 2)
                : $source->potential_traffic;

            $this->line("Fuente: <fg=cyan>topic_research</>");
            $this->line("  Título: {$source->title}");
            $this->line("  Tráfico potencial: {$source->potential_traffic}");
            $this->line("  Nivel de competencia: {$source->competition_level}");
            $this->line("  Priority score: {$score}");
        } else {
            $difficulty = (float) ($source->keyword_difficulty ?? 0);
            $score = round($source->search_volume_co / ($difficulty + 1), 2);

            $this->line("Fuente: <fg=cyan>keyword</>");
            $this->line("  Keyword: {$source->keyword}");
            $this->line("  Volumen CO: {$source->search_volume_co}");
            $this->line("  Dificultad: {$difficulty}");
            $this->line("  Priority score: {$score}");
        }
    }

    /**
     * Generate post from source.
     */
    private function generatePost(Keyword|TopicResearch $source): ?GeneratedPost
    {
        $this->newLine();
        $this->info('Generando contenido...');

        try {
            $generator = new ContentGenerator(
                $this->option('llm'),
                $this->option('image-llm')
            );

            $post = $generator->generatePost($source);

            $this->info("  ✓ Post generado: \"{$post->title}\" ({$post->word_count} palabras, score {$post->quality_score}/100)");

            return $post;
        } catch (\Exception $e) {
            $this->error("Error al generar post: {$e->getMessage()}");
            Log::error("DailyPublish: generation failed — {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Validate post before publishing.
     */
    private function validatePost(GeneratedPost $post): bool
    {
        $minQuality = (int) $this->option('min-quality');

        try {
            PostValidator::validateQualityScore($post, $minQuality);
            PostValidator::validate($post);
            $this->info('  ✓ Validación OK');
            return true;
        } catch (\Exception $e) {
            $this->error("Validación fallida: {$e->getMessage()}");
            $this->warn("  Post guardado como draft (ID #{$post->id}) para revisión manual.");
            Log::warning("DailyPublish: validation failed for post #{$post->id} — {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Publish post to WordPress.
     */
    private function publishToWordPress(
        GeneratedPost $post,
        WordPressSite $site,
        WordPressPublisher $publisher
    ): bool {
        $this->newLine();
        $this->info('Publicando en WordPress...');

        try {
            $result = $publisher->publish($post, $site);
            $this->info("  ✓ Publicado: {$result->publishedUrl}");
            return true;
        } catch (\Exception $e) {
            $this->error("Error al publicar en WordPress: {$e->getMessage()}");
            Log::error("DailyPublish: WordPress publish failed for post #{$post->id} — {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sync post to linked Nuxt site.
     */
    private function syncToNuxt(
        GeneratedPost $post,
        WordPressSite $site,
        NuxtBlogPublisher $nuxtPublisher
    ): void {
        if (!$site->domain_id) {
            $this->warn('  Nuxt sync omitido: el sitio no tiene domain_id configurado.');
            return;
        }

        $nuxtSite = NuxtSite::where('domain_id', $site->domain_id)
            ->where('is_active', true)
            ->first();

        if (!$nuxtSite) {
            $this->warn('  Nuxt sync omitido: no se encontró NuxtSite activo para este dominio.');
            return;
        }

        $this->newLine();
        $this->info("Sincronizando a Nuxt ({$nuxtSite->site_name})...");

        try {
            $nuxtPublisher->sync($post, $nuxtSite->site_url, $nuxtSite->api_key);
            $this->info('  ✓ Sincronizado a Nuxt');
        } catch (\RuntimeException $e) {
            $this->warn("  ⚠ Nuxt sync falló: {$e->getMessage()}");
            Log::warning("DailyPublish: Nuxt sync failed for post #{$post->id} — {$e->getMessage()}");
        }
    }
}
