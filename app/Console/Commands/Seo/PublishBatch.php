<?php

namespace App\Console\Commands\Seo;

use App\Models\GeneratedPost;
use App\Models\NuxtSite;
use App\Models\WordPressSite;
use App\Services\NuxtBlogPublisher;
use App\Services\WordPressPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PublishBatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:publish:batch
                            {--limit=10 : Máximo de posts a publicar}
                            {--min-quality=70 : Quality score mínimo requerido}
                            {--site= : ID del WordPressSite (opcional, si omitido publica a todos los sitios activos)}
                            {--sync-nuxt : Sincroniza cada post publicado al sitio Nuxt vinculado por domain_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publica un lote de posts a WordPress de forma automática';

    private int $successCount = 0;
    private int $failureCount = 0;
    private float $totalDuration = 0;

    /**
     * Execute the console command.
     */
    public function handle(WordPressPublisher $publisher, NuxtBlogPublisher $nuxtPublisher): int
    {
        $limit = (int) $this->option('limit');
        $minQuality = (int) $this->option('min-quality');
        $siteId = $this->option('site');

        $this->info("Publishing batch: limit={$limit}, min_quality={$minQuality}" . ($siteId ? ", site={$siteId}" : ''));

        // Buscar posts candidatos
        $postsQuery = GeneratedPost::query()
            ->where('status', 'draft')
            ->where('quality_score', '>=', $minQuality)
            ->whereNotNull('featured_image_url')
            ->orderBy('quality_score', 'desc')
            ->orderBy('created_at', 'asc')
            ->limit($limit);

        $posts = $postsQuery->get();

        if ($posts->isEmpty()) {
            $this->warn('No se encontraron posts para publicar con los criterios especificados');
            return self::SUCCESS;
        }

        $this->info("Found {$posts->count()} posts matching criteria");
        $this->newLine();

        // Buscar sitios WordPress
        $sitesQuery = WordPressSite::where('is_active', true);
        if ($siteId) {
            $sitesQuery->where('id', $siteId);
        }
        $sites = $sitesQuery->get();

        if ($sites->isEmpty()) {
            $this->error('No se encontraron sitios WordPress activos');
            return self::FAILURE;
        }

        // Si hay múltiples sitios, usar el primero por defecto
        $site = $sites->first();

        // Publicar posts
        $progressBar = $this->output->createProgressBar($posts->count());
        $progressBar->start();

        foreach ($posts as $index => $post) {
            $number = $index + 1;

            try {
                $result = $publisher->publish($post, $site);

                $this->successCount++;
                $this->totalDuration += $result->duration;

                if ($this->option('sync-nuxt') && $site->domain_id) {
                    $nuxtSite = NuxtSite::where('domain_id', $site->domain_id)
                        ->where('is_active', true)
                        ->first();

                    if ($nuxtSite) {
                        try {
                            $nuxtPublisher->sync($post, $nuxtSite->site_url, $nuxtSite->api_key);
                            $this->info("      ✓ Synced to Nuxt ({$nuxtSite->site_name})");
                        } catch (\RuntimeException $e) {
                            $this->warn("      ⚠ Nuxt sync failed for post #{$post->id}: {$e->getMessage()}");
                            Log::warning("Nuxt sync failed for post #{$post->id}: {$e->getMessage()}");
                        }
                    }
                }

                $progressBar->advance();
                $this->newLine(2);
                $this->info("[{$number}/{$posts->count()}] Post #{$post->id} \"{$post->title}\" ✓");
                $this->info("      {$result->publishedUrl}");
            } catch (\Exception $e) {
                $this->failureCount++;

                $progressBar->advance();
                $this->newLine(2);
                $this->error("[{$number}/{$posts->count()}] Post #{$post->id} \"{$post->title}\" ✗ FAILED");
                $this->error("      Error: {$e->getMessage()}");

                // Intentar retry una vez más
                if ($this->option('verbose')) {
                    $this->warn("      Retrying... (attempt 2/2)");

                    try {
                        $result = $publisher->publish($post, $site);

                        // Actualizar contadores
                        $this->failureCount--;
                        $this->successCount++;
                        $this->totalDuration += $result->duration;

                        $this->info("      Retry successful ✓");
                        $this->info("      {$result->publishedUrl}");

                        if ($this->option('sync-nuxt') && $site->domain_id) {
                            $nuxtSite = NuxtSite::where('domain_id', $site->domain_id)
                                ->where('is_active', true)
                                ->first();

                            if ($nuxtSite) {
                                try {
                                    $nuxtPublisher->sync($post, $nuxtSite->site_url, $nuxtSite->api_key);
                                    $this->info("      ✓ Synced to Nuxt ({$nuxtSite->site_name})");
                                } catch (\RuntimeException $e) {
                                    $this->warn("      ⚠ Nuxt sync failed for post #{$post->id}: {$e->getMessage()}");
                                    Log::warning("Nuxt sync failed for post #{$post->id}: {$e->getMessage()}");
                                }
                            }
                        }
                    } catch (\Exception $retryException) {
                        $this->error("      Retry also failed: {$retryException->getMessage()}");
                        Log::error("Failed to publish post #{$post->id} after retry: {$retryException->getMessage()}");
                    }
                } else {
                    Log::error("Failed to publish post #{$post->id}: {$e->getMessage()}");
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // Mostrar resumen
        $this->displaySummary($posts->count());

        return $this->failureCount === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Muestra el resumen de la ejecución del batch.
     *
     * @param int $totalProcessed
     */
    private function displaySummary(int $totalProcessed): void
    {
        $this->newLine();
        $this->line('<fg=cyan>Summary:</>');

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Processed', $totalProcessed],
                ['✓ Published', "<fg=green>{$this->successCount}</>"],
                ['✗ Failed', $this->failureCount > 0 ? "<fg=red>{$this->failureCount}</>" : $this->failureCount],
                ['⏱ Total Time', round($this->totalDuration, 2) . 's'],
                ['⏱ Avg Time', $this->successCount > 0 ? round($this->totalDuration / $this->successCount, 2) . 's/post' : 'N/A'],
            ]
        );

        if ($this->successCount > 0) {
            $this->newLine();
            $this->info("✓ {$this->successCount} posts published successfully!");
        }

        if ($this->failureCount > 0) {
            $this->newLine();
            $this->warn("⚠ {$this->failureCount} posts failed to publish. Check logs for details.");
            $this->info("Run with --verbose flag to see detailed error messages and enable automatic retry.");
        }
    }
}
