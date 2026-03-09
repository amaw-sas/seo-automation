<?php

namespace App\Console\Commands\Seo;

use App\Exceptions\ValidationException;
use App\Exceptions\WordPressPublishException;
use App\Models\GeneratedPost;
use App\Models\NuxtSite;
use App\Models\WordPressSite;
use App\Services\NuxtBlogPublisher;
use App\Services\WordPressPublisher;
use Illuminate\Console\Command;

class PublishPost extends Command
{
    protected $signature = 'seo:publish:post
                            {post : ID del GeneratedPost a publicar}
                            {--site= : ID del sitio destino (requerido)}
                            {--site-type=wordpress : Tipo de sitio: wordpress|nuxt}';

    protected $description = 'Publica un GeneratedPost específico a WordPress o Nuxt';

    public function handle(WordPressPublisher $wpPublisher, NuxtBlogPublisher $nuxtPublisher): int
    {
        $postId   = $this->argument('post');
        $siteId   = (int) $this->option('site');
        $siteType = $this->option('site-type');

        if (!$siteId) {
            $this->error('La opción --site es requerida');
            return self::FAILURE;
        }

        $post = GeneratedPost::find($postId);
        if (!$post) {
            $this->error("Post #{$postId} no encontrado");
            return self::FAILURE;
        }

        if ($post->status === 'published') {
            $this->warn("Post #{$postId} ya está publicado: {$post->published_url}");
            if (!$this->confirm('¿Desea re-publicar (actualizar) este post?', false)) {
                return self::SUCCESS;
            }
        }

        return $siteType === 'nuxt'
            ? $this->publishToNuxt($post, $siteId, $nuxtPublisher)
            : $this->publishToWordPress($post, $siteId, $wpPublisher);
    }

    private function publishToNuxt(GeneratedPost $post, int $siteId, NuxtBlogPublisher $publisher): int
    {
        $site = NuxtSite::where('id', $siteId)->where('is_active', true)->first();

        if (!$site) {
            $this->error("NuxtSite #{$siteId} no encontrado o inactivo");
            return self::FAILURE;
        }

        $this->info("Publishing post #{$post->id} to Nuxt site \"{$site->site_name}\" ({$site->site_url})...");

        try {
            $publisher->sync($post, $site->site_url, $site->api_key);

            $post->status             = 'published';
            $post->published_at       = now();
            $post->target_nuxt_site_id = $siteId;
            $post->published_url      = rtrim($site->site_url, '/') . '/blog/' . $post->slug;
            $post->save();

            $this->info("✓ Post published to Nuxt");
            $this->info("URL: {$post->published_url}");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("✗ Nuxt publish failed: {$e->getMessage()}");
            return self::FAILURE;
        }
    }

    private function publishToWordPress(GeneratedPost $post, int $siteId, WordPressPublisher $publisher): int
    {
        $site = WordPressSite::find($siteId);

        if (!$site) {
            $this->error("WordPressSite #{$siteId} no encontrado");
            return self::FAILURE;
        }

        $this->info("Publishing post #{$post->id} to WordPress site \"{$site->site_name}\" ({$site->site_url})...");

        try {
            $result = $publisher->publish($post, $site);

            $this->newLine();
            $this->info("✓ Published to WordPress → post_id: {$result->wordpressPostId}");
            $this->info("URL: {$result->publishedUrl}");
            $this->info("Time: {$result->duration}s");

            return self::SUCCESS;
        } catch (ValidationException $e) {
            $this->error("✗ Validation failed: {$e->getMessage()}");
            return self::FAILURE;
        } catch (WordPressPublishException $e) {
            $this->error("✗ Publication failed: {$e->getMessage()}");
            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error("✗ Unexpected error: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
