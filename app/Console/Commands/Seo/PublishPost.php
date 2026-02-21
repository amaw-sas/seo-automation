<?php

namespace App\Console\Commands\Seo;

use App\Exceptions\ValidationException;
use App\Exceptions\WordPressPublishException;
use App\Models\GeneratedPost;
use App\Models\WordPressSite;
use App\Services\WordPressPublisher;
use Illuminate\Console\Command;

class PublishPost extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seo:publish:post
                            {post : ID del GeneratedPost a publicar}
                            {--site= : ID del WordPressSite (requerido)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publica un GeneratedPost específico a WordPress';

    /**
     * Execute the console command.
     */
    public function handle(WordPressPublisher $publisher): int
    {
        $postId = $this->argument('post');
        $siteId = $this->option('site');

        // Validar opciones requeridas
        if (!$siteId) {
            $this->error('La opción --site es requerida');
            $this->info('Uso: php artisan seo:publish:post {post-id} --site={site-id}');
            return self::FAILURE;
        }

        // Buscar post
        $post = GeneratedPost::find($postId);
        if (!$post) {
            $this->error("Post #{$postId} no encontrado");
            return self::FAILURE;
        }

        // Buscar sitio
        $site = WordPressSite::find($siteId);
        if (!$site) {
            $this->error("WordPressSite #{$siteId} no encontrado");
            return self::FAILURE;
        }

        // Verificar que no esté ya publicado
        if ($post->status === 'published') {
            $this->warn("Post #{$postId} ya está publicado");
            $this->info("URL: {$post->published_url}");

            if (!$this->confirm('¿Desea re-publicar (actualizar) este post?', false)) {
                return self::SUCCESS;
            }
        }

        $this->info("Publishing post #{$post->id} to site #{$site->id} ({$site->site_url})...");
        $this->newLine();

        try {
            // Validar pre-publicación
            $this->info('✓ Validating: Title, meta description, content, image');

            // Publicar
            $result = $publisher->publish($post, $site);

            // Mostrar resultados
            $this->newLine();
            $this->info("✓ Uploaded featured image");
            $this->info("✓ Uploaded " . ($result->imagesUploaded - 1) . " inline images");
            $this->info("✓ Replaced image URLs in content");
            $this->info("✓ Published to WordPress → post_id: {$result->wordpressPostId}");

            $this->newLine();
            $this->line('<fg=green>Post published successfully!</>');
            $this->info("URL: {$result->publishedUrl}");
            $this->info("Time: {$result->duration}s");

            // Mostrar tabla de métricas
            $this->newLine();
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Post ID', $post->id],
                    ['WordPress Post ID', $result->wordpressPostId],
                    ['Title', $post->title],
                    ['Word Count', $post->word_count],
                    ['Quality Score', $post->quality_score . '/100'],
                    ['Images Uploaded', $result->imagesUploaded],
                    ['Duration', $result->duration . 's'],
                    ['Published URL', $result->publishedUrl],
                ]
            );

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->newLine();
            $this->error('✗ Validation failed:');
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (WordPressPublishException $e) {
            $this->newLine();
            $this->error('✗ Publication failed:');
            $this->error($e->getMessage());
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('✗ Unexpected error:');
            $this->error($e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
